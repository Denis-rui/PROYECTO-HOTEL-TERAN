<?php

namespace Models;

use Models\Entities\ReservaHabitacion;

class ReservaHabitacionModel
{
    public function crear(array $datos): ReservaHabitacion
    {
        return ReservaHabitacion::create($datos);
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
}
