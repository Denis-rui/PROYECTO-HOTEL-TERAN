<?php

namespace Models;

use Models\Entities\Hotel;

class ConfiguracionModel
{
    public function find($id = 1)
    {
        return Hotel::find($id);
    }

    public function actualizarHotel(Hotel $hotel): bool
    {
        return $hotel->save();
    }
}
