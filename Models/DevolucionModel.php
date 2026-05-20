<?php
namespace Models;

use Libraries\Core\Model;
use Models\Entities\Devolucion;

class DevolucionModel extends Model
{
    protected $table = 'devolucion';

    public function __construct()
    {
        parent::__construct();
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
                'id'                   => $d->id,
                'id_reserva'           => $d->id_reserva,
                'cliente'              => $d->reserva?->cliente?->nombre_completo ?? '—',
                'fecha_cancelacion'    => $d->fecha_cancelacion,
                'dias_usados'          => $d->dias_usados,
                'dias_no_usados'       => $d->dias_no_usados,
                'total_no_ocupado'     => $d->total_no_ocupado,
                'porcentaje_penalidad' => $d->porcentaje_penalidad,
                'monto_penalidad'      => $d->monto_penalidad,
                'monto_devuelto'       => $d->monto_devuelto,
            ];
        })->toArray();
    }

    public function crear($data)
    {
        return Devolucion::create([
            'id_reserva'           => $data['id_reserva'],
            'fecha_cancelacion'    => $data['fecha_cancelacion'] ?? now(),
            'dias_usados'          => $data['dias_usados']          ?? 0,
            'dias_no_usados'       => $data['dias_no_usados']       ?? 0,
            'total_no_ocupado'     => $data['total_no_ocupado']     ?? 0,
            'porcentaje_penalidad' => $data['porcentaje_penalidad'] ?? 0,
            'monto_penalidad'      => $data['monto_penalidad']      ?? 0,
            'monto_devuelto'       => $data['monto_devuelto']       ?? 0,
        ]) !== null;
    }

    public function actualizar($data)
    {
        return Devolucion::where('id', $data['id'])->update([
            'id_reserva'           => $data['id_reserva'],
            'fecha_cancelacion'    => $data['fecha_cancelacion']    ?? now(),
            'dias_usados'          => $data['dias_usados']          ?? 0,
            'dias_no_usados'       => $data['dias_no_usados']       ?? 0,
            'total_no_ocupado'     => $data['total_no_ocupado']     ?? 0,
            'porcentaje_penalidad' => $data['porcentaje_penalidad'] ?? 0,
            'monto_penalidad'      => $data['monto_penalidad']      ?? 0,
            'monto_devuelto'       => $data['monto_devuelto']       ?? 0,
        ]) !== false;
    }

    public function eliminar($id)
    {
        return Devolucion::destroy($id) > 0;
    }
}
