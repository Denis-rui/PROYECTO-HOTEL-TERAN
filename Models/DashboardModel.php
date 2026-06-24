<?php
namespace Models;

// Importamos el Query Builder de Illuminate
use Illuminate\Database\Capsule\Manager as DB;
use Models\Entities\Reserva as ReservaEntity;

class DashboardModel
{

        // ESTADÍSTICAS PRINCIPALES DEL DASHBOARD

    public function obtenerEstadisticasDashboard(): array
    {
        $stats = [];

        // ── Conteo de habitaciones por estado ──
        $conteoEstados = DB::table('habitacion')
            ->where('activo', 1)
            ->selectRaw("
                SUM(CASE WHEN estado = 'Disponible'    THEN 1 ELSE 0 END) AS disponibles,
                SUM(CASE WHEN estado = 'Ocupada'        THEN 1 ELSE 0 END) AS ocupadas,
                SUM(CASE WHEN estado = 'Reservada'      THEN 1 ELSE 0 END) AS reservadas,
                SUM(CASE WHEN estado = 'Mantenimiento'  THEN 1 ELSE 0 END) AS mantenimiento,
                SUM(CASE WHEN estado = 'En Limpieza'    THEN 1 ELSE 0 END) AS en_limpieza,
                COUNT(*)                                                    AS total
            ")
            ->first();

        $stats['habitaciones_disponibles']  = (int) ($conteoEstados->disponibles   ?? 0);
        $stats['habitaciones_ocupadas']     = (int) ($conteoEstados->ocupadas      ?? 0);
        $stats['habitaciones_reservadas']   = (int) ($conteoEstados->reservadas    ?? 0);
        $stats['habitaciones_mantenimiento']= (int) ($conteoEstados->mantenimiento ?? 0);
        $stats['habitaciones_en_limpieza']  = (int) ($conteoEstados->en_limpieza   ?? 0);
        $totalHabitaciones                  = max(1, (int) ($conteoEstados->total  ?? 1));

        // ── Porcentaje de ocupación ──
        $stats['ocupacion_porcentual'] = round(($stats['habitaciones_ocupadas'] / $totalHabitaciones) * 100, 1);

        // ── Reservas activas ──
        $stats['reservas_activas'] = (int) DB::table('reserva')
            ->whereIn('estado', ReservaEntity::ESTADOS_ACTIVOS)
            ->count();

        // ── Check-ins de hoy ──
        $stats['checkins_hoy'] = (int) DB::table('reserva_habitacion as rh')
            ->join('reserva as r', 'r.id', '=', 'rh.id_reserva')
            ->whereRaw('DATE(rh.check_in) = CURDATE()')
            ->whereIn('r.estado', ['confirmada', 'en_estadia'])
            ->count();

        // ── Check-outs de hoy ──
        $stats['checkouts_hoy'] = (int) DB::table('reserva_habitacion as rh')
            ->join('reserva as r', 'r.id', '=', 'rh.id_reserva')
            ->whereRaw('DATE(rh.check_out) = CURDATE()')
            ->whereIn('r.estado', ['en_estadia', 'checkout_pendiente', 'checkout_realizado'])
            ->count();

        // ── Check-outs vencidos ──
        $stats['checkouts_vencidos'] = (int) DB::table('reserva_habitacion as rh')
            ->join('reserva as r', 'r.id', '=', 'rh.id_reserva')
            ->whereIn('r.estado', ['en_estadia', 'checkout_pendiente'])
            ->whereNotNull('rh.check_out')
            ->whereRaw('NOW() > rh.check_out')
            ->where('rh.activo', 1)
            ->count();

        // ── Ingreso del día ──
        $stats['ingreso_dia'] = (float) DB::table('pago')
            ->whereRaw('DATE(fecha_pago) = CURDATE()')
            ->sum('monto');

        // Procedencias eliminadas: mantener valor 0 para compatibilidad de vista
        $stats['total_procedencias'] = 0;

        // ── Estancia mínima en días ──
        $stats['estancia_minima'] = (int) DB::table('reserva_habitacion as rh')
            ->join('reserva as r', 'r.id', '=', 'rh.id_reserva')
            ->whereNotIn('r.estado', ['cancelada', 'no_show'])
            ->whereNotNull('rh.check_out')
            ->selectRaw('IFNULL(MIN(DATEDIFF(rh.check_out, rh.check_in)), 0) AS min_dias')
            ->value('min_dias');

        // ── Detalles de habitaciones en mantenimiento ──
        $stats['detalles_mantenimiento'] = DB::table('habitacion')
            ->where('estado', 'Mantenimiento')
            ->where('activo', 1)
            ->select([
                'numero_habitacion',
                DB::raw("COALESCE(NULLIF(descripcion_habitacion, ''), 'Sin motivo especificado') AS motivo")
            ])
            ->orderBy('numero_habitacion')
            ->get()
            ->map(fn($row) => (array) $row)
            ->toArray();

        $stats['grafico_ingresos'] = $this->obtenerIngresosPorMes(6);
        $stats['grafico_estados_reserva'] = $this->obtenerEstadosReserva();

        return $stats;
    }

    public function obtenerIngresosPorMes(int $meses = 6): array
    {
        $filas = DB::table('pago')
            ->selectRaw("DATE_FORMAT(fecha_pago, '%Y-%m') AS periodo, DATE_FORMAT(fecha_pago, '%b %Y') AS mes, SUM(monto) AS total")
            ->whereRaw('fecha_pago >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)', [$meses])
            ->groupByRaw("DATE_FORMAT(fecha_pago, '%Y-%m'), DATE_FORMAT(fecha_pago, '%b %Y')")
            ->orderBy('periodo', 'asc')
            ->get();

        return $filas->map(fn($fila) => [
            'mes' => (string) $fila->mes,
            'total' => (float) $fila->total,
        ])->toArray();
    }

    public function obtenerEstadosReserva(): array
    {
        $estadosVisibles = [
            'pendiente',
            'confirmada',
            'checkin_realizado',
            'en_estadia',
            'checkout_pendiente',
            'checkout_realizado',
            'cancelada',
        ];

        $filas = DB::table('reserva')
            ->selectRaw('estado, COUNT(*) AS total')
            ->whereIn('estado', $estadosVisibles)
            ->groupBy('estado')
            ->orderBy('total', 'desc')
            ->get();

        return $filas->map(fn($fila) => [
            'estado' => (string) $fila->estado,
            'total' => (int) $fila->total,
        ])->toArray();
    }
}
