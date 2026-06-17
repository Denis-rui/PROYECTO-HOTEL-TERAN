<?php

namespace Services\Reservas;

use Helpers\FechaHotelHelper;
use Helpers\ReservaHabitacionHelper;
use Models\Entities\Habitacion;
use Models\HabitacionModel;
use Models\ReservaModel;

class ActualizarEstadoReservaService
{
    private ReservaModel $reservaModel;
    private HabitacionModel $habitacionModel;

    private array $estadosPermitidos = [
        'confirmada',
        'en_estadia',
        'checkout_realizado',
        'cancelada'
    ];

    private array $ordenEstados = [
        'confirmada' => 1,
        'en_estadia' => 2,
        'checkout_realizado' => 3,
    ];

    public function __construct()
    {
        $this->reservaModel = new ReservaModel();
        $this->habitacionModel = new HabitacionModel();
    }

    public function actualizarEstadoReserva(int $idReserva, string $nuevoEstado): array
    {
        $estadoNormalizado = strtolower(trim($nuevoEstado));

        if (!in_array($estadoNormalizado, $this->estadosPermitidos, true)) {
            return [
                'exito' => false,
                'mensaje' => 'Estado no permitido.'
            ];
        }

        try {
            $reserva = $this->reservaModel->obtenerReservaConHabitaciones($idReserva);

            if (!$reserva) {
                return [
                    'exito' => false,
                    'mensaje' => 'Reserva no encontrada.'
                ];
            }

            $estadoActual = strtolower(trim((string) $reserva->estado));

            if (
                isset($this->ordenEstados[$estadoActual], $this->ordenEstados[$estadoNormalizado])
                && $this->ordenEstados[$estadoNormalizado] < $this->ordenEstados[$estadoActual]
            ) {
                return [
                    'exito' => false,
                    'mensaje' => 'No se permite volver a un estado anterior.'
                ];
            }

            $fechaHoraActual = FechaHotelHelper::ahora();

            $reserva->estado = $estadoNormalizado;

            if ($estadoNormalizado === 'en_estadia' && empty($reserva->checkin_real)) {
                $reserva->checkin_real = $fechaHoraActual;
            }

            if ($estadoNormalizado === 'checkout_realizado' && empty($reserva->checkout_real)) {
                $reserva->checkout_real = $fechaHoraActual;
            }

            $this->reservaModel->guardar($reserva);

            if ($estadoNormalizado === 'checkout_realizado') {
                foreach (($reserva->reservaHabitacion ?? []) as $reservaHabitacion) {
                    if (!ReservaHabitacionHelper::esActiva($reservaHabitacion)) {
                        continue;
                    }

                    $idHabitacion = (int) $reservaHabitacion->id_habitacion;

                    Habitacion::where('id', $idHabitacion)->update([
                        'estado' => 'Mantenimiento',
                    ]);

                    $estadoAnterior = $reservaHabitacion->habitacion->estado ?? 'Ocupada';

                    $this->habitacionModel->registrarHistorial(
                        $idHabitacion,
                        (int) $idReserva,
                        $estadoAnterior,
                        'Mantenimiento',
                        null,
                        null,
                        'checkout_realizado',
                        'Habitación enviada a mantenimiento tras checkout realizado.'
                    );
                }
            }

            return [
                'exito' => true,
                'mensaje' => 'Estado de la reserva actualizado correctamente.',
                'reserva' => $this->reservaModel->obtenerReservaPorId($idReserva),
            ];
        } catch (\Throwable $e) {
            return [
                'exito' => false,
                'mensaje' => 'Error al actualizar estado: ' . $e->getMessage()
            ];
        }
    }
}
