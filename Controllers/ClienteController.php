<?php
namespace Controllers;

use Libraries\Core\Controller;

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
        $clientes = $this->model->obtenerClientesParaReserva($texto);
        echo json_encode(['clientes' => $clientes]);
    }

    public function registrar($params = '')
    {
        header('Content-Type: application/json');
        $datos = json_decode(file_get_contents('php://input'), true);

        // Validaciones básicas
        if (empty($datos['nombre'])) {
            echo json_encode(['exito' => false, 'mensaje' => 'El nombre es obligatorio']);
            exit;
        }

        if (empty($datos['documento'])) {
            echo json_encode(['exito' => false, 'mensaje' => 'El documento es obligatorio']);
            exit;
        }

        if (!is_numeric($datos['documento'])) {
            echo json_encode(['exito' => false, 'mensaje' => 'El documento solo puede contener números']);
            exit;
        }

        if (empty($datos['gmail'])) {
            echo json_encode(['exito' => false, 'mensaje' => 'El correo es obligatorio']);
            exit;
        }

        if (!filter_var($datos['gmail'], FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['exito' => false, 'mensaje' => 'Correo inválido']);
            exit;
        }

        if (empty($datos['telefono'])) {
            echo json_encode(['exito' => false, 'mensaje' => 'El teléfono es obligatorio']);
            exit;
        }

        if (!is_numeric($datos['telefono'])) {
            echo json_encode(['exito' => false, 'mensaje' => 'El teléfono solo puede contener números']);
            exit;
        }

        if (empty($datos['procedencia'])) {
            echo json_encode(['exito' => false, 'mensaje' => 'La procedencia es obligatoria']);
            exit;
        }

        if (empty($datos['id_tipo_documento']) || !is_numeric($datos['id_tipo_documento']) || (int) $datos['id_tipo_documento'] <= 0) {
            echo json_encode(['exito' => false, 'mensaje' => 'Seleccione un tipo de documento válido']);
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

        if (empty($datos['nombre'])) {
            echo json_encode(['exito' => false, 'mensaje' => 'El nombre es obligatorio']);
            exit;
        }

        if (empty($datos['documento'])) {
            echo json_encode(['exito' => false, 'mensaje' => 'El documento es obligatorio']);
            exit;
        }

        if (!is_numeric($datos['documento'])) {
            echo json_encode(['exito' => false, 'mensaje' => 'El documento solo puede contener números']);
            exit;
        }

        if (empty($datos['gmail'])) {
            echo json_encode(['exito' => false, 'mensaje' => 'El correo es obligatorio']);
            exit;
        }

        if (!filter_var($datos['gmail'], FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['exito' => false, 'mensaje' => 'Correo inválido']);
            exit;
        }

        if (empty($datos['telefono'])) {
            echo json_encode(['exito' => false, 'mensaje' => 'El teléfono es obligatorio']);
            exit;
        }

        if (!is_numeric($datos['telefono'])) {
            echo json_encode(['exito' => false, 'mensaje' => 'El teléfono solo puede contener números']);
            exit;
        }

        if (empty($datos['procedencia'])) {
            echo json_encode(['exito' => false, 'mensaje' => 'La procedencia es obligatoria']);
            exit;
        }

        if (empty($datos['id_tipo_documento']) || !is_numeric($datos['id_tipo_documento']) || (int) $datos['id_tipo_documento'] <= 0) {
            echo json_encode(['exito' => false, 'mensaje' => 'Seleccione un tipo de documento válido']);
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
            echo json_encode(['exito' => $ok, 'mensaje' => $ok ? 'Cliente inhabilitado correctamente' : 'No se pudo inhabilitar']);
        } catch (\Exception $e) {
            echo json_encode(['exito' => false, 'mensaje' => $e->getMessage()]);
        }
    }
}
