<?php

namespace Controllers;

use Libraries\Core\Controller;
use Models\DashboardModel;
use Models\HabitacionModel;
use Models\ClienteModel;
use Models\PagoModel;
use Models\NotificacionModel;
use Models\ReservaNuevaModel;
use Models\DocumentoElectronicoModel;
use Models\ReporteOcupacionModel;
use Models\ActualizarReservaModel;

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
        $modelo = new DocumentoElectronicoModel();
        echo json_encode($modelo->emitir($datos, $_SESSION['id_usuario'] ?? null));
    }


    public function registrar($params = '')
    {
        header('Content-Type: application/json');
        $datos     = json_decode(file_get_contents('php://input'), true);
        $modeloReserva = new ReservaNuevaModel();
        $resultado = $modeloReserva->registrarReserva($datos, $_SESSION['id_usuario'] ?? null);
        echo json_encode($resultado);
    }

    public function actualizar($params = '')
    {
        header('Content-Type: application/json');
        $datos     = json_decode(file_get_contents('php://input'), true);
        $modeloActualizarReserva = new ActualizarReservaModel();
        $resultado = $modeloActualizarReserva->actualizarReserva($datos, $_SESSION['id_usuario'] ?? null);
        echo json_encode($resultado);
    }

    public function pago($params = '')
    {
        header('Content-Type: application/json');
        $datos     = json_decode(file_get_contents('php://input'), true);
        $modeloPago = new PagoModel();
        $resultado = $modeloPago->registrarPago(
            (int) ($datos['id_reserva']    ?? 0),
            (float) ($datos['monto']       ?? 0),
            (int) ($datos['id_metodo_pago'] ?? 0),
            $datos['descripcion'] ?? '',
            $datos['fecha_pago']  ?? null,
            $_SESSION['id_usuario'] ?? null
        );
        echo json_encode($resultado);
    }

    public function checkin($params = '')
    {
        header('Content-Type: application/json');
        $datos     = json_decode(file_get_contents('php://input'), true);
        $resultado = $this->model->confirmarCheckIn(
            (int) ($datos['id_reserva'] ?? 0),
            $_SESSION['id_usuario'] ?? null
        );
        echo json_encode($resultado);
    }

    public function checkout($params = '')
    {
        header('Content-Type: application/json');
        $datos     = json_decode(file_get_contents('php://input'), true);
        $resultado = $this->model->confirmarCheckout(
            (int) ($datos['id_reserva'] ?? 0),
            $_SESSION['id_usuario'] ?? null,
            (bool) ($datos['autorizar_saldo']    ?? false),
            $datos['motivo_autorizacion'] ?? ''
        );
        echo json_encode($resultado);
    }

    public function marcarAusente($params = '')
    {
        header('Content-Type: application/json');
        $datos = json_decode(file_get_contents('php://input'), true);
        $resultado = $this->model->marcarAusente(
            (int) ($datos['id_reserva'] ?? 0),
            $_SESSION['id_usuario'] ?? null
        );
        echo json_encode($resultado);
    }

    public function marcarRegreso($params = '')
    {
        header('Content-Type: application/json');
        $datos = json_decode(file_get_contents('php://input'), true);
        $resultado = $this->model->marcarRegreso(
            (int) ($datos['id_reserva'] ?? 0),
            $_SESSION['id_usuario'] ?? null
        );
        echo json_encode($resultado);
    }

    public function actualizarEstado($params = '')
    {
        header('Content-Type: application/json');
        $datos     = json_decode(file_get_contents('php://input'), true);
        $resultado = $this->model->actualizarEstadoReserva(
            (int) ($datos['id_reserva'] ?? 0),
            $datos['nuevo_estado'] ?? ''
        );
        echo json_encode($resultado);
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

    public function extender($params = '')
    {
        header('Content-Type: application/json');
        $datos     = json_decode(file_get_contents('php://input'), true);
        $modeloActualizarReserva = new ActualizarReservaModel();
        $resultado = $modeloActualizarReserva->extenderEstadia(
            (int) ($datos['id_reserva']    ?? 0),
            $datos['nuevo_check_out'] ?? '',
            $_SESSION['id_usuario'] ?? null
        );
        echo json_encode($resultado);
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
        header('Content-Type: application/json');
        $datos = json_decode(file_get_contents('php://input'), true);
        $idReserva = (int) ($datos['id_reserva'] ?? 0);
        $motivo = $datos['motivo'] ?? '';
        $idUsuario = $_SESSION['id_usuario'] ?? null;
        $resultado = $this->model->cancelarReserva($idReserva, $motivo, $idUsuario);
        echo json_encode($resultado);
    }

    public function calcularCancelacion($params = '')
    {
        header('Content-Type: application/json');
        $datos = json_decode(file_get_contents('php://input'), true) ?: [];
        $modelo = new \Models\CalculoDevolucionModel();
        echo json_encode($modelo->calcular((int) ($datos['id_reserva'] ?? 0)));
    }

    public function cambiarHabitacion($params = '')
    {
        header('Content-Type: application/json');
        $datos     = json_decode(file_get_contents('php://input'), true);
        $modeloActualizarReserva = new ActualizarReservaModel();
        $resultado = $modeloActualizarReserva->cambiarHabitacion(
            (int) ($datos['id_reserva']        ?? 0),
            (int) ($datos['id_habitacion_actual'] ?? 0),
            (int) ($datos['id_habitacion_nueva'] ?? 0),
            $datos['tipo_motivo'] ?? '',
            $datos['motivo'] ?? '',
            $_SESSION['id_usuario'] ?? null
        );
        echo json_encode($resultado);
    }
}
