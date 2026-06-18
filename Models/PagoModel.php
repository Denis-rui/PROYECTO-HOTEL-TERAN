<?php

namespace Models;

use Models\Entities\Pago;

class PagoModel
{
    public function crear(array $datos): Pago
    {
        return Pago::create($datos);
    }

    public function obtenerPorReserva(int $idReserva)
    {
        return Pago::where('id_reserva', $idReserva)
            ->orderBy('fecha_pago', 'asc')
            ->get();
    }

    public function sumarPagosPorReserva(int $idReserva): float
    {
        return (float) Pago::where('id_reserva', $idReserva)
            ->sum('monto');
    }

    public function obtenerPorId(int $idPago): ?Pago
    {
        return Pago::find($idPago);
    }
}
