<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\ReservaModel;

class DashboardController extends Controller
{
    public function index($params = '')
    {
        if (!isset($_SESSION['usuario'])) {
            header('Location: ' . BASE_URL . '?url=Login/index');
            exit();
        }

        $reservaModel = new ReservaModel();
        
        $data['page_title'] = "Dashboard";
        $data['stats'] = $reservaModel->obtenerEstadisticasDashboard();
        $data['notificaciones'] = $reservaModel->obtenerNotificacionesCheckout();
        $data['page_js'] = ['Clientes.js', 'Modal-Clientes.js', 'Modal-NuevaReserva.js', 'Dashboard.js'];

        $this->views->render($this, 'index', $data);
    }
}
