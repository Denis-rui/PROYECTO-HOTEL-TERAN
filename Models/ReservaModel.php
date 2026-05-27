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

            $query = Reserva::with(['cliente', 'pagos', 'reservaHabitacion.habitacion']);

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
                        ->whereColumn('reserva_habitacion.id_reserva', 'reserva.id'),
                    'primer_check_in'
                );

            $total = (clone $query)->count();

            $reservas = $query
                ->orderByRaw(
                    "
                    CASE
                        WHEN LOWER(reserva.estado) = 'ausente' THEN 0
                        WHEN LOWER(reserva.estado) = 'en_estadia' THEN 1
                        WHEN DATE(primer_check_in) = CURDATE() THEN 2
                        WHEN DATE(primer_check_in) > CURDATE() THEN 3
                        ELSE 4
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
        $reserva = Reserva::with(['cliente', 'habitacion', 'pagos', 'reservaHabitacion'])
            ->find((int) $idReserva);

        return $reserva ? $this->formatearReserva($reserva) : null;
    }




    // este metodo hay que analizarlo, si lo dejamos aca o lo mandamos a otra parte
    private function formatearReserva($reserva)
    {

        $cliente = $reserva->cliente;

        $reservaHabitacion = $reserva->reservaHabitacion;
        $habitacionesRelacionadas = is_iterable($reservaHabitacion) ? $reservaHabitacion : [$reservaHabitacion];
        $habitaciones = [];
        foreach ($habitacionesRelacionadas as $itemHabitacion) {
            if (!$itemHabitacion) {
                continue;
            }

            $habitacion = $itemHabitacion->habitacion ?? null;
            if ($habitacion) {
                $habitacionModel = new HabitacionModel();
                $info = $habitacionModel->obtenerPorId($habitacion->id) ?? [];
                $precioHabit = (float) ($info['precio'] ?? 0);
                $habitaciones[] = [
                    'id' => $habitacion->id,
                    'numero_habitacion' => $habitacion->numero_habitacion,
                    'piso' => $habitacion->piso,
                    'tipo_nombre' => $habitacion->tipo_nombre ?? '',
                    'precio' => $precioHabit,
                ];
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

        $habitacionPrincipal = $habitaciones[0] ?? null;
        $totalPagado = (float) ($reserva->pagos->sum('monto') ?? 0);
        $checkInProgramado = $reserva->check_in ?? ($habitacionesRelacionadas[0]->check_in ?? null);
        $checkOutProgramado = $reserva->check_out ?? ($habitacionesRelacionadas[0]->check_out ?? null);
        $checkIn = $reserva->checkin_real ?? $checkInProgramado;
        $checkOut = $reserva->checkout_real ?? $checkOutProgramado;
        $estado = $reserva->estado ?? '';
        $total = (float) ($reserva->total ?? 0);
        $cargoTarde = (float) ($reserva->cargo_checkout_tarde ?? 0);
        $saldoPendiente = $total + $cargoTarde - $totalPagado;

        $minutosCheckoutVencido = 0;
        if (
            in_array($estado, ['en_estadia', 'checkout_pendiente'], true)
            && $checkOut
            && strtotime((string) $checkOut) < time()
            && empty($reserva->checkout_real)
        ) {
            $minutosCheckoutVencido = (int) floor((time() - strtotime((string) $checkOut)) / 60);
        }

        return [
            'id' => $reserva->id,
            'codigo_reserva' => $reserva->codigo_reserva,
            'id_cliente' => $reserva->id_cliente,
            'cliente' => $cliente->nombre_completo ?? '',
            'correo_electronico' => $cliente->correo_electronico ?? '',
            'id_habitacion' => $habitacionPrincipal['id'] ?? null,
            'habitacion' => $habitacionPrincipal ? 'Hab. ' . $habitacionPrincipal['numero_habitacion'] . ' - Piso ' . $habitacionPrincipal['piso'] : '',
            'habitaciones' => $habitaciones,
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

            if (in_array($reservaActual->estado, ['cancelada', 'checkout_realizado'], true)) {
                return ['exito' => false, 'mensaje' => 'No se puede cancelar una reserva en este estado.'];
            }

            $hotel = Hotel::first();
            $porcentajePenalidad = (int) ($hotel->porcentaje_penalidad_cancelacion ?? 25);

            $penalidad = (float) $reservaActual->total * ($porcentajePenalidad / 100);
            $montoPagado = (float) $reservaActual->pagos->sum('monto');

            $reembolso = max(0, $montoPagado - $penalidad);

            DB::connection()->beginTransaction();

            $reservaActual->estado = 'cancelada';
            $reservaActual->observaciones = trim((string) ($reservaActual->observaciones ?? '') . "\nCancelada: " . $motivo . " (Penalidad aplicada: S/ " . number_format($penalidad, 2) . ")");
            $reservaActual->save();

            $habitacionModel = new HabitacionModel();
            $checkIn = $reservaActual->reservaHabitacion->first()->check_in ?? null;
            $checkOut = $reservaActual->reservaHabitacion->first()->check_out ?? null;

            foreach ($reservaActual->reservaHabitacion as $reservaHabitacion) {
                if (!$reservaHabitacion || empty($reservaHabitacion->id_habitacion)) {
                    continue;
                }

                Habitacion::where('id', (int) $reservaHabitacion->id_habitacion)->update([
                    'estado' => 'Disponible',
                ]);

                $habitacionModel->registrarHistorial(
                    (int) $reservaHabitacion->id_habitacion,
                    (int) $idReserva,
                    'Ocupada',
                    'Disponible',
                    null,
                    null,
                    'cancelar_reserva',
                    'Reserva cancelada. Penalidad: S/ ' . $penalidad
                );
            }

            try {
                $diasUsados = 0;
                $diasNoUsados = 0;
                $totalNoOcupado = 0.0;

                if ($checkIn && $checkOut) {
                    $tsIn = strtotime($checkIn);
                    $tsOut = strtotime($checkOut);
                    $totalDias = max(1, (int) ceil(($tsOut - $tsIn) / 86400));
                    $now = time();

                    if ($now <= $tsIn) {
                        $diasUsados = 0;
                    } elseif ($now >= $tsOut) {
                        $diasUsados = $totalDias;
                    } else {
                        $diasUsados = (int) floor(($now - $tsIn) / 86400);
                        if ($diasUsados < 0)
                            $diasUsados = 0;
                        if ($diasUsados > $totalDias)
                            $diasUsados = $totalDias;
                    }

                    $diasNoUsados = max(0, $totalDias - $diasUsados);
                    $precioPorDia = $totalDias > 0 ? ((float) $reservaActual->total / $totalDias) : 0.0;
                    $totalNoOcupado = round($precioPorDia * $diasNoUsados, 2);
                }

                Devolucion::create([
                    'fecha_cancelacion' => date('Y-m-d H:i:s'),
                    'fecha_inicio' => $checkIn,
                    'fecha_prevista' => $checkOut,
                    'dias_usados' => (int) $diasUsados,
                    'dias_no_usados' => (int) $diasNoUsados,
                    'total_no_ocupado' => (float) $totalNoOcupado,
                    'porcentaje_penalidad' => (int) $porcentajePenalidad,
                    'monto_penalidad' => (float) $penalidad,
                    'monto_devuelto' => (float) $reembolso,
                    'id_reserva' => (int) $idReserva,
                    'id_usuario' => $idUsuario ? (int) $idUsuario : ($_SESSION['id_usuario'] ?? null),
                    'descripcion' => $motivo,
                ]);
            } catch (\Throwable $e) {
                error_log('Error al registrar devolucion: ' . $e->getMessage());
            }

            DB::connection()->commit();
            return ['exito' => true, 'mensaje' => 'Reserva cancelada. Penalidad administrativa: S/ ' . number_format($penalidad, 2)];
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

            if ($idHabitacionPrincipal > 0) {
                Habitacion::where('id', $idHabitacionPrincipal)->update([
                    'estado' => 'Ocupada',
                ]);

                $habitacionModel->registrarHistorial(
                    $idHabitacionPrincipal,
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

            if ($saldoFinal > 0.01 && !$autorizarSaldo) {
                return ['exito' => false, 'mensaje' => 'No se puede confirmar checkout porque existe saldo pendiente de S/ ' . number_format($saldoFinal, 2) . '.'];
            }

            DB::connection()->beginTransaction();

            $fechaCheckout = $this->fechaHoraActual();
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

    public function extenderEstadia($idReserva, $nuevoCheckOut, $idUsuario = null)
    {
        try {
            $reservaActual = Reserva::with(['reservaHabitacion.habitacion'])->find((int) $idReserva);
            if (!$reservaActual) {
                return ['exito' => false, 'mensaje' => 'Reserva no encontrada.'];
            }

            if (!in_array($reservaActual->estado, ['en_estadia', 'checkout_pendiente'], true)) {
                return ['exito' => false, 'mensaje' => 'Solo se puede extender una estadía activa.'];
            }

            $idHabitacionPrincipal = (int) ($reservaActual->reservaHabitacion->first()->id_habitacion ?? 0);
            $checkIn = $reservaActual->reservaHabitacion->first()->check_in ?? null;

            $reporteOcupacionModel = new ReporteOcupacionModel();
            $disponibilidad = $reporteOcupacionModel->validarDisponibilidadHabitacion(
                $idHabitacionPrincipal,
                $checkIn,
                $nuevoCheckOut,
                (int) $idReserva
            );

            if (!$disponibilidad['disponible']) {
                return ['exito' => false, 'mensaje' => 'No se puede extender porque existe una reserva futura cruzada.'];
            }

            $habitacionModel = new HabitacionModel();
            $nuevoTotal = $reporteOcupacionModel->calcularTotalReserva($idHabitacionPrincipal, $checkIn, $nuevoCheckOut);

            $reservaHabitacion = $reservaActual->reservaHabitacion->first();
            if (!$reservaHabitacion) {
                return ['exito' => false, 'mensaje' => 'No se encontró la habitación asociada a la reserva.'];
            }

            $reservaHabitacion->check_out = $nuevoCheckOut;
            $reservaHabitacion->save();

            $reservaActual->total = $nuevoTotal;
            $reservaActual->estado = 'en_estadia';
            $ok = $reservaActual->save();

            if ($ok) {
                $estadoOperativo = $reservaActual->reservaHabitacion->first()->habitacion->estado_operativo ?? 'ocupada';
                $estadoLimpieza = $reservaActual->reservaHabitacion->first()->habitacion->estado_limpieza ?? 'limpia';
                $habitacionModel->registrarHistorial($idHabitacionPrincipal, (int) $idReserva, $estadoOperativo, $estadoOperativo, $estadoLimpieza, $estadoLimpieza, 'extension_estadia', 'Checkout extendido hasta ' . $nuevoCheckOut, $idUsuario);
            }

            return ['exito' => $ok, 'mensaje' => $ok ? 'Estadía extendida correctamente.' : 'No se pudo extender la estadía.', 'total' => $nuevoTotal];
        } catch (\Exception $e) {
            return ['exito' => false, 'mensaje' => 'Error al extender estadía: ' . $e->getMessage()];
        }
    }

    // se v a a modificar tambien
    public function cambiarHabitacion($idReserva, $idHabitacionNueva, $motivo, $idUsuario = null)
    {
        try {
            $reservaActual = Reserva::with(['reservaHabitacion.habitacion'])->find((int) $idReserva);
            if (!$reservaActual || !in_array($reservaActual->estado, ['en_estadia', 'checkout_pendiente'], true)) {
                return ['exito' => false, 'mensaje' => 'Solo se puede cambiar habitación de una estadía activa.'];
            }

            if (trim((string) $motivo) === '') {
                return ['exito' => false, 'mensaje' => 'Debe indicar motivo del cambio de habitación.'];
            }

            $habitacionModel = new HabitacionModel();
            $reporteOcupacionModel = new ReporteOcupacionModel();
            $checkOut = $reservaActual->reservaHabitacion->first()->check_out ?? null;
            $disponibilidad = $reporteOcupacionModel->validarDisponibilidadHabitacion($idHabitacionNueva, date('Y-m-d H:i:s'), $checkOut);
            if (!$disponibilidad['disponible']) {
                return ['exito' => false, 'mensaje' => $disponibilidad['mensaje']];
            }

            $idHabitacionAnterior = (int) ($reservaActual->reservaHabitacion->first()->id_habitacion ?? 0);
            $habitacionAnterior = $habitacionModel->obtenerPorId($idHabitacionAnterior);
            $habitacionNueva = $habitacionModel->obtenerPorId((int) $idHabitacionNueva);

            DB::connection()->beginTransaction();

            $reservaActual->id_habitacion = $idHabitacionNueva;
            $reservaActual->save();
            Habitacion::where('id', $idHabitacionAnterior)->update([
                'estado_operativo' => 'bloqueada',
                'estado' => 'Bloqueada',
                'estado_limpieza' => 'sucia',
            ]);
            Habitacion::where('id', $idHabitacionNueva)->update([
                'estado_operativo' => 'ocupada',
                'estado' => 'Ocupada',
            ]);

            $habitacionModel->registrarHistorial($idHabitacionAnterior, (int) $idReserva, $habitacionAnterior['estado_operativo'], 'bloqueada', $habitacionAnterior['estado_limpieza'], 'sucia', 'cambio_habitacion_salida', $motivo, $idUsuario);
            $habitacionModel->registrarHistorial((int) $idHabitacionNueva, (int) $idReserva, $habitacionNueva['estado_operativo'], 'ocupada', $habitacionNueva['estado_limpieza'], $habitacionNueva['estado_limpieza'], 'cambio_habitacion_entrada', $motivo, $idUsuario);

            DB::connection()->commit();
            return ['exito' => true, 'mensaje' => 'Cambio de habitación registrado correctamente.'];
        } catch (\Exception $e) {
            $con = DB::connection();
            if ($con->getPdo()->inTransaction()) {
                $con->rollBack();
            }
            return ['exito' => false, 'mensaje' => 'Error al cambiar habitación: ' . $e->getMessage()];
        }
    }
}