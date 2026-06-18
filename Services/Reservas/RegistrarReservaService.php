<?php

namespace Services\Reservas;

use Illuminate\Database\Capsule\Manager as DB;
use Helpers\FechaHotelHelper;
use Helpers\HabitacionInputHelper;
use Helpers\ReservaHelper;
use Models\HabitacionModel;
use Models\PagoModel;
use Models\ReporteOcupacionModel;
use Models\ReservaHabitacionModel;
use Models\ReservaModel;
use Services\Comprobantes\ComprobanteService;

class RegistrarReservaService
{
    private ReservaModel $reservaModel;
    private ReservaHabitacionModel $reservaHabitacionModel;
    private HabitacionModel $habitacionModel;
    private ReporteOcupacionModel $reporteOcupacionModel;
    private PagoModel $pagoModel;
    private ComprobanteService $comprobanteService;

    public function __construct()
    {
        $this->reservaModel = new ReservaModel();
        $this->reservaHabitacionModel = new ReservaHabitacionModel();
        $this->habitacionModel = new HabitacionModel();
        $this->reporteOcupacionModel = new ReporteOcupacionModel();
        $this->pagoModel = new PagoModel();
        $this->comprobanteService = new ComprobanteService();
    }

    public function registrarReserva(array $reserva, ?int $idUsuario = null): array
    {
        try {
            $idUsuarioActual = $idUsuario
                ?? ($reserva['usuario'] ?? ($_SESSION['id_usuario'] ?? null));

            $checkIn = ReservaHelper::combinarFechaHora(
                $reserva['checkIn'] ?? null,
                $reserva['horaEntrada'] ?? null
            );

            $checkOut = ReservaHelper::combinarFechaHora(
                $reserva['checkOut'] ?? null,
                $reserva['horaSalida'] ?? null
            );

            $dias = ReservaHelper::obtenerDiasEstadia($checkIn, $checkOut);

            if ($dias <= 0) {
                return [
                    'exito' => false,
                    'mensaje' => 'Rango de fechas inválido.'
                ];
            }

            $idsHabitaciones = HabitacionInputHelper::obtenerIdsDesdeRequest($reserva);

            if (empty($idsHabitaciones)) {
                return [
                    'exito' => false,
                    'mensaje' => 'Debe seleccionar al menos una habitación válida.'
                ];
            }

            $habitacionesNormalizadas = [];
            $totalCalculado = 0;

            foreach ($idsHabitaciones as $idHabitacion) {
                $disponibilidad = $this->reporteOcupacionModel->validarDisponibilidadHabitacion(
                    $idHabitacion,
                    $checkIn,
                    $checkOut
                );

                if (!$disponibilidad['disponible']) {
                    return [
                        'exito' => false,
                        'mensaje' => $disponibilidad['mensaje']
                    ];
                }

                $habitacionActual = $this->habitacionModel->obtenerPorId($idHabitacion);

                if (!$habitacionActual) {
                    return [
                        'exito' => false,
                        'mensaje' => 'No se encontró una de las habitaciones seleccionadas.'
                    ];
                }

                $precioHabitacion = (float) ($habitacionActual['precio'] ?? 0);
                $subtotal = $precioHabitacion * $dias;

                $habitacionesNormalizadas[] = [
                    'id' => $idHabitacion,
                    'habitacion' => $habitacionActual,
                    'precio' => $precioHabitacion,
                    'dias' => $dias,
                    'subtotal' => $subtotal,
                ];

                $totalCalculado += $subtotal;
            }

            $pagoInicial = $reserva['pago'] ?? null;

            $montoPagoInicial = is_array($pagoInicial)
                ? (float) ($pagoInicial['monto'] ?? 0)
                : 0;

            if ($montoPagoInicial <= 0) {
                return [
                    'exito' => false,
                    'mensaje' => 'Debe registrar un pago inicial para realizar la reserva.'
                ];
            }

            $montoMinimoInicial = round($totalCalculado * 0.5, 2);

            if ($montoPagoInicial < $montoMinimoInicial) {
                return [
                    'exito' => false,
                    'mensaje' => 'El pago inicial debe ser al menos el 50% del total de la reserva. Monto mínimo: S/ ' . number_format($montoMinimoInicial, 2)
                ];
            }

            if ($montoPagoInicial > $totalCalculado) {
                return [
                    'exito' => false,
                    'mensaje' => 'El pago inicial no puede ser mayor al total de la reserva.'
                ];
            }

            DB::connection()->beginTransaction();

            $reservaCreada = $this->reservaModel->crear([
                'id_cliente' => $reserva['cliente'] ?? null,
                'total' => $totalCalculado,
                'estado' => $reserva['estado'] ?? 'confirmada',
                'codigo_reserva' => $reserva['codigoReserva'] ?? $this->reservaModel->generarCodigoReserva(),
                'id_usuario' => $idUsuarioActual,
                'observaciones' => $reserva['observaciones'] ?? null,
                'checkin_real' => null,
                'checkout_real' => null,
            ]);

            $idReserva = (int) $reservaCreada->id;

            foreach ($habitacionesNormalizadas as $habitacionNormalizada) {
                $this->reservaHabitacionModel->crear([
                    'id_reserva' => $idReserva,
                    'id_habitacion' => $habitacionNormalizada['id'],
                    'check_in' => $checkIn,
                    'check_out' => $checkOut,
                    'activo' => 1,
                    'tipo_asignacion' => 'original',
                    'estado' => 'activa',
                    'precio_aplicado' => $habitacionNormalizada['precio'],
                    'subtotal' => $habitacionNormalizada['subtotal'],
                    'id_usuario_movimiento' => $idUsuarioActual,
                    'fecha_movimiento' => FechaHotelHelper::ahora(),
                ]);

                $habitacionActual = $habitacionNormalizada['habitacion'];

                $this->habitacionModel->registrarHistorial(
                    $habitacionNormalizada['id'],
                    $idReserva,
                    $habitacionActual['estado_operativo'] ?? 'disponible',
                    $habitacionActual['estado_operativo'] ?? 'disponible',
                    $habitacionActual['estado_limpieza'] ?? 'limpia',
                    $habitacionActual['estado_limpieza'] ?? 'limpia',
                    'crear_reserva',
                    'Reserva creada',
                    $idUsuarioActual
                );
            }

            $pago = $this->pagoModel->crear([
                'id_reserva' => $idReserva,
                'monto' => $montoPagoInicial,
                'descripcion' => $pagoInicial['descripcion'] ?? 'Pago inicial',
                'fecha_pago' => $pagoInicial['fecha_pago'] ?? FechaHotelHelper::ahora(),
                'id_metodo_pago' => (int) ($pagoInicial['id_metodo_pago'] ?? 0),
                'id_usuario' => $idUsuarioActual,
            ]);

            if (!$pago) {
                throw new \RuntimeException('No se pudo registrar el pago inicial.');
            }

            $comprobante = $this->comprobanteService->crearDesdePago(
                $pago,
                ['total' => $totalCalculado],
                $habitacionesNormalizadas,
                $idUsuarioActual
            );

            if (!$comprobante) {
                throw new \RuntimeException('No se pudo generar el comprobante del pago inicial.');
            }

            $comprobanteData = $this->comprobanteService->obtenerPorPago((int) $pago->id);

            DB::connection()->commit();

            return [
                'exito' => true,
                'mensaje' => 'Reserva registrada correctamente.',
                'id_reserva' => $idReserva,
                'pago_id' => (int) $pago->id,
                'comprobante' => $comprobanteData,
            ];
        } catch (\Throwable $e) {
            $conexion = DB::connection();

            if ($conexion->getPdo()->inTransaction()) {
                $conexion->rollBack();
            }

            return [
                'exito' => false,
                'mensaje' => 'Error al registrar reserva: ' . $e->getMessage()
            ];
        }
    }
}
