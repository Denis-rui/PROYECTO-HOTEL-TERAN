<?php

namespace Services\Comprobantes;

use Helpers\FechaHotelHelper;
use Helpers\ReservaHelper;
use Models\DocumentoElectronicoModel;
use Models\ReservaModel;
use Services\Comprobantes\Nubefact\NubefactPayloadBuilder;
use Services\Comprobantes\Nubefact\NubefactClient;

class DocumentoElectronicoService
{
    private const IGV = 18.0;
    private const MAX_INTENTOS_NUMERACION_NUBEFACT = 50;

    private DocumentoElectronicoModel $documentoElectronicoModel;
    private ReservaModel $reservaModel;
    private NubefactPayloadBuilder $payloadBuilder;
    private NubefactClient $nubefactClient;


    public function __construct()
    {
        $this->documentoElectronicoModel = new DocumentoElectronicoModel();
        $this->reservaModel = new ReservaModel();
        $this->payloadBuilder = new NubefactPayloadBuilder();
        $this->nubefactClient = new NubefactClient();
    }

    public function prepararEmision(array $datos, ?int $idUsuario = null): array
    {
        $idReserva = (int) ($datos['id_reserva'] ?? 0);

        if ($idReserva <= 0) {
            return [
                'exito' => false,
                'mensaje' => 'Debe seleccionar una reserva válida.'
            ];
        }

        $tipoDocumento = strtoupper(trim((string) ($datos['tipo_documento'] ?? 'BOLETA')));

        if (!in_array($tipoDocumento, ['BOLETA', 'FACTURA'], true)) {
            return [
                'exito' => false,
                'mensaje' => 'El tipo de documento no es válido.'
            ];
        }

        $reserva = $this->reservaModel->obtenerReservaPorId($idReserva);

        if (!$reserva) {
            return [
                'exito' => false,
                'mensaje' => 'La reserva no fue encontrada.'
            ];
        }

        $checkInReal = $this->fechaInicioFacturable($reserva);

        if ($checkInReal === '') {
            return [
                'exito' => false,
                'mensaje' => 'Solo se puede emitir una boleta o factura después de realizar el check-in o checkout del cliente.',
            ];
        }

        $fechaDesde = $this->fechaIso((string) ($datos['fecha_desde'] ?? $checkInReal));

        $fechaHasta = $this->fechaIso((string) (
            $datos['fecha_hasta']
            ?? ($reserva['checkout_real'] ?? ($reserva['check_out_programado'] ?? ($reserva['check_out'] ?? '')))
        ));

        $validacionFechas = $this->validarFechas($reserva, $fechaDesde, $fechaHasta);

        if (!($validacionFechas['exito'] ?? false)) {
            return $validacionFechas;
        }

        $habitacionesReserva = array_values(array_filter(
            (array) ($reserva['habitaciones_historial'] ?? ($reserva['habitaciones'] ?? [])),
            static fn($habitacion) => is_array($habitacion)
                && (int) ($habitacion['id'] ?? $habitacion['id_habitacion'] ?? 0) > 0
        ));

        if (empty($habitacionesReserva)) {
            return [
                'exito' => false,
                'mensaje' => 'No hay habitaciones registradas en la reserva.'
            ];
        }

        $habitacionesSolicitadas = $datos['habitaciones'] ?? [];

        if (is_string($habitacionesSolicitadas)) {
            $decodificado = json_decode($habitacionesSolicitadas, true);
            $habitacionesSolicitadas = is_array($decodificado) ? $decodificado : [];
        }

        if (empty($habitacionesSolicitadas)) {
            $habitacionesSolicitadas = $habitacionesReserva;
        }

        $resultadoHabitaciones = $this->obtenerHabitacionesSeleccionadas(
            $habitacionesReserva,
            $habitacionesSolicitadas
        );

        if (!($resultadoHabitaciones['exito'] ?? false)) {
            return $resultadoHabitaciones;
        }

        $habitacionesSeleccionadas = $resultadoHabitaciones['habitaciones'];

        $documentosEmitidos = $this->documentoElectronicoModel->obtenerPorReserva($idReserva);

        $lineas = $this->construirLineas(
            $reserva,
            $habitacionesSeleccionadas,
            (int) ($validacionFechas['noches'] ?? 0),
            $fechaDesde,
            $fechaHasta,
            $documentosEmitidos
        );

        if (!($lineas['exito'] ?? false)) {
            return $lineas;
        }

        $habitacionesFacturadas = $lineas['habitaciones_facturadas'] ?? [];
        if (empty($habitacionesFacturadas)) {
            return [
                'exito' => false,
                'mensaje' => 'Ninguna de las habitaciones seleccionadas está disponible en el rango de fechas elegido.',
            ];
        }

        $totalDocumento = (float) ($lineas['total'] ?? 0);
        $totalGravada = (float) ($lineas['total_gravada'] ?? 0);
        $totalExonerada = (float) ($lineas['total_exonerada'] ?? 0);
        $totalIgv = (float) ($lineas['total_igv'] ?? 0);

        if ($totalDocumento <= 0 || $totalExonerada <= 0 || empty($lineas['items'])) {
            return [
                'exito' => false,
                'mensaje' => 'El documento no tiene importes válidos para emitir. Revise precios, fechas y habitaciones seleccionadas.'
            ];
        }

        $totalReserva = (float) ($reserva['total'] ?? 0)
            + (float) ($reserva['cargo_checkout_tarde'] ?? 0);

        $totalPagado = (float) ($reserva['total_pagado'] ?? 0);

        $datosCliente = $this->obtenerDatosClienteDocumento(
            $datos,
            $reserva,
            $tipoDocumento
        );

        if (!($datosCliente['exito'] ?? false)) {
            return $datosCliente;
        }

        $serie = $this->seriePorTipo($tipoDocumento);

        $numero = $this->documentoElectronicoModel->siguienteNumero(
            $tipoDocumento,
            $serie
        );

        $codigoUnico = $this->firmaUnica([
            'id_reserva' => $idReserva,
            'tipo_documento' => $tipoDocumento,
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'cliente_tipo_documento' => $datosCliente['cliente_tipo_documento'],
            'cliente_numero_documento' => $datosCliente['cliente_numero_documento'],
            'cliente_denominacion' => $datosCliente['cliente_denominacion'],
            'habitaciones' => $habitacionesFacturadas,
        ]);

        $duplicado = $this->documentoElectronicoModel->obtenerPorCodigoUnico($codigoUnico);

        if ($duplicado) {
            return [
                'exito' => true,
                'mensaje' => 'El documento ya fue emitido anteriormente.',
                'documento' => $this->formatearRegistro($duplicado),
                'duplicado' => true,
            ];
        }

        $validacionCruces = $this->validarCrucesConDocumentos(
            $documentosEmitidos,
            $habitacionesFacturadas,
            $fechaDesde,
            $fechaHasta
        );

        if (!($validacionCruces['exito'] ?? false)) {
            return $validacionCruces;
        }

        $totalYaDocumentado = $this->calcularTotalDocumentado($documentosEmitidos);
        $pagoDisponible = round(max(0, $totalPagado - $totalYaDocumentado), 2);

        if ($totalDocumento - 0.01 > $pagoDisponible) {
            return [
                'exito' => false,
                'mensaje' => 'El importe seleccionado supera el monto pagado disponible para emitir. Ya hay S/ '
                    . number_format($totalYaDocumentado, 2)
                    . ' documentados de esta reserva.',
                'total_pagado' => $totalPagado,
                'total_documentado' => $totalYaDocumentado,
                'pago_disponible' => $pagoDisponible,
                'total_documento' => $totalDocumento,
            ];
        }

        $observaciones = $this->construirObservaciones(
            $reserva,
            $idReserva,
            $fechaDesde,
            $fechaHasta,
            $habitacionesFacturadas
        );

        return [
            'exito' => true,
            'mensaje' => 'Validación correcta.',
            'datos' => [
                'id_reserva' => $idReserva,
                'tipo_documento' => $tipoDocumento,
                'tipo_de_comprobante' => $this->tipoComprobantePorTipo($tipoDocumento),
                'serie' => $serie,
                'numero' => $numero,
                'fecha_de_emision' => $this->fechaSunat(FechaHotelHelper::ahora()),
                'fecha_desde' => $fechaDesde,
                'fecha_hasta' => $fechaHasta,

                'cliente_tipo_documento' => $datosCliente['cliente_tipo_documento'],
                'cliente_numero_documento' => $datosCliente['cliente_numero_documento'],
                'cliente_denominacion' => $datosCliente['cliente_denominacion'],
                'cliente_email' => $datosCliente['cliente_email'],
                'cliente_direccion' => $datosCliente['cliente_direccion'],

                'habitaciones' => $habitacionesSeleccionadas,
                'items' => $lineas['items'] ?? [],
                'total_gravada' => $totalGravada,
                'total_exonerada' => $totalExonerada,
                'total_igv' => $totalIgv,
                'total' => $totalDocumento,
                'observaciones' => $observaciones,
                'codigo_unico' => $codigoUnico,
                'id_usuario' => $idUsuario ?? ($_SESSION['id_usuario'] ?? null),
            ],
            'reserva' => $reserva,
            'total_reserva' => $totalReserva,
            'total_pagado' => $totalPagado,
            'total_documentado' => $totalYaDocumentado,
            'pago_disponible' => $pagoDisponible,
            'saldo_pendiente' => round($totalReserva - $totalPagado, 2),
            'total_documento' => $totalDocumento,
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'noches' => (int) ($validacionFechas['noches'] ?? 0),
        ];
    }

    public function emitir(array $datos, ?int $idUsuario = null): array
    {
        $preparado = $this->prepararEmision($datos, $idUsuario);
        if (!($preparado['exito'] ?? false)) {
            return $preparado;
        }

        if (($preparado['duplicado'] ?? false) === true) {
            return $preparado;
        }

        $datosPreparados = $preparado['datos'] ?? [];

        if (empty($datosPreparados)) {
            return [
                'exito' => false,
                'mensaje' => 'No se encontraron datos válidos para enviar el documento electrónico.',
            ];
        }

        $numeroInicial = (int) ($datosPreparados['numero'] ?? 1);
        $payload = [];
        $respuestaApi = null;

        for ($intento = 0; $intento < self::MAX_INTENTOS_NUMERACION_NUBEFACT; $intento++) {
            $datosPreparados['numero'] = $numeroInicial + $intento;
            $payload = $this->payloadBuilder->construirPayload($datosPreparados);
            $respuestaApi = $this->nubefactClient->enviarComprobante($payload);

            if (($respuestaApi['exito'] ?? false)) {
                break;
            }

            if (($respuestaApi['codigo_error'] ?? '') !== 'documento_existente') {
                return $respuestaApi;
            }

            error_log(
                'DocumentoElectronicoService::emitir -> numero ya existe en NubeFact: '
                . (string) ($datosPreparados['serie'] ?? '')
                . '-'
                . (string) ($datosPreparados['numero'] ?? '')
            );
        }

        if (!($respuestaApi['exito'] ?? false)) {
            return [
                'exito' => false,
                'mensaje' => 'NubeFact indica que los últimos números intentados ya existen. Revise o actualice la numeración de la serie '
                    . (string) ($datosPreparados['serie'] ?? '')
                    . ' antes de volver a emitir.',
                'respuesta' => $respuestaApi['respuesta'] ?? null,
            ];
        }
        $respuesta = $respuestaApi['respuesta'] ?? [];
        $registro = [
            'id_reserva' => (int) ($datosPreparados['id_reserva'] ?? 0),
            'id_usuario' => (int) ($datosPreparados['id_usuario'] ?? ($_SESSION['id_usuario'] ?? 0)),
            'tipo_documento' => (string) ($datosPreparados['tipo_documento'] ?? 'BOLETA'),
            'tipo_de_comprobante' => (int) ($datosPreparados['tipo_de_comprobante'] ?? 0),
            'serie' => (string) ($datosPreparados['serie'] ?? ''),
            'numero' => (int) ($datosPreparados['numero'] ?? 0),
            'codigo_unico' => (string) ($datosPreparados['codigo_unico'] ?? ''),
            'fecha_emision' => (string) ($datosPreparados['fecha_de_emision'] ?? date('d-m-Y')),
            'fecha_desde' => (string) ($datosPreparados['fecha_desde'] ?? ''),
            'fecha_hasta' => (string) ($datosPreparados['fecha_hasta'] ?? ''),
            'cliente_tipo_documento' => (string) ($datosPreparados['cliente_tipo_documento'] ?? '-'),
            'cliente_numero_documento' => (string) ($datosPreparados['cliente_numero_documento'] ?? ''),
            'cliente_denominacion' => (string) ($datosPreparados['cliente_denominacion'] ?? ''),
            'cliente_email' => (string) ($datosPreparados['cliente_email'] ?? ''),
            'cliente_direccion' => (string) ($datosPreparados['cliente_direccion'] ?? ''),
            'habitaciones_json' => json_encode($datosPreparados['habitaciones'] ?? [], JSON_UNESCAPED_UNICODE),
            'detalle_json' => json_encode([
                'observaciones' => $datosPreparados['observaciones'] ?? '',
                'total_reserva' => $preparado['total_reserva'] ?? 0,
                'total_pagado' => $preparado['total_pagado'] ?? 0,
                'total_documentado_previo' => $preparado['total_documentado'] ?? 0,
                'pago_disponible_previo' => $preparado['pago_disponible'] ?? 0,
                'saldo_pendiente' => $preparado['saldo_pendiente'] ?? 0,
                'noches' => $preparado['noches'] ?? 0,
                'habitaciones' => $datosPreparados['habitaciones'] ?? [],
                'items' => $datosPreparados['items'] ?? [],
            ], JSON_UNESCAPED_UNICODE),
            'total_gravada' => (float) ($datosPreparados['total_gravada'] ?? 0),
            'total_igv' => (float) ($datosPreparados['total_igv'] ?? 0),
            'total' => (float) ($datosPreparados['total'] ?? 0),
            'estado_sunat' => !empty($respuesta['aceptada_por_sunat']) ? 'aceptado' : 'pendiente',
            'enlace' => $respuesta['enlace'] ?? null,
            'enlace_del_pdf' => $respuesta['enlace_del_pdf'] ?? null,
            'enlace_del_xml' => $respuesta['enlace_del_xml'] ?? null,
            'enlace_del_cdr' => $respuesta['enlace_del_cdr'] ?? null,
            'cadena_para_codigo_qr' => $respuesta['cadena_para_codigo_qr'] ?? null,
            'codigo_hash' => $respuesta['codigo_hash'] ?? null,
            'sunat_description' => $respuesta['sunat_description'] ?? null,
            'sunat_note' => $respuesta['sunat_note'] ?? null,
            'sunat_responsecode' => $respuesta['sunat_responsecode'] ?? null,
            'sunat_soap_error' => $respuesta['sunat_soap_error'] ?? null,
            'respuesta_json' => json_encode($respuesta, JSON_UNESCAPED_UNICODE),
            'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'created_at' => FechaHotelHelper::ahora(),
        ];

        $guardado = $this->documentoElectronicoModel->insertar($registro);

        if (!$guardado) {
            error_log(
                'DocumentoElectronicoService::emitir -> NubeFact acepto el comprobante, pero no se pudo guardar localmente: '
                . (string) ($datosPreparados['serie'] ?? '')
                . '-'
                . (string) ($datosPreparados['numero'] ?? '')
            );

            return [
                'exito' => false,
                'mensaje' => 'NubeFact aceptó el comprobante, pero no se pudo guardar en la base de datos local. No vuelva a emitirlo sin revisar el registro.',
                'documento' => $this->formatearRegistro($registro),
                'respuesta' => $respuesta,
            ];
        }

        return [
            'exito' => true,
            'mensaje' => 'Documento electrónico emitido correctamente.',
            'documento' => $this->formatearRegistro($registro),
            'respuesta' => $respuesta,
        ];
    }

    public function obtenerEmitidosPorReserva(int $idReserva): array
    {
        $documentos = $this->documentoElectronicoModel->obtenerPorReserva($idReserva);

        return array_map(
            fn(array $documento) => $this->formatearEmitido($documento),
            $documentos
        );
    }

    private function fechaIso(?string $fecha): string
    {
        $fecha = trim((string) $fecha);

        return $fecha === '' ? '' : substr($fecha, 0, 10);
    }

    private function fechaSunat(?string $fecha): string
    {
        $fecha = $this->fechaIso($fecha);

        if ($fecha === '') {
            return date('d-m-Y');
        }

        try {
            return (new \DateTimeImmutable($fecha))->format('d-m-Y');
        } catch (\Throwable $e) {
            return date('d-m-Y');
        }
    }

    private function noches(string $desde, string $hasta): int
    {
        if ($desde !== '' && $desde === $hasta) {
            return 1;
        }

        return max(0, ReservaHelper::obtenerDiasEstadia($desde, $hasta));
    }

    private function fechaInicioFacturable(array $reserva): string
    {
        $checkInReal = $this->fechaIso((string) ($reserva['checkin_real'] ?? ''));

        if ($checkInReal !== '') {
            return $checkInReal;
        }

        $estado = strtolower((string) ($reserva['estado'] ?? ''));
        $tieneCheckout = $this->fechaIso((string) ($reserva['checkout_real'] ?? '')) !== ''
            || $estado === 'checkout_realizado';

        if (!$tieneCheckout) {
            return '';
        }

        $inicioReserva = $this->fechaIso((string) (
            $reserva['check_in_programado'] ?? ($reserva['check_in'] ?? '')
        ));

        return $inicioReserva !== ''
            ? $inicioReserva
            : $this->fechaExtremaHabitaciones($reserva, 'check_in', 'min');
    }

    private function fechaFinFacturable(array $reserva): string
    {
        $checkout = $this->fechaIso((string) (
            $reserva['checkout_real']
            ?? ($reserva['check_out_programado'] ?? ($reserva['check_out'] ?? ''))
        ));

        return $checkout !== ''
            ? $checkout
            : $this->fechaExtremaHabitaciones($reserva, 'check_out', 'max');
    }

    private function fechaExtremaHabitaciones(array $reserva, string $campo, string $modo): string
    {
        $fechas = [];

        foreach ((array) ($reserva['habitaciones_historial'] ?? ($reserva['habitaciones'] ?? [])) as $habitacion) {
            if (!is_array($habitacion)) {
                continue;
            }

            $fecha = $this->fechaIso((string) ($habitacion[$campo] ?? ''));

            if ($fecha !== '') {
                $fechas[] = $fecha;
            }
        }

        if (empty($fechas)) {
            return '';
        }

        sort($fechas);

        return $modo === 'max' ? end($fechas) : $fechas[0];
    }

    private function limpiarTexto(string $texto): string
    {
        $texto = trim($texto);

        if ($texto === '') {
            return '';
        }

        return str_replace('"', '\\"', str_replace(["\r\n", "\r"], "\n", $texto));
    }

    private function seriePorTipo(string $tipoDocumento): string
    {
        if (strtoupper($tipoDocumento) === 'FACTURA') {
            return defined('NUBEFACT_SERIE_FACTURA')
                ? (string) constant('NUBEFACT_SERIE_FACTURA')
                : 'FFF1';
        }

        return defined('NUBEFACT_SERIE_BOLETA')
            ? (string) constant('NUBEFACT_SERIE_BOLETA')
            : 'BBB1';
    }

    private function tipoComprobantePorTipo(string $tipoDocumento): int
    {
        return strtoupper($tipoDocumento) === 'FACTURA' ? 1 : 2;
    }

    private function firmaUnica(array $datos): string
    {
        return sha1(json_encode($datos, JSON_UNESCAPED_UNICODE));
    }

    private function construirLineas(array $reserva,  array $habitaciones,  int $nochesDocumento,  string $fechaDesde, string $fechaHasta, array $documentosEmitidos = []): array
    {
        $items = [];
        $habitacionesFacturadas = [];
        $totalGravada = 0.0;
        $totalIgv = 0.0;
        $total = 0.0;

        foreach ($habitaciones as $habitacion) {
            $rangoHabitacion = $this->rangoFacturableHabitacion($habitacion, $reserva, $fechaDesde, $fechaHasta);

            if ($rangoHabitacion === null) {
                continue;
            }

            $desdeLinea = $rangoHabitacion['desde'];
            $hastaLinea = $rangoHabitacion['hasta'];
            $nochesLinea = $this->noches($desdeLinea, $hastaLinea);

            if ($nochesLinea <= 0) {
                continue;
            }

            $precioBruto = (float) ($habitacion['precio_aplicado'] ?? 0);

            if ($precioBruto <= 0) {
                $precioBruto = (float) ($habitacion['precio'] ?? 0);
            }

            if ($precioBruto <= 0) {
                $subtotalHabitacion = (float) ($habitacion['subtotal'] ?? 0);
                $diasHabitacion = max(1, $this->noches(
                    (string) ($habitacion['check_in'] ?? ($reserva['check_in_programado'] ?? ($reserva['check_in'] ?? ''))),
                    (string) ($habitacion['check_out'] ?? ($reserva['check_out_programado'] ?? ($reserva['check_out'] ?? '')))
                ));
                $precioBruto = $diasHabitacion > 0 ? ($subtotalHabitacion / $diasHabitacion) : 0;
            }

            $precioUnitario = round($precioBruto, 2);

            if ($precioUnitario <= 0) {
                return [
                    'exito' => false,
                    'mensaje' => 'Una de las habitaciones seleccionadas no tiene precio válido para generar la boleta o factura.',
                ];
            }

            $valorUnitario = $precioUnitario;
            $subtotal = round($valorUnitario * $nochesLinea, 2);
            $totalLinea = round($precioUnitario * $nochesLinea, 2);
            $igvLinea = 0.0;

            if ($subtotal <= 0 || $totalLinea <= 0) {
                return [
                    'exito' => false,
                    'mensaje' => 'El rango y habitaciones seleccionadas generan un total en cero. Revise las fechas, noches y precios antes de emitir.',
                ];
            }

            $items[] = [
                'unidad_de_medida' => 'ZZ',
                'codigo' => (string) ($habitacion['numero_habitacion'] ?? $habitacion['id'] ?? 'HAB'),
                'descripcion' => $this->limpiarTexto(sprintf(
                    'Hab. %s%s%s | %s al %s | %d noche%s',
                    (string) ($habitacion['numero_habitacion'] ?? '--'),
                    !empty($habitacion['piso']) ? ' - Piso ' . $habitacion['piso'] : '',
                    !empty($habitacion['tipo_nombre']) ? ' - ' . $habitacion['tipo_nombre'] : '',
                    $desdeLinea,
                    $hastaLinea,
                    $nochesLinea,
                    $nochesLinea === 1 ? '' : 's'
                )),
                'cantidad' => $nochesLinea,
                'valor_unitario' => $valorUnitario,
                'precio_unitario' => $precioUnitario,
                'descuento' => 0,
                'subtotal' => $subtotal,
                'tipo_de_igv' => 8,
                'igv' => $igvLinea,
                'total' => $totalLinea,
                'anticipo_regularizacion' => false,
                'anticipo_documento_serie' => '',
                'anticipo_documento_numero' => '',
            ];

            $totalIgv += $igvLinea;
            $total += $totalLinea;

            $habitacionesFacturadas[] = array_merge($habitacion, [
                'fecha_desde_facturada' => $desdeLinea,
                'fecha_hasta_facturada' => $hastaLinea,
                'noches_facturadas' => $nochesLinea,
            ]);
        }

        if (empty($items)) {
            return [
                'exito' => false,
                'mensaje' => 'Las habitaciones seleccionadas no tienen noches facturables dentro del rango de fechas elegido.',
            ];
        }

        if ($this->debeIncluirCargoCheckoutTarde($reserva, $fechaHasta, $documentosEmitidos)) {
            $cargoCheckoutTarde = round((float) ($reserva['cargo_checkout_tarde'] ?? 0), 2);

            $items[] = [
                'unidad_de_medida' => 'ZZ',
                'codigo' => 'CHECKOUT_TARDE',
                'descripcion' => $this->limpiarTexto(sprintf(
                    'Cargo por checkout tarde | Reserva %s | Salida %s',
                    (string) ($reserva['codigo_reserva'] ?? $reserva['id'] ?? '--'),
                    $this->fechaIso((string) ($reserva['checkout_real'] ?? $fechaHasta))
                )),
                'cantidad' => 1,
                'valor_unitario' => $cargoCheckoutTarde,
                'precio_unitario' => $cargoCheckoutTarde,
                'descuento' => 0,
                'subtotal' => $cargoCheckoutTarde,
                'tipo_de_igv' => 8,
                'igv' => 0,
                'total' => $cargoCheckoutTarde,
                'anticipo_regularizacion' => false,
                'anticipo_documento_serie' => '',
                'anticipo_documento_numero' => '',
            ];

            $total += $cargoCheckoutTarde;
        }

        return [
            'exito' => true,
            'items' => $items,
            'total_gravada' => round($totalGravada, 2),
            'total_exonerada' => round($total, 2),
            'total_igv' => round($totalIgv, 2),
            'total' => round($total, 2),
            'habitaciones_facturadas' => $habitacionesFacturadas,
        ];
    }

    private function rangoFacturableHabitacion(array $habitacion, array $reserva, string $fechaDesde, string $fechaHasta): ?array
    {
        $desdeHabitacion = $this->fechaIso((string) (
            $habitacion['check_in']
            ?? ($reserva['check_in_programado'] ?? ($reserva['check_in'] ?? ''))
        ));
        $hastaHabitacion = $this->fechaIso((string) (
            $habitacion['check_out']
            ?? ($reserva['check_out_programado'] ?? ($reserva['check_out'] ?? ''))
        ));

        if ($desdeHabitacion === '' || $hastaHabitacion === '') {
            return null;
        }

        $desde = max($fechaDesde, $desdeHabitacion);
        $hasta = min($fechaHasta, $hastaHabitacion);

        return $desde < $hasta ? ['desde' => $desde, 'hasta' => $hasta] : null;
    }

    private function debeIncluirCargoCheckoutTarde(array $reserva, string $fechaHasta, array $documentosEmitidos): bool
    {
        $cargoCheckoutTarde = round((float) ($reserva['cargo_checkout_tarde'] ?? 0), 2);

        if ($cargoCheckoutTarde <= 0 || $fechaHasta === '') {
            return false;
        }

        if ($this->cargoCheckoutTardeYaDocumentado($documentosEmitidos)) {
            return false;
        }

        $finHabitaciones = $this->fechaExtremaHabitaciones($reserva, 'check_out', 'max');
        $checkoutReal = $this->fechaIso((string) ($reserva['checkout_real'] ?? ''));
        $limites = array_values(array_filter([$finHabitaciones, $checkoutReal]));

        if (empty($limites)) {
            return false;
        }

        sort($limites);

        return $fechaHasta >= $limites[0];
    }

    private function cargoCheckoutTardeYaDocumentado(array $documentosEmitidos): bool
    {
        foreach ($documentosEmitidos as $documento) {
            $detalle = json_decode((string) ($documento['detalle_json'] ?? ''), true);

            if (!is_array($detalle)) {
                continue;
            }

            foreach ((array) ($detalle['items'] ?? []) as $item) {
                $codigo = strtoupper((string) ($item['codigo'] ?? ''));
                $descripcion = strtolower((string) ($item['descripcion'] ?? ''));

                if ($codigo === 'CHECKOUT_TARDE' || str_contains($descripcion, 'checkout tarde')) {
                    return true;
                }
            }
        }

        return false;
    }

    private function validarFechas(array $reserva, string $fechaDesde, string $fechaHasta): array
    {
        $checkIn = $this->fechaInicioFacturable($reserva);
        $checkOut = $this->fechaFinFacturable($reserva);

        if ($fechaDesde === '' || $fechaHasta === '' || $checkIn === '' || $checkOut === '') {
            return [
                'exito' => false,
                'mensaje' => 'Debe seleccionar un rango de fechas válido.'
            ];
        }

        if ($fechaDesde < $checkIn || $fechaHasta > $checkOut || $fechaHasta < $fechaDesde) {
            return [
                'exito' => false,
                'mensaje' => 'El rango del documento debe estar dentro de la reserva y generar al menos una noche.'
            ];
        }

        return [
            'exito' => true,
            'noches' => $this->noches($fechaDesde, $fechaHasta)
        ];
    }

    private function obtenerHabitacionesSeleccionadas(array $habitacionesReserva, array $habitacionesSolicitadas): array
    {
        $habitacionesSeleccionadas = [];

        foreach ($habitacionesSolicitadas as $habitacionSolicitada) {
            $idRelacion = (int) (
                $habitacionSolicitada['reserva_habitacion_id']
                ?? $habitacionSolicitada['id_reserva_habitacion']
                ?? 0
            );
            $idHabitacion = (int) (
                $habitacionSolicitada['id']
                ?? $habitacionSolicitada['id_habitacion']
                ?? 0
            );

            if ($idRelacion <= 0 && $idHabitacion <= 0) {
                return [
                    'exito' => false,
                    'mensaje' => 'Una de las habitaciones seleccionadas no pertenece a la reserva.'
                ];
            }

            $encontrada = false;

            foreach ($habitacionesReserva as $habitacionReserva) {
                $mismaRelacion = $idRelacion > 0
                    && (int) ($habitacionReserva['reserva_habitacion_id'] ?? 0) === $idRelacion;
                $mismaHabitacion = $idRelacion <= 0
                    && (int) ($habitacionReserva['id'] ?? $habitacionReserva['id_habitacion'] ?? 0) === $idHabitacion;

                if ($mismaRelacion || $mismaHabitacion) {
                    $habitacionesSeleccionadas[] = $habitacionReserva;
                    $encontrada = true;
                    break;
                }
            }

            if (!$encontrada) {
                return [
                    'exito' => false,
                    'mensaje' => 'Una de las habitaciones seleccionadas no pertenece a la reserva.'
                ];
            }
        }

        if (empty($habitacionesSeleccionadas)) {
            return [
                'exito' => false,
                'mensaje' => 'Debe seleccionar al menos una habitación válida.'
            ];
        }

        return [
            'exito' => true,
            'habitaciones' => $habitacionesSeleccionadas
        ];
    }

    private function validarRangoDentroDeHabitaciones(array $habitacionesSeleccionadas,   array $reserva,  string $fechaDesde,  string $fechaHasta): array
    {
        foreach ($habitacionesSeleccionadas as $habitacionSeleccionada) {
            $desdeHabitacion = $this->fechaIso((string) (
                $habitacionSeleccionada['check_in'] ?? ($reserva['check_in'] ?? '')
            ));

            $hastaHabitacion = $this->fechaIso((string) (
                $habitacionSeleccionada['check_out'] ?? ($reserva['check_out'] ?? '')
            ));

            if (
                $desdeHabitacion !== ''
                && $hastaHabitacion !== ''
                && ($fechaDesde < $desdeHabitacion || $fechaHasta > $hastaHabitacion)
            ) {
                return [
                    'exito' => false,
                    'mensaje' => 'El rango seleccionado debe estar dentro de las fechas asignadas a cada habitación.',
                ];
            }
        }

        return ['exito' => true];
    }

    private function idsHabitacionesDocumento(array $documento): array
    {
        $habitaciones = json_decode((string) ($documento['habitaciones_json'] ?? '[]'), true);

        if (!is_array($habitaciones)) {
            return [];
        }

        $ids = [];

        foreach ($habitaciones as $habitacion) {
            $id = (int) ($habitacion['id'] ?? $habitacion['id_habitacion'] ?? 0);

            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    private function habitacionesDocumentoConRango(array $documento): array
    {
        $habitaciones = json_decode((string) ($documento['habitaciones_json'] ?? '[]'), true);

        if (!is_array($habitaciones)) {
            return [];
        }

        $desdeDocumento = $this->fechaIso((string) ($documento['fecha_desde'] ?? ''));
        $hastaDocumento = $this->fechaIso((string) ($documento['fecha_hasta'] ?? ''));
        $resultado = [];

        foreach ($habitaciones as $habitacion) {
            if (!is_array($habitacion)) {
                continue;
            }

            $id = (int) ($habitacion['id'] ?? $habitacion['id_habitacion'] ?? 0);
            $desde = $this->fechaIso((string) (
                $habitacion['fecha_desde_facturada']
                ?? ($habitacion['check_in'] ?? $desdeDocumento)
            ));
            $hasta = $this->fechaIso((string) (
                $habitacion['fecha_hasta_facturada']
                ?? ($habitacion['check_out'] ?? $hastaDocumento)
            ));

            if ($id > 0 && $desde !== '' && $hasta !== '') {
                $resultado[] = [
                    'id' => $id,
                    'desde' => $desde,
                    'hasta' => $hasta,
                ];
            }
        }

        return $resultado;
    }

    private function rangosSeCruzan(string $inicioA,  string $finA,  string $inicioB, string $finB): bool
    {
        if ($inicioA === $finA) {
            $finA = (new \DateTimeImmutable($finA))
                ->modify('+1 day')
                ->format('Y-m-d');
        }

        if ($inicioB === $finB) {
            $finB = (new \DateTimeImmutable($finB))
                ->modify('+1 day')
                ->format('Y-m-d');
        }

        return $inicioA < $finB && $finA > $inicioB;
    }

    private function validarCrucesConDocumentos(array $documentos,  array $habitacionesSeleccionadas,  string $fechaDesde,  string $fechaHasta): array
    {
        foreach ($documentos as $documento) {
            $desdeEmitido = $this->fechaIso((string) ($documento['fecha_desde'] ?? ''));
            $hastaEmitido = $this->fechaIso((string) ($documento['fecha_hasta'] ?? ''));

            if (
                $desdeEmitido === ''
                || $hastaEmitido === ''
                || !$this->rangosSeCruzan($fechaDesde, $fechaHasta, $desdeEmitido, $hastaEmitido)
            ) {
                continue;
            }

            $habitacionesEmitidas = $this->habitacionesDocumentoConRango($documento);

            foreach ($habitacionesSeleccionadas as $habitacionSeleccionada) {
                $idSeleccionado = (int) ($habitacionSeleccionada['id'] ?? $habitacionSeleccionada['id_habitacion'] ?? 0);
                $desdeSeleccionado = $this->fechaIso((string) (
                    $habitacionSeleccionada['fecha_desde_facturada']
                    ?? ($habitacionSeleccionada['check_in'] ?? $fechaDesde)
                ));
                $hastaSeleccionado = $this->fechaIso((string) (
                    $habitacionSeleccionada['fecha_hasta_facturada']
                    ?? ($habitacionSeleccionada['check_out'] ?? $fechaHasta)
                ));

                foreach ($habitacionesEmitidas as $habitacionEmitida) {
                    if (
                        $idSeleccionado > 0
                        && $idSeleccionado === (int) ($habitacionEmitida['id'] ?? 0)
                        && $this->rangosSeCruzan(
                            $desdeSeleccionado,
                            $hastaSeleccionado,
                            (string) ($habitacionEmitida['desde'] ?? ''),
                            (string) ($habitacionEmitida['hasta'] ?? '')
                        )
                    ) {
                        return [
                            'exito' => false,
                            'mensaje' => sprintf(
                                'Ya existe un documento emitido para una de las habitaciones seleccionadas entre %s y %s. No se puede duplicar o cruzar el mismo periodo.',
                                (string) ($habitacionEmitida['desde'] ?? $desdeEmitido),
                                (string) ($habitacionEmitida['hasta'] ?? $hastaEmitido)
                            ),
                            'documento_cruzado' => $this->formatearRegistro($documento),
                        ];
                    }
                }
            }
        }

        return ['exito' => true];
    }

    private function calcularTotalDocumentado(array $documentosEmitidos): float
    {
        return round(array_reduce(
            $documentosEmitidos,
            static function ($acumulado, $documento) {
                return $acumulado + (float) ($documento['total'] ?? 0);
            },
            0.0
        ), 2);
    }

    private function obtenerDatosClienteDocumento(array $datos,  array $reserva,  string $tipoDocumento): array
    {
        $clienteNombre = trim((string) ($datos['cliente_denominacion'] ?? ($reserva['cliente'] ?? '')));
        $clienteNumero = trim((string) ($datos['cliente_numero_documento'] ?? ($reserva['documento'] ?? '')));
        $clienteTipoDocumento = strtoupper(trim((string) ($datos['cliente_tipo_documento'] ?? '')));
        $clienteEmail = trim((string) ($datos['cliente_email'] ?? ($reserva['correo_electronico'] ?? '')));
        $clienteDireccion = trim((string) ($datos['cliente_direccion'] ?? ($reserva['procedencia'] ?? '')));

        if ($tipoDocumento === 'FACTURA') {
            $clienteTipoDocumento = '6';

            if (strlen(preg_replace('/\D+/', '', $clienteNumero)) !== 11) {
                return [
                    'exito' => false,
                    'mensaje' => 'Para factura el cliente debe tener RUC de 11 dígitos.'
                ];
            }
        } elseif ($clienteTipoDocumento === '') {
            $numeroNormalizado = preg_replace('/\D+/', '', $clienteNumero);

            $clienteTipoDocumento = strlen($numeroNormalizado) === 11
                ? '6'
                : (strlen($numeroNormalizado) === 8 ? '1' : '-');
        }

        return [
            'exito' => true,
            'cliente_denominacion' => $clienteNombre !== '' ? $clienteNombre : ($reserva['cliente'] ?? ''),
            'cliente_numero_documento' => $clienteNumero,
            'cliente_tipo_documento' => $clienteTipoDocumento,
            'cliente_email' => $clienteEmail,
            'cliente_direccion' => $clienteDireccion,
        ];
    }

    private function construirObservaciones(array $reserva,  int $idReserva,  string $fechaDesde,  string $fechaHasta,  array $habitacionesSeleccionadas): string
    {
        $fechaReserva = $this->fechaIso((string) ($reserva['fecha_creacion'] ?? ''));
        $fechaIngresoReal = $this->fechaIso((string) ($reserva['checkin_real'] ?? ''));
        $fechaSalidaReal = $this->fechaIso((string) ($reserva['checkout_real'] ?? ''));

        $rangoOriginal = $fechaDesde === $this->fechaIso((string) (
            $reserva['check_in_programado'] ?? ($reserva['check_in'] ?? '')
        ))
            && $fechaHasta === $this->fechaIso((string) (
                $reserva['check_out_programado'] ?? ($reserva['check_out'] ?? '')
            ));

        $fechaIngresoDocumento = $rangoOriginal && $fechaIngresoReal !== ''
            ? $fechaIngresoReal
            : $fechaDesde;

        $fechaSalidaDocumento = $rangoOriginal && $fechaSalidaReal !== ''
            ? $fechaSalidaReal
            : $fechaHasta;

        return trim(implode(' | ', array_filter([
            'Reserva ' . ($reserva['codigo_reserva'] ?? $idReserva),
            $fechaReserva !== '' ? 'Fecha de reserva: ' . $fechaReserva : '',
            'Ingreso: ' . $fechaIngresoDocumento,
            'Salida: ' . $fechaSalidaDocumento,
            'Rango facturado: ' . $fechaDesde . ' al ' . $fechaHasta,
            'Habitaciones: ' . implode(', ', array_map(static function ($habitacion) {
                return 'Hab. ' . ($habitacion['numero_habitacion'] ?? $habitacion['id'] ?? '--');
            }, $habitacionesSeleccionadas)),
        ])));
    }


    private function formatearRegistro(array $registro): array
    {
        return [
            'id' => (int) ($registro['id'] ?? 0),
            'id_reserva' => (int) ($registro['id_reserva'] ?? 0),
            'tipo' => strtolower((string) ($registro['tipo_documento'] ?? 'boleta')) === 'factura'
                ? 'Factura'
                : 'Boleta',
            'tipo_documento' => (string) ($registro['tipo_documento'] ?? ''),
            'serie' => (string) ($registro['serie'] ?? ''),
            'numero' => (int) ($registro['numero'] ?? 0),
            'numero_documento' => trim((string) ($registro['serie'] ?? '') . '-' . (string) ($registro['numero'] ?? '')),
            'fecha' => (string) ($registro['fecha_emision'] ?? ''),
            'estado' => (string) ($registro['estado_sunat'] ?? 'emitido'),
            'monto' => (float) ($registro['total'] ?? 0),
            'descripcion' => (string) ($registro['detalle_json'] ?? ''),
            'enlace' => (string) ($registro['enlace'] ?? ''),
            'enlace_del_pdf' => (string) ($registro['enlace_del_pdf'] ?? ''),
            'enlace_del_xml' => (string) ($registro['enlace_del_xml'] ?? ''),
            'enlace_del_cdr' => (string) ($registro['enlace_del_cdr'] ?? ''),
            'cadena_para_codigo_qr' => (string) ($registro['cadena_para_codigo_qr'] ?? ''),
            'codigo_hash' => (string) ($registro['codigo_hash'] ?? ''),
        ];
    }

    private function formatearEmitido(array $documento): array
    {
        $detalle = json_decode((string) ($documento['detalle_json'] ?? ''), true);
        $habitaciones = json_decode((string) ($documento['habitaciones_json'] ?? ''), true);

        return [
            'id' => (int) ($documento['id'] ?? 0),
            'id_reserva' => (int) ($documento['id_reserva'] ?? 0),
            'es_documento_electronico' => true,
            'tipo' => strtoupper((string) ($documento['tipo_documento'] ?? '')) === 'FACTURA'
                ? 'Factura electrónica'
                : 'Boleta electrónica',
            'numero' => trim(
                (string) ($documento['serie'] ?? '')
                    . '-'
                    . str_pad((string) ($documento['numero'] ?? 0), 8, '0', STR_PAD_LEFT)
            ),
            'fecha' => $documento['created_at'] ?? ($documento['fecha_emision'] ?? ''),
            'estado' => $documento['estado_sunat'] ?? 'emitido',
            'monto' => (float) ($documento['total'] ?? 0),
            'descripcion' => is_array($detalle)
                ? (string) ($detalle['observaciones'] ?? '')
                : '',
            'fecha_desde' => $documento['fecha_desde'] ?? '',
            'fecha_hasta' => $documento['fecha_hasta'] ?? '',
            'habitaciones' => is_array($habitaciones) ? $habitaciones : [],
            'id_forma_pago' => null,
            'id_usuario' => $documento['id_usuario'] ?? null,
            'enlace' => $documento['enlace'] ?? '',
            'enlace_del_pdf' => $documento['enlace_del_pdf'] ?? '',
            'enlace_del_xml' => $documento['enlace_del_xml'] ?? '',
            'enlace_del_cdr' => $documento['enlace_del_cdr'] ?? '',
            'cadena_para_codigo_qr' => $documento['cadena_para_codigo_qr'] ?? '',
            'codigo_hash' => $documento['codigo_hash'] ?? '',
        ];
    }
}
