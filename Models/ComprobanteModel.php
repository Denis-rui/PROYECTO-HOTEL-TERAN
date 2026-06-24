<?php

namespace Models;

use Models\Entities\Comprobante;
use Models\Entities\Pago;

class ComprobanteModel
{
    public function generarNumeroTicket(?int $idPago = null): string
    {
        $anio = date('Y');
        $prefijo = 'TCK-' . $anio . '-';

        if ($idPago !== null && $idPago > 0) {
            return $prefijo . str_pad((string) $idPago, 6, '0', STR_PAD_LEFT);
        }

        $ultimo = Comprobante::where('numero_ticket', 'like', $prefijo . '%')
            ->orderBy('id', 'desc')
            ->first();

        $numero = 1;

        if ($ultimo && !empty($ultimo->numero_ticket)) {
            $partes = explode('-', $ultimo->numero_ticket);
            $numero = ((int) end($partes)) + 1;
        }

        return $prefijo . str_pad((string) $numero, 6, '0', STR_PAD_LEFT);
    }

    public function crear(array $datos): ?Comprobante
    {
        return Comprobante::create($datos);
    }

    public function obtenerEntidadPorPago(int $idPago): ?Comprobante
    {
        return Comprobante::with([
            'pago.reserva.reservaHabitacion.habitacion',
            'usuario'
        ])
            ->where('id_pago', $idPago)
            ->first();
    }

    public function obtenerTicketsPorReserva(int $idReserva)
    {
        return Comprobante::with('pago')
            ->whereHas('pago', fn($q) => $q->where('id_reserva', $idReserva))
            ->orderBy( 
                Pago::select('fecha_pago')
                ->whereColumn('pago.id', 'comprobante.id_pago')
                ->limit(1), 'asc')
            ->orderBy('id', 'asc')
            ->get();
       
    }

    public function sumarPagosPorReserva(int $idReserva): float
    {
        return (float) Pago::where('id_reserva', $idReserva)->sum('monto');
       
    }
}
