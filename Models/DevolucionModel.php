<?php

namespace Models;

use Models\Entities\Devolucion;

class DevolucionModel
{
    public function listar($busqueda = '')
    {
        $query = Devolucion::query()
            ->with(['reserva.cliente'])
            ->orderByDesc('id');

        if (!empty($busqueda)) {
            $query->where(function ($q) use ($busqueda) {
                $q->where('id_reserva', 'like', "%$busqueda%")
                    ->orWhereHas('reserva.cliente', function ($q2) use ($busqueda) {
                        $q2->whereRaw(
                            "CONCAT(COALESCE(nombres, ''), ' ', COALESCE(apellido_paterno, ''), ' ', COALESCE(apellido_materno, '')) LIKE ?",
                            ['%' . $busqueda . '%']
                        );
                    });
            });
        }

        return $query->get()->map(function ($d) {

            $cliente = $d->reserva?->cliente;

            // Construimos el string del nombre del cliente sobre la marcha
            $nombreCliente = '—';
            if ($cliente) {
                $nombreCliente = trim(
                    ($cliente->nombres ?? '') . ' ' .
                        ($cliente->apellido_paterno ?? '') . ' ' .
                        ($cliente->apellido_materno ?? '')
                );
                // Por si acaso el registro vino puramente con espacios vacíos
                if (empty($nombreCliente)) {
                    $nombreCliente = '—';
                }
            }
            return [
                'id' => $d->id,
                'id_reserva' => $d->id_reserva,
                'cliente' => $nombreCliente,
                'fecha_cancelacion' => $d->fecha_cancelacion,
                'fecha_inicio' => $d->fecha_inicio,
                'fecha_prevista' => $d->fecha_prevista,
                'dias_usados' => $d->dias_usados,
                'dias_no_usados' => $d->dias_no_usados,
                'total_no_ocupado' => $d->total_no_ocupado,
                'porcentaje_penalidad' => $d->porcentaje_penalidad,
                'monto_penalidad' => $d->monto_penalidad,
                'monto_devuelto' => $d->monto_devuelto,
            ];
        })->toArray();
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
