<?php
namespace Controllers;

use Libraries\Core\Controller;

class ClienteController extends Controller
{
    private function responderJson(array $payload, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($payload);
    }

    private function obtenerPayloadJson(): ?array
    {
        $datos = json_decode(file_get_contents('php://input'), true);
        return is_array($datos) ? $datos : null;
    }

    public function index($params = '')
    {
        if (!isset($_SESSION['usuario'])) {
            header('Location: ' . BASE_URL . 'Login/index');
            exit();
        }

        $nombre = $_GET['nombre'] ?? '';
        $data['page_title'] = 'Gestion de Clientes';
        $data['clientes'] = $this->model->listar($nombre);
        $data['page_js'] = ['Modal-Clientes.js', 'Clientes.js'];
        $this->views->render($this, 'index', $data);
    }

    public function listar($params = '')
    {
        $this->responderJson($this->model->obtenerClientes());
    }

    public function buscar($params = '')
    {
        $texto = $_GET['q'] ?? '';
        $clientes = $this->model->obtenerClientesParaReserva($texto);
        $this->responderJson(['clientes' => $clientes]);
    }

    public function registrar($params = '')
    {
        $datos = $this->obtenerPayloadJson();
        if ($datos === null) {
            $this->responderJson(['exito' => false, 'mensaje' => 'JSON invalido'], 400);
            return;
        }

        if (empty($datos['nombre'])) {
            $this->responderJson(['exito' => false, 'mensaje' => 'El nombre es obligatorio'], 422);
            return;
        }

        if (empty($datos['documento'])) {
            $this->responderJson(['exito' => false, 'mensaje' => 'El documento es obligatorio'], 422);
            return;
        }

        if (!is_numeric($datos['documento'])) {
            $this->responderJson(['exito' => false, 'mensaje' => 'El documento solo puede contener numeros'], 422);
            return;
        }

        if (empty($datos['gmail'])) {
            $this->responderJson(['exito' => false, 'mensaje' => 'El correo es obligatorio'], 422);
            return;
        }

        if (!filter_var($datos['gmail'], FILTER_VALIDATE_EMAIL)) {
            $this->responderJson(['exito' => false, 'mensaje' => 'Correo invalido'], 422);
            return;
        }

        if (empty($datos['telefono'])) {
            $this->responderJson(['exito' => false, 'mensaje' => 'El telefono es obligatorio'], 422);
            return;
        }

        if (!is_numeric($datos['telefono'])) {
            $this->responderJson(['exito' => false, 'mensaje' => 'El telefono solo puede contener numeros'], 422);
            return;
        }

        if (empty($datos['procedencia'])) {
            $this->responderJson(['exito' => false, 'mensaje' => 'La procedencia es obligatoria'], 422);
            return;
        }

        if (empty($datos['id_tipo_documento']) || !is_numeric($datos['id_tipo_documento']) || (int) $datos['id_tipo_documento'] <= 0) {
            $this->responderJson(['exito' => false, 'mensaje' => 'Seleccione un tipo de documento valido'], 422);
            return;
        }

        try {
            $ok = $this->model->crearCliente($datos);
            $this->responderJson([
                'exito' => (bool) $ok,
                'mensaje' => $ok ? 'Cliente creado correctamente' : 'No se pudo crear el cliente'
            ]);
        } catch (\Exception $e) {
            $this->responderJson(['exito' => false, 'mensaje' => $e->getMessage()], 500);
        }
    }

    public function actualizar($params = '')
    {
        $datos = $this->obtenerPayloadJson();
        if ($datos === null) {
            $this->responderJson(['exito' => false, 'mensaje' => 'JSON invalido'], 400);
            return;
        }

        if (empty($datos['id'])) {
            $this->responderJson(['exito' => false, 'mensaje' => 'ID requerido'], 422);
            return;
        }

        if (empty($datos['nombre'])) {
            $this->responderJson(['exito' => false, 'mensaje' => 'El nombre es obligatorio'], 422);
            return;
        }

        if (empty($datos['documento'])) {
            $this->responderJson(['exito' => false, 'mensaje' => 'El documento es obligatorio'], 422);
            return;
        }

        if (!is_numeric($datos['documento'])) {
            $this->responderJson(['exito' => false, 'mensaje' => 'El documento solo puede contener numeros'], 422);
            return;
        }

        if (empty($datos['gmail'])) {
            $this->responderJson(['exito' => false, 'mensaje' => 'El correo es obligatorio'], 422);
            return;
        }

        if (!filter_var($datos['gmail'], FILTER_VALIDATE_EMAIL)) {
            $this->responderJson(['exito' => false, 'mensaje' => 'Correo invalido'], 422);
            return;
        }

        if (empty($datos['telefono'])) {
            $this->responderJson(['exito' => false, 'mensaje' => 'El telefono es obligatorio'], 422);
            return;
        }

        if (!is_numeric($datos['telefono'])) {
            $this->responderJson(['exito' => false, 'mensaje' => 'El telefono solo puede contener numeros'], 422);
            return;
        }

        if (empty($datos['procedencia'])) {
            $this->responderJson(['exito' => false, 'mensaje' => 'La procedencia es obligatoria'], 422);
            return;
        }

        if (empty($datos['id_tipo_documento']) || !is_numeric($datos['id_tipo_documento']) || (int) $datos['id_tipo_documento'] <= 0) {
            $this->responderJson(['exito' => false, 'mensaje' => 'Seleccione un tipo de documento valido'], 422);
            return;
        }

        try {
            $ok = $this->model->actualizarCliente($datos);
            $this->responderJson([
                'exito' => (bool) $ok,
                'mensaje' => $ok ? 'Cliente actualizado' : 'No se pudo actualizar'
            ]);
        } catch (\Exception $e) {
            $this->responderJson(['exito' => false, 'mensaje' => $e->getMessage()], 500);
        }
    }

    public function eliminar($params = '')
    {
        $datos = $this->obtenerPayloadJson();
        if ($datos === null) {
            $this->responderJson(['exito' => false, 'mensaje' => 'JSON invalido'], 400);
            return;
        }

        $idCliente = (int) ($datos['id'] ?? 0);
        if ($idCliente <= 0) {
            $this->responderJson(['exito' => false, 'mensaje' => 'ID de cliente invalido'], 422);
            return;
        }

        try {
            $ok = $this->model->eliminarCliente($idCliente);
            $this->responderJson([
                'exito' => (bool) $ok,
                'mensaje' => $ok ? 'Cliente inhabilitado correctamente' : 'No se pudo inhabilitar'
            ]);
        } catch (\Exception $e) {
            $this->responderJson(['exito' => false, 'mensaje' => $e->getMessage()], 500);
        }
    }
}
