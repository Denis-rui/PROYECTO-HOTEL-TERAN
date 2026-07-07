<?php

namespace Models;

use Models\Entities\Devolucion;

class DevolucionModel
{
    public function obtenerDevolucionesDataTable(array $parametros): array
    {
        try {
            $inicio = max(0, (int) ($parametros['start'] ?? 0));
            $cantidad = (int) ($parametros['length'] ?? 30);
            $cantidad = $cantidad > 0 ? min($cantidad, 100) : 30;

            $busquedaDataTable = trim((string) ($parametros['search']['value'] ?? ''));
            $busquedaPropia = trim((string) ($parametros['busqueda'] ?? ''));
            $busqueda = $busquedaPropia !== '' ? $busquedaPropia : $busquedaDataTable;
            $fechaDesde = $this->normalizarFechaFiltro($parametros['fecha_desde'] ?? '');
            $fechaHasta = $this->normalizarFechaFiltro($parametros['fecha_hasta'] ?? '');

            $queryTotal = $this->crearConsultaDevolucionesDataTable('');
            $total = (clone $queryTotal)->count();

            $queryFiltrada = $this->crearConsultaDevolucionesDataTable($busqueda, $fechaDesde, $fechaHasta);
            $filtrados = (clone $queryFiltrada)->count();

            $this->aplicarOrdenDataTable($queryFiltrada, $parametros);

            $items = $queryFiltrada
                ->skip($inicio)
                ->take($cantidad)
                ->get()
                ->map(fn($devolucion) => $this->formatearDevolucionDataTable($devolucion))
                ->values()
                ->all();

            return [
                'items' => $items,
                'total' => (int) $total,
                'filtrados' => (int) $filtrados,
            ];
        } catch (\Throwable $e) {
            error_log('DevolucionModel::obtenerDevolucionesDataTable -> ' . $e->getMessage());
            return [
                'items' => [],
                'total' => 0,
                'filtrados' => 0,
                'error' => 'No se pudieron obtener las devoluciones para DataTables.',
            ];
        }
    }

    private function crearConsultaDevolucionesDataTable(string $busqueda, string $fechaDesde = '', string $fechaHasta = '')
    {
        $query = Devolucion::query()
            ->leftJoin('reserva as r', 'r.id', '=', 'devolucion.id_reserva')
            ->leftJoin('cliente as c', 'c.id', '=', 'r.id_cliente')
            ->select('devolucion.*')
            ->selectRaw("TRIM(CONCAT_WS(' ', NULLIF(c.nombres, ''), NULLIF(c.apellido_paterno, ''), NULLIF(c.apellido_materno, ''))) as cliente_nombre_orden");

        if ($busqueda !== '') {
            $query->where(function ($q) use ($busqueda) {
                $q->where('devolucion.id', 'like', '%' . $busqueda . '%')
                    ->orWhere('devolucion.id_reserva', 'like', '%' . $busqueda . '%')
                    ->orWhereRaw(
                        "CONCAT(COALESCE(c.nombres, ''), ' ', COALESCE(c.apellido_paterno, ''), ' ', COALESCE(c.apellido_materno, '')) LIKE ?",
                        ['%' . $busqueda . '%']
                    )
                    ->orWhere('c.documento', 'like', '%' . $busqueda . '%')
                    ->orWhere('c.ruc', 'like', '%' . $busqueda . '%');
            });
        }

        if ($fechaDesde !== '') {
            $query->where('devolucion.fecha_cancelacion', '>=', $fechaDesde . ' 00:00:00');
        }

        if ($fechaHasta !== '') {
            $query->where('devolucion.fecha_cancelacion', '<=', $fechaHasta . ' 23:59:59');
        }

        return $query;
    }

    private function normalizarFechaFiltro($fecha): string
    {
        $fecha = trim((string) $fecha);

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            return '';
        }

        return $fecha;
    }

    private function aplicarOrdenDataTable($query, array $parametros): void
    {
        $indiceColumna = (int) ($parametros['order'][0]['column'] ?? 0);
        $direccion = strtolower((string) ($parametros['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        $columnasOrdenables = [
            0 => 'devolucion.id',
            1 => 'devolucion.id_reserva',
            2 => 'cliente_nombre_orden',
            3 => 'devolucion.fecha_inicio',
            4 => 'devolucion.fecha_prevista',
            5 => 'devolucion.fecha_cancelacion',
            6 => 'devolucion.dias_usados',
            7 => 'devolucion.dias_no_usados',
            8 => 'devolucion.total_no_ocupado',
            9 => 'devolucion.porcentaje_penalidad',
            10 => 'devolucion.monto_penalidad',
            11 => 'devolucion.monto_devuelto',
        ];

        $columna = $columnasOrdenables[$indiceColumna] ?? 'devolucion.id';
        $query->orderBy($columna, $direccion)
            ->orderByDesc('devolucion.id');
    }

    private function formatearDevolucionDataTable($devolucion): array
    {
        $cliente = trim((string) ($devolucion->cliente_nombre_orden ?? ''));

        return [
            'id' => (int) $devolucion->id,
            'id_reserva' => (int) $devolucion->id_reserva,
            'cliente' => $cliente !== '' ? $cliente : '--',
            'fecha_cancelacion' => $devolucion->fecha_cancelacion,
            'fecha_inicio' => $devolucion->fecha_inicio,
            'fecha_prevista' => $devolucion->fecha_prevista,
            'dias_usados' => (int) $devolucion->dias_usados,
            'dias_no_usados' => (int) $devolucion->dias_no_usados,
            'total_no_ocupado' => (float) $devolucion->total_no_ocupado,
            'porcentaje_penalidad' => (float) $devolucion->porcentaje_penalidad,
            'monto_penalidad' => (float) $devolucion->monto_penalidad,
            'monto_devuelto' => (float) $devolucion->monto_devuelto,
        ];
    }

    public function obtenerDevolucion(int $id)
    {
        return Devolucion::find($id);
    }

    public function guardar(int $idReserva, array $datosGuardar)
    {
        return Devolucion::updateOrCreate(['id_reserva' => $idReserva], $datosGuardar) !== null;
    }

    public function actualizar(int $id, array $datos)
    {
        return Devolucion::where('id', $id)->update($datos) !== false;
    }

    public function eliminar(int $id)
    {
        return Devolucion::destroy($id) > 0;
    }
}
