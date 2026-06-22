<?php

namespace Helpers;

use Models\HabitacionModel;
use Illuminate\Database\Capsule\Manager as DB;

class FormatearReservas
{
    public static function formatear($reserva): array
    {
        $cliente = $reserva->cliente;
        $usuario = $reserva->usuario ?? null;
        $reservaHabitacion = $reserva->reservaHabitacion;
        $habitacionesRelacionadas = is_iterable($reservaHabitacion) ? $reservaHabitacion : [$reservaHabitacion];
        $habitaciones = [];
        $habitacionesHistorial = [];
        $habitacionModel = new HabitacionModel();
        foreach ($habitacionesRelacionadas as $itemHabitacion) {
            if (!$itemHabitacion) {
                continue;
            }
            $habitacion = $itemHabitacion->habitacion ?? null;
            if ($habitacion) {
                $info = $habitacionModel->obtenerPorId((int) $habitacion->id) ?? [];
                $precioHabit = (float) ($info['precio'] ?? 0);
                $precioAplicado = (float) ($itemHabitacion->precio_aplicado ?? 0);
                if ($precioAplicado <= 0) {
                    $precioAplicado = $precioHabit;
                }
                $datosHabitacion = ['reserva_habitacion_id' => $itemHabitacion->id ?? null, 'id' => $habitacion->id, 'numero_habitacion' => $habitacion->numero_habitacion, 'piso' => $habitacion->piso, 'tipo_nombre' => $habitacion->tipo_nombre ?? '', 'precio' => $precioHabit, 'precio_aplicado' => $precioAplicado, 'subtotal' => (float) ($itemHabitacion->subtotal ?? 0), 'tipo_asignacion' => $itemHabitacion->tipo_asignacion ?? 'original', 'estado_asignacion' => $itemHabitacion->estado ?? 'activa', 'motivo_cambio' => $itemHabitacion->motivo_cambio ?? null, 'check_in' => $itemHabitacion->check_in ?? null, 'check_out' => $itemHabitacion->check_out ?? null, 'fecha_movimiento' => $itemHabitacion->fecha_movimiento ?? null,];
                $habitacionesHistorial[] = $datosHabitacion;
                if (($itemHabitacion->estado ?? 'activa') === 'activa') {
                    $habitaciones[] = $datosHabitacion;
                }
            }
        }
        $pagos = [];
        foreach (($reserva->pagos ?? []) as $pago) {
            $pagos[] = ['id' => $pago->id, 'monto' => (float) $pago->monto, 'fecha_pago' => $pago->fecha_pago, 'id_metodo_pago' => $pago->id_metodo_pago ?? null, 'descripcion' => $pago->descripcion ?? '',];
        }
        $relacionPrincipal = null;
        foreach ($habitacionesRelacionadas as $itemHabitacion) {
            if ($itemHabitacion && (($itemHabitacion->estado ?? 'activa') === 'activa')) {
                $relacionPrincipal = $itemHabitacion;
                break;
            }
        }
        $habitacionPrincipal = $habitaciones[0] ?? null;
        $totalPagado = (float) ($reserva->pagos->sum('monto') ?? 0);
        $checkInProgramado = $reserva->check_in ?? ($relacionPrincipal->check_in ?? null);
        $checkOutProgramado = $reserva->check_out ?? ($relacionPrincipal->check_out ?? null);
        $checkIn = $reserva->checkin_real ?? $checkInProgramado;
        $checkOut = $reserva->checkout_real ?? $checkOutProgramado;
        $estado = $reserva->estado ?? '';
        $total = (float) ($reserva->total ?? 0);
        $cargoTarde = (float) ($reserva->cargo_checkout_tarde ?? 0);
        $saldoPendiente = $total + $cargoTarde - $totalPagado;
        $minutosCheckoutVencido = 0;
        $checkoutHoy = false;
        $zonaHoraria = new \DateTimeZone('America/Lima');
        $ahora = new \DateTimeImmutable('now', $zonaHoraria);
        $checkoutProgramadoFecha = null;
        if ($checkOut) {
            try {
                $checkoutProgramadoFecha = new \DateTimeImmutable((string) $checkOut, $zonaHoraria);
            } catch (\Exception $e) {
                $checkoutProgramadoFecha = null;
            }
        }
        if (in_array($estado, ['en_estadia', 'checkout_pendiente'], true) && $checkoutProgramadoFecha && empty($reserva->checkout_real)) {
            if ($checkoutProgramadoFecha < $ahora) {
                $minutosCheckoutVencido = (int) floor(($ahora->getTimestamp() - $checkoutProgramadoFecha->getTimestamp()) / 60);
            } elseif ($checkoutProgramadoFecha->format('Y-m-d') === $ahora->format('Y-m-d') && (int) $ahora->format('H') < 12) {
                $checkoutHoy = true;
            }
        }
        return [
            'id' => $reserva->id,
            'codigo_reserva' => $reserva->codigo_reserva,
            'fecha_creacion' => $reserva->fecha_creacion ?? null,
            'id_cliente' => $reserva->id_cliente,
            'cliente' => $cliente->nombre_completo ?? '',
            'documento' => $cliente->documento ?? '',
            'id_tipo_documento' => $cliente->id_tipo_documento ?? null,
            'documento_tipo_nombre' => self::obtenerNombreTipoDocumento($cliente),
            'correo_electronico' => $cliente->correo_electronico ?? '',
            'cliente_direccion' => $cliente->procedencia ?? '',
            'telefono' => $cliente->telefono ?? '',
            'procedencia' => $cliente->procedencia ?? '',
            'id_usuario' => $reserva->id_usuario ?? null,
            'usuario' => $usuario->nombre_completo ?? $usuario->nombre_usuario ?? '',
            'usuario_nombre' => $usuario->nombre_completo ?? $usuario->nombre_usuario ?? '',
            'id_habitacion' => $habitacionPrincipal['id'] ?? null,
            'habitacion' => $habitacionPrincipal ? 'Hab. ' . $habitacionPrincipal['numero_habitacion'] . ' - Piso ' . $habitacionPrincipal['piso'] : '',
            'habitaciones' => $habitaciones,
            'habitaciones_historial' => $habitacionesHistorial,
            'piso' => $habitacionPrincipal['piso'] ?? null,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'check_in_programado' => $checkInProgramado,
            'check_out_programado' => $checkOutProgramado,
            'checkin_real' => $reserva->checkin_real ?? null,
            'checkout_real' => $reserva->checkout_real ?? null,
            'minutos_demora_checkout' => $reserva->minutos_demora_checkout ?? 0,
            'cargo_checkout_tarde' => $cargoTarde,
            'total' => $total,
            'estado' => $estado,
            'observaciones' => $reserva->observaciones ?? '',
            'total_pagado' => $totalPagado,
            'saldo_pendiente' => $saldoPendiente,
            'porcentaje_pago' => $total + $cargoTarde > 0 ? round(($totalPagado / ($total + $cargoTarde)) * 100, 0) : 0,
            'pagos' => $pagos,
            'minutos_checkout_vencido' => $minutosCheckoutVencido,
            'checkout_hoy' => $checkoutHoy,
        ];
    } // revisar aca luego 
    private static function obtenerNombreTipoDocumento($cliente): ?string
    {
        try {
            if (empty($cliente->id_tipo_documento)) {
                return null;
            }
            return DB::table('tipo_documento')->where('id', $cliente->id_tipo_documento)->value('nombre');
        } catch (\Throwable $e) {
            return null;
        }
    }
}
