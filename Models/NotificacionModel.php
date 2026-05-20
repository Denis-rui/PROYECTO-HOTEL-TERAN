<?php
namespace Models;

use Illuminate\Database\Capsule\Manager as DB;
use Libraries\Core\Model;
use Models\Entities\Notificacion;

class NotificacionModel extends Model
{
    public function crear($tipo, $titulo, $mensaje, $idReserva = null, $idHabitacion = null, $idCliente = null, $prioridad = 'media')
    {
        return Notificacion::create([
            'tipo' => $tipo,
            'titulo' => $titulo,
            'mensaje' => $mensaje,
            'id_reserva' => $idReserva ? (int) $idReserva : null,
            'id_habitacion' => $idHabitacion ? (int) $idHabitacion : null,
            'id_cliente' => $idCliente ? (int) $idCliente : null,
            'leida' => 0,
            'fecha_creacion' => date('Y-m-d H:i:s'),
            'prioridad' => $prioridad,
        ]) !== null;
    }

    public function obtenerPendientes($limite = 30)
    {
        return Notificacion::query()
            ->where('leida', 0)
            ->orderByDesc('fecha_creacion')
            ->limit((int) $limite)
            ->get()
            ->map(function ($item) {
                return $item->toArray();
            })
            ->toArray();
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

        foreach ($reservas as $reserva) {
            $faltan = (int) $reserva['minutos_faltantes'];
            $excede = (int) $reserva['minutos_excedidos'];

            if ($excede > 0) {
                $this->crear('checkout_vencido', 'Checkout vencido', 'La reserva de ' . $reserva['cliente'] . ' excedió el checkout programado.', (int) $reserva['id_reserva'], (int) $reserva['id_habitacion'], (int) $reserva['id_cliente'], 'critica');
            } elseif ($faltan <= 15 && $faltan >= 0) {
                $this->crear('checkout_proximo_15m', 'Checkout en 15 minutos', 'La habitación ' . $reserva['habitacion'] . ' tiene checkout próximo.', (int) $reserva['id_reserva'], (int) $reserva['id_habitacion'], (int) $reserva['id_cliente'], 'alta');
            } elseif ($faltan <= 60 && $faltan > 15) {
                $this->crear('checkout_proximo_1h', 'Checkout en 1 hora', 'La habitación ' . $reserva['habitacion'] . ' tiene checkout en menos de 1 hora.', (int) $reserva['id_reserva'], (int) $reserva['id_habitacion'], (int) $reserva['id_cliente'], 'media');
            } elseif ($faltan <= 120 && $faltan > 60) {
                $this->crear('checkout_proximo_2h', 'Checkout en 2 horas', 'La habitación ' . $reserva['habitacion'] . ' tiene checkout en menos de 2 horas.', (int) $reserva['id_reserva'], (int) $reserva['id_habitacion'], (int) $reserva['id_cliente'], 'media');
            }
        }

        $notificaciones = $this->obtenerPendientes(30);

        return [
            'proximos' => array_values(array_filter($reservas, fn($r) => (int) $r['minutos_faltantes'] >= 0 && (int) $r['minutos_faltantes'] <= 120)),
            'vencidos' => array_values(array_filter($reservas, fn($r) => (int) $r['minutos_excedidos'] > 0)),
            'notificaciones' => $notificaciones,
        ];
    }
}