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
        return DB::table('reserva_habitacion as rh')
            ->join('reserva as r', 'r.id', '=', 'rh.id_reserva')
            ->whereIn('r.estado', ReservaEntity::ESTADOS_EN_ESTADIA)
            ->where('rh.activo', 1)
            ->select(['r.id as id_reserva', 'rh.id_habitacion'])
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->id_reserva . '|' . $item->id_habitacion => true];
            })
            ->all();
    }

    public function obtenerNoLeidas(int $limite): array
    {
        return Notificacion::query()
            ->where('leida', 0)
            ->orderByDesc('fecha_creacion')
            ->limit($limite)
            ->get()
            ->toArray();
    }

    public function obtenerReservasEnCheckout(): array
    {
        return DB::table('reserva_habitacion as rh')
            ->join('reserva as r', 'r.id', '=', 'rh.id_reserva')
            ->join('cliente as c', 'c.id', '=', 'r.id_cliente')
            ->join('habitacion as h', 'h.id', '=', 'rh.id_habitacion')
            ->whereIn('r.estado', ReservaEntity::ESTADOS_EN_ESTADIA)
            ->whereNotNull('rh.check_out')
            ->where('rh.activo', 1)
            ->orderBy('rh.check_out', 'asc')
            ->selectRaw("r.id AS id_reserva, c.id AS id_cliente, c.nombre_completo AS cliente, h.id AS id_habitacion, h.numero_habitacion AS habitacion, rh.check_out, TIMESTAMPDIFF(MINUTE, NOW(), rh.check_out) AS minutos_faltantes, CASE WHEN NOW() > rh.check_out THEN TIMESTAMPDIFF(MINUTE, rh.check_out, NOW()) ELSE 0 END AS minutos_excedidos")
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
}
