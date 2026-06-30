<?php

namespace Controllers;

use Libraries\Core\Controller;
use Services\HabitacionService;

class HabitacionController extends Controller
{
    private HabitacionService $habitacionService;

    public function __construct()
    {
        parent::__construct();
        $this->habitacionService = new HabitacionService();
    }

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

        $respuestaBuscar = $this->habitacionService->buscar($numero, $tipo, $estado, $piso);

        $data['page_title'] = "Habitaciones";
        $respuestaFiltros = $this->habitacionService->obtenerFiltros();
        $data['filtros'] = $respuestaFiltros['exito'] ? $respuestaFiltros['data'] : [];
        $data['habitaciones'] = $respuestaBuscar['exito'] ? $respuestaBuscar['data'] : [];
        $data['page_js'] = ['Modal-Habitaciones.js', 'Habitaciones.js'];

        $this->views->render($this, 'index', $data);
    }

    public function buscar($params = '')
    {
        $numero = $_GET['numero_habitacion'] ?? ($_GET['numero'] ?? '');
        $tipo = $_GET['id_tipo_habitacion'] ?? ($_GET['tipo'] ?? '');
        $estado = $_GET['estado'] ?? '';
        $piso = $_GET['piso'] ?? '';

        $respuesta = $this->habitacionService->buscar($numero, $tipo, $estado, $piso);
        $habitaciones = $respuesta['exito'] ? $respuesta['data'] : [];

        if (isset($_GET['html'])) {
            $data['habitaciones'] = $habitaciones;
            $data['is_partial'] = true;
            $this->views->render($this, 'grid', $data);
        } else {
            $this->responderJson($habitaciones);
        }
    }

    public function registrar($params = '')
    {
        $this->validarCsrf();
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $datos = $this->obtenerPayloadJson() ?? $_POST;
            $this->responderJson($this->habitacionService->registrar($datos));
        }
    }

    public function editar($params = '')
    {
        $this->validarCsrf();
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $datos = $this->obtenerPayloadJson() ?? $_POST;
            $this->responderJson($this->habitacionService->editar($datos));
        }
    }

    public function eliminar($params = '')
    {
        $this->validarCsrf();
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $datos = $this->obtenerPayloadJson() ?? $_POST;
            $this->responderJson($this->habitacionService->eliminar((int) ($datos['id'] ?? 0)));
        }
    }

    public function actualizarEstado($params = '')
    {
        $this->validarCsrf();
        $datos = $this->obtenerPayloadJson() ?? [];
        $this->responderJson($this->habitacionService->actualizarEstado((int) ($datos['id'] ?? 0), $datos['estado'] ?? '', $datos['motivo'] ?? ''));
    }

    public function terminarLimpieza($params = '')
    {
        $this->validarCsrf();
        $datos = $this->obtenerPayloadJson() ?? [];
        $this->responderJson($this->habitacionService->terminarLimpieza((int) ($datos['id'] ?? 0)));
    }

    public function notificarLimpiezaVencida($params = '')
    {
        $datos = $this->obtenerPayloadJson() ?? [];
        $this->responderJson($this->habitacionService->notificarLimpiezaVencida((int) ($datos['id'] ?? 0)));
    }

    public function extenderLimpieza($params = '')
    {
        $datos = $this->obtenerPayloadJson() ?? [];
        $this->responderJson($this->habitacionService->extenderLimpieza(
            (int) ($datos['id'] ?? 0),
            (int) ($datos['minutos'] ?? 15)
        ));
    }

    public function disponiblesPorRango($params = '')
    {
        $checkIn = $_GET['check_in'] ?? '';
        $checkOut = $_GET['check_out'] ?? '';
        $tipo = $_GET['tipo'] ?? null;
        $piso = $_GET['piso'] ?? null;
        $referencia = [
            'precio' => $_GET['precio_referencia'] ?? null,
            'tipo' => $_GET['tipo_referencia'] ?? null,
            'piso' => $_GET['piso_referencia'] ?? null,
        ];
        $respuesta = $this->habitacionService->disponiblesPorRango(
            $checkIn,
            $checkOut,
            $tipo,
            $piso,
            $referencia
        );

        $this->responderJson([
            'habitaciones' => $respuesta['data'],
            'exito' => $respuesta['exito'],
            'mensaje' => $respuesta['mensaje'] ?? '',
        ]);
    }

    public function obtenerFiltros($params = '')
    {
        $respuesta = $this->habitacionService->obtenerFiltros();
        $this->responderJson($respuesta['data']);
    }
}
