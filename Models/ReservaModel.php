<?php

namespace Models;

use Models\Entities\Reserva;
use Models\Entities\ReservaHabitacion;
use Helpers\ReservaFormatterHelper as ReservaFormatter;

class ReservaModel
{
    public function obtenerReservas(array $filtros = [], int $limite = 30): array
    {
        try {
            $busqueda = trim((string) ($filtros['busqueda'] ?? ''));
            $estado = strtolower(trim((string) ($filtros['estado'] ?? '')));

            $estadosPermitidos = [
                'confirmada',
                'en_estadia',
                'checkout_realizado',
                'checkout_pendiente',
                'ausente',
                'cancelada'
            ];

            $limite = max(30, $limite);

            $query = Reserva::with([
                'cliente',
                'usuario',
                'pagos',
                'reservaHabitacion.habitacion'
            ]);

            if ($estado === '' || $estado !== 'cancelada') {
                $query->where('estado', '!=', 'cancelada');
            }

            if ($busqueda !== '') {
                $query->whereHas('cliente', function ($q) use ($busqueda) {
                    $q->where(function ($subQuery) use ($busqueda) {
                        $subQuery->where('nombre_completo', 'like', '%' . $busqueda . '%')
                            ->orWhere('documento', 'like', '%' . $busqueda . '%');
                    });
                });
            }

            if ($estado !== '' && in_array($estado, $estadosPermitidos, true)) {
                $query->where('estado', $estado);
            }

            $query->select('reserva.*')
                ->selectSub(
                    ReservaHabitacion::selectRaw('MIN(reserva_habitacion.check_in)')
                        ->whereColumn('reserva_habitacion.id_reserva', 'reserva.id')
                        ->where(function ($q) {
                            $q->whereNull('reserva_habitacion.estado')
                                ->orWhere('reserva_habitacion.estado', 'activa');
                        }),
                    'primer_check_in'
                );

            $total = (clone $query)->count();

            $reservas = $query
                ->orderByRaw("
                    CASE
                        WHEN LOWER(reserva.estado) IN ('en_estadia', 'checkout_pendiente', 'ausente') THEN 0
                        WHEN LOWER(reserva.estado) = 'confirmada' THEN 1
                        WHEN LOWER(reserva.estado) = 'checkout_realizado' THEN 3
                        ELSE 2
                    END ASC
                ")
                ->orderByRaw('primer_check_in IS NULL ASC')
                ->orderByRaw('primer_check_in ASC')
                ->orderByDesc('reserva.id')
                ->limit($limite)
                ->get()
                ->map(fn($reserva) => ReservaFormatter::formatear($reserva))
                ->values()
                ->all();

            return [
                'items' => $reservas,
                'total' => (int) $total,
                'mostrados' => count($reservas),
                'hay_mas' => $total > count($reservas),
            ];
        } catch (\Throwable $e) {
            return [
                'items' => [],
                'total' => 0,
                'mostrados' => 0,
                'hay_mas' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function obtenerReservaPorId($idReserva): ?array
    {
        $reserva = $this->obtenerReservaCompleta((int) $idReserva);

        return $reserva ? ReservaFormatter::formatear($reserva) : null;
    }

    public function obtenerReservaCompleta(int $idReserva): ?Reserva
    {
        return Reserva::with([
            'cliente',
            'usuario',
            'pagos',
            'reservaHabitacion.habitacion'
        ])->find($idReserva);
    }

    public function obtenerReservaConHabitaciones(int $idReserva): ?Reserva
    {
        return Reserva::with([
            'reservaHabitacion.habitacion'
        ])->find($idReserva);
    }

    public function obtenerReservaConHabitacionesYPagos(int $idReserva): ?Reserva
    {
        return Reserva::with([
            'reservaHabitacion.habitacion',
            'pagos'
        ])->find($idReserva);
    }

    public function obtenerReservaSimple(int $idReserva): ?Reserva
    {
        return Reserva::find($idReserva);
    }

    public function guardar(Reserva $reserva): bool
    {
        return $reserva->save();
    }

    public function actualizar(Reserva $reserva, array $datos): bool
    {
        return $reserva->update($datos);
    }
    public function crear(array $datos): Reserva
    {
        return Reserva::create($datos);
    }

    public function generarCodigoReserva(): string
    {
        $anio = date('Y');
        $prefijo = 'TER-' . $anio . '-';

        $ultimaReserva = Reserva::where('codigo_reserva', 'like', $prefijo . '%')
            ->orderBy('id', 'desc')
            ->first();

        $numero = 1;

        if ($ultimaReserva && !empty($ultimaReserva->codigo_reserva)) {
            $partes = explode('-', $ultimaReserva->codigo_reserva);
            $numero = ((int) end($partes)) + 1;
        }

        return $prefijo . str_pad((string) $numero, 6, '0', STR_PAD_LEFT);
    }
}
