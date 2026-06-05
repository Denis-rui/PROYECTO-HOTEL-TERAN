<?php
namespace Models;

use Illuminate\Database\Capsule\Manager as DB;
use Models\Entities\Notificacion;

class NotificacionModel
{
    public function crear($tipo, $titulo, $mensaje, $idReserva = null, $idHabitacion = null, $idCliente = null, $prioridad = 'media')
    {
        $datosIdentificadores = [
            'tipo' => $tipo,
            'id_reserva' => $idReserva ? (int) $idReserva : null,
            'id_habitacion' => $idHabitacion ? (int) $idHabitacion : null,
            'leida' => 0,
        ];

        $notificacion = Notificacion::query()
            ->where($datosIdentificadores)
            ->first();

        if (!$notificacion) {
            $notificacion = new Notificacion();
        }

        $notificacion->fill([
            'tipo' => $tipo,
            'titulo' => $titulo,
            'mensaje' => $mensaje,
            'id_reserva' => $idReserva ? (int) $idReserva : null,
            'id_habitacion' => $idHabitacion ? (int) $idHabitacion : null,
            'id_cliente' => $idCliente ? (int) $idCliente : null,
            'leida' => 0,
            'prioridad' => $prioridad,
        ]);

        if (!$notificacion->exists) {
            $notificacion->fecha_creacion = date('Y-m-d H:i:s');
        }

        return $notificacion->save();
    }

    public function obtenerPendientes($limite = 30)
    {
        $limite = max(1, (int) $limite);
        $clavesActivasCheckout = DB::table('reserva_habitacion as rh')
            ->join('reserva as r', 'r.id', '=', 'rh.id_reserva')
            ->whereIn('r.estado', ['en_estadia', 'checkout_pendiente'])
            ->where('rh.activo', 1)
            ->select(['r.id as id_reserva', 'rh.id_habitacion'])
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->id_reserva . '|' . $item->id_habitacion => true];
            })
            ->all();

        $notificaciones = Notificacion::query()
            ->where('leida', 0)
            ->orderByDesc('fecha_creacion')
            ->limit(max(200, $limite * 10))
            ->get()
            ->all();

        $resultado = [];
        $clavesAgregadas = [];

        foreach ($notificaciones as $item) {
            $tipo = (string) $item->tipo;

            if (strpos($tipo, 'checkout_') === 0) {
                $claveCheckout = (int) $item->id_reserva . '|' . (int) $item->id_habitacion;
                if (!isset($clavesActivasCheckout[$claveCheckout])) {
                    continue;
                }
            }

            $claveNotificacion = $tipo
                . '|' . (int) $item->id_reserva
                . '|' . (int) $item->id_habitacion;

            if (isset($clavesAgregadas[$claveNotificacion])) {
                continue;
            }

            $clavesAgregadas[$claveNotificacion] = true;
            $resultado[] = $item->toArray();

            if (count($resultado) >= $limite) {
                break;
            }
        }

        return $resultado;
    }

    public function obtenerCheckoutCheckout()
    {
        return $this->obtenerPendientes();
    }

    public function obtenerNotificacionesCheckout()
    {
        $reservas = DB::table('reserva_habitacion as rh')
            ->join('reserva as r', 'r.id', '=', 'rh.id_reserva')
            ->join('cliente as c', 'c.id', '=', 'r.id_cliente')
            ->join('habitacion as h', 'h.id', '=', 'rh.id_habitacion')
            ->whereIn('r.estado', ['en_estadia', 'checkout_pendiente'])
            ->whereNotNull('rh.check_out')
            ->where('rh.activo', 1)
            ->orderBy('rh.check_out', 'asc')
            ->selectRaw("r.id AS id_reserva, c.id AS id_cliente, c.nombre_completo AS cliente, h.id AS id_habitacion, h.numero_habitacion AS habitacion, rh.check_out, TIMESTAMPDIFF(MINUTE, NOW(), rh.check_out) AS minutos_faltantes, CASE WHEN NOW() > rh.check_out THEN TIMESTAMPDIFF(MINUTE, rh.check_out, NOW()) ELSE 0 END AS minutos_excedidos")
            ->get()
            ->map(function ($item) {
                return (array) $item;
            })
            ->toArray();

        $notificaciones = $this->obtenerPendientes(30);

        return [
            'proximos' => array_values(array_filter($reservas, fn($r) => (int) $r['minutos_faltantes'] >= 0 && (int) $r['minutos_faltantes'] <= 120)),
            'vencidos' => array_values(array_filter($reservas, fn($r) => (int) $r['minutos_excedidos'] > 0)),
            'notificaciones' => $notificaciones,
        ];
    }
}
