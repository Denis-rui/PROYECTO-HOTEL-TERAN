<?php

namespace Controllers;

use Libraries\Core\ApiController;
use Helpers\CodigoHTTP;
use Services\ConfiguracionService;

class ConfiguracionController extends ApiController
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
        $data['page_js'] = ['Configuraciones.js'];
        $this->views->render($this, 'index', $data);
    }

    public function actualizar($params = '')
    {
        $this->validarCsrf();
        if (!isset($_SESSION['usuario'])) {
            $this->responderJson([
                'exito' => false,
                'codigo' => 'NO_AUTENTICADO',
                'mensaje' => 'No autenticado',
                'data' => null,
                'errores' => [],
            ], 401);
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->responderJson([
                'exito' => false,
                'codigo' => 'METODO_NO_PERMITIDO',
                'mensaje' => 'Método no permitido',
                'data' => null,
                'errores' => [],
            ], 405);
        }

        $datos = $this->obtenerPayloadJson() ?? [];

        try {
            $service = new ConfiguracionService();
            [$payload, $codigoHttp] = CodigoHTTP::prepararRespuesta($service->actualizarHotel($datos));
            $this->responderJson($payload, $codigoHttp);
        } catch (\Exception $e) {
            error_log('ConfiguracionController::actualizar -> ' . $e->getMessage());
            $this->responderJson([
                'exito' => false,
                'codigo' => 'ERROR_INTERNO',
                'mensaje' => 'No se pudo actualizar la configuración.',
                'data' => null,
                'errores' => [],
            ], 500);
        }
    }

    public function obtener($params = '')
    {
        $service = new ConfiguracionService();
        [$payload, $codigoHttp] = CodigoHTTP::prepararRespuesta($service->obtenerHotel());
        $this->responderJson($payload, $codigoHttp);
    }

    public function guardarTipo($params = '')
    {
        $this->validarCsrf();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->responderJson([
                'exito' => false,
                'codigo' => 'METODO_NO_PERMITIDO',
                'mensaje' => 'Método no permitido',
                'data' => null,
                'errores' => [],
            ], 405);
        }

        try {
            $datos = $this->obtenerPayloadJson() ?? [];

            $service = new ConfiguracionService();
            [$payload, $codigoHttp] = CodigoHTTP::prepararRespuesta($service->guardarTipoHabitacion($datos));
            $this->responderJson($payload, $codigoHttp);
        } catch (\Throwable $e) {
            error_log('ConfiguracionController::guardarTipo -> ' . $e->getMessage());
            $this->responderJson([
                'exito' => false,
                'codigo' => 'ERROR_INTERNO',
                'mensaje' => 'No se pudo guardar el tipo de habitación.',
                'data' => null,
                'errores' => [],
            ], 500);
        }
    }
}
