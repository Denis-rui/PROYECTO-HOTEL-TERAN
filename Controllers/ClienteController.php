<?php

class ClienteController extends Controller
{
    public function index($params = '')
    {
        if (!isset($_SESSION['usuario'])) {
            header('Location: ' . BASE_URL . '?url=Login/index');
            exit();
        }
        $nombre = $_GET['nombre'] ?? '';
        $data['page_title'] = "Gestión de Clientes";
        $data['clientes'] = $this->model->listar($nombre);
        $data['page_js'] = ['Modal-Clientes.js', 'Clientes.js'];
        $this->views->render($this, 'index', $data);
    }

    public function listar($params = '')
    {
        header('Content-Type: application/json');
        echo json_encode($this->model->obtenerClientes());
    }

    public function buscar($params = '')
    {
        header('Content-Type: application/json');
        $texto = $_GET['q'] ?? '';
        echo json_encode($this->model->obtenerClientesParaReserva($texto));
    }

    public function registrar($params = '')
    {
        header('Content-Type: application/json');
        $datos = json_decode(file_get_contents('php://input'), true);

        if (empty($datos['nombre']) || empty($datos['documento'])) {
            echo json_encode(['exito' => false, 'mensaje' => 'Datos incompletos']);
            exit;
        }

        try {
            $ok = $this->model->crearCliente($datos);
            echo json_encode(['exito' => $ok, 'mensaje' => $ok ? 'Cliente creado correctamente' : 'No se pudo crear el cliente']);
        } catch (\Exception $e) {
            echo json_encode(['exito' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    public function actualizar($params = '')
    {
        header('Content-Type: application/json');
        $datos = json_decode(file_get_contents('php://input'), true);

        if (empty($datos['id'])) {
            echo json_encode(['exito' => false, 'mensaje' => 'ID requerido']);
            exit;
        }

        try {
            $ok = $this->model->actualizarCliente($datos);
            echo json_encode(['exito' => $ok, 'mensaje' => $ok ? 'Cliente actualizado' : 'No se pudo actualizar']);
        } catch (\Exception $e) {
            echo json_encode(['exito' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    public function eliminar($params = '')
    {
        header('Content-Type: application/json');
        $datos = json_decode(file_get_contents('php://input'), true);
        try {
            $ok = $this->model->eliminarCliente((int) ($datos['id'] ?? 0));
            echo json_encode(['exito' => $ok, 'mensaje' => $ok ? 'Cliente eliminado' : 'No se pudo eliminar']);
        } catch (\Exception $e) {
            echo json_encode(['exito' => false, 'mensaje' => $e->getMessage()]);
        }
    }
}
