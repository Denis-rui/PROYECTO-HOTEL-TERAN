<?php
namespace Controllers;

use Libraries\Core\Controller;
use Models\Entities\TipoHabitacion;


class ConfiguracionController extends Controller
{
    public function index($params = '')
    {
        if (!isset($_SESSION['usuario'])) {
            header('Location: ' . BASE_URL . '?url=Login/index');
            exit();
        }
        $data['page_title'] = "Configuración del Hotel";
        $data['hotel'] = $this->model->find(1);
        
        // Cargar tipos de habitación
        $data['tipos_habitacion'] = TipoHabitacion::orderBy('id')->get()->toArray();
        $data['page_js'] = ['Configuraciones.js'];
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
            $ok = $this->model->actualizarHotel($datos);
            echo json_encode(['exito' => (bool) $ok]);
        } catch (\Exception $e) {
            echo json_encode(['exito' => false, 'mensaje' => $e->getMessage()]);
        }
        exit();
   }

    public function guardarTipo($params = '')
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

        $id     = $_POST['id']         ?? null;
        $tipo   = $_POST['tipo']        ?? '';
        $precio = $_POST['precio_base'] ?? 0;

        if ($id) {
            TipoHabitacion::where('id', $id)->update(['tipo' => $tipo, 'precio_base' => $precio]);
        } else {
            TipoHabitacion::create(['tipo' => $tipo, 'precio_base' => $precio]);
        }

        header('Location: ' . BASE_URL . '?url=Configuracion/index&exito=1');
    }

    public function obtener($params = '')
    {
        header('Content-Type: application/json');
        echo json_encode($this->model->find());
        exit();
    }
}
