<?php

namespace Models;

use Illuminate\Database\Capsule\Manager as DB;
use Models\Entities\Hotel;
use Models\Entities\Reserva;

class CalculoDevolucionModel
{
    public function obtenerReservaParaCalculo(int $idReserva): ?Reserva
    {
        return Reserva::with(['reservaHabitacion', 'pagos'])
            ->find($idReserva);
    }

    public function obtenerConfiguracionHotel(): ?Hotel
    {
        return Hotel::first();
    }

    public function obtenerDocumentosElectronicosPorReserva(int $idReserva)
    {
        return DB::table('documento_electronico_reserva')
            ->where('id_reserva', $idReserva)
            ->orderBy('id')
            ->get();
    }
}
