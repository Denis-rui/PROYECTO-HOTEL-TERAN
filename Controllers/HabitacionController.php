<?php
namespace Controllers;

use Libraries\Core\Controller;

class HabitacionController extends Controller
{
    public function index($params = '')
    {
        if (!isset($_SESSION['usuario'])) {
            header('Location: ' . BASE_URL . 'Login/index');
            exit();
        }

        $numero = $_GET['numero_habitacion'] ?? '';
        $tipo = $_GET['id_tipo_habitacion'] ?? '';
        $estado = $_GET['estado'] ?? 'Disponible';
        $piso = $_GET['piso'] ?? '';


        $data['page_title'] = "Habitaciones";
        $data['filtros'] = $this->model->obtenerFiltros();
        $data['habitaciones'] = $this->model->buscar($numero, $tipo, $estado, $piso);
        $data['page_js'] = ['Modal-Habitaciones.js', 'Habitaciones.js'];

        $this->views->render($this, 'index', $data);
    }

    public function buscar($params = '')
    {
        $numero = $_GET['numero_habitacion'] ?? ($_GET['numero'] ?? '');
        $tipo = $_GET['id_tipo_habitacion'] ?? ($_GET['tipo'] ?? '');
        $estado = $_GET['estado'] ?? '';
        $piso = $_GET['piso'] ?? '';

        $habitaciones = $this->model->buscar($numero, $tipo, $estado, $piso);

        if (isset($_GET['html'])) {
            $data['habitaciones'] = $habitaciones;
            $data['is_partial'] = true;
            $this->views->render($this, 'grid', $data);
        } else {
            header('Content-Type: application/json');
            echo json_encode($habitaciones);
        }
    }

    public function registrar($params = '')
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            header('Content-Type: application/json');
            $datos = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            try {
                $ok = $this->model->registrar($datos);
                echo json_encode([
                    'exito' => (bool) $ok,
                    'mensaje' => $ok ? 'Habitación registrada correctamente.' : 'No se pudo registrar la habitación.',
                ]);
            } catch (\Throwable $e) {
                echo json_encode(['exito' => false, 'mensaje' => $e->getMessage()]);
            }
        }
    }

    public function editar($params = '')
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            header('Content-Type: application/json');
            $datos = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            try {
                $resultado = $this->model->editarHabitacion($datos);
                echo json_encode($resultado);
            } catch (\Throwable $e) {
                echo json_encode(['exito' => false, 'mensaje' => $e->getMessage()]);
            }
        }
    }

    public function eliminar($params = '')
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            header('Content-Type: application/json');
            $datos = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            $resultado = $this->model->eliminarHabitacion((int) ($datos['id'] ?? 0));
            echo json_encode($resultado);
        }
    }

    public function actualizarEstado($params = '')
    {
        header('Content-Type: application/json');
        $datos = json_decode(file_get_contents('php://input'), true);
        $resultado = $this->model->actualizarEstado((int) ($datos['id'] ?? 0), $datos['estado'] ?? '', $datos['motivo'] ?? '');
        echo json_encode($resultado);
    }

    public function disponiblesPorRango($params = '')
    {
        header('Content-Type: application/json');
        $checkIn = $_GET['check_in'] ?? '';
        $checkOut = $_GET['check_out'] ?? '';
        $tipo = $_GET['tipo'] ?? null;
        $piso = $_GET['piso'] ?? null;
        $referencia = [
            'precio' => $_GET['precio_referencia'] ?? null,
            'tipo' => $_GET['tipo_referencia'] ?? null,
            'piso' => $_GET['piso_referencia'] ?? null,
        ];
        $habitaciones = $this->model->disponiblesPorRango($checkIn, $checkOut, $tipo, $piso, $referencia);
        echo json_encode(['habitaciones' => $habitaciones]);
    }

    public function terminarLimpieza($params = '')
    {
        header('Content-Type: application/json');
        $datos = json_decode(file_get_contents('php://input'), true);
        $id = (int) ($datos['id'] ?? 0);
        if (!$id) {
            echo json_encode(['exito' => false, 'mensaje' => 'ID inválido.']);
            return;
        }
        $resultado = $this->model->terminarLimpieza($id);
        echo json_encode($resultado);
    }

    public function obtenerFiltros()
    {
        header('Content-Type: application/json');
        echo json_encode($this->model->obtenerFiltros());
    }
}
