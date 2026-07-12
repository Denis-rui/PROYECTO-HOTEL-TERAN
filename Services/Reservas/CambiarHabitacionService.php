<?php

namespace Services\Reservas;

use Illuminate\Database\Capsule\Manager as DB;
use Helpers\FechaHotelHelper;
use Helpers\ReservaHabitacionHelper;
use Helpers\ReservaHelper;
use Models\Entities\Habitacion;
use Models\Entities\Devolucion;
use Models\Entities\Hotel;
use Models\HabitacionModel;
use Models\ReporteOcupacionModel;
use Models\ReservaHabitacionModel;
use Models\ReservaModel;

class CambiarHabitacionService
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

    public function cambiarHabitacion(
        int $idReserva,
        int $idHabitacionActual,
        int $idHabitacionNueva,
        string $tipoMotivo,
        string $motivo,
        ?int $idUsuario = null
    ): array {
        try {
            $idUsuarioActual = $idUsuario ?? ($_SESSION['id_usuario'] ?? null);

            $reservaActual = $this->reservaModel->obtenerReservaConHabitacionesYPagos($idReserva);

            if (!$reservaActual || !in_array($reservaActual->estado, ['en_estadia', 'checkout_pendiente'], true)) {
                return $this->respuesta(false, 'CONFLICTO', 'Solo se puede cambiar habitación de una estadía activa.');
            }

            $tipoMotivo = strtolower(trim($tipoMotivo));

            if (!in_array($tipoMotivo, ['falla_hotel', 'solicitud_cliente'], true)) {
                return $this->respuesta(false, 'VALIDACION_ERROR', 'Seleccione si el cambio es por falla del hotel o solicitud del cliente.');
            }

            if (trim($motivo) === '') {
                return $this->respuesta(false, 'VALIDACION_ERROR', 'Debe indicar motivo del cambio de habitación.');
            }

            $relacionActual = null;

            foreach ($reservaActual->reservaHabitacion as $itemHabitacion) {
                if (
                    ReservaHabitacionHelper::esActiva($itemHabitacion)
                    && (int) $itemHabitacion->id_habitacion === $idHabitacionActual
                ) {
                    $relacionActual = $itemHabitacion;
                    break;
                }
            }

            if (!$relacionActual) {
                return $this->respuesta(false, 'NO_ENCONTRADO', 'No se encontró la habitación activa que desea cambiar.');
            }

            $fechaCambio = FechaHotelHelper::ahora();
            $checkOut = $relacionActual->check_out ?? null;
            $fechaEfectivaCobro = $this->obtenerFechaEfectivaCobro($fechaCambio, $tipoMotivo);

            if (
                $tipoMotivo === 'solicitud_cliente'
                && substr($fechaCambio, 0, 10) === substr((string) $checkOut, 0, 10)
            ) {
                return $this->respuesta(false, 'CONFLICTO', 'No se puede cambiar la habitación por solicitud del cliente el mismo día de salida. Primero actualice la fecha de checkout.');
            }

            $disponibilidad = $this->reporteOcupacionModel->validarDisponibilidadHabitacion(
                $idHabitacionNueva,
                $fechaCambio,
                $checkOut,
                $idReserva
            );

            if (!$disponibilidad['disponible']) {
                return $this->respuesta(false, 'CONFLICTO', $disponibilidad['mensaje']);
            }

            $habitacionAnterior = $this->habitacionModel->obtenerPorId($idHabitacionActual);
            $habitacionNueva = $this->habitacionModel->obtenerPorId($idHabitacionNueva);

            if (!$habitacionAnterior || !$habitacionNueva) {
                return $this->respuesta(false, 'NO_ENCONTRADO', 'No se encontró la habitación seleccionada.');
            }

            $precioAnterior = (float) (
                $relacionActual->precio_aplicado
                ?: ($habitacionAnterior['precio'] ?? 0)
            );

            $precioNuevoReal = (float) ($habitacionNueva['precio'] ?? 0);

            $precioNuevoAplicado = $tipoMotivo === 'falla_hotel'
                ? min($precioAnterior, $precioNuevoReal)
                : $precioNuevoReal;

            $diasHabitacionAnterior = ReservaHelper::obtenerDiasEstadia(
                $relacionActual->check_in,
                $fechaEfectivaCobro
            );

            $diasHabitacionNueva = ReservaHelper::obtenerDiasEstadia(
                $fechaEfectivaCobro,
                $checkOut
            );

            $subtotalAnterior = $precioAnterior * $diasHabitacionAnterior;
            $subtotalNuevo = $precioNuevoAplicado * $diasHabitacionNueva;

            DB::connection()->beginTransaction();

            $this->habitacionModel->bloquearParaReserva([
                $idHabitacionActual,
                $idHabitacionNueva,
            ]);

            $disponibilidad = $this->reporteOcupacionModel->validarDisponibilidadHabitacion(
                $idHabitacionNueva,
                $fechaCambio,
                $checkOut,
                $idReserva
            );

            if (!$disponibilidad['disponible']) {
                DB::connection()->rollBack();
                return $this->respuesta(false, 'CONFLICTO', $disponibilidad['mensaje']);
            }

            $totalAnterior = (float) ($reservaActual->total ?? 0);

            $relacionActual->check_out = $fechaEfectivaCobro;
            $relacionActual->activo = 0;
            $relacionActual->estado = 'cambiada';
            $relacionActual->motivo_cambio = $tipoMotivo . ': ' . trim($motivo);
            $relacionActual->id_usuario_movimiento = $idUsuarioActual;
            $relacionActual->fecha_movimiento = $fechaCambio;
            $relacionActual->subtotal = $subtotalAnterior;

            $this->reservaHabitacionModel->guardar($relacionActual);

            $this->reservaHabitacionModel->crear([
                'id_reserva' => $idReserva,
                'id_habitacion' => $idHabitacionNueva,
                'check_in' => $fechaEfectivaCobro,
                'check_out' => $checkOut,
                'activo' => 1,
                'tipo_asignacion' => 'cambio',
                'estado' => 'activa',
                'motivo_cambio' => $tipoMotivo . ': ' . trim($motivo),
                'id_usuario_movimiento' => $idUsuarioActual,
                'fecha_movimiento' => $fechaCambio,
                'precio_aplicado' => $precioNuevoAplicado,
                'subtotal' => $subtotalNuevo,
            ]);

            $estadoHabitacionAnterior = $tipoMotivo === 'falla_hotel'
                ? 'Mantenimiento'
                : 'En Limpieza';
            $datosHabitacionAnterior = [
                'estado' => $estadoHabitacionAnterior,
                'limpieza_inicio' => $estadoHabitacionAnterior === 'En Limpieza'
                    ? $fechaCambio
                    : null,
            ];

            if ($tipoMotivo === 'falla_hotel') {
                $datosHabitacionAnterior['descripcion_habitacion'] = trim($motivo);
            }

            Habitacion::where('id', $idHabitacionActual)->update($datosHabitacionAnterior);

            Habitacion::where('id', $idHabitacionNueva)->update([
                'estado' => 'Ocupada',
            ]);

            $nuevoTotal = $this->reservaHabitacionModel->sumarSubtotales($idReserva);

            $reservaActual->total = $nuevoTotal;
            $reservaActual->observaciones = trim(
                (string) ($reservaActual->observaciones ?? '')
                    . "\nCambio de habitación: Hab. "
                    . ($habitacionAnterior['numero_habitacion'] ?? $idHabitacionActual)
                    . " por Hab. "
                    . ($habitacionNueva['numero_habitacion'] ?? $idHabitacionNueva)
                    . ". Motivo: "
                    . $motivo
            );

            $this->reservaModel->guardar($reservaActual);

            // Calcular Devolución si es que se ha pagado de más y el total bajó
            $sumPagos = (float) ($reservaActual->pagos->sum('monto') ?? 0);
            $sumPenalidades = (float) Devolucion::where('id_reserva', $reservaActual->id)->sum('monto_penalidad');
            $totalPagado = max(0.0, $sumPagos - $sumPenalidades);

            $devolucion = null;
            $montoDevolver = 0.0;
            $montoPenalidad = 0.0;
            $porcentaje = 0.0;

            if ($totalPagado > $nuevoTotal) {
                $montoCancelado = max(0.0, $totalAnterior - $nuevoTotal);
                $hotel = Hotel::first();
                $porcentaje = max(0.0, min(100.0, (float) ($hotel->porcentaje_penalidad_cancelacion ?? 25)));
                $montoPenalidad = round($montoCancelado * ($porcentaje / 100), 1);
                $excesoDevolvible = max(0.0, $totalPagado - $nuevoTotal);
                $montoDevolver = round(min($montoCancelado - $montoPenalidad, $excesoDevolvible), 1);

                if ($montoDevolver > 0.00001) {
                    $descripcionDevolucion = sprintf(
                        'Devolución por cambio de habitación en estadía (Hab. %s por Hab. %s). Total anterior: S/ %s; nuevo total: S/ %s; pagado: S/ %s; penalidad (%s%%): S/ %s.',
                        ($habitacionAnterior['numero_habitacion'] ?? $idHabitacionActual),
                        ($habitacionNueva['numero_habitacion'] ?? $idHabitacionNueva),
                        number_format($totalAnterior, 2),
                        number_format($nuevoTotal, 2),
                        number_format($totalPagado, 2),
                        $porcentaje,
                        number_format($montoPenalidad, 2)
                    );

                    Devolucion::create([
                        'id_reserva' => $idReserva,
                        'fecha_cancelacion' => FechaHotelHelper::ahora(),
                        'fecha_inicio' => $relacionActual->check_in,
                        'fecha_prevista' => $checkOut,
                        'dias_usados' => $diasHabitacionAnterior,
                        'dias_no_usados' => $diasHabitacionNueva,
                        'total_no_ocupado' => $montoCancelado,
                        'porcentaje_penalidad' => $porcentaje,
                        'monto_penalidad' => $montoPenalidad,
                        'monto_devuelto' => $montoDevolver,
                        'id_usuario' => $idUsuarioActual,
                        'descripcion' => $descripcionDevolucion,
                    ]);

                    $devolucion = [
                        'monto_devuelto' => $montoDevolver,
                        'monto_penalidad' => $montoPenalidad,
                        'porcentaje_penalidad' => $porcentaje,
                        'descripcion' => $descripcionDevolucion,
                    ];
                }
            }

            DB::connection()->commit();

            return $this->respuesta(true, 'ACTUALIZADO', 'Cambio de habitación registrado correctamente.', [
                'total_anterior' => $totalAnterior,
                'total_nuevo' => $nuevoTotal,
                'diferencia' => round($nuevoTotal - $totalAnterior, 2),
                'monto_adicional' => max(0, round($nuevoTotal - $totalAnterior, 2)),
                'fecha_cambio_real' => $fechaCambio,
                'fecha_efectiva_cobro' => $fechaEfectivaCobro,
                'reserva' => $this->reservaModel->obtenerReservaPorId($idReserva),
                'devolucion' => $devolucion,
            ]);
        } catch (\Throwable $e) {
            error_log('CambiarHabitacionService::cambiarHabitacion -> ' . $e->getMessage());
            $conexion = DB::connection();

            if ($conexion->getPdo()->inTransaction()) {
                $conexion->rollBack();
            }

            return $this->respuesta(false, 'EXCEPCION', 'No se pudo cambiar la habitación. Intente nuevamente.');
        }
    }

    private function obtenerFechaEfectivaCobro(string $fechaCambio, string $tipoMotivo): string
    {
        try {
            $zonaHoraria = new \DateTimeZone('America/Lima');
            $fecha = new \DateTimeImmutable($fechaCambio, $zonaHoraria);

            if ($tipoMotivo === 'solicitud_cliente' && (int) $fecha->format('H') >= 12) {
                return $fecha->modify('+1 day')->format('Y-m-d') . ' 12:00:00';
            }

            return $fecha->format('Y-m-d') . ' 12:00:00';
        } catch (\Throwable $e) {
            return $fechaCambio;
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
