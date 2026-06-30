<?php

namespace Models;

use Models\Entities\Reserva;
use Models\Entities\ReservaHabitacion;
use Models\Entities\Cliente;
use Helpers\FormatearReservas as ReservaFormatter;


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
            error_log('ReservaModel::obtenerReservas -> ' . $e->getMessage());
            return [
                'items' => [],
                'total' => 0,
                'mostrados' => 0,
                'hay_mas' => false,
                'error' => 'No se pudieron obtener las reservas.',
            ];
        }
    }

    public function obtenerReservasDataTable(array $parametros): array
    {
        try {
            // DataTables envía start/length para pedir solo una "página" de registros.
            // Esto evita traer todas las reservas a PHP cuando solo se mostrarán unas cuantas filas.
            $inicio = max(0, (int) ($parametros['start'] ?? 0));
            $cantidad = (int) ($parametros['length'] ?? 30);
            $cantidad = $cantidad > 0 ? min($cantidad, 100) : 30;

            $busquedaDataTable = trim((string) ($parametros['search']['value'] ?? ''));
            $busquedaPropia = trim((string) ($parametros['busqueda'] ?? ''));
            $busqueda = $busquedaPropia !== '' ? $busquedaPropia : $busquedaDataTable;
            $estado = strtolower(trim((string) ($parametros['estado'] ?? '')));

            $queryTotal = $this->crearConsultaReservasDataTable('', '');
            $total = (clone $queryTotal)->count();

            $queryFiltrada = $this->crearConsultaReservasDataTable($busqueda, $estado);
            $filtrados = (clone $queryFiltrada)->count();

            $this->aplicarOrdenDataTable($queryFiltrada, $parametros);

            $reservas = $queryFiltrada
                ->skip($inicio)
                ->take($cantidad)
                ->get()
                ->map(fn($reserva) => ReservaFormatter::formatear($reserva))
                ->values()
                ->all();

            return [
                'items' => $reservas,
                'total' => (int) $total,
                'filtrados' => (int) $filtrados,
            ];
        } catch (\Throwable $e) {
            error_log('ReservaModel::obtenerReservasDataTable -> ' . $e->getMessage());
            return [
                'items' => [],
                'total' => 0,
                'filtrados' => 0,
                'error' => 'No se pudieron obtener las reservas para DataTables.',
            ];
        }
    }

    private function crearConsultaReservasDataTable(string $busqueda, string $estado)
    {
        $estadosPermitidos = [
            'confirmada',
            'en_estadia',
            'checkout_realizado',
            'checkout_pendiente',
            'ausente',
            'cancelada'
        ];

        $query = Reserva::with([
            'cliente',
            'usuario',
            'pagos',
            'reservaHabitacion.habitacion'
        ]);

        // Mantenemos la misma regla del listado anterior: las canceladas no salen en "todos";
        // solo aparecen cuando el usuario filtra explícitamente por estado cancelada.
        if ($estado === '' || $estado !== 'cancelada') {
            $query->where('estado', '!=', 'cancelada');
        }

        if ($estado !== '' && in_array($estado, $estadosPermitidos, true)) {
            $query->where('estado', $estado);
        }

        if ($busqueda !== '') {
            $query->where(function ($q) use ($busqueda) {
                $q->where('codigo_reserva', 'like', '%' . $busqueda . '%')
                    ->orWhere('estado', 'like', '%' . $busqueda . '%')
                    ->orWhereHas('cliente', function ($clienteQuery) use ($busqueda) {
                        $clienteQuery->where('nombre_completo', 'like', '%' . $busqueda . '%')
                            ->orWhere('documento', 'like', '%' . $busqueda . '%');
                    })
                    ->orWhereHas('reservaHabitacion.habitacion', function ($habitacionQuery) use ($busqueda) {
                        $habitacionQuery->where('numero_habitacion', 'like', '%' . $busqueda . '%');
                    });
            });
        }

        return $query
            ->select('reserva.*')
            ->selectSub(
                Cliente::select('nombre_completo')
                    ->whereColumn('cliente.id', 'reserva.id_cliente')
                    ->limit(1),
                'cliente_nombre_orden'
            )
            ->selectSub(
                ReservaHabitacion::selectRaw('MIN(reserva_habitacion.check_in)')
                    ->whereColumn('reserva_habitacion.id_reserva', 'reserva.id')
                    ->where(function ($q) {
                        $q->whereNull('reserva_habitacion.estado')
                            ->orWhere('reserva_habitacion.estado', 'activa');
                    }),
                'primer_check_in'
            );
    }

    private function aplicarOrdenDataTable($query, array $parametros): void
    {
        $indiceColumna = (int) ($parametros['order'][0]['column'] ?? -1);
        $direccion = strtolower((string) ($parametros['order'][0]['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

        // Solo se ordenan columnas seguras que pertenecen directamente a reserva.
        // Para cliente/habitación dejamos el orden por prioridad porque requieren joins adicionales.
        $columnasOrdenables = [
            0 => 'cliente_nombre_orden',
            2 => 'primer_check_in',
            3 => 'primer_check_in',
            4 => 'estado',
        ];

        if (isset($columnasOrdenables[$indiceColumna])) {
            $query->orderBy($columnasOrdenables[$indiceColumna], $direccion)
                ->orderByDesc('reserva.id');
            return;
        }

        // Orden por defecto del negocio: primero reservas activas/urgentes, luego confirmadas,
        // y al final registros cerrados. Es la misma prioridad que tenía la carga MVC anterior.
        $query
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
            ->orderByDesc('reserva.id');
    }

    public function obtenerReservaPorId($idReserva): ?array
    {
        $reserva = Reserva::with([
            'cliente',
            'usuario',
            'pagos',
            'reservaHabitacion.habitacion'
        ])->find($idReserva);
        return $reserva ? ReservaFormatter::formatear($reserva) : null;
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
