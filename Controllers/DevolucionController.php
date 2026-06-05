<?php
namespace Controllers;

use Libraries\Core\Controller;

class DevolucionController extends Controller
{
    public function index($params = '')
    {
        if (!isset($_SESSION['usuario'])) {
            header('Location: ' . BASE_URL . 'Login/index');
            exit();
        }

        $busqueda = $_GET['busqueda'] ?? '';
        $data['page_title'] = "Devoluciones";
        $data['devoluciones'] = $this->model->listar($busqueda);
        $data['page_js'] = [];
        $this->views->render($this, 'index', $data);
    }

    public function registrar($params = '')
    {
        header('Content-Type: application/json');
        $datos = json_decode(file_get_contents('php://input'), true) ?: [];
        $exito = $this->model->crear($datos);
        echo json_encode([
            'exito' => $exito,
            'mensaje' => $exito
                ? 'Devolución registrada con el cálculo vigente.'
                : 'No se pudo registrar la devolución. Solo corresponde a reservas canceladas sin checkout.',
        ]);
    }

    public function actualizar($params = '')
    {
        header('Content-Type: application/json');
        $datos = json_decode(file_get_contents('php://input'), true) ?: [];
        $exito = $this->model->actualizar($datos);
        echo json_encode([
            'exito' => $exito,
            'mensaje' => $exito
                ? 'Devolución recalculada correctamente.'
                : 'No se pudo actualizar la devolución.',
        ]);
    }

    public function eliminar($params = '')
    {
        header('Content-Type: application/json');
        $datos = json_decode(file_get_contents('php://input'), true) ?: [];
        $exito = $this->model->eliminar((int) ($datos['id'] ?? 0));
        echo json_encode([
            'exito' => $exito,
            'mensaje' => $exito ? 'Devolución eliminada.' : 'No se pudo eliminar la devolución.',
        ]);
    }
}
