<?php
namespace Models;

use Libraries\Core\Model;
use PDO;

class DashboardModel extends Model
{
    public function __construct()
    {
        parent::__construct();
    }

    public function obtenerEstadisticasDashboard()
    {
        $stats = [];
        $con = $this->conectar();
        $stats['habitaciones_disponibles'] = (int) $con->query("SELECT COUNT(*) FROM habitacion WHERE activo = 1 AND estado = 'Disponible'")->fetchColumn();
        $stats['habitaciones_ocupadas'] = (int) $con->query("SELECT COUNT(*) FROM habitacion WHERE activo = 1 AND estado = 'Ocupada'")->fetchColumn();
        $stats['habitaciones_reservadas'] = (int) $con->query("SELECT COUNT(*) FROM habitacion WHERE activo = 1 AND estado = 'Reservada'")->fetchColumn();
        $stats['habitaciones_mantenimiento'] = (int) $con->query("SELECT COUNT(*) FROM habitacion WHERE activo = 1 AND estado = 'Mantenimiento'")->fetchColumn();
        $stats['reservas_activas'] = (int) $con->query("SELECT COUNT(*) FROM reserva WHERE estado IN ('pendiente','confirmada','checkin_realizado','en_estadia','checkout_pendiente')")->fetchColumn();
        $stats['checkins_hoy'] = (int) $con->query("SELECT COUNT(*) FROM reserva_habitacion rh JOIN reserva r ON r.id = rh.id_reserva WHERE DATE(rh.check_in) = CURDATE() AND r.estado IN ('confirmada','en_estadia')")->fetchColumn();
        $stats['checkouts_hoy'] = (int) $con->query("SELECT COUNT(*) FROM reserva_habitacion rh JOIN reserva r ON r.id = rh.id_reserva WHERE DATE(rh.check_out) = CURDATE() AND r.estado IN ('en_estadia','checkout_pendiente','checkout_realizado')")->fetchColumn();
        $stats['checkouts_vencidos'] = (int) $con->query("SELECT COUNT(*) FROM reserva_habitacion rh JOIN reserva r ON r.id = rh.id_reserva WHERE r.estado IN ('en_estadia','checkout_pendiente') AND rh.check_out IS NOT NULL AND NOW() > rh.check_out AND rh.activo = 1")->fetchColumn();
        $stats['ingreso_dia'] = (float) $con->query("SELECT IFNULL(SUM(monto),0) FROM pago WHERE DATE(fecha_pago) = CURDATE()")->fetchColumn();
        $stats['total_procedencias'] = (int) $con->query("SELECT COUNT(DISTINCT procedencia) FROM cliente WHERE procedencia IS NOT NULL AND procedencia != ''")->fetchColumn();
        $stats['estancia_minima'] = (int) $con->query("SELECT IFNULL(MIN(DATEDIFF(rh.check_out, rh.check_in)), 0) FROM reserva_habitacion rh JOIN reserva r ON r.id = rh.id_reserva WHERE r.estado NOT IN ('cancelada', 'no_show') AND rh.check_out IS NOT NULL")->fetchColumn();

        $totalHabitaciones = max(1, (int) $con->query("SELECT COUNT(*) FROM habitacion WHERE activo = 1")->fetchColumn());
        $stats['ocupacion_porcentual'] = round(($stats['habitaciones_ocupadas'] / $totalHabitaciones) * 100, 1);

        $sqlMante = "SELECT numero_habitacion, COALESCE(descripcion_habitacion, 'Sin motivo especificado') as motivo 
                     FROM habitacion 
                     WHERE estado = 'Mantenimiento' AND activo = 1";
        $stats['detalles_mantenimiento'] = $con->query($sqlMante)->fetchAll(PDO::FETCH_ASSOC);

        return $stats;
    }
}