<?php

namespace Services\Reservas;

use Models\HabitacionModel;
use Models\ReporteOcupacionModel;
use Models\ReservaModel;

class ExtenderEstadiaService
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

    public function extenderEstadia(int $idReserva, string $nuevoCheckOut, ?int $idUsuario = null): array
    {
        try {
            $reservaActual = $this->reservaModel->obtenerReservaConHabitaciones($idReserva);

            if (!$reservaActual) {
                return [
                    'exito' => false,
                    'mensaje' => 'Reserva no encontrada.'
                ];
            }

            if (!in_array($reservaActual->estado, ['en_estadia', 'checkout_pendiente'], true)) {
                return [
                    'exito' => false,
                    'mensaje' => 'Solo se puede extender una estadía activa.'
                ];
            }

            $reservaHabitacion = $reservaActual->reservaHabitacion->first();

            if (!$reservaHabitacion) {
                return [
                    'exito' => false,
                    'mensaje' => 'No se encontró la habitación asociada a la reserva.'
                ];
            }

            $idHabitacionPrincipal = (int) ($reservaHabitacion->id_habitacion ?? 0);
            $checkIn = $reservaHabitacion->check_in ?? null;

            $disponibilidad = $this->reporteOcupacionModel->validarDisponibilidadHabitacion(
                $idHabitacionPrincipal,
                $checkIn,
                $nuevoCheckOut,
                $idReserva
            );

            if (!$disponibilidad['disponible']) {
                return [
                    'exito' => false,
                    'mensaje' => 'No se puede extender porque existe una reserva futura cruzada.'
                ];
            }

            $nuevoTotal = $this->reporteOcupacionModel->calcularTotalReserva(
                $idHabitacionPrincipal,
                $checkIn,
                $nuevoCheckOut
            );

            $reservaHabitacion->check_out = $nuevoCheckOut;
            $reservaHabitacion->save();

            $reservaActual->total = $nuevoTotal;
            $reservaActual->estado = 'en_estadia';

            $ok = $this->reservaModel->guardar($reservaActual);

            if ($ok) {
                $habitacion = $reservaHabitacion->habitacion;

                $estadoOperativo = $habitacion->estado_operativo ?? 'ocupada';
                $estadoLimpieza = $habitacion->estado_limpieza ?? 'limpia';

                $this->habitacionModel->registrarHistorial(
                    $idHabitacionPrincipal,
                    $idReserva,
                    $estadoOperativo,
                    $estadoOperativo,
                    $estadoLimpieza,
                    $estadoLimpieza,
                    'extension_estadia',
                    'Checkout extendido hasta ' . $nuevoCheckOut,
                    $idUsuario
                );
            }

            return [
                'exito' => $ok,
                'mensaje' => $ok
                    ? 'Estadía extendida correctamente.'
                    : 'No se pudo extender la estadía.',
                'total' => $nuevoTotal,
            ];
        } catch (\Throwable $e) {
            return [
                'exito' => false,
                'mensaje' => 'Error al extender estadía: ' . $e->getMessage()
            ];
        }
    }
}
