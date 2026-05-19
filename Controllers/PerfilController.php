<?php
namespace App\Controllers;

use App\Core\Controller;

class PerfilController extends Controller
{
    public function index($params = '')
    {
        if (!isset($_SESSION['usuario'])) {
            header('Location: ' . BASE_URL . '?url=Login/index');
            exit();
        }
        $data['page_title'] = "Mi Perfil";
        $data['page_js'] = [];
        $this->views->render($this, 'index', $data);
    }
}
