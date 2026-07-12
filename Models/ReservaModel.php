<?php

namespace Models;

use Models\Entities\Reserva;
use Models\Entities\ReservaHabitacion;
use Models\Entities\Cliente;
use Helpers\FormatearReservas as ReservaFormatter;


class ReservaModel
{
    public function obtenerReservasDataTable(array $parametros): array
    {
        try {
            $this->inactivarPendientesVencidas();

            // DataTables envía start/length para pedir solo una "página" de registros.
            // Esto evita traer todas las reservas a PHP cuando solo se mostrarán unas cuantas filas.
            $inicio = max(0, (int) ($parametros['start'] ?? 0));
            $cantidad = (int) ($parametros['length'] ?? 30);
            $cantidad = $cantidad > 0 ? min($cantidad, 100) : 30;

            $busquedaDataTable = trim((string) ($parametros['search']['value'] ?? ''));
            $busquedaPropia = trim((string) ($parametros['busqueda'] ?? ''));
            $busqueda = $busquedaPropia !== '' ? $busquedaPropia : $busquedaDataTable;
            $estado = strtolower(trim((string) ($parametros['estado'] ?? '')));
            $filtroHoy = strtolower(trim((string) ($parametros['filtro_hoy'] ?? '')));

            if ($this->esFiltroHoyDataTable($filtroHoy)) {
                $busqueda = '';
                $estado = '';
            }

            $queryTotal = $this->crearConsultaReservasDataTable('', '', '');
            $total = (clone $queryTotal)->count();

            $queryFiltrada = $this->crearConsultaReservasDataTable($busqueda, $estado, $filtroHoy);
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

    private function crearConsultaReservasDataTable(string $busqueda, string $estado, string $filtroHoy = '')
    {
        $estadosPermitidos = [
            'confirmada',
            'pre_checkin',
            'pendiente',
            'en_estadia',
            'checkout_realizado',
            'checkout_pendiente',
            'ausente',
            'cancelada',
            'inactiva'
        ];

        $query = Reserva::with([
            'cliente',
            'usuario',
            'pagos',
            'reservaHabitacion.habitacion'
        ]);

        // Mantenemos la misma regla del listado anterior: las canceladas no salen en "todos"
        if ($estado === '') {
            $query->whereNotIn('estado', ['cancelada', 'inactiva']);
        } elseif (!in_array($estado, ['cancelada', 'inactiva'], true)) {
            $query->whereNotIn('estado', ['cancelada', 'inactiva']);
        }

        if ($estado !== '' && in_array($estado, $estadosPermitidos, true)) {
            $query->where('estado', $estado);
        }

        if ($this->esFiltroHoyDataTable($filtroHoy)) {
            $this->aplicarFiltroHoyDataTable($query, $filtroHoy);
        } elseif ($busqueda !== '') {
            $query->where(function ($q) use ($busqueda) {
                $q->where('codigo_reserva', 'like', '%' . $busqueda . '%')
                    ->orWhere('estado', 'like', '%' . $busqueda . '%')
                    ->orWhereHas('cliente', function ($clienteQuery) use ($busqueda) {
                        $clienteQuery->where(function ($sub) use ($busqueda) {
                            $sub->whereRaw(
                                "CONCAT(COALESCE(nombres, ''), ' ', COALESCE(apellido_paterno, ''), ' ', COALESCE(apellido_materno, '')) LIKE ?",
                                ['%' . $busqueda . '%']
                            )
                                ->orWhere('documento', 'like', '%' . $busqueda . '%')
                                ->orWhere('ruc', 'like', '%' . $busqueda . '%'); // Aseguramos RUC aquí también
                        });
                    })
                    ->orWhereHas('reservaHabitacion.habitacion', function ($habitacionQuery) use ($busqueda) {
                        $habitacionQuery->where('numero_habitacion', 'like', '%' . $busqueda . '%');
                    });
            });
        }

        return $query
            ->select('reserva.*')
            ->selectRaw('reserva.check_in_programado as fecha_programada_orden')
            ->selectSub(
                Cliente::selectRaw("TRIM(CONCAT_WS(' ', NULLIF(nombres, ''), NULLIF(apellido_paterno, ''), NULLIF(apellido_materno, '')))")
                    ->whereColumn('cliente.id', 'reserva.id_cliente')
                    ->limit(1),
                'cliente_nombre_orden'
            );
    }

    private function aplicarFiltroHoyDataTable($query, string $filtroHoy): void
    {
        if (!$this->esFiltroHoyDataTable($filtroHoy)) {
            return;
        }

        if ($filtroHoy === 'checkin_hoy') {
            $query->whereRaw('DATE(reserva.check_in_programado) = CURDATE()');
            return;
        }

        if ($filtroHoy === 'checkout_hoy') {
            $query->whereRaw('DATE(reserva.check_out_programado) = CURDATE()');
            return;
        }

        if ($filtroHoy === 'checkout_vencido') {
            $query->whereIn('reserva.estado', ['en_estadia', 'checkout_pendiente', 'ausente'])
                ->whereNull('reserva.checkout_real')
                ->whereNotNull('reserva.check_out_programado')
                ->whereRaw('NOW() > reserva.check_out_programado');
            return;
        }

        if ($filtroHoy === 'checkins_realizados_hoy') {
            $query->whereRaw('DATE(reserva.checkin_real) = CURDATE()');
            return;
        }

        if ($filtroHoy === 'checkouts_realizados_hoy') {
            $query->whereRaw('DATE(reserva.checkout_real) = CURDATE()');
            return;
        }

        $query->whereHas('pagos', function ($q) {
            $q->whereRaw('DATE(pago.fecha_pago) = CURDATE()');
        });
    }

    private function esFiltroHoyDataTable(string $filtroHoy): bool
    {
        return in_array($filtroHoy, [
            'checkin_hoy',
            'checkout_hoy',
            'checkout_vencido',
            'checkins_realizados_hoy',
            'checkouts_realizados_hoy',
            'pagos_realizados_hoy',
        ], true);
    }

    private function aplicarOrdenDataTable($query, array $parametros): void
    {
        $indiceColumna = (int) ($parametros['order'][0]['column'] ?? -1);
        $direccion = strtolower((string) ($parametros['order'][0]['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

        // Solo se ordenan columnas seguras que pertenecen directamente a reserva.
        // Para cliente/habitación dejamos el orden por prioridad porque requieren joins adicionales.
        $columnasOrdenables = [
            0 => 'cliente_nombre_orden',
            2 => 'fecha_programada_orden',
            3 => 'fecha_programada_orden',
            4 => 'estado',
        ];

        if (isset($columnasOrdenables[$indiceColumna])) {
            $query->orderByRaw("
                CASE
                    WHEN LOWER(reserva.estado) = 'pendiente' THEN 0
                    ELSE 1
                END ASC
            ")
                ->orderBy($columnasOrdenables[$indiceColumna], $direccion)
                ->orderByDesc('reserva.id');
            return;
        }

        // Orden por defecto del negocio: primero reservas activas/urgentes, luego confirmadas,
        // y al final registros cerrados. Es la misma prioridad que tenía la carga MVC anterior.
        $query
            ->orderByRaw("
                CASE
                    WHEN LOWER(reserva.estado) = 'pendiente' THEN 0
                    WHEN LOWER(reserva.estado) IN ('en_estadia', 'checkout_pendiente', 'ausente') THEN 1
                    WHEN LOWER(reserva.estado) IN ('confirmada', 'pre_checkin') THEN 2
                    WHEN LOWER(reserva.estado) = 'checkout_realizado' THEN 3
                    ELSE 4
                END ASC
            ")
            ->orderByRaw('fecha_programada_orden IS NULL ASC')
            ->orderByRaw('fecha_programada_orden ASC')
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

    public function inactivarPendientesVencidas(): int
    {
        try {
            $reservasVencidas = Reserva::with('reservaHabitacion')
                ->where('estado', 'pendiente')
                ->whereNotNull('fecha_creacion')
                ->whereRaw('fecha_creacion <= DATE_SUB(NOW(), INTERVAL 6 HOUR)')
                ->whereDoesntHave('pagos')
                ->get();

            if ($reservasVencidas->isEmpty()) {
                return 0;
            }

            $idsReserva = $reservasVencidas->pluck('id')->map(fn($id) => (int) $id)->all();
            $actualizadas = Reserva::whereIn('id', $idsReserva)
                ->update(['estado' => 'inactiva']);

            return (int) $actualizadas;
        } catch (\Throwable $e) {
            error_log('ReservaModel::inactivarPendientesVencidas -> ' . $e->getMessage());
            return 0;
        }
    }
}
