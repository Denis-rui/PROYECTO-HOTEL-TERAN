<?php

class ReservaController extends Controller
{
    public function index($params = '')
    {
        if (!isset($_SESSION['usuario'])) {
            header('Location: ' . BASE_URL . '?url=Login/index');
            exit();
        }
        $data['page_title'] = "Gestión de Reservas";
        $data['reservas'] = $this->model->obtenerReservas();
        $data['page_js'] = ['Clientes.js', 'Modal-Clientes.js', 'Modal-Dashboard.js', 'Pago.js', 'Reservas.js'];
        $this->views->render($this, 'index', $data);
    }

    public function listar($params = '')
    {
        header('Content-Type: application/json');
        echo json_encode($this->model->listarReservas());
    }

    public function registrar($params = '')
    {
        header('Content-Type: application/json');
        $datos     = json_decode(file_get_contents('php://input'), true);
        $resultado = $this->model->registrarReserva($datos);
        echo json_encode($resultado);
    }

    public function pago($params = '')
    {
        header('Content-Type: application/json');
        $datos     = json_decode(file_get_contents('php://input'), true);
        $resultado = $this->model->registrarPago(
            (int) ($datos['id_reserva']    ?? 0),
            (float) ($datos['monto']       ?? 0),
            (int) ($datos['id_metodo_pago'] ?? 0),
            $datos['descripcion'] ?? '',
            $datos['fecha_pago']  ?? null
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

    public function actualizarEstado($params = '')
    {
        header('Content-Type: application/json');
        $datos     = json_decode(file_get_contents('php://input'), true);
        $resultado = $this->model->actualizarEstado(
            (int) ($datos['id_reserva'] ?? 0),
            $datos['nuevo_estado'] ?? ''
        );
        echo json_encode($resultado);
    }

    public function obtener($params = '')
    {
        header('Content-Type: application/json');
        $id = (int) ($params ?? 0);
        echo json_encode($this->model->obtenerReserva($id));
    }

    public function dashboard($params = '')
    {
        header('Content-Type: application/json');
        echo json_encode($this->model->obtenerEstadisticasDashboard());
    }

    public function notificaciones($params = '')
    {
        header('Content-Type: application/json');
        echo json_encode($this->model->obtenerNotificacionesCheckout());
    }

    public function calcularTotal($params = '')
    {
        header('Content-Type: application/json');
        $datos     = json_decode(file_get_contents('php://input'), true);
        $resultado = $this->model->calcularTotalReserva(
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
        $resultado = $this->model->extenderEstadia(
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

    public function cambiarHabitacion($params = '')
    {
        header('Content-Type: application/json');
        $datos     = json_decode(file_get_contents('php://input'), true);
        $resultado = $this->model->cambiarHabitacion(
            (int) ($datos['id_reserva']        ?? 0),
            (int) ($datos['id_habitacion_nueva'] ?? 0),
            $datos['motivo'] ?? '',
            $_SESSION['id_usuario'] ?? null
        );
        echo json_encode($resultado);
    }
}
