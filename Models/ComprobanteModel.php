<?php

namespace Models;

use Illuminate\Database\Capsule\Manager as DB;
use Models\Entities\Comprobante;

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
        return DB::table('comprobante as c')
            ->join('pago as p', 'p.id', '=', 'c.id_pago')
            ->where('p.id_reserva', $idReserva)
            ->orderBy('p.fecha_pago', 'asc')
            ->orderBy('c.id', 'asc')
            ->select([
                'c.id',
                'c.id_pago',
                'c.numero_ticket',
                'c.fecha_emision',
                'c.descripcion',
                'c.total',
                'c.id_forma_pago',
                'c.id_usuario',
                'p.fecha_pago',
            ])
            ->get();
    }

    public function sumarPagosPorReserva(int $idReserva): float
    {
        return (float) DB::table('pago')
            ->where('id_reserva', $idReserva)
            ->sum('monto');
    }
}
