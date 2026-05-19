<?php
namespace App\Controllers;

use App\Core\Controller;

class DashboardController extends Controller
{
    public function index($params = '')
    {
        if (!isset($_SESSION['usuario'])) {
            header('Location: ' . BASE_URL . '?url=Login/index');
            exit();
        }

        require_once("Models/ReservaModel.php");
        $reservaModel = new ReservaModel();
        
        $data['page_title'] = "Dashboard";
        $data['stats'] = $reservaModel->obtenerEstadisticasDashboard();
        $data['notificaciones'] = $reservaModel->obtenerNotificacionesCheckout();
        $data['page_js'] = ['Clientes.js', 'Modal-Clientes.js', 'Modal-NuevaReserva.js', 'Dashboard.js'];

        $this->views->render($this, 'index', $data);
    }
}
