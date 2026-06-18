<?php

namespace Models;

use Illuminate\Database\Capsule\Manager as DB;
use Models\Entities\ReservaHabitacion;

class ReservaHabitacionModel
{
    public function crear(array $datos): ReservaHabitacion
    {
        return ReservaHabitacion::create($datos);
    }

    public function guardar(ReservaHabitacion $reservaHabitacion): bool
    {
        return $reservaHabitacion->save();
    }

    public function obtenerPorReserva(int $idReserva)
    {
        return ReservaHabitacion::where('id_reserva', $idReserva)
            ->get();
    }

    public function eliminarPorReserva(int $idReserva): void
    {
        ReservaHabitacion::where('id_reserva', $idReserva)
            ->delete();
    }

    public function sumarSubtotales(int $idReserva): float
    {
        return (float) DB::table('reserva_habitacion')
            ->where('id_reserva', $idReserva)
            ->sum('subtotal');
    }

    public function habitacionSigueOcupada(int $idHabitacion, array $estadosActivos): bool
    {
        return DB::table('reserva_habitacion as rh')
            ->join('reserva as r', 'r.id', '=', 'rh.id_reserva')
            ->where('rh.id_habitacion', $idHabitacion)
            ->where('rh.activo', 1)
            ->whereIn('r.estado', $estadosActivos)
            ->exists();
    }
}
