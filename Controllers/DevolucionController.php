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

        $busqueda = $_GET['busqueda'] ?? '';

        // Llamamos al servicio en lugar del modelo
        $respuesta = $this->devolucionService->listarDevoluciones($busqueda);

        $data['page_title'] = "Devoluciones";
        // Si el servicio tuvo éxito, enviamos la data; si no, un arreglo vacío
        $data['devoluciones'] = $respuesta['exito'] ? $respuesta['data'] : [];
        $data['page_js'] = [];

        $this->views->render($this, 'index', $data);
    }

    public function registrar($params = '')
    {
        header('Content-Type: application/json');
        $datos = json_decode(file_get_contents('php://input'), true) ?: [];
        $idUsuario = $_SESSION['id_usuario'] ?? null;

        // El servicio ya devuelve el arreglo ['exito' => ..., 'mensaje' => ...]
        // Así que podemos imprimirlo directamente en el json_encode
        $respuesta = $this->devolucionService->registrarDevolucion($datos, $idUsuario);
        echo json_encode($respuesta);
    }

    public function actualizar($params = '')
    {
        header('Content-Type: application/json');
        $datos = json_decode(file_get_contents('php://input'), true) ?: [];
        $idUsuario = $_SESSION['id_usuario'] ?? null;

        $respuesta = $this->devolucionService->actualizarDevolucion($datos, $idUsuario);
        echo json_encode($respuesta);
    }

    public function eliminar($params = '')
    {
        header('Content-Type: application/json');
        $datos = json_decode(file_get_contents('php://input'), true) ?: [];

        $respuesta = $this->devolucionService->eliminarDevolucion((int) ($datos['id'] ?? 0));
        echo json_encode($respuesta);
    }
}
