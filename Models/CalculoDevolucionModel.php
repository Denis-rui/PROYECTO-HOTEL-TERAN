<?php

namespace Models;

use Models\Entities\Hotel;
use Models\Entities\Reserva;
use Models\Entities\DocumentoElectronico;

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
        return DocumentoElectronico::where('id_reserva', $idReserva)
            ->orderBy('id')
            ->get();
    }
}
