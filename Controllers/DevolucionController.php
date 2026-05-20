<?php
namespace Controllers;

use Libraries\Core\Controller;
use Models\DevolucionModel;

class DevolucionController extends Controller
{
    public function index($params = '')
    {
        if (!isset($_SESSION['usuario'])) {
            header('Location: ' . BASE_URL . '?url=Login/index');
            exit();
        }

        $busqueda = $_GET['busqueda'] ?? '';
        $data['page_title'] = "Devoluciones";
        $data['devoluciones'] = $this->model->listar($busqueda);
        $data['page_js'] = ['Devoluciones.js'];
        $this->views->render($this, 'index', $data);
    }

    public function listar($params = '')
    {
        header('Content-Type: application/json');
        echo json_encode($this->model->listar());
    }

    public function registrar($params = '')
    {
        header('Content-Type: application/json');
        $datos = json_decode(file_get_contents('php://input'), true);

        if (empty($datos['id_reserva']) || empty($datos['motivo'])) {
            echo json_encode(['exito' => false, 'mensaje' => 'Datos incompletos']);
            exit;
        }

        try {
            $ok = $this->model->crear($datos);
            echo json_encode(['exito' => $ok, 'mensaje' => $ok ? 'Devolución registrada correctamente' : 'No se pudo registrar la devolución']);
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
            $ok = $this->model->actualizar($datos);
            echo json_encode(['exito' => $ok, 'mensaje' => $ok ? 'Devolución actualizada' : 'No se pudo actualizar']);
        } catch (\Exception $e) {
            echo json_encode(['exito' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    public function eliminar($params = '')
    {
        header('Content-Type: application/json');
        $datos = json_decode(file_get_contents('php://input'), true);
        try {
            $ok = $this->model->eliminar((int) ($datos['id'] ?? 0));
            echo json_encode(['exito' => $ok, 'mensaje' => $ok ? 'Devolución eliminada' : 'No se pudo eliminar']);
        } catch (\Exception $e) {
            echo json_encode(['exito' => false, 'mensaje' => $e->getMessage()]);
        }
    }
}
