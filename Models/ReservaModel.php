<?php

namespace Models;

use Illuminate\Database\Capsule\Manager as DB;
use Models\Entities\Devolucion;
use Models\Entities\Habitacion;
use Models\Entities\Hotel;
use Models\HabitacionModel;
use Models\Entities\Reserva;
use Models\Entities\ReservaHabitacion;
use Models\PagoModel;
use Models\NotificacionModel;
use Models\ReporteOcupacionModel;
use Models\ReservaNuevaModel;
use Helpers\ReservaHelper;

use function Illuminate\Support\now;

class ReservaModel
{
    private function fechaHoraActual(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('America/Lima')))->format('Y-m-d H:i:s');
    }

    public function obtenerReservas(array $filtros = [], int $limite = 30)
    {

        try {
            $busqueda = trim((string) ($filtros['busqueda'] ?? ''));
            $estado = strtolower(trim((string) ($filtros['estado'] ?? '')));
            $estadosPermitidos = ['confirmada', 'en_estadia', 'checkout_realizado', 'checkout_pendiente', 'ausente', 'cancelada'];
            $limite = max(30, $limite);

            $query = Reserva::with(['cliente', 'usuario', 'pagos', 'reservaHabitacion.habitacion']);

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
                ->orderByRaw(
                    "
                    CASE
                        WHEN LOWER(reserva.estado) IN ('en_estadia', 'checkout_pendiente', 'ausente') THEN 0
                        WHEN LOWER(reserva.estado) = 'confirmada' THEN 1
                        WHEN LOWER(reserva.estado) = 'checkout_realizado' THEN 3
                        ELSE 2
                    END ASC
                    "
                )
                ->orderByRaw('primer_check_in IS NULL ASC')
                ->orderByRaw('primer_check_in ASC')
                ->orderByDesc('reserva.id')
                ->limit($limite)
                ->get()
                ->map(fn($reserva) => $this->formatearReserva($reserva))
                ->values()
                ->all();

            return [
                'items' => $reservas,
                'total' => (int) $total,
                'mostrados' => count($reservas),
                'hay_mas' => $total > count($reservas),
            ];
        } catch (\Throwable $e) {
            return [
                'items' => ["Error" . $e->getMessage()],
                'total' => 0,
                'mostrados' => 0,
                'hay_mas' => false,
            ];
        }
    }
    // se queda sirve par ala edicion y pagar
    public function obtenerReservaPorId($idReserva)
    {
        $reserva = Reserva::with(['cliente', 'usuario', 'habitacion', 'pagos', 'reservaHabitacion'])
            ->find((int) $idReserva);

        return $reserva ? $this->formatearReserva($reserva) : null;
    }




    // este metodo hay que analizarlo, si lo dejamos aca o lo mandamos a otra parte
    private function formatearReserva($reserva)
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
                $info = $habitacionModel->obtenerPorId($habitacion->id) ?? [];
                $precioHabit = (float) ($info['precio'] ?? 0);
                $precioAplicado = (float) ($itemHabitacion->precio_aplicado ?? 0);
                if ($precioAplicado <= 0) {
                    $precioAplicado = $precioHabit;
                }

                $datosHabitacion = [
                    'reserva_habitacion_id' => $itemHabitacion->id ?? null,
                    'id' => $habitacion->id,
                    'numero_habitacion' => $habitacion->numero_habitacion,
                    'piso' => $habitacion->piso,
                    'tipo_nombre' => $habitacion->tipo_nombre ?? '',
                    'precio' => $precioHabit,
                    'precio_aplicado' => $precioAplicado,
                    'subtotal' => (float) ($itemHabitacion->subtotal ?? 0),
                    'tipo_asignacion' => $itemHabitacion->tipo_asignacion ?? 'original',
                    'estado_asignacion' => $itemHabitacion->estado ?? 'activa',
                    'motivo_cambio' => $itemHabitacion->motivo_cambio ?? null,
                    'check_in' => $itemHabitacion->check_in ?? null,
                    'check_out' => $itemHabitacion->check_out ?? null,
                    'fecha_movimiento' => $itemHabitacion->fecha_movimiento ?? null,
                ];
                $habitacionesHistorial[] = $datosHabitacion;
                if (($itemHabitacion->estado ?? 'activa') === 'activa') {
                    $habitaciones[] = $datosHabitacion;
                }
            }
        }

        $pagos = [];
        foreach (($reserva->pagos ?? []) as $pago) {
            $pagos[] = [
                'id' => $pago->id,
                'monto' => (float) $pago->monto,
                'fecha_pago' => $pago->fecha_pago,
                'id_metodo_pago' => $pago->id_metodo_pago ?? null,
                'descripcion' => $pago->descripcion ?? '',
            ];
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

        if (
            in_array($estado, ['en_estadia', 'checkout_pendiente'], true)
            && $checkoutProgramadoFecha
            && empty($reserva->checkout_real)
        ) {
            if ($checkoutProgramadoFecha < $ahora) {
                $minutosCheckoutVencido = (int) floor(($ahora->getTimestamp() - $checkoutProgramadoFecha->getTimestamp()) / 60);
            } elseif (
                $checkoutProgramadoFecha->format('Y-m-d') === $ahora->format('Y-m-d')
                && (int) $ahora->format('H') < 12
            ) {
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
            'documento_tipo_nombre' => (function() use ($cliente) {
                try {
                    if (empty($cliente->id_tipo_documento)) return null;
                    return DB::table('tipo_documento')->where('id', $cliente->id_tipo_documento)->value('nombre');
                } catch (\Throwable $e) {
                    return null;
                }
            })(),
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
    }

    // ahqy qeu analizarlo, me parece que se va a ir no se xd 
    public function actualizarEstadoReserva($idReserva, $nuevoEstado)
    {
        $estadoNormalizado = strtolower(trim((string) $nuevoEstado));
        $estadosPermitidos = ['confirmada', 'en_estadia', 'checkout_realizado', 'cancelada'];
        $ordenEstados = [
            'confirmada' => 1,
            'en_estadia' => 2,
            'checkout_realizado' => 3,
        ];

        if (!in_array($estadoNormalizado, $estadosPermitidos, true)) {
            return ['exito' => false, 'mensaje' => 'Estado no permitido.'];
        }

        try {
            $reserva = Reserva::with(['reservaHabitacion.habitacion'])->find((int) $idReserva);
            if (!$reserva) {
                return ['exito' => false, 'mensaje' => 'Reserva no encontrada.'];
            }

            $estadoActual = strtolower(trim((string) $reserva->estado));
            if (isset($ordenEstados[$estadoActual], $ordenEstados[$estadoNormalizado]) && $ordenEstados[$estadoNormalizado] < $ordenEstados[$estadoActual]) {
                return ['exito' => false, 'mensaje' => 'No se permite volver a un estado anterior.'];
            }

            $fechaHoraActual = $this->fechaHoraActual();
            $reserva->estado = $estadoNormalizado;
            if ($estadoNormalizado === 'en_estadia' && empty($reserva->checkin_real)) {
                $reserva->checkin_real = $fechaHoraActual;
            }
            if ($estadoNormalizado === 'checkout_realizado' && empty($reserva->checkout_real)) {
                $reserva->checkout_real = $fechaHoraActual;
            }
            $reserva->save();

            if ($estadoNormalizado === 'checkout_realizado') {
                $habitacionesRelacionadas = $reserva->reservaHabitacion ?? [];
                foreach ($habitacionesRelacionadas as $itemHabitacion) {
                    if (!$itemHabitacion || empty($itemHabitacion->id_habitacion)) {
                        continue;
                    }
                    if ((int) ($itemHabitacion->activo ?? 1) !== 1 || (($itemHabitacion->estado ?? 'activa') !== 'activa')) {
                        continue;
                    }

                    Habitacion::where('id', (int) $itemHabitacion->id_habitacion)->update([
                        'estado' => 'Mantenimiento',
                    ]);

                    $habitacionModel = new HabitacionModel();
                    $habitacionActual = $habitacionModel->obtenerPorId((int) $itemHabitacion->id_habitacion) ?? [];
                    $estadoAnterior = $itemHabitacion->habitacion->estado ?? 'Ocupada';
                    $habitacionModel->registrarHistorial(
                        (int) $itemHabitacion->id_habitacion,
                        (int) $idReserva,
                        $estadoAnterior,
                        'Mantenimiento',
                        null,
                        null,
                        'checkout_realizado',
                        'Habitación enviada a mantenimiento tras checkout realizado.'
                    );
                }
            }

            return [
                'exito' => true,
                'mensaje' => 'Estado de la reserva actualizado correctamente.',
                'reserva' => $this->obtenerReservaPorId($idReserva),
            ];
        } catch (\Throwable $e) {
            return ['exito' => false, 'mensaje' => 'Error al actualizar estado: ' . $e->getMessage()];
        }
    }


    // se va a modificar 
    public function cancelarReserva($idReserva, $motivo = '', $idUsuario = null)
    {
        try {
            $reservaActual = Reserva::with(['reservaHabitacion', 'pagos'])->find((int) $idReserva);
            if (!$reservaActual) {
                return ['exito' => false, 'mensaje' => 'Reserva no encontrada.'];
            }

            if (
                in_array($reservaActual->estado, ['cancelada', 'checkout_realizado'], true)
                || !empty($reservaActual->checkout_real)
            ) {
                return ['exito' => false, 'mensaje' => 'No se puede cancelar una reserva en este estado.'];
            }

            $calculo = (new CalculoDevolucionModel())->calcular((int) $idReserva);
            if (!($calculo['exito'] ?? false)) {
                return $calculo;
            }

            DB::connection()->beginTransaction();

            $reservaActual->estado = 'cancelada';
            $reservaActual->observaciones = trim(
                (string) ($reservaActual->observaciones ?? '')
                . "\nCancelada: " . $motivo
                . " (No reembolsable: S/ " . number_format((float) $calculo['monto_no_reembolsable'], 2)
                . "; devolución: S/ " . number_format((float) $calculo['monto_devuelto'], 2) . ")"
            );
            $reservaActual->save();

            $habitacionModel = new HabitacionModel();
            $estadoHabitacionDestino = !empty($reservaActual->checkin_real)
                ? 'Mantenimiento'
                : 'Disponible';

            foreach ($reservaActual->reservaHabitacion as $reservaHabitacion) {
                if (!$reservaHabitacion || empty($reservaHabitacion->id_habitacion)) {
                    continue;
                }
                if ((int) ($reservaHabitacion->activo ?? 1) !== 1 || (($reservaHabitacion->estado ?? 'activa') !== 'activa')) {
                    continue;
                }

                Habitacion::where('id', (int) $reservaHabitacion->id_habitacion)->update([
                    'estado' => $estadoHabitacionDestino,
                ]);

                $habitacionModel->registrarHistorial(
                    (int) $reservaHabitacion->id_habitacion,
                    (int) $idReserva,
                    'Ocupada',
                    $estadoHabitacionDestino,
                    null,
                    null,
                    'cancelar_reserva',
                    'Reserva cancelada. Monto no reembolsable: S/ ' . $calculo['monto_no_reembolsable']
                );
            }

            Devolucion::updateOrCreate(['id_reserva' => (int) $idReserva], [
                'fecha_cancelacion' => $calculo['fecha_cancelacion'],
                'fecha_inicio' => $calculo['fecha_inicio'],
                'fecha_prevista' => $calculo['fecha_prevista'],
                'dias_usados' => (int) $calculo['dias_usados'],
                'dias_no_usados' => (int) $calculo['dias_no_usados'],
                'total_no_ocupado' => (float) $calculo['total_no_ocupado'],
                'porcentaje_penalidad' => (float) $calculo['porcentaje_penalidad'],
                'monto_penalidad' => (float) $calculo['monto_penalidad'],
                'monto_devuelto' => (float) $calculo['monto_devuelto'],
                'id_reserva' => (int) $idReserva,
                'id_usuario' => $idUsuario ? (int) $idUsuario : ($_SESSION['id_usuario'] ?? null),
                'descripcion' => trim(
                    $motivo
                    . ' | Hospedaje: S/ ' . number_format((float) $calculo['monto_usado'], 2)
                    . ' | Documentado: S/ ' . number_format((float) $calculo['monto_documentado'], 2)
                    . ' | No reembolsable: S/ ' . number_format((float) $calculo['monto_no_reembolsable'], 2)
                ),
            ]);

            DB::connection()->commit();
            return [
                'exito' => true,
                'mensaje' => 'Reserva cancelada. Devolución: S/ ' . number_format((float) $calculo['monto_devuelto'], 2)
                    . '. Monto no reembolsable: S/ ' . number_format((float) $calculo['monto_no_reembolsable'], 2) . '.',
                'devolucion' => $calculo,
            ];
        } catch (\Exception $e) {
            $con = DB::connection();
            if ($con->getPdo()->inTransaction())
                $con->rollBack();
            return ['exito' => false, 'mensaje' => 'Error al cancelar reserva: ' . $e->getMessage()];
        }
    }

    public function confirmarCheckIn($idReserva, $idUsuario = null)
    {
        try {
            $reservaActual = Reserva::with(['reservaHabitacion.habitacion'])->find((int) $idReserva);
            if (!$reservaActual) {
                return ['exito' => false, 'mensaje' => 'Reserva no encontrada.'];
            }

            if ($reservaActual->estado !== 'confirmada') {
                return ['exito' => false, 'mensaje' => 'Solo se puede confirmar check-in de reservas confirmadas.'];
            }

            $reporteOcupacionModel = new ReporteOcupacionModel();
            $idHabitacionPrincipal = (int) ($reservaActual->reservaHabitacion->first()->id_habitacion ?? 0);
            $ocupada = $reporteOcupacionModel->obtenerReser_EstadiaHab($idHabitacionPrincipal);
            if ($ocupada && (int) $ocupada['id'] !== (int) $idReserva) {
                return ['exito' => false, 'mensaje' => 'La habitación está ocupada por otra reserva.'];
            }

            $habitacionModel = new HabitacionModel();

            DB::connection()->beginTransaction();

            $fechaCheckin = $this->fechaHoraActual();
            $reservaActual->estado = 'en_estadia';
            $reservaActual->checkin_real = $fechaCheckin;
            $reservaActual->save();

            foreach ($reservaActual->reservaHabitacion as $reservaHabitacion) {
                if (!$reservaHabitacion || empty($reservaHabitacion->id_habitacion)) {
                    continue;
                }

                $idHabitacion = (int) $reservaHabitacion->id_habitacion;
                Habitacion::where('id', $idHabitacion)->update([
                    'estado' => 'Ocupada',
                ]);

                $habitacionModel->registrarHistorial(
                    $idHabitacion,
                    (int) $idReserva,
                    'confirmada',
                    'Ocupada',
                    null,
                    null,
                    'check_in',
                    'Check-in manual confirmado',
                    $idUsuario
                );
            }

            DB::connection()->commit();
            return [
                'exito' => true,
                'mensaje' => 'Check-in confirmado correctamente.',
                'checkin_real' => $fechaCheckin,
                'reserva' => $this->obtenerReservaPorId($idReserva),
            ];
        } catch (\Exception $e) {
            $con = DB::connection();
            if ($con->getPdo()->inTransaction()) {
                $con->rollBack();
            }
            return ['exito' => false, 'mensaje' => 'Error al confirmar check-in: ' . $e->getMessage()];
        }
    }

    public function confirmarCheckout($idReserva, $idUsuario = null, $autorizarSaldo = false, $motivoAutorizacion = '')
    {
        try {
            $reservaActual = Reserva::with(['reservaHabitacion.habitacion', 'pagos'])->find((int) $idReserva);
            if (!$reservaActual) {
                return ['exito' => false, 'mensaje' => 'Reserva no encontrada.'];
            }

            if (!in_array($reservaActual->estado, ['en_estadia', 'checkout_pendiente'], true)) {
                return ['exito' => false, 'mensaje' => 'Solo se puede hacer checkout de reservas en estadía o checkout pendiente.'];
            }

            $checkOut = $reservaActual->reservaHabitacion->first()->check_out ?? null;
            $minutosDemora = max(0, (int) floor((time() - strtotime((string) $checkOut)) / 60));
            $cargoTarde = ReservaHelper::calcularCargoCheckoutTarde($minutosDemora, (float) $reservaActual->total);
            $saldoFinal = max(0, ((float) $reservaActual->total - (float) $reservaActual->pagos->sum('monto')) + $cargoTarde);
            $fechaCheckout = $this->fechaHoraActual();

            if ($saldoFinal > 0.01 && !$autorizarSaldo) {
                DB::connection()->beginTransaction();

                $reservaActual->minutos_demora_checkout = $minutosDemora;
                $reservaActual->cargo_checkout_tarde = $cargoTarde;
                $reservaActual->save();

                DB::connection()->commit();

                return [
                    'exito' => false,
                    'requiere_pago' => true,
                    'mensaje' => 'Existe saldo pendiente de S/ ' . number_format($saldoFinal, 2) . '. Registre el pago completo antes de confirmar el checkout.',
                    'saldo_pendiente' => round($saldoFinal, 2),
                    'cargo_checkout_tarde' => $cargoTarde,
                    'minutos_demora' => $minutosDemora,
                    'reserva' => $this->obtenerReservaPorId($idReserva),
                ];
            }

            DB::connection()->beginTransaction();

            $reservaActual->estado = 'checkout_realizado';
            $reservaActual->checkout_real = $fechaCheckout;
            $reservaActual->minutos_demora_checkout = $minutosDemora;
            $reservaActual->cargo_checkout_tarde = $cargoTarde;
            $reservaActual->observaciones = trim((string) ($reservaActual->observaciones ?? '') . "\n" . ($motivoAutorizacion ? 'Checkout autorizado: ' . $motivoAutorizacion : ''));
            $reservaActual->save();

            $habitacionModel = new HabitacionModel();
            foreach ($reservaActual->reservaHabitacion as $reservaHabitacion) {
                if (!$reservaHabitacion || empty($reservaHabitacion->id_habitacion)) {
                    continue;
                }
                if ((int) ($reservaHabitacion->activo ?? 1) !== 1 || (($reservaHabitacion->estado ?? 'activa') !== 'activa')) {
                    continue;
                }

                Habitacion::where('id', (int) $reservaHabitacion->id_habitacion)->update([
                    'estado' => 'En Limpieza',
                    'limpieza_inicio' => $fechaCheckout,
                ]);

                $habitacionModel->registrarHistorial((int) $reservaHabitacion->id_habitacion, (int) $idReserva, 'Ocupada', 'Mantenimiento', null, null, 'checkout', 'Checkout manual confirmado.', $idUsuario);
                $Notificacion = new NotificacionModel();
                $Notificacion->crear('habitacion_limpieza_pendiente', 'Habitación pendiente de limpieza', 'La habitación ' . ($reservaHabitacion->habitacion->numero_habitacion ?? '') . ' quedó en mantenimiento después del checkout.', (int) $idReserva, (int) $reservaHabitacion->id_habitacion, (int) $reservaActual->id_cliente, 'alta');
            }

            DB::connection()->commit();
            return [
                'exito' => true,
                'mensaje' => 'Checkout confirmado. La habitación quedó en mantenimiento hasta limpieza.',
                'checkout_real' => $fechaCheckout,
                'cargo_checkout_tarde' => $cargoTarde,
                'minutos_demora' => $minutosDemora,
                'reserva' => $this->obtenerReservaPorId($idReserva),
            ];
        } catch (\Exception $e) {
            $con = DB::connection();
            if ($con->getPdo()->inTransaction()) {
                $con->rollBack();
            }
            return ['exito' => false, 'mensaje' => 'Error al confirmar checkout: ' . $e->getMessage()];
        }
    }

    public function marcarAusente($idReserva, $idUsuario = null)
    {
        try {
            $reservaActual = Reserva::find((int) $idReserva);
            if (!$reservaActual) {
                return ['exito' => false, 'mensaje' => 'Reserva no encontrada.'];
            }

            if ($reservaActual->estado !== 'en_estadia') {
                return ['exito' => false, 'mensaje' => 'Solo se puede marcar ausente una reserva en estadía.'];
            }

            DB::connection()->beginTransaction();

            $reservaActual->estado = 'ausente';
            $reservaActual->save();

            DB::connection()->commit();

            return [
                'exito' => true,
                'mensaje' => 'Reserva marcada como ausente.',
                'reserva' => $this->obtenerReservaPorId($idReserva),
            ];
        } catch (\Throwable $e) {
            $con = DB::connection();
            if ($con->getPdo()->inTransaction()) {
                $con->rollBack();
            }
            return ['exito' => false, 'mensaje' => 'Error al marcar ausente: ' . $e->getMessage()];
        }
    }

    public function marcarRegreso($idReserva, $idUsuario = null)
    {
        try {
            $reservaActual = Reserva::find((int) $idReserva);
            if (!$reservaActual) {
                return ['exito' => false, 'mensaje' => 'Reserva no encontrada.'];
            }

            if ($reservaActual->estado !== 'ausente') {
                return ['exito' => false, 'mensaje' => 'Solo se puede marcar regreso de una reserva ausente.'];
            }

            DB::connection()->beginTransaction();

            $reservaActual->estado = 'en_estadia';
            $reservaActual->save();

            DB::connection()->commit();

            return [
                'exito' => true,
                'mensaje' => 'Reserva marcada como regreso a estadía.',
                'reserva' => $this->obtenerReservaPorId($idReserva),
            ];
        } catch (\Throwable $e) {
            $con = DB::connection();
            if ($con->getPdo()->inTransaction()) {
                $con->rollBack();
            }
            return ['exito' => false, 'mensaje' => 'Error al marcar regreso: ' . $e->getMessage()];
        }
    }

}
