<?php

namespace Services\Reservas;

use Illuminate\Database\Capsule\Manager as DB;
use Helpers\FechaHotelHelper;
use Helpers\HabitacionInputHelper;
use Helpers\ReservaHabitacionHelper;
use Helpers\ReservaHelper;
use Models\Entities\Devolucion;
use Models\Entities\Habitacion;
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

            if ($estadoReserva !== 'confirmada') {
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

            $totalPagado = (float) ($reservaActual->pagos->sum('monto') ?? 0);

            if ($totalPagado > $totalCalculado + 0.00001) {
                return $this->respuesta(false, 'VALIDACION_ERROR', 'No se puede dejar un total menor al monto ya pagado. Total pagado: S/ ' . number_format($totalPagado, 2));
            }

            $habitacionesAnteriores = $reservaActual->reservaHabitacion ?? [];

            DB::connection()->beginTransaction();

            $this->reservaModel->actualizar($reservaActual, [
                'id_cliente' => (int) ($datos['cliente'] ?? $datos['id_cliente'] ?? $reservaActual->id_cliente),
                'total' => $totalCalculado,
                'observaciones' => $datos['observaciones'] ?? $reservaActual->observaciones,
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

            DB::connection()->commit();

            return $this->respuesta(true, 'ACTUALIZADO', 'Reserva actualizada correctamente.', [
                'id_reserva' => $idReserva,
                'reserva' => $this->reservaModel->obtenerReservaPorId($idReserva),
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
            $totalCalculado = 0;
            $totalAnterior = (float) ($reservaActual->total ?? 0);
            $totalPagado = (float) ($reservaActual->pagos->sum('monto') ?? 0);
            $checkOutPrevistoAnterior = $this->obtenerFechaExtremaRelaciones($relacionesActivas, 'check_out', 'max');
            $checkInProgramadoAnterior = $this->obtenerFechaExtremaRelaciones($relacionesActivas, 'check_in', 'min');
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

            $this->reservaModel->guardar($reservaActual);

            $montoDevolver = round(max(0, $totalPagado - $totalCalculado), 2);
            $devolucion = null;

            if ($montoDevolver > 0.00001) {
                $diasNuevos = $this->obtenerDiasEstadiaActiva($fechaInicioEstadia, $checkOut);
                $diasNoUsados = max(0, $diasPrevios - $diasNuevos);
                $descripcionDevolucion = sprintf(
                    'Devolución por disminución de días de estadía del %s al %s. Total anterior: S/ %s; nuevo total: S/ %s; pagado: S/ %s.',
                    substr($checkOut, 0, 10),
                    substr(($checkOutPrevistoAnterior !== '' ? $checkOutPrevistoAnterior : $checkOut), 0, 10),
                    number_format($totalAnterior, 2),
                    number_format($totalCalculado, 2),
                    number_format($totalPagado, 2)
                );

                Devolucion::updateOrCreate(
                    ['id_reserva' => $idReserva],
                    [
                        'id_reserva' => $idReserva,
                        'fecha_cancelacion' => FechaHotelHelper::ahora(),
                        'fecha_inicio' => $fechaInicioEstadia !== '' ? $fechaInicioEstadia : $fechaAlta,
                        'fecha_prevista' => $checkOutPrevistoAnterior !== '' ? $checkOutPrevistoAnterior : $checkOut,
                        'dias_usados' => max(1, $diasNuevos),
                        'dias_no_usados' => $diasNoUsados,
                        'total_no_ocupado' => $montoDevolver,
                        'porcentaje_penalidad' => 0,
                        'monto_penalidad' => 0,
                        'monto_devuelto' => $montoDevolver,
                        'id_usuario' => $idUsuarioActual,
                        'descripcion' => $descripcionDevolucion,
                    ]
                );

                $devolucion = [
                    'monto_devuelto' => $montoDevolver,
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

            $checkInFecha = substr(trim((string) ($itemHabitacion->check_in ?? '')), 0, 10);
            $checkOutFecha = substr(trim((string) ($itemHabitacion->check_out ?? '')), 0, 10);
            $hoy = FechaHotelHelper::hoy();

            if (
                $checkInFecha !== ''
                && $checkOutFecha !== ''
                && $hoy >= $checkInFecha
                && $hoy < $checkOutFecha
            ) {
                Habitacion::where('id', $idHabitacionAnterior)->update([
                    'estado' => 'Mantenimiento',
                ]);

            }
        }
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
