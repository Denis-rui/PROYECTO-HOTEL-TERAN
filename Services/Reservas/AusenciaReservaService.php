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
                return $this->respuesta(false, 'NO_ENCONTRADO', 'Reserva no encontrada.');
            }

            if ($reservaActual->estado !== 'en_estadia') {
                return $this->respuesta(false, 'CONFLICTO', 'Solo se puede marcar ausente una reserva en estadía.');
            }

            DB::connection()->beginTransaction();

            $reservaActual->estado = 'ausente';
            $this->reservaModel->guardar($reservaActual);

            DB::connection()->commit();

            return $this->respuesta(true, 'ACTUALIZADO', 'Reserva marcada como ausente.', [
                'reserva' => $this->reservaModel->obtenerReservaPorId($idReserva),
            ]);
        } catch (\Throwable $e) {
            error_log('AusenciaReservaService::marcarAusente -> ' . $e->getMessage());
            $conexion = DB::connection();

            if ($conexion->getPdo()->inTransaction()) {
                $conexion->rollBack();
            }

            return $this->respuesta(false, 'EXCEPCION', 'No se pudo marcar la reserva como ausente.');
        }
    }

    public function marcarRegreso(int $idReserva, ?int $idUsuario = null): array
    {
        try {
            $reservaActual = $this->reservaModel->obtenerReservaSimple($idReserva);

            if (!$reservaActual) {
                return $this->respuesta(false, 'NO_ENCONTRADO', 'Reserva no encontrada.');
            }

            if ($reservaActual->estado !== 'ausente') {
                return $this->respuesta(false, 'CONFLICTO', 'Solo se puede marcar regreso de una reserva ausente.');
            }

            DB::connection()->beginTransaction();

            $reservaActual->estado = 'en_estadia';
            $this->reservaModel->guardar($reservaActual);

            DB::connection()->commit();

            return $this->respuesta(true, 'ACTUALIZADO', 'Reserva marcada como regreso a estadía.', [
                'reserva' => $this->reservaModel->obtenerReservaPorId($idReserva),
            ]);
        } catch (\Throwable $e) {
            error_log('AusenciaReservaService::marcarRegreso -> ' . $e->getMessage());
            $conexion = DB::connection();

            if ($conexion->getPdo()->inTransaction()) {
                $conexion->rollBack();
            }

            return $this->respuesta(false, 'EXCEPCION', 'No se pudo registrar el regreso de la reserva.');
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
