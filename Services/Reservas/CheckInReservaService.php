<?php

namespace Services\Reservas;

use Illuminate\Database\Capsule\Manager as DB;
use Helpers\FechaHotelHelper;
use Helpers\ReservaHabitacionHelper;
use Models\Entities\Habitacion;
use Models\HabitacionModel;
use Models\ReporteOcupacionModel;
use Models\ReservaModel;

class CheckInReservaService
{
    private ReservaModel $reservaModel;
    private HabitacionModel $habitacionModel;
    private ReporteOcupacionModel $reporteOcupacionModel;

    public function __construct()
    {
        $this->reservaModel = new ReservaModel();
        $this->habitacionModel = new HabitacionModel();
        $this->reporteOcupacionModel = new ReporteOcupacionModel();
    }

    public function confirmarCheckIn(int $idReserva, ?int $idUsuario = null): array
    {
        try {
            $reservaActual = $this->reservaModel->obtenerReservaConHabitaciones($idReserva);
            if (!$reservaActual) {
                return [
                    'exito' => false,
                    'mensaje' => 'Reserva no encontrada.'
                ];
            }
            if ($reservaActual->estado !== 'confirmada') {
                return [
                    'exito' => false,
                    'mensaje' => 'Solo se puede confirmar check-in de reservas confirmadas.'
                ];
            }
            foreach ($reservaActual->reservaHabitacion as $reservaHabitacion) {
                if (!ReservaHabitacionHelper::esActiva($reservaHabitacion)) {
                    continue;
                }

                $ocupada = $this->reporteOcupacionModel->obtenerReser_EstadiaHab(
                    (int) $reservaHabitacion->id_habitacion
                );

                if ($ocupada && (int) $ocupada['id'] !== (int) $idReserva) {
                    $numeroHabitacion = $reservaHabitacion->habitacion->numero_habitacion ?? '';

                    return [
                        'exito' => false,
                        'mensaje' => 'La habitación ' . $numeroHabitacion . ' está ocupada por otra reserva.',
                    ];
                }
            }

            DB::connection()->beginTransaction();
            $fechaCheckin = FechaHotelHelper::ahora();
            $reservaActual->estado = 'en_estadia';
            $reservaActual->checkin_real = $fechaCheckin;
            $this->reservaModel->guardar($reservaActual);
            foreach ($reservaActual->reservaHabitacion as $reservaHabitacion) {
                if (!ReservaHabitacionHelper::esActiva($reservaHabitacion)) {
                    continue;
                }
                $idHabitacion = (int) $reservaHabitacion->id_habitacion;
                Habitacion::where('id', $idHabitacion)->update([
                    'estado' => 'Ocupada',
                ]);
                $this->habitacionModel->registrarHistorial(
                    $idHabitacion,
                    (int) $idReserva,
                    'confirmada',
                    'Ocupada',
                    null,
                    null,
                    'check_in',
                    'Check-in manual confirmado',
                    $idUsuario
                );
            }

            DB::connection()->commit();
            return [
                'exito' => true,
                'mensaje' => 'Check-in confirmado correctamente.',
                'checkin_real' => $fechaCheckin,
                'reserva' => $this->reservaModel->obtenerReservaPorId($idReserva),
            ];
        } catch (\Throwable $e) {
            $conexion = DB::connection();

            if ($conexion->getPdo()->inTransaction()) {
                $conexion->rollBack();
            }
            return [
                'exito' => false,
                'mensaje' => 'Error al confirmar check-in: ' . $e->getMessage()
            ];
        }
    }
}
