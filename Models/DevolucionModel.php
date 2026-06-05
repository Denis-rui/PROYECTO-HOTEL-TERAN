<?php

namespace Models;

use Models\Entities\Devolucion;
use Models\Entities\Reserva;

use function Illuminate\Support\now;

class DevolucionModel
{
    private function usuarioActual()
    {
        return $_SESSION['id_usuario'] ?? null;
    }


    public function listar($busqueda = '')
    {
        $query = Devolucion::query()
            ->with(['reserva.cliente'])
            ->orderByDesc('id');

        if (!empty($busqueda)) {
            $query->where(function ($q) use ($busqueda) {
                $q->where('id_reserva', 'like', "%$busqueda%")
                    ->orWhereHas('reserva.cliente', function ($q2) use ($busqueda) {
                        $q2->where('nombre_completo', 'like', "%$busqueda%");
                    });
            });
        }

        return $query->get()->map(function ($d) {
            return [
                'id' => $d->id,
                'id_reserva' => $d->id_reserva,
                'cliente' => $d->reserva?->cliente?->nombre_completo ?? '—',
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

    public function crear($data)
    {
        $idReserva = (int) ($data['id_reserva'] ?? 0);
        $reserva = Reserva::find($idReserva);
        if (!$reserva || $reserva->estado !== 'cancelada') {
            return false;
        }
        if (!empty($reserva->checkout_real) || $reserva->estado === 'checkout_realizado') {
            return false;
        }

        $calculo = (new CalculoDevolucionModel())->calcular(
            $idReserva,
            $data['fecha_cancelacion'] ?? null
        );
        if (!($calculo['exito'] ?? false)) {
            return false;
        }

        return Devolucion::updateOrCreate(['id_reserva' => $idReserva], [
            'id_reserva' => $idReserva,
            'fecha_cancelacion' => $calculo['fecha_cancelacion'],
            'fecha_inicio' => $calculo['fecha_inicio'],
            'fecha_prevista' => $calculo['fecha_prevista'],
            'dias_usados' => $calculo['dias_usados'],
            'dias_no_usados' => $calculo['dias_no_usados'],
            'total_no_ocupado' => $calculo['total_no_ocupado'],
            'porcentaje_penalidad' => $calculo['porcentaje_penalidad'],
            'monto_penalidad' => $calculo['monto_penalidad'],
            'monto_devuelto' => $calculo['monto_devuelto'],
            'id_usuario' => $data['id_usuario'] ?? $this->usuarioActual(),
        ]) !== null;
    }

    public function actualizar($data)
    {
        $devolucion = Devolucion::find((int) ($data['id'] ?? 0));
        if (!$devolucion) {
            return false;
        }

        $calculo = (new CalculoDevolucionModel())->calcular(
            (int) $devolucion->id_reserva,
            $data['fecha_cancelacion'] ?? $devolucion->fecha_cancelacion
        );
        if (!($calculo['exito'] ?? false)) {
            return false;
        }

        return Devolucion::where('id', $data['id'])->update([
            'fecha_cancelacion' => $calculo['fecha_cancelacion'],
            'dias_usados' => $calculo['dias_usados'],
            'dias_no_usados' => $calculo['dias_no_usados'],
            'total_no_ocupado' => $calculo['total_no_ocupado'],
            'porcentaje_penalidad' => $calculo['porcentaje_penalidad'],
            'monto_penalidad' => $calculo['monto_penalidad'],
            'monto_devuelto' => $calculo['monto_devuelto'],
            'id_usuario' => $data['id_usuario'] ?? $this->usuarioActual(),
        ]) !== false;
    }

    public function eliminar($id)
    {
        return Devolucion::destroy($id) > 0;
    }
}
