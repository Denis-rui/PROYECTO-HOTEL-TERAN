<?php

namespace Services\Reservas;

use Illuminate\Database\Capsule\Manager as DB;
use Helpers\FechaHotelHelper;
use Helpers\ReservaHabitacionHelper;
use Helpers\ReservaHelper;
use Models\Entities\Habitacion;
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

            Habitacion::where('id', $idHabitacionActual)->update([
                'estado' => 'Mantenimiento',
                'limpieza_inicio' => $fechaCambio,
            ]);

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


            DB::connection()->commit();

            return $this->respuesta(true, 'ACTUALIZADO', 'Cambio de habitación registrado correctamente.', [
                'total_anterior' => $totalAnterior,
                'total_nuevo' => $nuevoTotal,
                'diferencia' => round($nuevoTotal - $totalAnterior, 2),
                'monto_adicional' => max(0, round($nuevoTotal - $totalAnterior, 2)),
                'fecha_cambio_real' => $fechaCambio,
                'fecha_efectiva_cobro' => $fechaEfectivaCobro,
                'reserva' => $this->reservaModel->obtenerReservaPorId($idReserva),
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
