<?php
namespace Controllers;

use Libraries\Core\Controller;
use Models\DashboardModel;
use Models\ReservaModel;

class DashboardController extends Controller
{
    public function index($params = '')
    {
        if (!isset($_SESSION['usuario'])) {
            header('Location: ' . BASE_URL . '?url=Login/index');
            exit();
        }

        $dashboardModel = new DashboardModel();
        $reservaModel = new ReservaModel();
        
        $data['page_title'] = "Dashboard";
        $data['stats'] = $dashboardModel->obtenerEstadisticasDashboard();
        $data['notificaciones'] = $reservaModel->obtenerNotificacionesCheckout();
        $data['page_js'] = ['Clientes.js', 'Modal-Clientes.js', 'Modal-NuevaReserva.js', 'Pago.js', 'Comprobante.js', 'Dashboard.js'];

        $this->views->render($this, 'index', $data);
    }
}
