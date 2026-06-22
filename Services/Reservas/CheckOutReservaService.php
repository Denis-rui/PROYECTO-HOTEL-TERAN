<?php

namespace Services\Reservas;

use Illuminate\Database\Capsule\Manager as DB;
use Helpers\FechaHotelHelper;
use Helpers\ReservaHabitacionHelper;
use Helpers\ReservaHelper;
use Models\Entities\Habitacion;
use Models\HabitacionModel;
use Models\NotificacionModel;
use Models\ReservaModel;

class CheckOutReservaService
{
    private ReservaModel $reservaModel;
    private HabitacionModel $habitacionModel;
    private NotificacionModel $notificacionModel;

    public function __construct()
    {
        $this->reservaModel = new ReservaModel();
        $this->habitacionModel = new HabitacionModel();
        $this->notificacionModel = new NotificacionModel();
    }

    public function confirmarCheckout(int $idReserva, ?int $idUsuario = null,  bool $autorizarSaldo = false,  string $motivoAutorizacion = ''): array
    {
        try {
            $reservaActual = $this->reservaModel->obtenerReservaConHabitacionesYPagos($idReserva);

            if (!$reservaActual) {
                return [
                    'exito' => false,
                    'mensaje' => 'Reserva no encontrada.'
                ];
            }

            if (!in_array($reservaActual->estado, ['en_estadia', 'checkout_pendiente'], true)) {
                return [
                    'exito' => false,
                    'mensaje' => 'Solo se puede hacer checkout de reservas en estadía o checkout pendiente.'
                ];
            }

            $primeraRelacion = $reservaActual->reservaHabitacion->first();
            $checkOutProgramado = $primeraRelacion->check_out ?? null;

            $minutosDemora = max(
                0,
                (int) floor((time() - strtotime((string) $checkOutProgramado)) / 60)
            );

            $cargoTarde = ReservaHelper::calcularCargoCheckoutTarde(
                $minutosDemora,
                (float) $reservaActual->total
            );

            $totalPagado = (float) $reservaActual->pagos->sum('monto');

            $saldoFinal = max(
                0,
                ((float) $reservaActual->total - $totalPagado) + $cargoTarde
            );

            $fechaCheckout = FechaHotelHelper::ahora();

            if ($saldoFinal > 0.01 && !$autorizarSaldo) {
                DB::connection()->beginTransaction();

                $reservaActual->minutos_demora_checkout = $minutosDemora;
                $reservaActual->cargo_checkout_tarde = $cargoTarde;
                $this->reservaModel->guardar($reservaActual);

                DB::connection()->commit();

                return [
                    'exito' => false,
                    'requiere_pago' => true,
                    'mensaje' => 'Existe saldo pendiente de S/ ' . number_format($saldoFinal, 2) . '. Registre el pago completo antes de confirmar el checkout.',
                    'saldo_pendiente' => round($saldoFinal, 2),
                    'cargo_checkout_tarde' => $cargoTarde,
                    'minutos_demora' => $minutosDemora,
                    'reserva' => $this->reservaModel->obtenerReservaPorId($idReserva),
                ];
            }

            DB::connection()->beginTransaction();

            $reservaActual->estado = 'checkout_realizado';
            $reservaActual->checkout_real = $fechaCheckout;
            $reservaActual->minutos_demora_checkout = $minutosDemora;
            $reservaActual->cargo_checkout_tarde = $cargoTarde;

            $reservaActual->observaciones = trim(
                (string) ($reservaActual->observaciones ?? '')
                    . "\n"
                    . ($motivoAutorizacion ? 'Checkout autorizado: ' . $motivoAutorizacion : '')
            );

            $this->reservaModel->guardar($reservaActual);

            foreach ($reservaActual->reservaHabitacion as $reservaHabitacion) {
                if (!ReservaHabitacionHelper::esActiva($reservaHabitacion)) {
                    continue;
                }

                $idHabitacion = (int) $reservaHabitacion->id_habitacion;

                Habitacion::where('id', $idHabitacion)->update([
                    'estado' => 'En Limpieza',
                    'limpieza_inicio' => $fechaCheckout,
                ]);

                $this->habitacionModel->registrarHistorial(
                    $idHabitacion,
                    (int) $idReserva,
                    'Ocupada',
                    'Mantenimiento',
                    null,
                    null,
                    'checkout',
                    'Checkout manual confirmado.',
                    $idUsuario
                );

                $this->notificacionModel->guardarNotificacion(
                    [
                        'id_reserva' => $idReserva,
                        'id_habitacion' => $idHabitacion,
                    ],
                    [
                        'tipo' => 'checkout',
                        'titulo' => 'Checkout confirmado',
                        'mensaje' => "El checkout de la habitación {$reservaHabitacion->habitacion->numero_habitacion} ha sido confirmado. La habitación está en mantenimiento hasta limpieza.",
                        'leida' => 0,
                    ]
                );
            }

            DB::connection()->commit();

            return [
                'exito' => true,
                'mensaje' => 'Checkout confirmado. La habitación quedó en mantenimiento hasta limpieza.',
                'checkout_real' => $fechaCheckout,
                'cargo_checkout_tarde' => $cargoTarde,
                'minutos_demora' => $minutosDemora,
                'reserva' => $this->reservaModel->obtenerReservaPorId($idReserva),
            ];
        } catch (\Throwable $e) {
            $conexion = DB::connection();

            if ($conexion->getPdo()->inTransaction()) {
                $conexion->rollBack();
            }

            return [
                'exito' => false,
                'mensaje' => 'Error al confirmar checkout: ' . $e->getMessage()
            ];
        }
    }
}
