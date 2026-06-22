<?php

namespace Controllers;

use Libraries\Core\Controller;
use Services\ConfiguracionService;

class ConfiguracionController extends Controller
{
    public function index($params = '')
    {
        if (!isset($_SESSION['usuario'])) {
            header('Location: ' . BASE_URL . 'Login/index');
            exit();
        }
        $data['page_title'] = "Configuración del Hotel";
        $service = new ConfiguracionService();
        $data['hotel'] = $service->obtenerHotel();
        $data['tipos_habitacion'] = $service->obtenerTiposHabitacion();
        $data['page_js'] = ['Configuraciones.js', 'Modal-TipoHabitacion.js'];
        $this->views->render($this, 'index', $data);
    }

    public function actualizar($params = '')
    {
        if (!isset($_SESSION['usuario'])) {
            header('Content-Type: application/json');
            echo json_encode(['exito' => false, 'mensaje' => 'No autenticado']);
            exit();
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

        $body  = file_get_contents('php://input');
        $datos = json_decode($body, true) ?? [];

        header('Content-Type: application/json');

        try {
            $service = new ConfiguracionService();
            $ok = $service->actualizarHotel($datos);
            echo json_encode($ok);
        } catch (\Exception $e) {
            echo json_encode(['exito' => false, 'mensaje' => $e->getMessage()]);
        }
        exit();
    }

    public function obtener($params = '')
    {
        $service = new ConfiguracionService();
        $this->responderJson($service->obtenerHotel());
    }

    public function guardarTipo($params = '')
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode([
                'exito' => false,
                'mensaje' => 'Método no permitido'
            ]);
            return;
        }

        header('Content-Type: application/json');

        try {


            $datos = json_decode(file_get_contents('php://input'), true);

            $service = new ConfiguracionService();
            echo json_encode($service->guardarTipoHabitacion(is_array($datos) ? $datos : []));
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'exito' => false,
                'mensaje' => $e->getMessage()
            ]);
        }
    }
}
