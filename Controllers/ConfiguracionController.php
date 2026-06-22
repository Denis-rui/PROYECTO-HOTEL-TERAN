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
            $this->responderJson(['exito' => false, 'mensaje' => 'No autenticado'], 401);
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

        $datos = $this->obtenerPayloadJson() ?? [];

        try {
            $service = new ConfiguracionService();
            $ok = $service->actualizarHotel($datos);
            $this->responderJson($ok);
        } catch (\Exception $e) {
            error_log('ConfiguracionController::actualizar -> ' . $e->getMessage());
            $this->responderJson(['exito' => false, 'mensaje' => 'No se pudo actualizar la configuración.'], 500);
        }
    }

    public function obtener($params = '')
    {
        $service = new ConfiguracionService();
        $this->responderJson($service->obtenerHotel());
    }

    public function guardarTipo($params = '')
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->responderJson([
                'exito' => false,
                'mensaje' => 'Método no permitido'
            ], 405);
        }

        try {
            $datos = $this->obtenerPayloadJson() ?? [];

            $service = new ConfiguracionService();
            $this->responderJson($service->guardarTipoHabitacion($datos));
        } catch (\Throwable $e) {
            error_log('ConfiguracionController::guardarTipo -> ' . $e->getMessage());
            $this->responderJson([
                'exito' => false,
                'mensaje' => 'No se pudo guardar el tipo de habitación.'
            ], 500);
        }
    }
}
