<?php

namespace Services\Reservas;

use Illuminate\Database\Capsule\Manager as DB;
use Helpers\FechaHotelHelper;
use Helpers\HabitacionInputHelper;
use Helpers\ReservaHabitacionHelper;
use Helpers\ReservaHelper;
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
                return [
                    'exito' => false,
                    'mensaje' => 'No se recibió el ID de la reserva.'
                ];
            }

            $reservaActual = $this->reservaModel->obtenerReservaConHabitacionesYPagos($idReserva);

            if (!$reservaActual) {
                return [
                    'exito' => false,
                    'mensaje' => 'Reserva no encontrada.'
                ];
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
                return [
                    'exito' => false,
                    'mensaje' => 'Solo se puede editar una reserva confirmada o una estadía activa.'
                ];
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
                return [
                    'exito' => false,
                    'mensaje' => 'Rango de fechas inválido.'
                ];
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
                        return [
                            'exito' => false,
                            'mensaje' => $disponibilidad['mensaje']
                        ];
                    }
                }

                $habitacionActual = $this->habitacionModel->obtenerPorId($idHabitacion);

                if (!$habitacionActual) {
                    return [
                        'exito' => false,
                        'mensaje' => 'No se encontró una de las habitaciones seleccionadas.'
                    ];
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
                return [
                    'exito' => false,
                    'mensaje' => 'Debe seleccionar al menos una habitación válida.'
                ];
            }

            $totalPagado = (float) ($reservaActual->pagos->sum('monto') ?? 0);

            if ($totalPagado > $totalCalculado + 0.00001) {
                return [
                    'exito' => false,
                    'mensaje' => 'No se puede dejar un total menor al monto ya pagado. Total pagado: S/ ' . number_format($totalPagado, 2)
                ];
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

            return [
                'exito' => true,
                'mensaje' => 'Reserva actualizada correctamente.',
                'id_reserva' => $idReserva,
                'reserva' => $this->reservaModel->obtenerReservaPorId($idReserva),
            ];
        } catch (\Throwable $e) {
            error_log('ActualizarReservaService::actualizarReserva -> ' . $e->getMessage());
            $conexion = DB::connection();

            if ($conexion->getPdo()->inTransaction()) {
                $conexion->rollBack();
            }

            return [
                'exito' => false,
                'mensaje' => 'No se pudo actualizar la reserva. Intente nuevamente.'
            ];
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
                return [
                    'exito' => false,
                    'mensaje' => 'Debe indicar la fecha de salida.'
                ];
            }

            $clienteNuevo = (int) ($datos['cliente'] ?? $datos['id_cliente'] ?? $reservaActual->id_cliente);

            if ($clienteNuevo !== (int) $reservaActual->id_cliente) {
                return [
                    'exito' => false,
                    'mensaje' => 'No se puede cambiar el cliente cuando la reserva está en estadía.'
                ];
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
                    return [
                        'exito' => false,
                        'mensaje' => 'No se puede quitar habitaciones durante una estadía. Use Cambiar habitación.'
                    ];
                }
            }

            $idsNuevos = array_values(array_diff($idsSolicitados, $idsActivos));
            $fechaAlta = FechaHotelHelper::ahora();
            $totalCalculado = 0;

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

                    return [
                        'exito' => false,
                        'mensaje' => $disponibilidad['mensaje']
                    ];
                }

                $precioAplicado = (float) ($relacion->precio_aplicado ?: 0);

                if ($precioAplicado <= 0) {
                    $habitacionActual = $this->habitacionModel->obtenerPorId(
                        (int) $relacion->id_habitacion
                    );

                    $precioAplicado = (float) ($habitacionActual['precio'] ?? 0);
                }

                $dias = ReservaHelper::obtenerDiasEstadia(
                    $relacion->check_in,
                    $checkOut
                );

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

                    return [
                        'exito' => false,
                        'mensaje' => $disponibilidad['mensaje']
                    ];
                }

                $habitacionNueva = $this->habitacionModel->obtenerPorId($idHabitacionNueva);

                if (!$habitacionNueva) {
                    DB::connection()->rollBack();

                    return [
                        'exito' => false,
                        'mensaje' => 'No se encontró una de las habitaciones seleccionadas.'
                    ];
                }

                $precio = (float) ($habitacionNueva['precio'] ?? 0);
                $dias = ReservaHelper::obtenerDiasEstadia($fechaAlta, $checkOut);
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

            DB::connection()->commit();

            return [
                'exito' => true,
                'mensaje' => 'Estadía actualizada correctamente.',
                'id_reserva' => $idReserva,
                'reserva' => $this->reservaModel->obtenerReservaPorId($idReserva),
            ];
        } catch (\Throwable $e) {
            error_log('ActualizarReservaService::actualizarEstadiaActiva -> ' . $e->getMessage());
            $conexion = DB::connection();

            if ($conexion->getPdo()->inTransaction()) {
                $conexion->rollBack();
            }

            return [
                'exito' => false,
                'mensaje' => 'No se pudo actualizar la estadía. Intente nuevamente.'
            ];
        }
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

                try {
                    $habitacionActual = $this->habitacionModel->obtenerPorId($idHabitacionAnterior) ?? [];

                    $this->habitacionModel->registrarHistorial(
                        $idHabitacionAnterior,
                        $idReserva,
                        $habitacionActual['estado'] ?? 'Ocupada',
                        'Mantenimiento',
                        null,
                        null,
                        'editar_reserva_quitar_habitacion',
                        'Habitación removida de reserva: estado ajustado según fechas.'
                    );
                } catch (\Throwable $e) {
                    // No detenemos la actualización si falla el historial.
                }
            }
        }
    }
}
