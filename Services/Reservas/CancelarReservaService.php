<?php

namespace Services\Reservas;

use Illuminate\Database\Capsule\Manager as DB;
use Helpers\ReservaHabitacionHelper;
use Models\CalculoDevolucionModel;
use Models\Entities\Devolucion;
use Models\Entities\Habitacion;
use Models\HabitacionModel;
use Models\ReservaModel;

class CancelarReservaService
{
    private ReservaModel $reservaModel;
    private HabitacionModel $habitacionModel;
    private CalculoDevolucionModel $calculoDevolucionModel;

    public function __construct()
    {
        $this->reservaModel = new ReservaModel();
        $this->habitacionModel = new HabitacionModel();
        $this->calculoDevolucionModel = new CalculoDevolucionModel();
    }

    public function cancelarReserva(int $idReserva, string $motivo = '', ?int $idUsuario = null): array
    {
        try {
            $reservaActual = $this->reservaModel->obtenerReservaConHabitacionesYPagos($idReserva);

            if (!$reservaActual) {
                return [
                    'exito' => false,
                    'mensaje' => 'Reserva no encontrada.'
                ];
            }

            if (
                in_array($reservaActual->estado, ['cancelada', 'checkout_realizado'], true)
                || !empty($reservaActual->checkout_real)
            ) {
                return [
                    'exito' => false,
                    'mensaje' => 'No se puede cancelar una reserva en este estado.'
                ];
            }

            $calculo = $this->calculoDevolucionModel->obtenerReservaParaCalculo($idReserva);

            if (!$calculo) {
                return [
                    'exito' => false,
                    'mensaje' => 'Error al calcular devolución: ' . ($calculo['mensaje'] ?? 'Desconocido')
                ];
            }

            DB::connection()->beginTransaction();

            $reservaActual->estado = 'cancelada';

            $reservaActual->observaciones = trim(
                (string) ($reservaActual->observaciones ?? '')
                    . "\nCancelada: " . $motivo
                    . " (No reembolsable: S/ " . number_format((float) $calculo['monto_no_reembolsable'], 2)
                    . "; devolución: S/ " . number_format((float) $calculo['monto_devuelto'], 2) . ")"
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

                $this->habitacionModel->registrarHistorial(
                    $idHabitacion,
                    (int) $idReserva,
                    'Ocupada',
                    $estadoHabitacionDestino,
                    null,
                    null,
                    'cancelar_reserva',
                    'Reserva cancelada. Monto no reembolsable: S/ ' . $calculo['monto_no_reembolsable']
                );
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

            return [
                'exito' => true,
                'mensaje' => 'Reserva cancelada. Devolución: S/ ' . number_format((float) $calculo['monto_devuelto'], 2)
                    . '. Monto no reembolsable: S/ ' . number_format((float) $calculo['monto_no_reembolsable'], 2) . '.',
                'devolucion' => $calculo,
            ];
        } catch (\Throwable $e) {
            $conexion = DB::connection();

            if ($conexion->getPdo()->inTransaction()) {
                $conexion->rollBack();
            }

            return [
                'exito' => false,
                'mensaje' => 'Error al cancelar reserva: ' . $e->getMessage()
            ];
        }
    }
}
