<?php

namespace Helpers;

class HabitacionInputHelper
{
    public static function obtenerIdsDesdeRequest(array $datos): array
    {
        $habitacionesIngresadas = $datos['habitaciones'] ?? [];

        if (is_string($habitacionesIngresadas)) {
            $decoded = json_decode($habitacionesIngresadas, true);
            $habitacionesIngresadas = is_array($decoded) ? $decoded : [];
        }

        if (empty($habitacionesIngresadas) && !empty($datos['habitacion'])) {
            $habitacionesIngresadas = [$datos['habitacion']];
        }

        $idsHabitaciones = [];

        foreach ($habitacionesIngresadas as $habitacionIngresada) {
            $idHabitacion = is_array($habitacionIngresada)
                ? (int) ($habitacionIngresada['id'] ?? $habitacionIngresada['id_habitacion'] ?? 0)
                : (int) $habitacionIngresada;

            if ($idHabitacion > 0) {
                $idsHabitaciones[] = $idHabitacion;
            }
        }

        return array_values(array_unique($idsHabitaciones));
    }
}
