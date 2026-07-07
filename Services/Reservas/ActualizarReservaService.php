<?php

namespace Services\Reservas;

use Illuminate\Database\Capsule\Manager as DB;
use Helpers\FechaHotelHelper;
use Helpers\HabitacionInputHelper;
use Helpers\ReservaHabitacionHelper;
use Helpers\ReservaHelper;
use Models\Entities\Devolucion;
use Models\Entities\Habitacion;
use Models\Entities\Hotel;
use Models\Entities\Reserva as ReservaEntity;
use Models\HabitacionModel;
use Models\ReporteOcupacionModel;
use Models\ReservaHabitacionModel;
use Models\ReservaModel;

class ActualizarReservaService
{
    private ReservaModel $reservaModel;
    private ReservaHabitacionModel $reservaHabitacionModel;
    private HabitacionModel $habitacionModel;
    private ReporteOcupacionModel $reporteOcupacionModel;

    public function __construct()
    {
        $this->reservaModel = new ReservaModel();
        $this->reservaHabitacionModel = new ReservaHabitacionModel();
        $this->habitacionModel = new HabitacionModel();
        $this->reporteOcupacionModel = new ReporteOcupacionModel();
    }

    public function actualizarReserva(array $datos, ?int $idUsuario = null): array
    {
        try {
            $idUsuarioActual = $idUsuario ?? ($_SESSION['id_usuario'] ?? null);
            $idReserva = (int) ($datos['id_reserva'] ?? 0);

            if ($idReserva <= 0) {
                return $this->respuesta(false, 'VALIDACION_ERROR', 'No se recibió el ID de la reserva.');
            }

            $reservaActual = $this->reservaModel->obtenerReservaConHabitacionesYPagos($idReserva);

            if (!$reservaActual) {
                return $this->respuesta(false, 'NO_ENCONTRADO', 'Reserva no encontrada.');
            }

            $estadoReserva = strtolower(trim((string) $reservaActual->estado));

            if (in_array($estadoReserva, ['en_estadia', 'checkout_pendiente'], true)) {
                return $this->actualizarEstadiaActiva(
                    $reservaActual,
                    $datos,
                    $idUsuarioActual
                );
            }

            if (!in_array($estadoReserva, ['confirmada', 'pre_checkin'], true)) {
                return $this->respuesta(false, 'CONFLICTO', 'Solo se puede editar una reserva confirmada o una estadía activa.');
            }

            $checkIn = ReservaHelper::combinarFechaHora(
                $datos['checkIn'] ?? null,
                $datos['horaEntrada'] ?? null
            );

            $checkOut = ReservaHelper::combinarFechaHora(
                $datos['checkOut'] ?? null,
                $datos['horaSalida'] ?? null
            );

            $dias = ReservaHelper::obtenerDiasEstadia($checkIn, $checkOut);

            if ($dias <= 0) {
                return $this->respuesta(false, 'VALIDACION_ERROR', 'Rango de fechas inválido.');
            }

            $idsHabitacionesActuales = $this->obtenerIdsHabitacionesActuales($reservaActual);
            $idsHabitacionesIngresadas = HabitacionInputHelper::obtenerIdsDesdeRequest($datos);

            $habitacionesNormalizadas = [];
            $totalCalculado = 0;

            foreach ($idsHabitacionesIngresadas as $idHabitacion) {
                $esHabitacionYaAsignada = in_array(
                    $idHabitacion,
                    $idsHabitacionesActuales,
                    true
                );

                if (!$esHabitacionYaAsignada) {
                    $disponibilidad = $this->reporteOcupacionModel->validarDisponibilidadHabitacion(
                        $idHabitacion,
                        $checkIn,
                        $checkOut,
                        $idReserva
                    );

                    if (!$disponibilidad['disponible']) {
                        return $this->respuesta(false, 'CONFLICTO', $disponibilidad['mensaje']);
                    }
                }

                $habitacionActual = $this->habitacionModel->obtenerPorId($idHabitacion);

                if (!$habitacionActual) {
                    return $this->respuesta(false, 'NO_ENCONTRADO', 'No se encontró una de las habitaciones seleccionadas.');
                }

                $precioHabitacion = (float) ($habitacionActual['precio'] ?? 0);
                $subtotal = $precioHabitacion * $dias;

                $habitacionesNormalizadas[] = [
                    'id' => $idHabitacion,
                    'habitacion' => $habitacionActual,
                    'precio' => $precioHabitacion,
                    'subtotal' => $subtotal,
                ];

                $totalCalculado += $subtotal;
            }

            if (empty($habitacionesNormalizadas)) {
                return $this->respuesta(false, 'VALIDACION_ERROR', 'Debe seleccionar al menos una habitación válida.');
            }

            $totalAnterior = (float) ($reservaActual->total ?? 0);
            $sumPagos = (float) ($reservaActual->pagos->sum('monto') ?? 0);
            $sumPenalidades = (float) Devolucion::where('id_reserva', $reservaActual->id)->sum('monto_penalidad');
            $totalPagado = max(0.0, $sumPagos - $sumPenalidades);
            $devolucion = null;

            $habitacionesAnteriores = $reservaActual->reservaHabitacion ?? [];

            DB::connection()->beginTransaction();

            $this->reservaModel->actualizar($reservaActual, [
                'id_cliente' => (int) ($datos['cliente'] ?? $datos['id_cliente'] ?? $reservaActual->id_cliente),
                'total' => $totalCalculado,
                'observaciones' => $datos['observaciones'] ?? $reservaActual->observaciones,
                'check_in_programado' => $checkIn,
                'check_out_programado' => $checkOut,
            ]);

            $this->reservaHabitacionModel->eliminarPorReserva($idReserva);

            foreach ($habitacionesNormalizadas as $habitacionNormalizada) {
                $this->reservaHabitacionModel->crear([
                    'id_reserva' => $idReserva,
                    'id_habitacion' => $habitacionNormalizada['id'],
                    'check_in' => $checkIn,
                    'check_out' => $checkOut,
                    'activo' => 1,
                    'tipo_asignacion' => 'original',
                    'estado' => 'activa',
                    'precio_aplicado' => $habitacionNormalizada['precio'],
                    'subtotal' => $habitacionNormalizada['subtotal'],
                    'id_usuario_movimiento' => $idUsuarioActual,
                    'fecha_movimiento' => FechaHotelHelper::ahora(),
                ]);
            }

            $this->procesarHabitacionesRemovidas(
                $habitacionesAnteriores,
                $habitacionesNormalizadas,
                $idReserva
            );

            if ($totalPagado > $totalCalculado + 0.00001) {
                $montoCancelado = max(0.0, $totalAnterior - $totalCalculado);
                $hotel = Hotel::first();
                $porcentaje = max(0.0, min(100.0, (float) ($hotel->porcentaje_penalidad_cancelacion ?? 25)));
                $montoPenalidad = round($montoCancelado * ($porcentaje / 100), 1);
                $excesoDevolvible = max(0.0, $totalPagado - $totalCalculado);
                $montoDevolver = round(min($montoCancelado - $montoPenalidad, $excesoDevolvible), 1);

                if ($montoDevolver > 0.00001) {
                    $diasAnteriores = ReservaHelper::obtenerDiasEstadia($reservaActual->check_in_programado, $reservaActual->check_out_programado);
                    $diasNoUsados = max(0, $diasAnteriores - $dias);
                    $detalleCambios = $this->obtenerDescripcionCambios($reservaActual, $checkIn, $checkOut, $habitacionesNormalizadas);
                    $descripcionDevolucion = sprintf(
                        'Devolución por modificación de reserva (%s). Total anterior: S/ %s; nuevo total: S/ %s; pagado: S/ %s; penalidad (%s%%): S/ %s.',
                        $detalleCambios,
                        number_format($totalAnterior, 2),
                        number_format($totalCalculado, 2),
                        number_format($totalPagado, 2),
                        $porcentaje,
                        number_format($montoPenalidad, 2)
                    );

                    Devolucion::create(
                        [
                            'id_reserva' => $idReserva,
                            'fecha_cancelacion' => FechaHotelHelper::ahora(),
                            'fecha_inicio' => $checkIn,
                            'fecha_prevista' => $checkOut,
                            'dias_usados' => max(1, $dias),
                            'dias_no_usados' => $diasNoUsados,
                            'total_no_ocupado' => $montoCancelado,
                            'porcentaje_penalidad' => $porcentaje,
                            'monto_penalidad' => $montoPenalidad,
                            'monto_devuelto' => $montoDevolver,
                            'id_usuario' => $idUsuarioActual,
                            'descripcion' => $descripcionDevolucion,
                        ]
                    );

                    $devolucion = [
                        'monto_devuelto' => $montoDevolver,
                        'monto_penalidad' => $montoPenalidad,
                        'porcentaje_penalidad' => $porcentaje,
                        'descripcion' => $descripcionDevolucion,
                        'total_anterior' => round($totalAnterior, 2),
                        'total_nuevo' => round($totalCalculado, 2),
                        'total_pagado' => round($totalPagado, 2),
                        'fecha_desde_devuelta' => substr($checkIn, 0, 10),
                        'fecha_hasta_devuelta' => substr($checkOut, 0, 10),
                        'dias_no_usados' => $diasNoUsados,
                    ];
                }
            }

            DB::connection()->commit();

            $mensaje = $devolucion
                ? 'Reserva actualizada correctamente. Devolución a realizar: S/ ' . number_format($devolucion['monto_devuelto'], 2) . '.'
                : 'Reserva actualizada correctamente.';

            return $this->respuesta(true, 'ACTUALIZADO', $mensaje, [
                'id_reserva' => $idReserva,
                'reserva' => $this->reservaModel->obtenerReservaPorId($idReserva),
                'devolucion' => $devolucion,
            ]);
        } catch (\Throwable $e) {
            error_log('ActualizarReservaService::actualizarReserva -> ' . $e->getMessage());
            $conexion = DB::connection();

            if ($conexion->getPdo()->inTransaction()) {
                $conexion->rollBack();
            }

            return $this->respuesta(false, 'EXCEPCION', 'No se pudo actualizar la reserva. Intente nuevamente.');
        }
    }
    private function actualizarEstadiaActiva($reservaActual, array $datos, ?int $idUsuario = null): array
    {
        try {
            $idUsuarioActual = $idUsuario ?? ($_SESSION['id_usuario'] ?? null);
            $idReserva = (int) $reservaActual->id;

            $checkOut = ReservaHelper::combinarFechaHora(
                $datos['checkOut'] ?? null,
                $datos['horaSalida'] ?? null
            );

            if (!$checkOut) {
                return $this->respuesta(false, 'VALIDACION_ERROR', 'Debe indicar la fecha de salida.');
            }

            $hoy = FechaHotelHelper::hoy();
            $checkOutFecha = substr($checkOut, 0, 10);

            if ($checkOutFecha < $hoy) {
                return $this->respuesta(false, 'VALIDACION_ERROR', 'La salida de una estadía activa no puede ser anterior a hoy.');
            }

            $clienteNuevo = (int) ($datos['cliente'] ?? $datos['id_cliente'] ?? $reservaActual->id_cliente);

            if ($clienteNuevo !== (int) $reservaActual->id_cliente) {
                return $this->respuesta(false, 'CONFLICTO', 'No se puede cambiar el cliente cuando la reserva está en estadía.');
            }

            $idsSolicitados = HabitacionInputHelper::obtenerIdsDesdeRequest($datos);

            $relacionesActivas = [];
            $idsActivos = [];

            foreach (($reservaActual->reservaHabitacion ?? []) as $relacion) {
                if (ReservaHabitacionHelper::esActiva($relacion)) {
                    $relacionesActivas[] = $relacion;
                    $idsActivos[] = (int) $relacion->id_habitacion;
                }
            }

            foreach ($idsActivos as $idActivo) {
                if (!in_array($idActivo, $idsSolicitados, true)) {
                    return $this->respuesta(false, 'CONFLICTO', 'No se puede quitar habitaciones durante una estadía. Use Cambiar habitación.');
                }
            }

            $idsNuevos = array_values(array_diff($idsSolicitados, $idsActivos));
            $fechaAlta = FechaHotelHelper::ahora();
            $totalHistorico = $this->calcularTotalRelacionesHistoricas($reservaActual->reservaHabitacion ?? []);
            $totalCalculado = $totalHistorico;
            $totalAnterior = (float) ($reservaActual->total ?? 0);
            $sumPagos = (float) ($reservaActual->pagos->sum('monto') ?? 0);
            $sumPenalidades = (float) Devolucion::where('id_reserva', $reservaActual->id)->sum('monto_penalidad');
            $totalPagado = max(0.0, $sumPagos - $sumPenalidades);
            $checkOutPrevistoAnterior = trim((string) ($reservaActual->check_out_programado ?? ''));
            if ($checkOutPrevistoAnterior === '') {
                $checkOutPrevistoAnterior = $this->obtenerFechaExtremaRelaciones($relacionesActivas, 'check_out', 'max');
            }

            $checkInProgramadoAnterior = trim((string) ($reservaActual->check_in_programado ?? ''));
            if ($checkInProgramadoAnterior === '') {
                $checkInProgramadoAnterior = $this->obtenerFechaExtremaRelaciones($relacionesActivas, 'check_in', 'min');
            }

            $fechaInicioEstadia = (string) ($reservaActual->checkin_real ?? $checkInProgramadoAnterior);
            $diasPrevios = ReservaHelper::obtenerDiasEstadia($fechaInicioEstadia, $checkOutPrevistoAnterior);

            DB::connection()->beginTransaction();

            foreach ($relacionesActivas as $relacion) {
                $disponibilidad = $this->reporteOcupacionModel->validarDisponibilidadHabitacion(
                    (int) $relacion->id_habitacion,
                    $relacion->check_in,
                    $checkOut,
                    $idReserva
                );

                if (!$disponibilidad['disponible']) {
                    DB::connection()->rollBack();

                    return $this->respuesta(false, 'CONFLICTO', $disponibilidad['mensaje']);
                }

                $precioAplicado = (float) ($relacion->precio_aplicado ?: 0);

                if ($precioAplicado <= 0) {
                    $habitacionActual = $this->habitacionModel->obtenerPorId(
                        (int) $relacion->id_habitacion
                    );

                    $precioAplicado = (float) ($habitacionActual['precio'] ?? 0);
                }

                $dias = $this->obtenerDiasEstadiaActiva(
                    $relacion->check_in,
                    $checkOut
                );

                if ($dias <= 0) {
                    DB::connection()->rollBack();

                    return $this->respuesta(false, 'VALIDACION_ERROR', 'La fecha de salida debe generar al menos un día de estadía.');
                }

                $subtotal = $precioAplicado * $dias;

                $relacion->check_out = $checkOut;
                $relacion->precio_aplicado = $precioAplicado;
                $relacion->subtotal = $subtotal;

                $this->reservaHabitacionModel->guardar($relacion);

                $totalCalculado += $subtotal;
            }

            foreach ($idsNuevos as $idHabitacionNueva) {
                $disponibilidad = $this->reporteOcupacionModel->validarDisponibilidadHabitacion(
                    $idHabitacionNueva,
                    $fechaAlta,
                    $checkOut,
                    $idReserva
                );

                if (!$disponibilidad['disponible']) {
                    DB::connection()->rollBack();

                    return $this->respuesta(false, 'CONFLICTO', $disponibilidad['mensaje']);
                }

                $habitacionNueva = $this->habitacionModel->obtenerPorId($idHabitacionNueva);

                if (!$habitacionNueva) {
                    DB::connection()->rollBack();

                    return $this->respuesta(false, 'NO_ENCONTRADO', 'No se encontró una de las habitaciones seleccionadas.');
                }

                $precio = (float) ($habitacionNueva['precio'] ?? 0);
                $dias = $this->obtenerDiasEstadiaActiva($fechaAlta, $checkOut);

                if ($dias <= 0) {
                    DB::connection()->rollBack();

                    return $this->respuesta(false, 'VALIDACION_ERROR', 'La fecha de salida debe generar al menos un día de estadía.');
                }

                $subtotal = $precio * $dias;

                $this->reservaHabitacionModel->crear([
                    'id_reserva' => $idReserva,
                    'id_habitacion' => $idHabitacionNueva,
                    'check_in' => $fechaAlta,
                    'check_out' => $checkOut,
                    'activo' => 1,
                    'tipo_asignacion' => 'agregada',
                    'estado' => 'activa',
                    'motivo_cambio' => 'Habitación agregada durante estadía',
                    'id_usuario_movimiento' => $idUsuarioActual,
                    'fecha_movimiento' => $fechaAlta,
                    'precio_aplicado' => $precio,
                    'subtotal' => $subtotal,
                ]);

                Habitacion::where('id', $idHabitacionNueva)->update([
                    'estado' => 'Ocupada'
                ]);

                $totalCalculado += $subtotal;
            }

            $reservaActual->total = $totalCalculado;
            $reservaActual->observaciones = $datos['observaciones'] ?? $reservaActual->observaciones;
            $reservaActual->check_out_programado = $checkOut;

            $this->reservaModel->guardar($reservaActual);

            $montoCancelado = max(0.0, $totalAnterior - $totalCalculado);
            $hotel = Hotel::first();
            $porcentaje = max(0.0, min(100.0, (float) ($hotel->porcentaje_penalidad_cancelacion ?? 25)));
            $montoPenalidad = round($montoCancelado * ($porcentaje / 100), 1);
            $excesoDevolvible = max(0.0, $totalPagado - $totalCalculado);
            $montoDevolver = round(min($montoCancelado - $montoPenalidad, $excesoDevolvible), 1);
            $devolucion = null;

            if ($montoDevolver > 0.00001) {
                $diasNuevos = $this->obtenerDiasEstadiaActiva($fechaInicioEstadia, $checkOut);
                $diasNoUsados = max(0, $diasPrevios - $diasNuevos);
                $descripcionDevolucion = sprintf(
                    'Devolución por disminución de días de estadía del %s al %s. Total anterior: S/ %s; nuevo total: S/ %s; pagado: S/ %s; penalidad (%s%%): S/ %s.',
                    substr($checkOut, 0, 10),
                    substr(($checkOutPrevistoAnterior !== '' ? $checkOutPrevistoAnterior : $checkOut), 0, 10),
                    number_format($totalAnterior, 2),
                    number_format($totalCalculado, 2),
                    number_format($totalPagado, 2),
                    $porcentaje,
                    number_format($montoPenalidad, 2)
                );

                Devolucion::create(
                    [
                        'id_reserva' => $idReserva,
                        'fecha_cancelacion' => FechaHotelHelper::ahora(),
                        'fecha_inicio' => $fechaInicioEstadia !== '' ? $fechaInicioEstadia : $fechaAlta,
                        'fecha_prevista' => $checkOutPrevistoAnterior !== '' ? $checkOutPrevistoAnterior : $checkOut,
                        'dias_usados' => max(1, $diasNuevos),
                        'dias_no_usados' => $diasNoUsados,
                        'total_no_ocupado' => $montoCancelado,
                        'porcentaje_penalidad' => $porcentaje,
                        'monto_penalidad' => $montoPenalidad,
                        'monto_devuelto' => $montoDevolver,
                        'id_usuario' => $idUsuarioActual,
                        'descripcion' => $descripcionDevolucion,
                    ]
                );

                $devolucion = [
                    'monto_devuelto' => $montoDevolver,
                    'monto_penalidad' => $montoPenalidad,
                    'porcentaje_penalidad' => $porcentaje,
                    'descripcion' => $descripcionDevolucion,
                    'total_anterior' => round($totalAnterior, 2),
                    'total_nuevo' => round($totalCalculado, 2),
                    'total_pagado' => round($totalPagado, 2),
                    'fecha_desde_devuelta' => substr($checkOut, 0, 10),
                    'fecha_hasta_devuelta' => substr(($checkOutPrevistoAnterior !== '' ? $checkOutPrevistoAnterior : $checkOut), 0, 10),
                    'dias_no_usados' => $diasNoUsados,
                ];
            }

            DB::connection()->commit();

            $mensaje = $devolucion
                ? 'Estadía actualizada correctamente. Devolución a realizar: S/ ' . number_format($montoDevolver, 2) . '.'
                : 'Estadía actualizada correctamente.';

            return $this->respuesta(true, 'ACTUALIZADO', $mensaje, [
                'id_reserva' => $idReserva,
                'reserva' => $this->reservaModel->obtenerReservaPorId($idReserva),
                'devolucion' => $devolucion,
            ]);
        } catch (\Throwable $e) {
            error_log('ActualizarReservaService::actualizarEstadiaActiva -> ' . $e->getMessage());
            $conexion = DB::connection();

            if ($conexion->getPdo()->inTransaction()) {
                $conexion->rollBack();
            }

            return $this->respuesta(false, 'EXCEPCION', 'No se pudo actualizar la estadía. Intente nuevamente.');
        }
    }

    private function obtenerDiasEstadiaActiva($checkIn, $checkOut): int
    {
        $dias = ReservaHelper::obtenerDiasEstadia($checkIn, $checkOut);
        $checkOutFecha = ReservaHelper::normalizarFecha($checkOut);

        if ($checkOutFecha === FechaHotelHelper::hoy()) {
            return max(1, $dias);
        }

        return $dias;
    }

    private function obtenerFechaExtremaRelaciones(array $relaciones, string $campo, string $modo): string
    {
        $fechas = [];

        foreach ($relaciones as $relacion) {
            $fecha = trim((string) ($relacion->{$campo} ?? ''));

            if ($fecha !== '') {
                $fechas[] = $fecha;
            }
        }

        if (empty($fechas)) {
            return '';
        }

        sort($fechas);

        return $modo === 'max' ? end($fechas) : reset($fechas);
    }

    private function calcularTotalRelacionesHistoricas($relaciones): float
    {
        $total = 0.0;

        foreach (($relaciones ?? []) as $relacion) {
            if (!$relacion || ReservaHabitacionHelper::esActiva($relacion)) {
                continue;
            }

            $subtotal = (float) ($relacion->subtotal ?? 0);

            if ($subtotal <= 0) {
                $precio = (float) ($relacion->precio_aplicado ?? 0);
                $dias = ReservaHelper::obtenerDiasEstadia(
                    $relacion->check_in ?? null,
                    $relacion->check_out ?? null
                );
                $subtotal = $precio * max(0, $dias);
            }

            $total += $subtotal;
        }

        return $total;
    }

    private function obtenerIdsHabitacionesActuales($reservaActual): array
    {
        $idsHabitacionesActuales = [];

        foreach (($reservaActual->reservaHabitacion ?? []) as $itemHabitacionActual) {
            if ($itemHabitacionActual && !empty($itemHabitacionActual->id_habitacion)) {
                $idsHabitacionesActuales[] = (int) $itemHabitacionActual->id_habitacion;
            }
        }

        return array_values(array_unique($idsHabitacionesActuales));
    }

    private function procesarHabitacionesRemovidas($habitacionesAnteriores, array $habitacionesNormalizadas, int $idReserva): void
    {
        $idsHabitacionesNuevas = array_values(array_unique(array_map(
            fn($item) => (int) $item['id'],
            $habitacionesNormalizadas
        )));

        $reservaActual = $this->reservaModel->obtenerReservaSimple($idReserva);
        $estadoHabitacionDestino = ($reservaActual && !empty($reservaActual->checkin_real))
            ? 'Mantenimiento'
            : 'Disponible';

        foreach ($habitacionesAnteriores as $itemHabitacion) {
            if (!$itemHabitacion || empty($itemHabitacion->id_habitacion)) {
                continue;
            }

            $idHabitacionAnterior = (int) $itemHabitacion->id_habitacion;

            if (in_array($idHabitacionAnterior, $idsHabitacionesNuevas, true)) {
                continue;
            }

            $sigueOcupada = $this->reservaHabitacionModel->habitacionSigueOcupada(
                $idHabitacionAnterior,
                ReservaEntity::ESTADOS_ACTIVOS
            );

            if ($sigueOcupada) {
                continue;
            }

            Habitacion::where('id', $idHabitacionAnterior)->update([
                'estado' => $estadoHabitacionDestino,
            ]);
        }
    }

    private function obtenerDescripcionCambios($reservaActual, $checkIn, $checkOut, array $habitacionesNormalizadas): string
    {
        $cambios = [];

        // 1. Comparar fechas de check-in / check-out
        $checkInAnterior = substr($reservaActual->check_in_programado, 0, 10);
        $checkOutAnterior = substr($reservaActual->check_out_programado, 0, 10);
        $checkInNuevo = substr($checkIn, 0, 10);
        $checkOutNuevo = substr($checkOut, 0, 10);

        if ($checkInAnterior !== $checkInNuevo || $checkOutAnterior !== $checkOutNuevo) {
            $cambios[] = sprintf(
                'se redujeron fechas de estadía (antes: %s al %s, ahora: %s al %s)',
                $checkInAnterior,
                $checkOutAnterior,
                $checkInNuevo,
                $checkOutNuevo
            );
        }

        // 2. Comparar habitaciones
        $idsAnteriores = [];
        $numsAnteriores = [];
        foreach (($reservaActual->reservaHabitacion ?? []) as $rel) {
            if (($rel->estado ?? 'activa') === 'activa') {
                $idHab = (int) $rel->id_habitacion;
                $idsAnteriores[] = $idHab;
                $numsAnteriores[$idHab] = $rel->habitacion->numero_habitacion ?? $idHab;
            }
        }

        $idsNuevos = array_map(fn($h) => (int) $h['id'], $habitacionesNormalizadas);

        // Habitaciones eliminadas
        $eliminadas = array_diff($idsAnteriores, $idsNuevos);
        if (!empty($eliminadas)) {
            $numsEliminadas = [];
            foreach ($eliminadas as $id) {
                $numsEliminadas[] = 'Hab. ' . ($numsAnteriores[$id] ?? $id);
            }
            $cambios[] = 'se eliminó habitación: ' . implode(', ', $numsEliminadas);
        }

        if (empty($cambios)) {
            $cambios[] = 'modificación de reserva';
        }

        return implode(', ', $cambios);
    }

    private function respuesta(bool $exito, string $codigo, string $mensaje, mixed $data = null, array $errores = []): array
    {
        $respuesta = [
            'exito' => $exito,
            'codigo' => $codigo,
            'mensaje' => $mensaje,
            'data' => $data,
            'errores' => $errores,
        ];

        return is_array($data) ? array_merge($respuesta, $data) : $respuesta;
    }
}
