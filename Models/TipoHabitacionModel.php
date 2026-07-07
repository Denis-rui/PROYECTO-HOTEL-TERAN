<?php

namespace Models;

use Models\Entities\TipoHabitacion;

class TipoHabitacionModel
{
    public function listar(): array
    {
        return TipoHabitacion::orderBy('id')->get()->toArray();
    }

    public function guardar(?int $id, array $datos): bool
    {
        if ($id !== null && $id > 0) {
            $tipoHabitacion = TipoHabitacion::find($id);
            if (!$tipoHabitacion) {
                return false;
            }

            $tipoHabitacion->fill($datos);
            return $tipoHabitacion->save();
        }

        TipoHabitacion::create($datos);
        return true;
    }
}
