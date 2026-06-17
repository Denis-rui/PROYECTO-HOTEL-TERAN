<?php

namespace Models;

use Illuminate\Database\Capsule\Manager as DB;
use Models\ComprobanteModel;
use Models\Entities\Pago;

class PagoModel
{
    public function registrarPago($idReserva, $monto, $idMetodoPago, $descripcion = '', $fechaPago = null, $idUsuario = null)
    {
        try {
            $reservaModel = new ReservaModel();
            $reserva = $reservaModel->obtenerReservaPorId($idReserva);

            if (!$reserva) {
                return ['exito' => false, 'mensaje' => 'Reserva no encontrada.'];
            }

            if ((float) $monto <= 0) {
                return ['exito' => false, 'mensaje' => 'El monto debe ser mayor a cero.'];
            }

            $saldoDisponible = (float) ($reserva['saldo_pendiente'] ?? 0);
            if ($monto > $saldoDisponible + 0.00001) {
                return ['exito' => false, 'mensaje' => 'El monto no puede ser mayor al saldo pendiente. Saldo disponible: S/ ' . number_format($saldoDisponible, 2)];
            }

            $fecha = $fechaPago ? $fechaPago . ' ' . date('H:i:s') : date('Y-m-d H:i:s');
            $comprobanteModel = new ComprobanteModel();

            DB::connection()->beginTransaction();

            $pago = Pago::create([
                'id_reserva' => (int) $idReserva,
                'monto' => $monto,
                'descripcion' => $descripcion,
                'fecha_pago' => $fecha,
                'id_metodo_pago' => (int) $idMetodoPago,
                'id_usuario' => $idUsuario ?? ($_SESSION['id_usuario'] ?? null),
            ]);

            if (!$pago) {
                throw new \RuntimeException('No se pudo registrar el pago.');
            }

            $habitaciones = $reserva['habitaciones'] ?? [];
            $comprobante = $comprobanteModel->crearDesdePago(
                $pago,
                $reserva,
                $habitaciones,
                $idUsuario ?? ($reserva['id_usuario'] ?? ($_SESSION['id_usuario'] ?? null))
            );

            if (!$comprobante) {
                throw new \RuntimeException('No se pudo generar el comprobante.');
            }

            DB::connection()->commit();

            return [
                'exito' => true,
                'mensaje' => 'Pago registrado correctamente.',
                'pago_id' => (int) $pago->id,
                'comprobante' => $comprobanteModel->obtenerPorPago((int) $pago->id),
            ];
        } catch (\Throwable $e) {
            $conexion = DB::connection();
            if ($conexion->getPdo()->inTransaction()) {
                $conexion->rollBack();
            }
            return ['exito' => false, 'mensaje' => 'Error al registrar pago: ' . $e->getMessage()];
        }
    }

    public function crear(array $datos): Pago
    {
        return Pago::create($datos);
    }
}
