<?php

namespace Helpers;

class ReservaHabitacionHelper
{
    public static function esActiva($reservaHabitacion): bool
    {
        return $reservaHabitacion
            && !empty($reservaHabitacion->id_habitacion)
            && (int) ($reservaHabitacion->activo ?? 1) === 1
            && (($reservaHabitacion->estado ?? 'activa') === 'activa');
    }
}
