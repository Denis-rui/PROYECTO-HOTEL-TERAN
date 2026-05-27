<?php

namespace Models;

use Illuminate\Database\Capsule\Manager as DB;
use Models\ComprobanteModel;
use Models\HabitacionModel;
use Models\Entities\Pago;
use Models\Entities\Reserva;
use Models\Entities\ReservaHabitacion;
use Models\ReporteOcupacionModel;
use Helpers\ReservaHelper;

class ReservaNuevaModel
{
    private function obtenerDiasEstadia($checkIn, $checkOut)
    {
        return ReservaHelper::obtenerDiasEstadia($checkIn, $checkOut);
    }

    private function combinarFechaHora($fecha, $hora = null)
    {
        return ReservaHelper::combinarFechaHora($fecha, $hora);
    }

    public function generarCodigoReserva(): string
    {
        $anio = date('Y');
        $prefijo = 'TER-' . $anio . '-';

        $ultimaReserva = Reserva::where('codigo_reserva', 'like', $prefijo . '%')
            ->orderBy('id', 'desc')
            ->first();

        $numero = 1;

        if ($ultimaReserva && !empty($ultimaReserva->codigo_reserva)) {
            $partes = explode('-', $ultimaReserva->codigo_reserva);
            $numero = ((int) end($partes)) + 1;
        }

        return $prefijo . str_pad((string) $numero, 6, '0', STR_PAD_LEFT);
    }

    public function registrarReserva($reserva, $idUsuario = null)
    {
        try {
            $habitacionModel = new HabitacionModel();
            $reporteOcupacionModel = new ReporteOcupacionModel();
            $comprobanteModel = new ComprobanteModel();
            $pago = null;
            $comprobanteData = null;
            $checkIn = $this->combinarFechaHora($reserva['checkIn'] ?? null, $reserva['horaEntrada'] ?? null);
            $checkOut = $this->combinarFechaHora($reserva['checkOut'] ?? null, $reserva['horaSalida'] ?? null);

            $habitacionesIngresadas = $reserva['habitaciones'] ?? [];
            if (is_string($habitacionesIngresadas)) {
                $decoded = json_decode($habitacionesIngresadas, true);
                $habitacionesIngresadas = is_array($decoded) ? $decoded : [];
            }

            if (empty($habitacionesIngresadas) && !empty($reserva['habitacion'])) {
                $habitacionesIngresadas = [$reserva['habitacion']];
            }

            $habitacionesNormalizadas = [];
            $totalCalculado = 0;
            $dias = $this->obtenerDiasEstadia($checkIn, $checkOut);

            if ($dias <= 0) {
                return ['exito' => false, 'mensaje' => 'Rango de fechas inválido.'];
            }

            foreach ($habitacionesIngresadas as $habitacionIngresada) {
                $idHabitacion = is_array($habitacionIngresada)
                    ? (int) ($habitacionIngresada['id'] ?? $habitacionIngresada['id_habitacion'] ?? 0)
                    : (int) $habitacionIngresada;

                if ($idHabitacion <= 0) {
                    continue;
                }

                $disponibilidad = $reporteOcupacionModel->validarDisponibilidadHabitacion(
                    $idHabitacion,
                    $checkIn,
                    $checkOut
                );

                if (!$disponibilidad['disponible']) {
                    return ['exito' => false, 'mensaje' => $disponibilidad['mensaje']];
                }

                $habitacionActual = $habitacionModel->obtenerPorId($idHabitacion);
                if (!$habitacionActual) {
                    return ['exito' => false, 'mensaje' => 'No se encontró una de las habitaciones seleccionadas.'];
                }

                $precioHabitacion = (float) ($habitacionActual['precio'] ?? 0);
                $habitacionesNormalizadas[] = [
                    'id' => $idHabitacion,
                    'habitacion' => $habitacionActual,
                    'precio' => $precioHabitacion,
                ];

                $totalCalculado += $precioHabitacion * $dias;
            }

            if (empty($habitacionesNormalizadas)) {
                return ['exito' => false, 'mensaje' => 'Debe seleccionar al menos una habitación válida.'];
            }

            $pagoInicial = $reserva['pago'] ?? null;
            $montoPagoInicial = is_array($pagoInicial)
                ? (float) ($pagoInicial['monto'] ?? 0)
                : 0;

            if ($montoPagoInicial <= 0) {
                return ['exito' => false, 'mensaje' => 'Debe registrar un pago inicial para realizar la reserva.'];
            }

            $montoMinimoInicial = round($totalCalculado * 0.5, 2);
            if ($montoPagoInicial < $montoMinimoInicial) {
                return [
                    'exito' => false,
                    'mensaje' => 'El pago inicial debe ser al menos el 50% del total de la reserva. Monto mínimo: S/ ' . number_format($montoMinimoInicial, 2)
                ];
            }

            DB::connection()->beginTransaction();

            $reservaCreada = Reserva::create([
                'id_cliente'     => $reserva['cliente'] ?? null,
                'total'          => $totalCalculado,
                'estado'         => $reserva['estado'] ?? 'confirmada',
                'codigo_reserva' => $reserva['codigoReserva'] ?? $this->generarCodigoReserva(),
                'id_usuario'     => $idUsuario ?? ($reserva['usuario'] ?? ($_SESSION['id_usuario'] ?? null)),
                'observaciones'  => $reserva['observaciones'] ?? null,
                'checkin_real'   => null,
                'checkout_real'  => null,
            ]);

            $ok = (bool) $reservaCreada;
            $idReserva = (int) $reservaCreada->id;

            foreach ($habitacionesNormalizadas as $habitacionNormalizada) {
                ReservaHabitacion::create([
                    'id_reserva' => $idReserva,
                    'id_habitacion' => $habitacionNormalizada['id'],
                    'check_in' => $checkIn,
                    'check_out' => $checkOut,
                    'activo' => 1,
                ]);

                $habitacionActual = $habitacionNormalizada['habitacion'];
                $habitacionModel->registrarHistorial(
                    $habitacionNormalizada['id'],
                    $idReserva,
                    $habitacionActual['estado_operativo'] ?? 'disponible',
                    $habitacionActual['estado_operativo'] ?? 'disponible',
                    $habitacionActual['estado_limpieza'] ?? 'limpia',
                    $habitacionActual['estado_limpieza'] ?? 'limpia',
                    'crear_reserva',
                    'Reserva creada',
                    $reserva['usuario'] ?? null
                );
            }

            if (is_array($pagoInicial) && $montoPagoInicial > 0) {
                if ($montoPagoInicial > $totalCalculado) {
                    return ['exito' => false, 'mensaje' => 'El pago inicial no puede ser mayor al total de la reserva.'];
                }

                $pago = Pago::create([
                    'id_reserva' => $idReserva,
                    'monto' => $montoPagoInicial,
                    'descripcion' => $pagoInicial['descripcion'] ?? 'Pago inicial',
                    'fecha_pago' => $pagoInicial['fecha_pago'] ?? date('Y-m-d H:i:s'),
                    'id_metodo_pago' => (int) ($pagoInicial['id_metodo_pago'] ?? 0),
                    'id_usuario' => $idUsuario ?? ($_SESSION['id_usuario'] ?? null),
                ]);

                if (!$pago) {
                    throw new \RuntimeException('No se pudo registrar el pago inicial.');
                }

                $comprobante = $comprobanteModel->crearDesdePago(
                    $pago,
                    ['total' => $totalCalculado],
                    $habitacionesNormalizadas,
                    $idUsuario ?? ($reservaCreada->id_usuario ?? ($_SESSION['id_usuario'] ?? null))
                );

                if (!$comprobante) {
                    throw new \RuntimeException('No se pudo generar el comprobante del pago inicial.');
                }

                $comprobanteData = $comprobanteModel->obtenerPorPago((int) $pago->id);
            }

            DB::connection()->commit();

            return [
                'exito' => $ok,
                'mensaje' => 'Reserva registrada correctamente.',
                'id_reserva' => $idReserva,
                'pago_id' => $pago->id ?? null,
                'comprobante' => $comprobanteData ?? null,
            ];
        } catch (\Exception $e) {
            $conexion = DB::connection();
            if ($conexion->getPdo()->inTransaction()) {
                $conexion->rollBack();
            }
            return ['exito' => false, 'mensaje' => 'Error al registrar reserva: ' . $e->getMessage()];
        }
    }
}
