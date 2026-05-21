<?php
namespace Controllers;

use Libraries\Core\Controller;

class HomeController extends Controller
{
    public function index($params = '')
    {
        if (isset($_SESSION['usuario'])) {
            header('Location: ' . BASE_URL . 'Dashboard/index');
            exit();
        } else {
            header('Location: ' . BASE_URL . 'Login/index');
            exit();
        }
    }
}
