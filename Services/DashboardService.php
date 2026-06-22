<?php

namespace Services;

use Models\DashboardModel;
use Exception;

class DashboardService
{
    private DashboardModel $dashboardModel;

    public function __construct()
    {
        $this->dashboardModel = new DashboardModel();
    }

    /*Envuelve los datos del modelo en una respuesta estandarizada y
     protege al sistema de caídas si la base de datos falla*/
    public function obtenerEstadisticas(): array
    {
        try {
            // Llamamos al Modelo para que haga el trabajo pesado de SQL
            $estadisticas = $this->dashboardModel->obtenerEstadisticasDashboard();

            // Retornamos el éxito con el formato estándar
            return [
                'exito' => true,
                'codigo' => 'OK',
                'mensaje' => 'Estadísticas del dashboard cargadas correctamente.',
                'data' => $estadisticas
            ];
        } catch (Exception $e) {
            // Si algo falla en el SQL (una tabla no existe, se cayó la BD, etc.)
            // Guardamos el error real en los logs para el programador
            error_log('Error al cargar métricas del Dashboard: ' . $e->getMessage());

            // Y le devolvemos una respuesta controlada al usuario/frontend
            return [
                'exito' => false,
                'codigo' => 'ERROR_ESTADISTICAS',
                'mensaje' => 'Ocurrió un error al intentar cargar las estadísticas. Por favor, intenta más tarde.',
                'data' => null
            ];
        }
    }
}
