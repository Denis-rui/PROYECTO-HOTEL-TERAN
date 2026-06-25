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
            $this->responderJson($service->listarParaDataTable($_POST));
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

}
