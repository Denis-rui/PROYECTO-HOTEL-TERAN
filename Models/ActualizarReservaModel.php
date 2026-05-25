<?php

namespace Models;

use Illuminate\Database\Capsule\Manager as DB;
use Models\Entities\Habitacion;
use Models\HabitacionModel;
use Models\Entities\Reserva;
use Models\Entities\ReservaHabitacion;
use Models\ReporteOcupacionModel;
use Helpers\ReservaHelper;

class ActualizarReservaModel extends ReservaModel
{
    private const ESTADOS_ACTIVOS = ['pendiente', 'confirmada', 'checkin_realizado', 'en_estadia', 'checkout_pendiente'];
    public function actualizarReserva($datos)
    {
        try {
            $idReserva = (int) ($datos['id_reserva'] ?? 0);
            if ($idReserva <= 0) {
                return ['exito' => false, 'mensaje' => 'No se recibió el ID de la reserva.'];
            }

            $reservaActual = Reserva::with(['pagos', 'reservaHabitacion.habitacion'])->find($idReserva);
            if (!$reservaActual) {
                return ['exito' => false, 'mensaje' => 'Reserva no encontrada.'];
            }

            $habitacionModel = new HabitacionModel();
            $reporteOcupacionModel = new ReporteOcupacionModel();
            $checkIn = ReservaHelper::combinarFechaHora($datos['checkIn'] ?? null, $datos['horaEntrada'] ?? null);
            $checkOut = ReservaHelper::combinarFechaHora($datos['checkOut'] ?? null, $datos['horaSalida'] ?? null);

            $habitacionesActuales = $reservaActual->reservaHabitacion ?? [];
            $idsHabitacionesActuales = [];
            foreach ($habitacionesActuales as $itemHabitacionActual) {
                if ($itemHabitacionActual && !empty($itemHabitacionActual->id_habitacion)) {
                    $idsHabitacionesActuales[] = (int) $itemHabitacionActual->id_habitacion;
                }
            }

            $habitacionesIngresadas = $datos['habitaciones'] ?? [];
            if (is_string($habitacionesIngresadas)) {
                $decoded = json_decode($habitacionesIngresadas, true);
                $habitacionesIngresadas = is_array($decoded) ? $decoded : [];
            }

            if (empty($habitacionesIngresadas) && !empty($datos['habitacion'])) {
                $habitacionesIngresadas = [$datos['habitacion']];
            }

            $habitacionesNormalizadas = [];
            $totalCalculado = 0;
            $dias = ReservaHelper::obtenerDiasEstadia($checkIn, $checkOut);

            if ($dias <= 0) {
                return ['exito' => false, 'mensaje' => 'Rango de fechas inválido.'];
            }

            foreach ($habitacionesIngresadas as $habitacionIngresada) {
                $idHabitacion = is_array($habitacionIngresada)
                    ? (int) ($habitacionIngresada['id'] ?? $habitacionIngresada['id_habitacion'] ?? 0)
                    : (int) $habitacionIngresada;

                if ($idHabitacion <= 0) {
                    continue;
                }

                $esHabitacionYaAsignada = in_array($idHabitacion, $idsHabitacionesActuales, true);
                if (!$esHabitacionYaAsignada) {
                    $disponibilidad = $reporteOcupacionModel->validarDisponibilidadHabitacion(
                        $idHabitacion,
                        $checkIn,
                        $checkOut,
                        $idReserva
                    );

                    if (!$disponibilidad['disponible']) {
                        return ['exito' => false, 'mensaje' => $disponibilidad['mensaje']];
                    }
                }

                $habitacionActual = $habitacionModel->obtenerPorId($idHabitacion);
                if (!$habitacionActual) {
                    return ['exito' => false, 'mensaje' => 'No se encontró una de las habitaciones seleccionadas.'];
                }

                $precioHabitacion = (float) ($habitacionActual['precio'] ?? 0);
                $habitacionesNormalizadas[] = [
                    'id' => $idHabitacion,
                    'habitacion' => $habitacionActual,
                    'precio' => $precioHabitacion,
                ];

                $totalCalculado += $precioHabitacion * $dias;
            }

            if (empty($habitacionesNormalizadas)) {
                return ['exito' => false, 'mensaje' => 'Debe seleccionar al menos una habitación válida.'];
            }

            $totalPagado = (float) ($reservaActual->pagos->sum('monto') ?? 0);
            if ($totalPagado > $totalCalculado + 0.00001) {
                return [
                    'exito' => false,
                    'mensaje' => 'No se puede dejar un total menor al monto ya pagado. Total pagado: S/ ' . number_format($totalPagado, 2)
                ];
            }

            $habitacionesAnteriores = $reservaActual->reservaHabitacion ?? [];
            $idsHabitacionesAnteriores = [];
            $mapHabitacionFechas = [];
            foreach ($habitacionesAnteriores as $itemHabitacion) {
                if ($itemHabitacion && !empty($itemHabitacion->id_habitacion)) {
                    $idHab = (int) $itemHabitacion->id_habitacion;
                    $idsHabitacionesAnteriores[] = $idHab;
                    $mapHabitacionFechas[$idHab] = [
                        'check_in' => $itemHabitacion->check_in ?? null,
                        'check_out' => $itemHabitacion->check_out ?? null,
                    ];
                }
            }

            $idsHabitacionesNuevas = array_values(array_unique(array_map(
                fn($item) => (int) $item['id'],
                $habitacionesNormalizadas
            )));

            DB::connection()->beginTransaction();

            $reservaActual->update([
                'id_cliente' => (int) ($datos['cliente'] ?? $datos['id_cliente'] ?? $reservaActual->id_cliente),
                'total' => $totalCalculado,
                'observaciones' => $datos['observaciones'] ?? $reservaActual->observaciones,
            ]);

            ReservaHabitacion::where('id_reserva', $idReserva)
                ->where('id_reserva', $idReserva)
                ->delete();

            foreach ($habitacionesNormalizadas as $habitacionNormalizada) {
                ReservaHabitacion::create([
                    'id_reserva'    => $idReserva,
                    'id_habitacion' => $habitacionNormalizada['id'],
                    'check_in'      => $checkIn,
                    'check_out'     => $checkOut,
                    'activo'        => 1,
                ]);
            }

            // Decidir estado de habitaciones que fueron removidas de la reserva
            foreach ($idsHabitacionesAnteriores as $idHabitacionAnterior) {
                if (in_array($idHabitacionAnterior, $idsHabitacionesNuevas, true)) {
                    continue;
                }

                $sigueOcupada = DB::table('reserva_habitacion as rh')
                    ->join('reserva as r', 'r.id', '=', 'rh.id_reserva')
                    ->where('rh.id_habitacion', $idHabitacionAnterior)
                    ->where('rh.activo', 1)
                    ->whereIn('r.estado', self::ESTADOS_ACTIVOS)
                    ->exists();

                if ($sigueOcupada) {
                    // Hay otra reserva activa que ocupa o reservó la habitación: no cambiamos su estado aquí.
                    continue;
                }

                // Determinar si la habitación removida corresponde a fechas actuales (durante la estadía)
                $fechas = $mapHabitacionFechas[$idHabitacionAnterior] ?? null;
                $hoy = date('Y-m-d');
                $nuevoEstado = null; // null => no cambiar

                if ($fechas && !empty($fechas['check_in']) && !empty($fechas['check_out'])) {
                    $checkInFecha = substr(trim((string) $fechas['check_in']), 0, 10);
                    $checkOutFecha = substr(trim((string) $fechas['check_out']), 0, 10);

                    if ($checkInFecha !== '' && $checkOutFecha !== '' && $hoy >= $checkInFecha && $hoy < $checkOutFecha) {
                        // Si la eliminación ocurre dentro del periodo original de la reserva, enviar a mantenimiento
                        $nuevoEstado = 'Mantenimiento';
                    }
                }

                // Solo actualizar el estado si determinamos un nuevo estado (p.ej. Mantenimiento).
                // Si $nuevoEstado es null, dejamos la habitación tal como está.
                if ($nuevoEstado !== null) {
                    Habitacion::where('id', $idHabitacionAnterior)->update([
                        'estado' => $nuevoEstado,
                    ]);

                    // Registrar historial de movimiento de habitación
                    try {
                        $habitacionActual = $habitacionModel->obtenerPorId((int) $idHabitacionAnterior) ?? [];
                        $estadoAnterior = $habitacionActual['estado'] ?? 'Ocupada';
                        $habitacionModel->registrarHistorial(
                            (int) $idHabitacionAnterior,
                            (int) $idReserva,
                            $estadoAnterior,
                            $nuevoEstado,
                            null,
                            null,
                            'editar_reserva_quitar_habitacion',
                            'Habitación removida de reserva: estado ajustado según fechas.'
                        );
                    } catch (\Throwable $e) {
                    }
                }
            }

            DB::connection()->commit();

            return [
                'exito' => true,
                'mensaje' => 'Reserva actualizada correctamente.',
                'id_reserva' => $idReserva,
                'reserva' => $this->obtenerReservaPorId($idReserva),
            ];
        } catch (\Throwable $e) {
            $conexion = DB::connection();
            if ($conexion->getPdo()->inTransaction()) {
                $conexion->rollBack();
            }

            return ['exito' => false, 'mensaje' => 'Error al actualizar reserva: ' . $e->getMessage()];
        }
    }
}
