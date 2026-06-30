<?php

namespace Services\Pagos;

use Illuminate\Database\Capsule\Manager as DB;
use Helpers\FechaHotelHelper;
use Models\PagoModel;
use Models\ReservaModel;
use Services\Comprobantes\ComprobanteService;

class RegistrarPagoService
{
    private ReservaModel $reservaModel;
    private PagoModel $pagoModel;
    private ComprobanteService $comprobanteService;

    public function __construct()
    {
        $this->reservaModel = new ReservaModel();
        $this->pagoModel = new PagoModel();
        $this->comprobanteService = new ComprobanteService();
    }

    public function registrarPago(int $idReserva, float $monto, int $idMetodoPago, string $descripcion = '', ?string $fechaPago = null, ?int $idUsuario = null): array
    {
        try {
            $idUsuarioActual = $idUsuario ?? ($_SESSION['id_usuario'] ?? null);

            $reserva = $this->reservaModel->obtenerReservaPorId($idReserva);

            if (!$reserva) {
                return [
                    'exito' => false,
                    'mensaje' => 'Reserva no encontrada.'
                ];
            }

            if ($monto <= 0) {
                return [
                    'exito' => false,
                    'mensaje' => 'El monto debe ser mayor a cero.'
                ];
            }

            $saldoDisponible = (float) ($reserva['saldo_pendiente'] ?? 0);

            if ($monto > $saldoDisponible + 0.00001) {
                return [
                    'exito' => false,
                    'mensaje' => 'El monto no puede ser mayor al saldo pendiente. Saldo disponible: S/ ' . number_format($saldoDisponible, 2)
                ];
            }

            $fecha = $this->normalizarFechaPago($fechaPago);

            DB::connection()->beginTransaction();

            $pago = $this->pagoModel->crear([
                'id_reserva' => $idReserva,
                'monto' => $monto,
                'descripcion' => $descripcion !== '' ? $descripcion : 'Pago de reserva',
                'fecha_pago' => $fecha,
                'id_metodo_pago' => $idMetodoPago,
                'id_usuario' => $idUsuarioActual,
            ]);

            if (!$pago) {
                throw new \RuntimeException('No se pudo registrar el pago.');
            }

            if (strtolower((string) ($reserva['estado'] ?? '')) === 'pendiente') {
                $reservaEntidad = $this->reservaModel->obtenerReservaSimple($idReserva);
                if ($reservaEntidad) {
                    $reservaEntidad->estado = 'confirmada';
                    $this->reservaModel->guardar($reservaEntidad);
                }
            }

            $habitaciones = $reserva['habitaciones'] ?? [];

            $comprobante = $this->comprobanteService->crearDesdePago(
                $pago,
                $reserva,
                $habitaciones,
                $idUsuarioActual ?? ($reserva['id_usuario'] ?? null)
            );

            if (!$comprobante) {
                throw new \RuntimeException('No se pudo generar el comprobante.');
            }

            $comprobanteData = $this->comprobanteService->obtenerPorPago((int) $pago->id);

            DB::connection()->commit();

            return [
                'exito' => true,
                'mensaje' => 'Pago registrado correctamente.',
                'pago_id' => (int) $pago->id,
                'comprobante' => $comprobanteData,
            ];
        } catch (\Throwable $e) {
            error_log('RegistrarPagoService::registrarPago -> ' . $e->getMessage());
            $conexion = DB::connection();

            if ($conexion->getPdo()->inTransaction()) {
                $conexion->rollBack();
            }

            return [
                'exito' => false,
                'mensaje' => 'No se pudo registrar el pago. Intente nuevamente.'
            ];
        }
    }

    private function normalizarFechaPago(?string $fechaPago): string
    {
        $fechaPago = trim((string) $fechaPago);

        if ($fechaPago === '') {
            return FechaHotelHelper::ahora();
        }

        // Si viene solo fecha: 2026-06-17
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaPago)) {
            $horaActual = substr(FechaHotelHelper::ahora(), 11, 8);
            return $fechaPago . ' ' . $horaActual;
        }

        // Si viene fecha y hora sin segundos: 2026-06-17 15:20
        if (preg_match('/^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}$/', $fechaPago)) {
            return $fechaPago . ':00';
        }

        // Si viene fecha y hora completa: 2026-06-17 15:20:30
        if (preg_match('/^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}:\d{2}$/', $fechaPago)) {
            return $fechaPago;
        }

        return FechaHotelHelper::ahora();
    }
}
