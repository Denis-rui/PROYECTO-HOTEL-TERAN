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
                return [
                    'exito' => false,
                    'mensaje' => 'Solo se puede cambiar habitación de una estadía activa.'
                ];
            }

            $tipoMotivo = strtolower(trim($tipoMotivo));

            if (!in_array($tipoMotivo, ['falla_hotel', 'solicitud_cliente'], true)) {
                return [
                    'exito' => false,
                    'mensaje' => 'Seleccione si el cambio es por falla del hotel o solicitud del cliente.'
                ];
            }

            if (trim($motivo) === '') {
                return [
                    'exito' => false,
                    'mensaje' => 'Debe indicar motivo del cambio de habitación.'
                ];
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
                return [
                    'exito' => false,
                    'mensaje' => 'No se encontró la habitación activa que desea cambiar.'
                ];
            }

            $fechaCambio = FechaHotelHelper::ahora();
            $checkOut = $relacionActual->check_out ?? null;

            if (
                $tipoMotivo === 'solicitud_cliente'
                && substr($fechaCambio, 0, 10) === substr((string) $checkOut, 0, 10)
            ) {
                return [
                    'exito' => false,
                    'mensaje' => 'No se puede cambiar la habitación por solicitud del cliente el mismo día de salida. Primero actualice la fecha de checkout.'
                ];
            }

            $disponibilidad = $this->reporteOcupacionModel->validarDisponibilidadHabitacion(
                $idHabitacionNueva,
                $fechaCambio,
                $checkOut,
                $idReserva
            );

            if (!$disponibilidad['disponible']) {
                return [
                    'exito' => false,
                    'mensaje' => $disponibilidad['mensaje']
                ];
            }

            $habitacionAnterior = $this->habitacionModel->obtenerPorId($idHabitacionActual);
            $habitacionNueva = $this->habitacionModel->obtenerPorId($idHabitacionNueva);

            if (!$habitacionAnterior || !$habitacionNueva) {
                return [
                    'exito' => false,
                    'mensaje' => 'No se encontró la habitación seleccionada.'
                ];
            }

            $precioAnterior = (float) (
                $relacionActual->precio_aplicado
                ?: ($habitacionAnterior['precio'] ?? 0)
            );

            $precioNuevoReal = (float) ($habitacionNueva['precio'] ?? 0);

            $precioNuevoAplicado = $tipoMotivo === 'falla_hotel'
                ? $precioAnterior
                : $precioNuevoReal;

            $diasHabitacionAnterior = ReservaHelper::obtenerDiasEstadia(
                $relacionActual->check_in,
                $fechaCambio
            );

            $diasHabitacionNueva = ReservaHelper::obtenerDiasEstadia(
                $fechaCambio,
                $checkOut
            );

            $subtotalAnterior = $precioAnterior * $diasHabitacionAnterior;
            $subtotalNuevo = $precioNuevoAplicado * $diasHabitacionNueva;

            DB::connection()->beginTransaction();

            $totalAnterior = (float) ($reservaActual->total ?? 0);

            $relacionActual->check_out = $fechaCambio;
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
                'check_in' => $fechaCambio,
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

            $this->habitacionModel->registrarHistorial(
                $idHabitacionActual,
                $idReserva,
                $habitacionAnterior['estado'] ?? 'Ocupada',
                'Mantenimiento',
                null,
                null,
                'cambio_habitacion_salida',
                $motivo,
                $idUsuarioActual
            );

            $this->habitacionModel->registrarHistorial(
                $idHabitacionNueva,
                $idReserva,
                $habitacionNueva['estado'] ?? 'Disponible',
                'Ocupada',
                null,
                null,
                'cambio_habitacion_entrada',
                $motivo,
                $idUsuarioActual
            );

            DB::connection()->commit();

            return [
                'exito' => true,
                'mensaje' => 'Cambio de habitación registrado correctamente.',
                'total_anterior' => $totalAnterior,
                'total_nuevo' => $nuevoTotal,
                'diferencia' => max(0, $nuevoTotal - $totalAnterior),
                'reserva' => $this->reservaModel->obtenerReservaPorId($idReserva),
            ];
        } catch (\Throwable $e) {
            $conexion = DB::connection();

            if ($conexion->getPdo()->inTransaction()) {
                $conexion->rollBack();
            }

            return [
                'exito' => false,
                'mensaje' => 'Error al cambiar habitación: ' . $e->getMessage()
            ];
        }
    }
}
