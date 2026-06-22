<?php

namespace Controllers;

use Libraries\Core\Controller;
use Services\DashboardService;
use Services\NotificacionService;

class DashboardController extends Controller
{
    public function index($params = '')
    {
        // 1. Validación de seguridad
        if (!isset($_SESSION['usuario'])) {
            header('Location: ' . BASE_URL . 'Login/index');
            exit();
        }

        // 2. Instanciamos las clases necesarias
        $dashboardService = new DashboardService();
        $notificacionService = new NotificacionService();

        // 3. Solicitamos los datos al servicio
        $respuestaEstadisticas = $dashboardService->obtenerEstadisticas();

        // 4. Preparamos los datos para la Vista
        $data['page_title'] = "Dashboard";

        // EL CAMBIO CLAVE: Si el servicio tuvo éxito, pasamos los datos. Si falló, pasamos un array vacío.
        $data['stats'] = $respuestaEstadisticas['exito'] ? $respuestaEstadisticas['data'] : [];

        $respuestaNotificaciones = $notificacionService->obtenerNotificacionesCheckout();
        $data['notificaciones'] = $respuestaNotificaciones['data'];
        $data['page_js'] = ['Clientes.js', 'Modal-Clientes.js', 'Modal-NuevaReserva.js', 'Pago.js', 'Comprobante.js', 'Dashboard.js'];

        // 5. Renderizamos la vista
        $this->views->render($this, 'index', $data);
    }
}
