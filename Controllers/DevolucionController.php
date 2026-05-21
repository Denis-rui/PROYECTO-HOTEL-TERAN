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
}
