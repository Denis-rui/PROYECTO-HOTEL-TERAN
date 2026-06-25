<?php

namespace Controllers;

use Libraries\Core\Controller;
use Services\DashboardService;
use Services\Reservas\CheckInReservaService;
use Services\Reservas\CheckOutReservaService;
use Services\Reservas\AusenciaReservaService;
use Services\Reservas\CancelarReservaService;
use Services\Reservas\RegistrarReservaService;
use Services\Reservas\ActualizarReservaService;
use Services\Reservas\ExtenderEstadiaService;
use Services\Reservas\CambiarHabitacionService;
use Services\Reservas\ConsultarReservaService;
use Services\Pagos\RegistrarPagoService;
use Services\Comprobantes\DocumentoElectronicoService;
use Services\Devoluciones\CalculoDevolucionService;
use Services\NotificacionService;



class ReservaController extends Controller
{
    public function index($params = '')
    {
        if (!isset($_SESSION['usuario'])) {
            header('Location: ' . BASE_URL . 'Login/index');
            exit();
        }

        // La vista de reservas ahora solo pinta la estructura de la tabla.
        // Los registros se cargan aparte desde Reserva/datatable usando Ajax + DataTables server-side.
        $data['filtros'] = [
            'busqueda' => trim((string) ($_GET['busqueda'] ?? '')),
            'estado' => trim((string) ($_GET['estado'] ?? '')),
        ];

        $data['page_js'] = ['Clientes.js', 'Modal-Clientes.js', 'Modal-NuevaReserva.js', 'Pago.js', 'Comprobante.js', 'Modal-VerDetalles.js', 'DocumentoElectronico.js', 'Reservas.js'];
        $this->views->render($this, 'index', $data);
    }

    public function datatable($params = '')
    {
        if (!isset($_SESSION['usuario'])) {
            $this->responderJson([
                'draw' => (int) ($_POST['draw'] ?? 0),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => 'Sesión no válida.',
            ], 401);
        }

        try {
            $service = new ConsultarReservaService();
            $resultado = $service->listarParaDataTable($_POST);

            $filas = array_map(
                fn(array $reserva): array => $this->formatearFilaReservaDataTable($reserva),
                $resultado['items'] ?? []
            );

            // DataTables necesita estas llaves exactas para sincronizar paginación, búsqueda y total.
            $this->responderJson([
                'draw' => (int) ($_POST['draw'] ?? 0),
                'recordsTotal' => (int) ($resultado['total'] ?? 0),
                'recordsFiltered' => (int) ($resultado['filtrados'] ?? 0),
                'data' => $filas,
            ]);
        } catch (\Throwable $e) {
            error_log('ReservaController::datatable -> ' . $e->getMessage());
            $this->responderJson([
                'draw' => (int) ($_POST['draw'] ?? 0),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => 'No se pudieron cargar las reservas.',
            ], 500);
        }
    }

    public function emitirDocumentoElectronico($params = '')
    {
        $datos = $this->obtenerPayloadJson() ?? [];
        $modelo = new DocumentoElectronicoService();
        $this->responderJson($modelo->emitir($datos, $_SESSION['id_usuario'] ?? null));
    }


    public function registrar($params = '')
    {
        $datos = $this->obtenerPayloadJson();

        if (!is_array($datos)) {
            $this->responderJson([
                'exito' => false,
                'mensaje' => 'Datos inválidos.'
            ], 400);
        }
        $service = new RegistrarReservaService();

        $resultado = $service->registrarReserva(
            $datos,
            $_SESSION['id_usuario'] ?? null
        );

        $this->responderJson($resultado);
    }



    public function actualizar($params = '')
    {
        $datos = $this->obtenerPayloadJson();

        if (!is_array($datos)) {
            $this->responderJson([
                'exito' => false,
                'mensaje' => 'Datos inválidos.'
            ], 400);
        }

        $service = new ActualizarReservaService();

        $resultado = $service->actualizarReserva(
            $datos,
            $_SESSION['id_usuario'] ?? null
        );

        $this->responderJson($resultado);
    }

    public function pago($params = '')
    {
        $datos = $this->obtenerPayloadJson();

        if (!is_array($datos)) {
            $this->responderJson([
                'exito' => false,
                'mensaje' => 'Datos inválidos.'
            ], 400);
        }

        $service = new RegistrarPagoService();

        $resultado = $service->registrarPago(
            (int) ($datos['id_reserva'] ?? 0),
            (float) ($datos['monto'] ?? 0),
            (int) ($datos['id_metodo_pago'] ?? 0),
            (string) ($datos['descripcion'] ?? ''),
            $datos['fecha_pago'] ?? null,
            $_SESSION['id_usuario'] ?? null
        );

        $this->responderJson($resultado);
    }

    public function checkin($params = '')
    {
        $datos = $this->obtenerPayloadJson() ?? [];
        $idReserva = (int) ($datos['id_reserva'] ?? 0);

        $service = new CheckInReservaService();

        $resultado = $service->confirmarCheckIn(
            $idReserva,
            $_SESSION['id_usuario'] ?? null
        );

        $this->responderJson($resultado);
    }

    public function checkout($params = '')
    {
        $datos = $this->obtenerPayloadJson() ?? [];

        $idReserva = (int) ($datos['id_reserva'] ?? 0);
        $autorizarSaldo = (bool) ($datos['autorizar_saldo'] ?? false);
        $motivoAutorizacion = trim((string) ($datos['motivo_autorizacion'] ?? ''));

        $service = new CheckOutReservaService();

        $resultado = $service->confirmarCheckout(
            $idReserva,
            $_SESSION['id_usuario'] ?? null,
            $autorizarSaldo,
            $motivoAutorizacion
        );

        $this->responderJson($resultado);
    }

    public function marcarAusente($params = '')
    {
        $datos = $this->obtenerPayloadJson() ?? [];
        $idReserva = (int) ($datos['id_reserva'] ?? 0);

        $service = new AusenciaReservaService();

        $resultado = $service->marcarAusente(
            $idReserva,
            $_SESSION['id_usuario'] ?? null
        );

        $this->responderJson($resultado);
    }

    public function marcarRegreso($params = '')
    {
        $datos = $this->obtenerPayloadJson() ?? [];
        $idReserva = (int) ($datos['id_reserva'] ?? 0);

        $service = new AusenciaReservaService();

        $resultado = $service->marcarRegreso(
            $idReserva,
            $_SESSION['id_usuario'] ?? null
        );

        $this->responderJson($resultado);
    }

    public function obtener($params = '')
    {
        $id = (int) ($params ?? 0);
        $service = new ConsultarReservaService();
        $this->responderJson($service->obtenerPorId($id));
    }

    public function dashboard($params = '')
    {
        $dashboardService = new DashboardService();
        $respuesta = $dashboardService->obtenerEstadisticas();
        $this->responderJson($respuesta['data']);
    }

    public function notificaciones($params = '')
    {
        $notificacionService = new NotificacionService();
        $respuesta = $notificacionService->obtenerNotificacionesCheckout();

        $this->responderJson($respuesta['data']);
    }

    public function calcularTotal($params = '')
    {
        $datos = $this->obtenerPayloadJson() ?? [];
        $service = new ConsultarReservaService();
        $resultado = $service->calcularTotal(
            (int) ($datos['id_habitacion'] ?? 0),
            $datos['check_in']  ?? '',
            $datos['check_out'] ?? ''
        );
        $this->responderJson($resultado);
    }

    public function extenderEstadia($params = '')
    {
        $datos = $this->obtenerPayloadJson() ?? [];

        $idReserva = (int) ($datos['id_reserva'] ?? 0);
        $nuevoCheckOut = (string) ($datos['nuevo_check_out'] ?? $datos['checkOut'] ?? '');

        $service = new ExtenderEstadiaService();

        $resultado = $service->extenderEstadia(
            $idReserva,
            $nuevoCheckOut,
            $_SESSION['id_usuario'] ?? null
        );

        $this->responderJson($resultado);
    }

    public function cancelar($params = '')
    {
        $datos = $this->obtenerPayloadJson() ?? [];

        $idReserva = (int) ($datos['id_reserva'] ?? 0);
        $motivo = trim((string) ($datos['motivo'] ?? ''));

        $service = new CancelarReservaService();

        $resultado = $service->cancelarReserva(
            $idReserva,
            $motivo,
            $_SESSION['id_usuario'] ?? null
        );

        $this->responderJson($resultado);
    }

    public function calcularCancelacion($params = '')
    {
        $datos = $this->obtenerPayloadJson() ?? [];
        $modelo = new CalculoDevolucionService();
        $this->responderJson($modelo->calcular((int) ($datos['id_reserva'] ?? 0)));
    }

    public function cambiarHabitacion($params = '')
    {
        $datos = $this->obtenerPayloadJson() ?? [];

        $service = new CambiarHabitacionService();

        $resultado = $service->cambiarHabitacion(
            (int) ($datos['id_reserva'] ?? 0),
            (int) ($datos['id_habitacion_actual'] ?? 0),
            (int) ($datos['id_habitacion_nueva'] ?? 0),
            (string) ($datos['tipo_motivo'] ?? ''),
            (string) ($datos['motivo'] ?? ''),
            $_SESSION['id_usuario'] ?? null
        );

        $this->responderJson($resultado);
    }

    private function formatearFilaReservaDataTable(array $reserva): array
    {
        // DataTables permite enviar atributos para el <tr> con DT_RowAttr.
        // Los mantenemos porque los botones/modales actuales leen datos desde fila.dataset.
        return [
            'DT_RowAttr' => $this->atributosFilaReserva($reserva),
            'codigo_reserva' => $this->e($reserva['codigo_reserva'] ?? ('#' . ($reserva['id'] ?? ''))),
            'cliente' => $this->e($reserva['cliente'] ?? ''),
            'habitacion' => $this->htmlHabitacionesReserva($reserva),
            'check_in' => $this->e($this->formatearFechaReserva($reserva['check_in'] ?? null)),
            'check_out' => $this->e($this->formatearFechaReserva($reserva['check_out'] ?? null)),
            'estado' => $this->htmlEstadoReserva((string) ($reserva['estado'] ?? '')),
            'pago' => $this->htmlPagoReserva($reserva),
            'acciones' => $this->htmlAccionesReserva($reserva),
        ];
    }

    private function atributosFilaReserva(array $reserva): array
    {
        return [
            'data-id' => (int) ($reserva['id'] ?? 0),
            'data-estado' => (string) ($reserva['estado'] ?? ''),
            'data-porcentajepago' => (string) ($reserva['porcentaje_pago'] ?? 0),
            'data-total' => (string) ($reserva['total'] ?? 0),
            'data-saldo-pendiente' => (string) ($reserva['saldo_pendiente'] ?? 0),
            'data-cliente' => (string) ($reserva['cliente'] ?? ''),
            'data-cliente-documento' => (string) ($reserva['documento'] ?? ''),
            'data-cliente-tipo-documento' => (string) ($reserva['id_tipo_documento'] ?? ''),
            'data-cliente-direccion' => (string) ($reserva['cliente_direccion'] ?? $reserva['procedencia'] ?? ''),
            'data-habitacion' => (string) ($reserva['habitacion'] ?? ''),
            'data-habitaciones' => json_encode($reserva['habitaciones'] ?? [], JSON_UNESCAPED_UNICODE),
            'data-checkin' => (string) ($reserva['check_in'] ?? ''),
            'data-checkout' => (string) ($reserva['check_out'] ?? ''),
            'data-email' => (string) ($reserva['correo_electronico'] ?? ''),
            'data-total-pagado' => (string) ($reserva['total_pagado'] ?? 0),
            'data-dias-estadia' => (string) ($reserva['dias_estadia'] ?? 0),
        ];
    }

    private function htmlHabitacionesReserva(array $reserva): string
    {
        $habitaciones = $reserva['habitaciones'] ?? [];
        if (is_array($habitaciones) && !empty($habitaciones)) {
            $partes = [];

            foreach ($habitaciones as $habitacion) {
                if (!is_array($habitacion)) {
                    continue;
                }

                $numero = $habitacion['numero_habitacion'] ?? '';
                $piso = $habitacion['piso'] ?? '';
                $tipo = $habitacion['tipo_nombre'] ?? '';
                $texto = trim('Hab. ' . $numero . ($piso !== '' ? ' - Piso ' . $piso : '') . ($tipo !== '' ? ' - ' . $tipo : ''));

                if ($texto !== '') {
                    $partes[] = $this->e($texto);
                }
            }

            if (!empty($partes)) {
                return implode('<br>', $partes);
            }
        }

        return $this->e($reserva['habitacion'] ?? 'Sin habitación');
    }

    private function htmlEstadoReserva(string $estado): string
    {
        return sprintf(
            '<span class="estado-reserva %s" data-estado="%s">%s</span>',
            $this->e($this->claseEstadoReserva($estado)),
            $this->e($estado),
            $this->e($this->textoEstadoReserva($estado))
        );
    }

    private function htmlPagoReserva(array $reserva): string
    {
        $porcentaje = (int) ($reserva['porcentaje_pago'] ?? 0);
        $porcentaje = max(0, min(100, $porcentaje));

        return sprintf(
            '<div class="barra-pago"><span style="width:%d%%"></span></div><small>%d%% pagado</small>',
            $porcentaje,
            $porcentaje
        );
    }

    private function htmlAccionesReserva(array $reserva): string
    {
        $id = (int) ($reserva['id'] ?? 0);
        $estado = (string) ($reserva['estado'] ?? '');
        $codigo = $this->e($reserva['codigo_reserva'] ?? ('#' . $id));
        $cliente = $this->e($reserva['cliente'] ?? '');
        $checkIn = $this->e($this->formatearFechaReserva($reserva['check_in'] ?? null));
        $editarDisabled = $estado === 'checkout_realizado'
            ? ' disabled title="No se puede editar una reserva con checkout realizado"'
            : '';

        $html = '<div class="acciones-reserva">';
        $html .= sprintf('<button class="boton-editar-reserva" data-id="%d"%s>✏️</button>', $id, $editarDisabled);

        if ($estado === 'confirmada') {
            $html .= sprintf('<button class="boton-checkin-reserva" data-id="%d" title="Confirmar check-in">Check-in</button>', $id);
        } elseif (in_array($estado, ['en_estadia', 'checkout_pendiente'], true)) {
            $html .= sprintf('<button class="boton-checkout-reserva" data-id="%d" title="Confirmar checkout">Checkout</button>', $id);
        }

        $html .= sprintf('<button class="boton-pago-tabla" data-id="%d" title="Registrar pago">💳</button>', $id);
        $html .= '<div class="menu-mas-opciones-wrap">';
        $html .= '<button type="button" class="boton-mas-opciones" aria-label="Más opciones">⋮</button>';
        $html .= sprintf('<div class="menu-mas-opciones-panel" data-id="%d">', $id);

        if ($estado === 'en_estadia') {
            $html .= sprintf('<button type="button" class="item-menu-opcion accion-marcar-ausente" data-id="%d">Marcar ausente</button>', $id);
        } elseif ($estado === 'ausente') {
            $html .= sprintf('<button type="button" class="item-menu-opcion accion-marcar-regreso" data-id="%d">Marcar regreso</button>', $id);
        }

        $html .= sprintf('<button type="button" class="item-menu-opcion accion-emitir-documento" data-id="%d">Emitir boleta / factura</button>', $id);
        $html .= sprintf('<button type="button" class="item-menu-opcion accion-ver-detalles" data-id="%d">Ver detalles</button>', $id);
        $html .= sprintf('<button type="button" class="item-menu-opcion boton-extender-reserva" data-id="%d">Extender estadía</button>', $id);
        $html .= sprintf('<button type="button" class="item-menu-opcion boton-cambio-habitacion" data-id="%d">Cambiar habitación</button>', $id);

        if (!in_array($estado, ['cancelada', 'checkout_realizado'], true)) {
            $html .= sprintf(
                '<button type="button" class="item-menu-opcion accion-cancelar-reserva" data-id="%d" data-codigo="%s" data-cliente="%s" data-checkin="%s">Cancelar reserva</button>',
                $id,
                $codigo,
                $cliente,
                $checkIn
            );
        }

        $html .= '</div></div></div>';

        return $html;
    }

    private function formatearFechaReserva(?string $fecha): string
    {
        if (empty($fecha)) {
            return 'Sin fecha';
        }

        $timestamp = strtotime($fecha);
        return $timestamp ? date('d/m/Y H:i', $timestamp) : $fecha;
    }

    private function textoEstadoReserva(string $estado): string
    {
        $mapa = [
            'confirmada' => 'Confirmada',
            'en_estadia' => 'En estadía',
            'ausente' => 'Ausente',
            'checkout_pendiente' => 'Checkout pendiente',
            'checkout_realizado' => 'Checkout',
            'cancelada' => 'Cancelada',
        ];

        return $mapa[strtolower(trim($estado))] ?? ucfirst($estado);
    }

    private function claseEstadoReserva(string $estado): string
    {
        $mapa = [
            'confirmada' => 'estado-confirmada',
            'en_estadia' => 'estado-en-estadia',
            'ausente' => 'estado-ausente',
            'checkout_pendiente' => 'estado-checkout-pendiente',
            'checkout_realizado' => 'estado-checkout-realizado',
            'cancelada' => 'estado-cancelada',
        ];

        return $mapa[strtolower(trim($estado))] ?? 'estado-reserva-desconocido';
    }

    private function e(mixed $valor): string
    {
        return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
