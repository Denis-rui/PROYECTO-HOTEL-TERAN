<?php

namespace Models;

use Illuminate\Database\Capsule\Manager as DB;
use Models\Entities\Notificacion;
use Models\Entities\Reserva as ReservaEntity;

class NotificacionModel
{
    public function guardarNotificacion(array $identificadores, array $datosActualizar)
    {
        $notificacion = Notificacion::query()->where($identificadores)->first();

        if (!$notificacion) {
            $notificacion = new Notificacion();
            $notificacion->fecha_creacion = date('Y-m-d H:i:s');
        }

        $notificacion->fill($datosActualizar);
        return $notificacion->save();
    }

    public function obtenerClavesActivasCheckout(): array
    {
        return DB::table('reserva as r')
            ->whereIn('r.estado', ReservaEntity::ESTADOS_EN_ESTADIA)
            ->whereNotNull('r.check_out_programado')
            ->select(['r.id as id_reserva'])
            ->get()
            ->mapWithKeys(function ($item) {
                return [(int) $item->id_reserva => true];
            })
            ->all();
    }

    public function obtenerNoLeidas(int $limite): array
    {
        return Notificacion::query()
            ->with('habitacion')
            ->where('leida', 0)
            ->orderByDesc('fecha_creacion')
            ->limit($limite)
            ->get()
            ->toArray();
    }

    public function obtenerReservasEnCheckout(): array
    {
        return DB::table('reserva as r')
            ->join('cliente as c', 'c.id', '=', 'r.id_cliente')
            ->leftJoin('reserva_habitacion as rh', function ($join) {
                $join->on('rh.id_reserva', '=', 'r.id')
                    ->where('rh.activo', '=', 1);
            })
            ->leftJoin('habitacion as h', 'h.id', '=', 'rh.id_habitacion')
            ->whereIn('r.estado', ReservaEntity::ESTADOS_EN_ESTADIA)
            ->whereNotNull('r.check_out_programado')
            ->groupBy('r.id', 'c.id', 'c.nombres', 'c.apellido_paterno', 'c.apellido_materno', 'r.check_out_programado')
            ->orderBy('r.check_out_programado', 'asc')
            ->selectRaw("
                        r.id AS id_reserva, 
                        c.id AS id_cliente, 
                        CONCAT(COALESCE(c.nombres, ''), ' ', COALESCE(c.apellido_paterno, ''), ' ', COALESCE(c.apellido_materno, '')) AS cliente, 
                        MIN(h.id) AS id_habitacion, 
                        GROUP_CONCAT(DISTINCT h.numero_habitacion ORDER BY h.numero_habitacion SEPARATOR ', ') AS habitacion, 
                        r.check_out_programado AS check_out, 
                        TIMESTAMPDIFF(MINUTE, NOW(), r.check_out_programado) AS minutos_faltantes, 
                        CASE WHEN NOW() > r.check_out_programado THEN TIMESTAMPDIFF(MINUTE, r.check_out_programado, NOW()) ELSE 0 END AS minutos_excedidos
                    ")
            ->get()
            ->map(fn($item) => (array) $item)
            ->toArray();
    }
    public function crear(array $datos): bool
    {
        $notificacion = new Notificacion();
        $notificacion->fill($datos);
        $notificacion->fecha_creacion = date('Y-m-d H:i:s');
        return $notificacion->save();
    }

    // Agregamos funcion para actualizar notificacion en dashboard

    public function marcarCheckoutLeidoPorHabitacion(int $idHabitacion): bool
    {
        return Notificacion::where('id_habitacion', $idHabitacion)
            ->where('tipo', 'checkout')
            ->where('leida', 0)
            ->update(['leida' => 1]) !== false;
    }

    public function marcarLimpiezaLeidaPorHabitacion(int $idHabitacion): bool
    {
        return Notificacion::where('id_habitacion', $idHabitacion)
            ->where('tipo', 'limpieza_vencida')
            ->where('leida', 0)
            ->update(['leida' => 1]) !== false;
    }
}
