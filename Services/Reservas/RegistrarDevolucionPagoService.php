<?php

namespace Services\Reservas;

use Helpers\FechaHotelHelper;
use Illuminate\Database\Capsule\Manager as DB;
use Models\ComprobanteModel;
use Models\Entities\DocumentoElectronico;
use Models\Entities\Pago;
use Models\Entities\Devolucion;
use Models\PagoModel;
use Models\ReservaModel;
use Services\Comprobantes\ComprobanteService;

class RegistrarDevolucionPagoService
{
    private ReservaModel $reservaModel;
    private PagoModel $pagoModel;
    private ComprobanteModel $comprobanteModel;
    private ComprobanteService $comprobanteService;

    public function __construct()
    {
        $this->reservaModel = new ReservaModel();
        $this->pagoModel = new PagoModel();
        $this->comprobanteModel = new ComprobanteModel();
        $this->comprobanteService = new ComprobanteService();
    }

    public function registrar(array $datos, ?int $idUsuario = null): array
    {
        try {
            $validacion = $this->validarSolicitud($datos);
            if (!($validacion['exito'] ?? false)) {
                return $validacion;
            }

            $idReserva = (int) ($datos['id_reserva'] ?? 0);
            $monto = round((float) ($datos['monto'] ?? 0), 2);
            $fechaDesde = $this->normalizarFecha($datos['fecha_desde_devuelta'] ?? '');
            $fechaHasta = $this->normalizarFecha($datos['fecha_hasta_devuelta'] ?? '');

            $reserva = $this->reservaModel->obtenerReservaSimple($idReserva);
            $tipoDevolucion = ($reserva && in_array(strtolower($reserva->estado), ['en_estadia', 'checkout_pendiente']))
                ? 'disminución de estadía'
                : 'modificación de reserva';

            $descripcionOriginal = sprintf(
                'Devolución de dinero al cliente por %s del %s al %s. Movimiento negativo de caja.',
                $tipoDevolucion,
                $fechaDesde,
                $fechaHasta
            );

            $pagoExistente = Pago::where('id_reserva', $idReserva)
                ->where('monto', -$monto)
                ->first();

            if ($pagoExistente) {
                return $this->respuesta(false, 'CONFLICTO', 'Esta devolución ya fue registrada en pagos.');
            }

            $devolucionModel = Devolucion::where('id_reserva', $idReserva)->latest('id')->first();

            $descripcionPago = "";
            $descripcionComprobante = "";

            if ($devolucionModel && !empty($devolucionModel->descripcion)) {
                $descripcionPago = $devolucionModel->descripcion;
                $descripcionComprobante = $devolucionModel->descripcion;
            } else {
                $descripcionPago = $descripcionOriginal;
                $descripcionComprobante = $descripcionOriginal;
            }

            $descripcionComprobante .= "\nImporte devuelto: -S/ " . number_format($monto, 2);

            DB::connection()->beginTransaction();

            $pago = $this->pagoModel->crear([
                'id_reserva' => $idReserva,
                'monto' => -$monto,
                'descripcion' => $descripcionPago,
                'fecha_pago' => FechaHotelHelper::ahora(),
                'id_metodo_pago' => 1,
                'id_usuario' => $idUsuario ?? ($_SESSION['id_usuario'] ?? null),
            ]);

            if (!$pago) {
                throw new \RuntimeException('No se pudo registrar el movimiento de devolución.');
            }

            $comprobante = $this->comprobanteModel->crear([
                'id_pago' => (int) $pago->id,
                'numero_ticket' => $this->comprobanteModel->generarNumeroTicket((int) $pago->id),
                'fecha_emision' => FechaHotelHelper::ahora(),
                'descripcion' => $descripcionComprobante,
                'total' => -$monto,
                'id_forma_pago' => 1,
                'id_usuario' => $idUsuario ?? ($_SESSION['id_usuario'] ?? null),
            ]);

            if (!$comprobante) {
                throw new \RuntimeException('No se pudo generar el comprobante de devolución.');
            }

            $comprobanteData = $this->comprobanteService->obtenerPorPago((int) $pago->id);

            DB::connection()->commit();

            return $this->respuesta(true, 'CREADO', 'Devolución registrada correctamente.', [
                'pago_id' => (int) $pago->id,
                'comprobante' => $comprobanteData,
            ]);
        } catch (\Throwable $e) {
            error_log('RegistrarDevolucionPagoService::registrar -> ' . $e->getMessage());
            $conexion = DB::connection();

            if ($conexion->getPdo()->inTransaction()) {
                $conexion->rollBack();
            }

            return $this->respuesta(false, 'EXCEPCION', 'No se pudo registrar la devolución. Intente nuevamente.');
        }
    }

    public function validarSolicitud(array $datos): array
    {
        $idReserva = (int) ($datos['id_reserva'] ?? 0);
        $monto = round((float) ($datos['monto'] ?? 0), 2);
        $fechaDesde = $this->normalizarFecha($datos['fecha_desde_devuelta'] ?? '');
        $fechaHasta = $this->normalizarFecha($datos['fecha_hasta_devuelta'] ?? '');

        if ($idReserva <= 0 || $monto <= 0) {
            return $this->respuesta(false, 'VALIDACION_ERROR', 'Datos de devolución inválidos.');
        }

        if ($fechaDesde === '' || $fechaHasta === '' || $fechaHasta <= $fechaDesde) {
            return $this->respuesta(false, 'VALIDACION_ERROR', 'El rango de fechas a devolver no es válido.');
        }

        if (!$this->reservaModel->obtenerReservaPorId($idReserva)) {
            return $this->respuesta(false, 'NO_ENCONTRADO', 'Reserva no encontrada.');
        }

        if ($this->existeDocumentoElectronicoEnRango($idReserva, $fechaDesde, $fechaHasta)) {
            return $this->respuesta(false, 'CONFLICTO', 'No se puede registrar la devolución porque ya existe boleta o factura electrónica emitida para las fechas a devolver.');
        }

        return $this->respuesta(true, 'OK', 'La devolución puede registrarse.');
    }

    private function existeDocumentoElectronicoEnRango(int $idReserva, string $fechaDesde, string $fechaHasta): bool
    {
        return DocumentoElectronico::where('id_reserva', $idReserva)
            ->where('fecha_desde', '<', $fechaHasta)
            ->where('fecha_hasta', '>', $fechaDesde)
            ->exists();
    }

    private function normalizarFecha($fecha): string
    {
        $fecha = substr(trim((string) $fecha), 0, 10);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) ? $fecha : '';
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
