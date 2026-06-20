<?php

namespace Controllers;

use Libraries\Core\Controller;
use Models\DashboardModel;
use Models\NotificacionModel;
use Models\ReporteOcupacionModel;
use Services\Reservas\CheckInReservaService;
use Services\Reservas\CheckOutReservaService;
use Services\Reservas\ActualizarEstadoReservaService;
use Services\Reservas\AusenciaReservaService;
use Services\Reservas\CancelarReservaService;
use Services\Reservas\RegistrarReservaService;
use Services\Reservas\ActualizarReservaService;
use Services\Reservas\ExtenderEstadiaService;
use Services\Reservas\CambiarHabitacionService;
use Services\Pagos\RegistrarPagoService;
use Services\Comprobantes\DocumentoElectronicoService;
use Services\Devoluciones\CalculoDevolucionService;



class ReservaController extends Controller
{
    public function index($params = '')
    {
        if (!isset($_SESSION['usuario'])) {
            header('Location: ' . BASE_URL . 'Login/index');
            exit();
        }

        $data['reservas'] = [];
        $data['error_reservas'] = '';
        $data['filtros'] = [
            'busqueda' => trim((string) ($_GET['busqueda'] ?? '')),
            'estado' => trim((string) ($_GET['estado'] ?? '')),
        ];
        $data['limite'] = max(30, (int) ($_GET['limite'] ?? 30));
        $data['hay_mas'] = false;
        $data['total_reservas'] = 0;
        $data['mostradas_reservas'] = 0;

        try {
            $resultado = $this->model->obtenerReservas($data['filtros'], $data['limite']);

            if (!is_array($resultado)) {
                throw new \RuntimeException('Resultado no es un array');
            }

            $items = $resultado['items'] ?? [];
            if (count($items) === 1 && is_string($items[0])) {
                $data['error_reservas'] = 'Error al cargar las reservas. Intenta nuevamente en unos minutos.';
            } else {
                $data['reservas'] = is_array($items) ? $items : [];
                $data['hay_mas'] = (bool) ($resultado['hay_mas'] ?? false);
                $data['total_reservas'] = (int) ($resultado['total'] ?? 0);
                $data['mostradas_reservas'] = (int) ($resultado['mostrados'] ?? count($data['reservas']));
            }
        } catch (\Throwable $e) {
            error_log('ReservaController::index -> ' . $e->getMessage());
            $data['error_reservas'] = 'Error al cargar las reservas. Intenta nuevamente en unos minutos.';
        }

        $data['page_js'] = ['Clientes.js', 'Modal-Clientes.js', 'Modal-NuevaReserva.js', 'Pago.js', 'Comprobante.js', 'Modal-VerDetalles.js', 'DocumentoElectronico.js', 'Reservas.js'];
        $this->views->render($this, 'index', $data);
    }

    public function emitirDocumentoElectronico($params = '')
    {
        header('Content-Type: application/json');
        $datos = json_decode(file_get_contents('php://input'), true) ?: [];
        $modelo = new DocumentoElectronicoService();
        echo json_encode($modelo->emitir($datos, $_SESSION['id_usuario'] ?? null));
    }


    public function registrar($params = '')
    {
        header('Content-Type: application/json; charset=utf-8');

        $datos = json_decode(file_get_contents('php://input'), true);

        if (!is_array($datos)) {
            echo json_encode([
                'exito' => false,
                'mensaje' => 'Datos inválidos.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }
        $service = new RegistrarReservaService();

        $resultado = $service->registrarReserva(
            $datos,
            $_SESSION['id_usuario'] ?? null
        );

        echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
    }



    public function actualizar($params = '')
    {
        header('Content-Type: application/json; charset=utf-8');

        $datos = json_decode(file_get_contents('php://input'), true);

        if (!is_array($datos)) {
            echo json_encode([
                'exito' => false,
                'mensaje' => 'Datos inválidos.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $service = new ActualizarReservaService();

        $resultado = $service->actualizarReserva(
            $datos,
            $_SESSION['id_usuario'] ?? null
        );

        echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
    }

    public function pago($params = '')
    {
        header('Content-Type: application/json; charset=utf-8');

        $datos = json_decode(file_get_contents('php://input'), true);

        if (!is_array($datos)) {
            echo json_encode([
                'exito' => false,
                'mensaje' => 'Datos inválidos.'
            ], JSON_UNESCAPED_UNICODE);
            return;
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

        echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
    }

    public function checkin($params = '')
    {
        header('Content-Type: application/json; charset=utf-8');

        $datos = json_decode(file_get_contents('php://input'), true);
        $idReserva = (int) ($datos['id_reserva'] ?? 0);

        $service = new CheckInReservaService();

        $resultado = $service->confirmarCheckIn(
            $idReserva,
            $_SESSION['id_usuario'] ?? null
        );

        echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
    }

    public function checkout($params = '')
    {
        header('Content-Type: application/json; charset=utf-8');

        $datos = json_decode(file_get_contents('php://input'), true);

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

        echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
    }

    public function marcarAusente($params = '')
    {
        header('Content-Type: application/json; charset=utf-8');

        $datos = json_decode(file_get_contents('php://input'), true);
        $idReserva = (int) ($datos['id_reserva'] ?? 0);

        $service = new AusenciaReservaService();

        $resultado = $service->marcarAusente(
            $idReserva,
            $_SESSION['id_usuario'] ?? null
        );

        echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
    }

    public function marcarRegreso($params = '')
    {
        header('Content-Type: application/json; charset=utf-8');

        $datos = json_decode(file_get_contents('php://input'), true);
        $idReserva = (int) ($datos['id_reserva'] ?? 0);

        $service = new AusenciaReservaService();

        $resultado = $service->marcarRegreso(
            $idReserva,
            $_SESSION['id_usuario'] ?? null
        );

        echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
    }
    public function actualizarEstado($params = '')
    {
        header('Content-Type: application/json; charset=utf-8');

        $datos = json_decode(file_get_contents('php://input'), true);

        $idReserva = (int) ($datos['id_reserva'] ?? 0);
        $nuevoEstado = trim((string) ($datos['estado'] ?? ''));

        $service = new ActualizarEstadoReservaService();

        $resultado = $service->actualizarEstadoReserva(
            $idReserva,
            $nuevoEstado
        );

        echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
    }

    public function obtener($params = '')
    {
        header('Content-Type: application/json');
        $id = (int) ($params ?? 0);
        echo json_encode($this->model->obtenerReservaPorId($id));
    }

    public function dashboard($params = '')
    {
        header('Content-Type: application/json');
        $dashboardModel = new DashboardModel();
        echo json_encode($dashboardModel->obtenerEstadisticasDashboard());
    }

    public function notificaciones($params = '')
    {
        header('Content-Type: application/json');
        $notificacionModel = new NotificacionModel();
        echo json_encode($notificacionModel->obtenerNotificacionesCheckout());
    }

    public function calcularTotal($params = '')
    {
        header('Content-Type: application/json');
        $datos     = json_decode(file_get_contents('php://input'), true);
        $reporteOcupacionModel = new ReporteOcupacionModel();
        $resultado = $reporteOcupacionModel->calcularTotalReserva(
            (int) ($datos['id_habitacion'] ?? 0),
            $datos['check_in']  ?? '',
            $datos['check_out'] ?? ''
        );
        echo json_encode($resultado);
    }

    public function extenderEstadia($params = '')
    {
        header('Content-Type: application/json; charset=utf-8');

        $datos = json_decode(file_get_contents('php://input'), true);

        $idReserva = (int) ($datos['id_reserva'] ?? 0);
        $nuevoCheckOut = (string) ($datos['nuevo_check_out'] ?? $datos['checkOut'] ?? '');

        $service = new ExtenderEstadiaService();

        $resultado = $service->extenderEstadia(
            $idReserva,
            $nuevoCheckOut,
            $_SESSION['id_usuario'] ?? null
        );

        echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
    }

    public function consumo($params = '')
    {
        header('Content-Type: application/json');
        $datos     = json_decode(file_get_contents('php://input'), true);
        $resultado = $this->model->registrarConsumo(
            (int) ($datos['id_reserva']      ?? 0),
            $datos['concepto']       ?? '',
            (int) ($datos['cantidad']        ?? 1),
            (float) ($datos['precio_unitario'] ?? 0),
            $_SESSION['id_usuario'] ?? null
        );
        echo json_encode($resultado);
    }
    public function cancelar($params = '')
    {
        header('Content-Type: application/json; charset=utf-8');

        $datos = json_decode(file_get_contents('php://input'), true);

        $idReserva = (int) ($datos['id_reserva'] ?? 0);
        $motivo = trim((string) ($datos['motivo'] ?? ''));

        $service = new CancelarReservaService();

        $resultado = $service->cancelarReserva(
            $idReserva,
            $motivo,
            $_SESSION['id_usuario'] ?? null
        );

        echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
    }

    public function calcularCancelacion($params = '')
    {
        header('Content-Type: application/json');
        $datos = json_decode(file_get_contents('php://input'), true) ?: [];
        $modelo = new CalculoDevolucionService();
        echo json_encode($modelo->calcular((int) ($datos['id_reserva'] ?? 0)));
    }

    public function cambiarHabitacion($params = '')
    {
        header('Content-Type: application/json; charset=utf-8');

        $datos = json_decode(file_get_contents('php://input'), true);

        $service = new CambiarHabitacionService();

        $resultado = $service->cambiarHabitacion(
            (int) ($datos['id_reserva'] ?? 0),
            (int) ($datos['id_habitacion_actual'] ?? 0),
            (int) ($datos['id_habitacion_nueva'] ?? 0),
            (string) ($datos['tipo_motivo'] ?? ''),
            (string) ($datos['motivo'] ?? ''),
            $_SESSION['id_usuario'] ?? null
        );

        echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
    }
}
