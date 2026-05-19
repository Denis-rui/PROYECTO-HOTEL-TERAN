<?php
namespace App\Controllers;

use App\Core\Controller;

class ConfiguracionController extends Controller
{
    public function index($params = '')
    {
        if (!isset($_SESSION['usuario'])) {
            header('Location: ' . BASE_URL . '?url=Login/index');
            exit();
        }
        $data['page_title'] = "Configuración del Hotel";
        $data['hotel'] = $this->model->read();
        
        // Cargar tipos de habitación
        $con = $this->model->conectar();
        $data['tipos_habitacion'] = $con->query("SELECT * FROM tipo_habitacion")->fetchAll(PDO::FETCH_ASSOC);

        $data['page_js'] = ['Configuraciones.js'];
        $this->views->render($this, 'index', $data);
    }

    public function actualizar($params = '')
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $ok = $this->model->actualizarHotel($_POST);
            if ($ok) {
                header('Location: ' . BASE_URL . '?url=Configuracion/index&exito=1');
            } else {
                header('Location: ' . BASE_URL . '?url=Configuracion/index&error=1');
            }
        }
    }

    public function guardarTipo($params = '')
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $con = $this->model->conectar();
            $id = $_POST['id'] ?? null;
            $tipo = $_POST['tipo'] ?? '';
            $precio = $_POST['precio_base'] ?? 0;

            if ($id) {
                $stmt = $con->prepare("UPDATE tipo_habitacion SET tipo = ?, precio_base = ? WHERE id = ?");
                $stmt->execute([$tipo, $precio, $id]);
            } else {
                $stmt = $con->prepare("INSERT INTO tipo_habitacion (tipo, precio_base) VALUES (?, ?)");
                $stmt->execute([$tipo, $precio]);
            }
            header('Location: ' . BASE_URL . '?url=Configuracion/index&exito=1');
        }
    }

    public function obtener($params = '')
    {
        header('Content-Type: application/json');
        echo json_encode($this->model->read());
    }
}
