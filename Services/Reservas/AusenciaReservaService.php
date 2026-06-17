<?php

namespace Services\Reservas;

use Illuminate\Database\Capsule\Manager as DB;
use Models\ReservaModel;

class AusenciaReservaService
{
    private ReservaModel $reservaModel;

    public function __construct()
    {
        $this->reservaModel = new ReservaModel();
    }

    public function marcarAusente(int $idReserva, ?int $idUsuario = null): array
    {
        try {
            $reservaActual = $this->reservaModel->obtenerReservaSimple($idReserva);

            if (!$reservaActual) {
                return [
                    'exito' => false,
                    'mensaje' => 'Reserva no encontrada.'
                ];
            }

            if ($reservaActual->estado !== 'en_estadia') {
                return [
                    'exito' => false,
                    'mensaje' => 'Solo se puede marcar ausente una reserva en estadía.'
                ];
            }

            DB::connection()->beginTransaction();

            $reservaActual->estado = 'ausente';
            $this->reservaModel->guardar($reservaActual);

            DB::connection()->commit();

            return [
                'exito' => true,
                'mensaje' => 'Reserva marcada como ausente.',
                'reserva' => $this->reservaModel->obtenerReservaPorId($idReserva),
            ];
        } catch (\Throwable $e) {
            $conexion = DB::connection();

            if ($conexion->getPdo()->inTransaction()) {
                $conexion->rollBack();
            }

            return [
                'exito' => false,
                'mensaje' => 'Error al marcar ausente: ' . $e->getMessage()
            ];
        }
    }

    public function marcarRegreso(int $idReserva, ?int $idUsuario = null): array
    {
        try {
            $reservaActual = $this->reservaModel->obtenerReservaSimple($idReserva);

            if (!$reservaActual) {
                return [
                    'exito' => false,
                    'mensaje' => 'Reserva no encontrada.'
                ];
            }

            if ($reservaActual->estado !== 'ausente') {
                return [
                    'exito' => false,
                    'mensaje' => 'Solo se puede marcar regreso de una reserva ausente.'
                ];
            }

            DB::connection()->beginTransaction();

            $reservaActual->estado = 'en_estadia';
            $this->reservaModel->guardar($reservaActual);

            DB::connection()->commit();

            return [
                'exito' => true,
                'mensaje' => 'Reserva marcada como regreso a estadía.',
                'reserva' => $this->reservaModel->obtenerReservaPorId($idReserva),
            ];
        } catch (\Throwable $e) {
            $conexion = DB::connection();

            if ($conexion->getPdo()->inTransaction()) {
                $conexion->rollBack();
            }

            return [
                'exito' => false,
                'mensaje' => 'Error al marcar regreso: ' . $e->getMessage()
            ];
        }
    }
}
