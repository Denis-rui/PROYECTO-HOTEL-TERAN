<?php
namespace Models;

use Illuminate\Database\Capsule\Manager as DB;
use Helpers\ReservaHelper;

class ReporteOcupacionModel
{
    public function obtenerReser_EstadiaHab($idHabitacion)
    {
        try {
            $reserva = DB::table('reserva as r')
                ->join('reserva_habitacion as rh', 'rh.id_reserva', '=', 'r.id')
                ->where('rh.id_habitacion', (int) $idHabitacion)
                ->where('r.estado', 'en_estadia')
                ->select('r.*')
                ->first();

            return $reserva ? (array) $reserva : null;
        } catch (\Throwable $e) {
            error_log('Error en obtenerReser_EstadiaHab: ' . $e->getMessage());
            return null;
        }
    }

    public function calcularTotalReserva($idHabitacion, $checkIn, $checkOut)
    {
        try {
            $precio = (float) DB::table('habitacion as h')
                ->join('tipo_habitacion as t', 't.id', '=', 'h.id_tipo_habitacion')
                ->where('h.id', (int) $idHabitacion)
                ->value('t.precio_base');

            $dias = ReservaHelper::obtenerDiasEstadia($checkIn, $checkOut);
            return $dias * $precio;
        } catch (\Throwable $e) {
            error_log('Error en calcularTotalReserva: ' . $e->getMessage());
            return 0;
        }
    }

    public function validarDisponibilidadHabitacion($idHabitacion, $checkIn, $checkOut, $idReservaExcluir = null)
    {
        try {
            if (empty($idHabitacion) || empty($checkIn) || empty($checkOut)) {
                return ['disponible' => false, 'mensaje' => 'Parámetros incompletos para validar disponibilidad.'];
            }

            $query = DB::table('reserva_habitacion as rh')
                ->join('reserva as r', 'r.id', '=', 'rh.id_reserva')
                ->where('rh.id_habitacion', (int) $idHabitacion)
                ->where('rh.activo', 1)
                ->where(function ($q) {
                    $q->whereNull('rh.estado')
                      ->orWhere('rh.estado', 'activa');
                })
                ->whereIn('r.estado', ['pendiente', 'confirmada', 'checkin_realizado', 'en_estadia', 'checkout_pendiente'])
                ->where('rh.check_in', '<', $checkOut)
                ->where(function ($q) use ($checkIn) {
                    $q->whereNull('rh.check_out')
                      ->orWhere('rh.check_out', '>', $checkIn);
                });

            if ($idReservaExcluir) {
                $query->where('r.id', '!=', (int) $idReservaExcluir);
            }

            $count = (int) $query->count();

            if ($count > 0) {
                return ['disponible' => false, 'mensaje' => 'La habitación no está disponible en el rango indicado.'];
            }

            return ['disponible' => true, 'mensaje' => 'Disponible'];
        } catch (\Throwable $e) {
            return ['disponible' => false, 'mensaje' => 'Error al validar disponibilidad: ' . $e->getMessage()];
        }
    }

    public function obtenerDisponiblesPorRango($checkIn, $checkOut, $tipo = null, $piso = null, array $referencia = [])
    {
        try {
            $habitacionesOcupadas = DB::table('reserva_habitacion as rh')
                ->join('reserva as r', 'r.id', '=', 'rh.id_reserva')
                ->where('rh.activo', 1)
                ->where(function ($q) {
                    $q->whereNull('rh.estado')
                      ->orWhere('rh.estado', 'activa');
                })
                ->whereIn('r.estado', ['pendiente', 'confirmada', 'checkin_realizado', 'en_estadia', 'checkout_pendiente'])
                ->where('rh.check_in', '<', $checkOut)
                ->where('rh.check_out', '>', $checkIn)
                ->distinct()
                ->pluck('rh.id_habitacion')
                ->toArray();

            $query = DB::table('habitacion as h')
                ->join('tipo_habitacion as t', 't.id', '=', 'h.id_tipo_habitacion')
                ->where('h.activo', 1)
                ->where('h.estado', '!=', 'Mantenimiento')
                ->whereNotIn('h.id', $habitacionesOcupadas)
                ->select([
                    'h.id',
                    'h.numero_habitacion',
                    'h.piso',
                    'h.id_tipo_habitacion',
                    DB::raw('t.precio_base as precio'),
                    'h.capacidad',
                    't.tipo as tipo_nombre',
                ]);

            if ($tipo) {
                $query->where('h.id_tipo_habitacion', $tipo);
            }

            if ($piso) {
                $query->where('h.piso', (int) $piso);
            }

            $precioReferencia = is_numeric($referencia['precio'] ?? null) ? (float) $referencia['precio'] : null;
            $tipoReferencia = !empty($referencia['tipo']) ? (int) $referencia['tipo'] : null;
            $pisoReferencia = !empty($referencia['piso']) ? (int) $referencia['piso'] : null;

            if ($precioReferencia !== null) {
                $query->orderByRaw('CASE WHEN t.precio_base = ? THEN 0 WHEN t.precio_base > ? THEN 1 ELSE 2 END', [$precioReferencia, $precioReferencia])
                    ->orderByRaw('ABS(t.precio_base - ?)', [$precioReferencia]);
            }

            if ($tipoReferencia) {
                $query->orderByRaw('CASE WHEN h.id_tipo_habitacion = ? THEN 0 ELSE 1 END', [$tipoReferencia]);
            }

            if ($pisoReferencia) {
                $query->orderByRaw('CASE WHEN h.piso = ? THEN 0 ELSE 1 END', [$pisoReferencia]);
            }

            return $query->orderBy('h.piso', 'asc')
                ->orderBy('h.numero_habitacion', 'asc')
                ->get()
                ->map(function ($item) {
                    return (array) $item;
                })
                ->toArray();
        } catch (\Throwable $e) {
            error_log('Error en obtenerDisponiblesPorRango: ' . $e->getMessage());
            return [];
        }
    }

    public function obtenerReservaBloqueante(int $idHabitacion)
    {
        try {
            $res = DB::table('reserva_habitacion as rh')
                ->join('reserva as r', 'r.id', '=', 'rh.id_reserva')
                ->where('rh.id_habitacion', $idHabitacion)
                ->where('rh.activo', 1)
                ->whereIn('r.estado', ['checkin_realizado', 'en_estadia', 'checkout_pendiente'])
                ->where(function ($q) {
                    $q->whereNull('rh.check_out')
                      ->orWhere('rh.check_out', '>', DB::raw('NOW()'));
                })
                ->select(['r.id as id_reserva', 'r.estado as estado_reserva', 'rh.check_in', 'rh.check_out'])
                ->orderBy('rh.check_out', 'asc')
                ->first();

            return $res ? (array) $res : null;
        } catch (\Throwable $e) {
            error_log('Error obtenerReservaBloqueante: ' . $e->getMessage());
            return null;
        }
    }
}
