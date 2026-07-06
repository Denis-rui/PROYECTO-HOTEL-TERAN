<?php

namespace Controllers;

use Libraries\Core\Controller;
use Services\Devoluciones\DevolucionService; // Asegúrate de que la ruta coincida con tu namespace

class DevolucionController extends Controller
{
    private DevolucionService $devolucionService;

    public function __construct()
    {
        parent::__construct();
        // Instanciamos el servicio para usarlo en todos los métodos
        $this->devolucionService = new DevolucionService();
    }

    public function index($params = '')
    {
        if (!isset($_SESSION['usuario'])) {
            header('Location: ' . BASE_URL . 'Login/index');
            exit();
        }

        $data['page_title'] = "Devoluciones";
        $data['page_js'] = ['Devoluciones.js'];

        $this->views->render($this, 'index', $data);
    }

    public function datatable($params = '')
    {
        if (!isset($_SESSION['usuario'])) {
            $this->responderJson([
                'draw' => (int) ($_POST['draw'] ?? 0),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => 'Sesión no válida.',
            ], 401);
        }

        try {
            $this->responderJson($this->devolucionService->listarParaDataTable($_POST));
        } catch (\Throwable $e) {
            error_log('DevolucionController::datatable -> ' . $e->getMessage());
            $this->responderJson([
                'draw' => (int) ($_POST['draw'] ?? 0),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => 'No se pudieron cargar las devoluciones.',
            ], 500);
        }
    }

    public function registrar($params = '')
    {
        $this->validarCsrf();
        $datos = $this->obtenerPayloadJson() ?? [];
        $idUsuario = $_SESSION['id_usuario'] ?? null;

        // El servicio ya devuelve el arreglo ['exito' => ..., 'mensaje' => ...]
        // Así que podemos imprimirlo directamente en el json_encode
        $respuesta = $this->devolucionService->registrarDevolucion($datos, $idUsuario);
        $this->responderJson($respuesta);
    }

    public function actualizar($params = '')
    {
        $this->validarCsrf();
        $datos = $this->obtenerPayloadJson() ?? [];
        $idUsuario = $_SESSION['id_usuario'] ?? null;

        $respuesta = $this->devolucionService->actualizarDevolucion($datos, $idUsuario);
        $this->responderJson($respuesta);
    }

    public function eliminar($params = '')
    {
        $this->validarCsrf();
        $datos = $this->obtenerPayloadJson() ?? [];

        $respuesta = $this->devolucionService->eliminarDevolucion((int) ($datos['id'] ?? 0));
        $this->responderJson($respuesta);
    }
}
