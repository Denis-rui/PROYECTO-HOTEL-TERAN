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
            return TipoHabitacion::where('id', $id)->update($datos) > 0;
        }

        TipoHabitacion::create($datos);
        return true;
    }
}
