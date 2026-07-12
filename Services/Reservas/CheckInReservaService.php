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
                return $this->respuesta(false, 'NO_ENCONTRADO', 'Reserva no encontrada.');
            }
            if (!in_array($reservaActual->estado, ['confirmada', 'pre_checkin'], true)) {
                return $this->respuesta(false, 'CONFLICTO', 'Solo se puede confirmar check-in de reservas confirmadas o en pre-check-in.');
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

                    return $this->respuesta(false, 'CONFLICTO', 'La habitación ' . $numeroHabitacion . ' está ocupada por otra reserva.');
                }

                $estadoHabitacion = strtolower((string) ($reservaHabitacion->habitacion->estado ?? ''));
                if (in_array($estadoHabitacion, ['mantenimiento', 'en limpieza'], true)) {
                    $numeroHabitacion = $reservaHabitacion->habitacion->numero_habitacion ?? '';
                    return $this->respuesta(false, 'CONFLICTO', 'La habitación ' . $numeroHabitacion . ' aún no está lista para el check-in.');
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
            }

            DB::connection()->commit();
            return $this->respuesta(true, 'ACTUALIZADO', 'Check-in confirmado correctamente.', [
                'checkin_real' => $fechaCheckin,
                'reserva' => $this->reservaModel->obtenerReservaPorId($idReserva),
            ]);
        } catch (\Throwable $e) {
            error_log('CheckInReservaService::confirmarCheckIn -> ' . $e->getMessage());
            $conexion = DB::connection();

            if ($conexion->getPdo()->inTransaction()) {
                $conexion->rollBack();
            }
            return $this->respuesta(false, 'EXCEPCION', 'No se pudo confirmar el check-in. Intente nuevamente.');
        }
    }

    public function registrarPreCheckIn(int $idReserva, ?int $idUsuario = null): array
    {
        try {
            $reservaActual = $this->reservaModel->obtenerReservaConHabitaciones($idReserva);
            if (!$reservaActual) {
                return $this->respuesta(false, 'NO_ENCONTRADO', 'Reserva no encontrada.');
            }

            if ($reservaActual->estado !== 'confirmada') {
                return $this->respuesta(false, 'CONFLICTO', 'Solo se puede registrar pre-check-in de reservas confirmadas.');
            }

            // Validar ventana horaria del pre-check-in según la fecha de ingreso
            $zona = new \DateTimeZone('America/Lima');
            $ahora = new \DateTimeImmutable('now', $zona);
            $fechaCheckinProgramado = $reservaActual->check_in_programado;

            if ($fechaCheckinProgramado) {
                $checkinDate = new \DateTimeImmutable($fechaCheckinProgramado, $zona);
                $hoyStr = $ahora->format('Y-m-d');
                $checkinStr = $checkinDate->format('Y-m-d');

                if ($hoyStr < $checkinStr) {
                    return $this->respuesta(false, 'CONFLICTO', 'El pre-check-in solo está disponible el mismo día de ingreso a partir de las 12:00 p. m. Se recomienda actualizar la fecha de check-in de la reserva si el cliente llegó antes.');
                }

                if ($hoyStr === $checkinStr) {
                    $inicioPreCheckin = $ahora->setTime(12, 0, 0);
                    $finPreCheckin = $ahora->setTime(13, 40, 0);

                    if ($ahora < $inicioPreCheckin) {
                        return $this->respuesta(false, 'CONFLICTO', 'El pre-check-in estará disponible a partir de las 12:00 p. m. del día de ingreso. Se recomienda actualizar la fecha de check-in de la reserva si el cliente llegó antes.');
                    }

                    if ($ahora >= $finPreCheckin) {
                        return $this->respuesta(false, 'CONFLICTO', 'Desde las 1:40 p. m. corresponde realizar check-in normal.');
                    }
                }

                if ($hoyStr > $checkinStr) {
                    return $this->respuesta(false, 'CONFLICTO', 'Desde las 1:40 p. m. corresponde realizar check-in normal.');
                }
            } else {
                // Sin fecha programada, usar la lógica general
                if (!$this->esAntesCheckinNormal()) {
                    return $this->respuesta(false, 'CONFLICTO', 'Desde las 1:40 p. m. corresponde realizar check-in normal.');
                }
            }

            $fechaPreCheckin = FechaHotelHelper::ahora();
            $reservaActual->estado = 'pre_checkin';
            $reservaActual->observaciones = trim(
                (string) ($reservaActual->observaciones ?? '')
                    . "\nPre-check-in registrado el "
                    . $fechaPreCheckin
                    . ($idUsuario ? ' por usuario #' . $idUsuario : '')
                    . '. Cliente dejó pertenencias antes del check-in normal.'
            );
            $this->reservaModel->guardar($reservaActual);

            return $this->respuesta(true, 'ACTUALIZADO', 'Pre-check-in registrado correctamente.', [
                'fecha_pre_checkin' => $fechaPreCheckin,
                'reserva' => $this->reservaModel->obtenerReservaPorId($idReserva),
            ]);
        } catch (\Throwable $e) {
            error_log('CheckInReservaService::registrarPreCheckIn -> ' . $e->getMessage());
            return $this->respuesta(false, 'EXCEPCION', 'No se pudo registrar el pre-check-in. Intente nuevamente.');
        }
    }

    private function esAntesCheckinNormal(): bool
    {
        $ahora = new \DateTimeImmutable('now', new \DateTimeZone('America/Lima'));
        $limite = $ahora->setTime(13, 40, 0);
        return $ahora < $limite;
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
