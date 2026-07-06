<?php

namespace Services\Reservas;

use Illuminate\Database\Capsule\Manager as DB;
use Helpers\ReservaHabitacionHelper;
use Models\Entities\Devolucion;
use Models\Entities\Habitacion;
use Models\HabitacionModel;
use Models\ReservaModel;
use Services\Devoluciones\CalculoDevolucionService;

class CancelarReservaService
{
    private ReservaModel $reservaModel;
    private HabitacionModel $habitacionModel;
    private CalculoDevolucionService $calculoDevolucionService;

    public function __construct()
    {
        $this->reservaModel = new ReservaModel();
        $this->habitacionModel = new HabitacionModel();
        $this->calculoDevolucionService = new CalculoDevolucionService();
    }

    public function cancelarReserva(int $idReserva, string $motivo = '', ?int $idUsuario = null): array
    {
        try {
            $reservaActual = $this->reservaModel->obtenerReservaConHabitacionesYPagos($idReserva);

            if (!$reservaActual) {
                return $this->respuesta(false, 'NO_ENCONTRADO', 'Reserva no encontrada.');
            }

            if (
                in_array($reservaActual->estado, ['cancelada', 'checkout_realizado'], true)
                || !empty($reservaActual->checkout_real)
            ) {
                return $this->respuesta(false, 'CONFLICTO', 'No se puede cancelar una reserva en este estado.');
            }

            $resultadoCalculo = $this->calculoDevolucionService->calcular($idReserva);

            if (!($resultadoCalculo['exito'] ?? false)) {
                return $this->respuesta(false, $resultadoCalculo['codigo'] ?? 'ERROR_INTERNO', 'Error al calcular devolucion: ' . ($resultadoCalculo['mensaje'] ?? 'Desconocido'));
            }

            $calculo = $resultadoCalculo['data'] ?? [];

            DB::connection()->beginTransaction();

            $reservaActual->estado = 'cancelada';

            $reservaActual->observaciones = trim(
                (string) ($reservaActual->observaciones ?? '')
                    . "\nCancelada: " . $motivo
                    . " (No reembolsable: S/ " . number_format((float) $calculo['monto_no_reembolsable'], 2)
                    . "; devolucion: S/ " . number_format((float) $calculo['monto_devuelto'], 2) . ")"
            );

            $this->reservaModel->guardar($reservaActual);

            $estadoHabitacionDestino = !empty($reservaActual->checkin_real)
                ? 'Mantenimiento'
                : 'Disponible';

            foreach ($reservaActual->reservaHabitacion as $reservaHabitacion) {
                if (!ReservaHabitacionHelper::esActiva($reservaHabitacion)) {
                    continue;
                }

                $idHabitacion = (int) $reservaHabitacion->id_habitacion;

                Habitacion::where('id', $idHabitacion)->update([
                    'estado' => $estadoHabitacionDestino,
                ]);
            }

            Devolucion::updateOrCreate(
                ['id_reserva' => $idReserva],
                [
                    'fecha_cancelacion' => $calculo['fecha_cancelacion'],
                    'fecha_inicio' => $calculo['fecha_inicio'],
                    'fecha_prevista' => $calculo['fecha_prevista'],
                    'dias_usados' => (int) $calculo['dias_usados'],
                    'dias_no_usados' => (int) $calculo['dias_no_usados'],
                    'total_no_ocupado' => (float) $calculo['total_no_ocupado'],
                    'porcentaje_penalidad' => (float) $calculo['porcentaje_penalidad'],
                    'monto_penalidad' => (float) $calculo['monto_penalidad'],
                    'monto_devuelto' => (float) $calculo['monto_devuelto'],
                    'id_reserva' => $idReserva,
                    'id_usuario' => $idUsuario ?? ($_SESSION['id_usuario'] ?? null),
                    'descripcion' => trim(
                        $motivo
                            . ' | Hospedaje: S/ ' . number_format((float) $calculo['monto_usado'], 2)
                            . ' | Documentado: S/ ' . number_format((float) $calculo['monto_documentado'], 2)
                            . ' | No reembolsable: S/ ' . number_format((float) $calculo['monto_no_reembolsable'], 2)
                    ),
                ]
            );

            DB::connection()->commit();

            return $this->respuesta(true, 'ACTUALIZADO', 'Reserva cancelada. Devolucion: S/ ' . number_format((float) $calculo['monto_devuelto'], 2)
                . '. Monto no reembolsable: S/ ' . number_format((float) $calculo['monto_no_reembolsable'], 2) . '.', [
                'devolucion' => $calculo,
            ]);
        } catch (\Throwable $e) {
            error_log('CancelarReservaService::cancelarReserva -> ' . $e->getMessage());
            $conexion = DB::connection();

            if ($conexion->getPdo()->inTransaction()) {
                $conexion->rollBack();
            }

            return $this->respuesta(false, 'EXCEPCION', 'No se pudo cancelar la reserva. Intente nuevamente.');
        }
    }

    public function eliminarReservaPendiente(int $idReserva, ?int $idUsuario = null): array
    {
        try {
            $reservaActual = $this->reservaModel->obtenerReservaConHabitacionesYPagos($idReserva);

            if (!$reservaActual) {
                return $this->respuesta(false, 'NO_ENCONTRADO', 'Reserva no encontrada.');
            }

            if ($reservaActual->estado !== 'pendiente') {
                return $this->respuesta(false, 'CONFLICTO', 'Solo se puede eliminar una reserva pendiente de pago.');
            }

            if ($reservaActual->pagos && $reservaActual->pagos->count() > 0) {
                return $this->respuesta(false, 'CONFLICTO', 'La reserva ya tiene pagos registrados.');
            }

            DB::connection()->beginTransaction();

            $reservaActual->estado = 'inactiva';
            $reservaActual->observaciones = trim(
                (string) ($reservaActual->observaciones ?? '')
                    . "\nReserva pendiente eliminada por el usuario."
            );

            $this->reservaModel->guardar($reservaActual);

            foreach ($reservaActual->reservaHabitacion as $reservaHabitacion) {
                if (!ReservaHabitacionHelper::esActiva($reservaHabitacion)) {
                    continue;
                }

                Habitacion::where('id', (int) $reservaHabitacion->id_habitacion)->update([
                    'estado' => 'Disponible',
                ]);
            }

            DB::connection()->commit();

            return $this->respuesta(true, 'ELIMINADO', 'Reserva pendiente eliminada correctamente.');
        } catch (\Throwable $e) {
            error_log('CancelarReservaService::eliminarReservaPendiente -> ' . $e->getMessage());
            $conexion = DB::connection();

            if ($conexion->getPdo()->inTransaction()) {
                $conexion->rollBack();
            }

            return $this->respuesta(false, 'EXCEPCION', 'No se pudo eliminar la reserva pendiente. Intente nuevamente.');
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
