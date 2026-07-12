<?php

namespace Services\Reservas;

use Illuminate\Database\Capsule\Manager as DB;
use Helpers\FechaHotelHelper;
use Helpers\HabitacionInputHelper;
use Helpers\ReservaHelper;
use Models\HabitacionModel;
use Models\PagoModel;
use Models\ReporteOcupacionModel;
use Models\ReservaHabitacionModel;
use Models\ReservaModel;
use Services\Comprobantes\ComprobanteService;
use Services\ConfiguracionService;

class RegistrarReservaService
{
    private ReservaModel $reservaModel;
    private ReservaHabitacionModel $reservaHabitacionModel;
    private HabitacionModel $habitacionModel;
    private ReporteOcupacionModel $reporteOcupacionModel;
    private PagoModel $pagoModel;
    private ComprobanteService $comprobanteService;
    private ConfiguracionService $configuracionService;

    public function __construct()
    {
        $this->reservaModel = new ReservaModel();
        $this->reservaHabitacionModel = new ReservaHabitacionModel();
        $this->habitacionModel = new HabitacionModel();
        $this->reporteOcupacionModel = new ReporteOcupacionModel();
        $this->pagoModel = new PagoModel();
        $this->comprobanteService = new ComprobanteService();
        $this->configuracionService = new ConfiguracionService();
    }

    public function registrarReserva(array $reserva, ?int $idUsuario = null): array
    {
        try {
            $idUsuarioActual = $idUsuario
                ?? ($reserva['usuario'] ?? ($_SESSION['id_usuario'] ?? null));

            $checkIn = ReservaHelper::combinarFechaHora(
                $reserva['checkIn'] ?? null,
                $reserva['horaEntrada'] ?? null
            );

            $checkOut = ReservaHelper::combinarFechaHora(
                $reserva['checkOut'] ?? null,
                $reserva['horaSalida'] ?? null
            );

            $fechaEntrada = ReservaHelper::normalizarFecha($checkIn);
            $fechaMinimaIngreso = ReservaHelper::obtenerFechaMinimaIngresoHotel();

            if (!$fechaEntrada || $fechaEntrada < $fechaMinimaIngreso) {
                return $this->respuesta(
                    false,
                    'VALIDACION_ERROR',
                    'La fecha de check-in es anterior a la fecha hotelera permitida.'
                );
            }

            $dias = ReservaHelper::obtenerDiasEstadia($checkIn, $checkOut);

            if ($dias <= 0) {
                return $this->respuesta(false, 'VALIDACION_ERROR', 'Rango de fechas inválido.');
            }

            $idsHabitaciones = HabitacionInputHelper::obtenerIdsDesdeRequest($reserva);

            if (empty($idsHabitaciones)) {
                return $this->respuesta(false, 'VALIDACION_ERROR', 'Debe seleccionar al menos una habitación válida.');
            }

            $habitacionesNormalizadas = [];
            $totalCalculado = 0;

            foreach ($idsHabitaciones as $idHabitacion) {
                $disponibilidad = $this->reporteOcupacionModel->validarDisponibilidadHabitacion(
                    $idHabitacion,
                    $checkIn,
                    $checkOut
                );

                if (!$disponibilidad['disponible']) {
                    return $this->respuesta(false, 'CONFLICTO', $disponibilidad['mensaje']);
                }

                $habitacionActual = $this->habitacionModel->obtenerPorId($idHabitacion);

                if (!$habitacionActual) {
                    return $this->respuesta(false, 'NO_ENCONTRADO', 'No se encontró una de las habitaciones seleccionadas.');
                }

                $precioHabitacion = (float) ($habitacionActual['precio'] ?? 0);
                $subtotal = $precioHabitacion * $dias;

                $habitacionesNormalizadas[] = [
                    'id' => $idHabitacion,
                    'habitacion' => $habitacionActual,
                    'precio' => $precioHabitacion,
                    'dias' => $dias,
                    'subtotal' => $subtotal,
                ];

                $totalCalculado += $subtotal;
            }

            $estadoReserva = strtolower(trim((string) ($reserva['estado'] ?? 'confirmada')));
            $esPagoPendiente = $estadoReserva === 'pendiente'
                || !empty($reserva['dejar_pago_pendiente']);

            $pagoInicial = $reserva['pago'] ?? null;

            $montoPagoInicial = is_array($pagoInicial)
                ? (float) ($pagoInicial['monto'] ?? 0)
                : 0;

            if (!$esPagoPendiente && $montoPagoInicial <= 0) {
                return $this->respuesta(false, 'VALIDACION_ERROR', 'Debe registrar un pago inicial para realizar la reserva.');
            }

            $hotelConfig = $this->configuracionService->obtenerHotel(1);
            $porcentajeAdelanto = 50; 
            if ($hotelConfig['exito'] && isset($hotelConfig['data']['porcentaje_adelanto'])) {
                $porcentajeAdelanto = (float) $hotelConfig['data']['porcentaje_adelanto'];
            }

            $montoMinimoInicial = round($totalCalculado * ($porcentajeAdelanto / 100), 2);

            if (!$esPagoPendiente && $montoPagoInicial < $montoMinimoInicial) {
                return $this->respuesta(false, 'VALIDACION_ERROR', "El pago inicial debe ser al menos el {$porcentajeAdelanto}% del total de la reserva. Monto mínimo: S/ " . number_format($montoMinimoInicial, 2));
            }

            if (!$esPagoPendiente && $montoPagoInicial > $totalCalculado) {
                return $this->respuesta(false, 'VALIDACION_ERROR', 'El pago inicial no puede ser mayor al total de la reserva.');
            }

            DB::connection()->beginTransaction();

            $reservaCreada = $this->reservaModel->crear([
                'id_cliente' => $reserva['cliente'] ?? null,
                'total' => $totalCalculado,
                'estado' => $esPagoPendiente ? 'pendiente' : 'confirmada',
                'codigo_reserva' => $reserva['codigoReserva'] ?? $this->reservaModel->generarCodigoReserva(),
                'id_usuario' => $idUsuarioActual,
                'observaciones' => $reserva['observaciones'] ?? null,
                'fecha_creacion' => FechaHotelHelper::ahora(),
                'check_in_programado' => $checkIn,
                'check_out_programado' => $checkOut,
                'checkin_real' => null,
                'checkout_real' => null,
            ]);

            $idReserva = (int) $reservaCreada->id;

            foreach ($habitacionesNormalizadas as $habitacionNormalizada) {
                $this->reservaHabitacionModel->crear([
                    'id_reserva' => $idReserva,
                    'id_habitacion' => $habitacionNormalizada['id'],
                    'check_in' => $checkIn,
                    'check_out' => $checkOut,
                    'activo' => 1,
                    'tipo_asignacion' => 'original',
                    'estado' => 'activa',
                    'precio_aplicado' => $habitacionNormalizada['precio'],
                    'subtotal' => $habitacionNormalizada['subtotal'],
                    'id_usuario_movimiento' => $idUsuarioActual,
                    'fecha_movimiento' => FechaHotelHelper::ahora(),
                ]);
            }

            if ($esPagoPendiente) {
                DB::connection()->commit();

                return $this->respuesta(true, 'CREADO', 'Reserva registrada como pendiente de pago.', [
                    'id_reserva' => $idReserva,
                    'estado' => 'pendiente',
                ]);
            }

            $pago = $this->pagoModel->crear([
                'id_reserva' => $idReserva,
                'monto' => $montoPagoInicial,
                'descripcion' => $pagoInicial['descripcion'] ?? 'Pago inicial',
                'fecha_pago' => $pagoInicial['fecha_pago'] ?? FechaHotelHelper::ahora(),
                'id_metodo_pago' => (int) ($pagoInicial['id_metodo_pago'] ?? 0),
                'id_usuario' => $idUsuarioActual,
            ]);

            if (!$pago) {
                throw new \RuntimeException('No se pudo registrar el pago inicial.');
            }

            $comprobante = $this->comprobanteService->crearDesdePago(
                $pago,
                ['total' => $totalCalculado],
                $habitacionesNormalizadas,
                $idUsuarioActual
            );

            if (!$comprobante) {
                throw new \RuntimeException('No se pudo generar el comprobante del pago inicial.');
            }

            $comprobanteData = $this->comprobanteService->obtenerPorPago((int) $pago->id);
            $comprobanteData['cliente'] = !empty($reserva['nombre']) ? trim($reserva['nombre']) : '—';
            DB::connection()->commit();

            return $this->respuesta(true, 'CREADO', 'Reserva registrada correctamente.', [
                'id_reserva' => $idReserva,
                'pago_id' => (int) $pago->id,
                'comprobante' => $comprobanteData,
            ]);
        } catch (\Throwable $e) {
            error_log('RegistrarReservaService::registrarReserva -> ' . $e->getMessage());
            $conexion = DB::connection();

            if ($conexion->getPdo()->inTransaction()) {
                $conexion->rollBack();
            }

            return $this->respuesta(false, 'EXCEPCION', 'No se pudo registrar la reserva. Intente nuevamente.');
        }
    }

    private function respuesta(bool $exito, string $codigo, string $mensaje, mixed $data = null, array $errores = []): array
    {
        $respuesta = [
            'exito' => $exito,
            'codigo' => $codigo,
            'mensaje' => $mensaje,
            'data' => $data,
            'errores' => $errores,
        ];

        return is_array($data) ? array_merge($respuesta, $data) : $respuesta;
    }
}
