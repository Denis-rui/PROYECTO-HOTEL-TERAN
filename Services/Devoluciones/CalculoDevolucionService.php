<?php

namespace Services\Devoluciones;

use Helpers\FechaHotelHelper;
use Helpers\ReservaHelper;
use Models\CalculoDevolucionModel;
use Models\HabitacionModel;

class CalculoDevolucionService
{
    private CalculoDevolucionModel $calculoDevolucionModel;
    private HabitacionModel $habitacionModel;

    public function __construct()
    {
        $this->calculoDevolucionModel = new CalculoDevolucionModel();
        $this->habitacionModel = new HabitacionModel();
    }

    public function calcular(int $idReserva, ?string $fechaCancelacion = null): array
    {
        try {
            $reserva = $this->calculoDevolucionModel->obtenerReservaParaCalculo($idReserva);

            if (!$reserva) {
                return [
                    'exito' => false,
                    'codigo' => 'NO_ENCONTRADO',
                    'mensaje' => 'Reserva no encontrada.',
                ];
            }

            if ($reserva->estado === 'checkout_realizado' || !empty($reserva->checkout_real)) {
                return [
                    'exito' => false,
                    'codigo' => 'CONFLICTO',
                    'mensaje' => 'No se puede cancelar ni devolver dinero de una reserva con checkout realizado.',
                ];
            }

            $fechaCancelacion = $this->fecha($fechaCancelacion ?: FechaHotelHelper::hoy());

            $hotel = $this->calculoDevolucionModel->obtenerConfiguracionHotel();

            $porcentaje = max(
                0.0,
                min(100.0, (float) ($hotel->porcentaje_penalidad_cancelacion ?? 25))
            );

            $totalReserva = round(
                (float) $reserva->total + (float) ($reserva->cargo_checkout_tarde ?? 0),
                2
            );

            $montoPagado = round((float) $reserva->pagos->sum('monto'), 2);

            $huboHospedaje = !empty($reserva->checkin_real);

            $resultadoNoches = $this->construirNochesReserva($reserva);

            $noches = $resultadoNoches['noches'];
            $fechaInicio = $resultadoNoches['fecha_inicio'];
            $fechaPrevista = $resultadoNoches['fecha_prevista'];

            if ($huboHospedaje) {
                $this->marcarNochesUsadas($noches, $fechaCancelacion);
            }

            $documentos = $this->calculoDevolucionModel
                ->obtenerDocumentosElectronicosPorReserva($idReserva);

            $this->marcarNochesDocumentadas($noches, $documentos);

            $montos = $this->calcularMontosPorNoches($noches, $totalReserva);

            $montoUsado = $montos['monto_usado'];
            $montoDocumentado = $montos['monto_documentado'];
            $montoBaseNoReembolsable = $montos['monto_base_no_reembolsable'];

            if ($huboHospedaje) {
                $basePenalizable = max(0, $totalReserva - $montoBaseNoReembolsable);

                $montoPenalidad = round($basePenalizable * ($porcentaje / 100), 2);

                $montoNoReembolsable = round(
                    min($totalReserva, $montoBaseNoReembolsable + $montoPenalidad),
                    2
                );
            } else {
                $montoPenalidad = round($totalReserva * ($porcentaje / 100), 2);

                $montoNoReembolsable = round(
                    min($totalReserva, max($montoDocumentado, $montoPenalidad)),
                    2
                );
            }

            $montoDevuelto = round(max(0, $montoPagado - $montoNoReembolsable), 2);

            $totalNoOcupado = round(
                max(0, $totalReserva - $montoBaseNoReembolsable),
                2
            );

            return [
                'exito' => true,
                'codigo' => 'OK',
                'mensaje' => 'Cálculo de devolución realizado correctamente.',
                'data' => [
                    'id_reserva' => $idReserva,
                    'fecha_cancelacion' => $fechaCancelacion . ' ' . substr(FechaHotelHelper::ahora(), 11, 8),
                    'fecha_inicio' => $fechaInicio !== '' ? $fechaInicio . ' 00:00:00' : null,
                    'fecha_prevista' => $fechaPrevista !== '' ? $fechaPrevista . ' 00:00:00' : null,

                    'hubo_hospedaje' => $huboHospedaje,
                    'dias_usados' => count($montos['fechas_usadas']),
                    'dias_documentados' => count($montos['fechas_documentadas']),
                    'dias_no_usados' => count($montos['fechas_reembolsables']),

                    'total_reserva' => $totalReserva,
                    'monto_pagado' => $montoPagado,
                    'monto_usado' => $montoUsado,
                    'monto_documentado' => $montoDocumentado,
                    'total_no_ocupado' => $totalNoOcupado,

                    'porcentaje_penalidad' => $porcentaje,
                    'monto_penalidad' => $montoPenalidad,
                    'monto_no_reembolsable' => $montoNoReembolsable,
                    'monto_devuelto' => $montoDevuelto,

                    'fechas_documentadas' => array_keys($montos['fechas_documentadas']),
                ],
            ];
        } catch (\Throwable $e) {
            error_log('CalculoDevolucionService::calcular -> ' . $e->getMessage());

            return [
                'exito' => false,
                'codigo' => 'ERROR_INTERNO',
                'mensaje' => 'Ocurrió un error interno al calcular la devolución.',
            ];
        }
    }

    private function fecha(?string $valor): string
    {
        return ReservaHelper::normalizarFecha($valor) ?? '';
    }

    private function diasRango(string $desde, string $hasta): array
    {
        if ($desde === '' || $hasta === '' || $hasta <= $desde) {
            return [];
        }

        try {
            $dias = [];
            $actual = new \DateTimeImmutable($desde);
            $fin = new \DateTimeImmutable($hasta);

            while ($actual < $fin) {
                $dias[] = $actual->format('Y-m-d');
                $actual = $actual->modify('+1 day');
            }

            return $dias;
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function construirNochesReserva($reserva): array
    {
        $noches = [];
        $fechaInicio = '';
        $fechaPrevista = '';

        foreach ($reserva->reservaHabitacion as $asignacion) {
            if (!$asignacion || (int) ($asignacion->activo ?? 1) !== 1) {
                continue;
            }

            $desde = $this->fecha((string) ($asignacion->check_in ?? ''));
            $hasta = $this->fecha((string) ($asignacion->check_out ?? ''));
            $idHabitacion = (int) ($asignacion->id_habitacion ?? 0);

            if ($idHabitacion <= 0 || $desde === '' || $hasta === '' || $hasta <= $desde) {
                continue;
            }

            $fechaInicio = $fechaInicio === '' || $desde < $fechaInicio
                ? $desde
                : $fechaInicio;

            $fechaPrevista = $fechaPrevista === '' || $hasta > $fechaPrevista
                ? $hasta
                : $fechaPrevista;

            $precio = $this->obtenerPrecioNoche($asignacion, $idHabitacion, $desde, $hasta);

            foreach ($this->diasRango($desde, $hasta) as $dia) {
                $clave = $idHabitacion . '|' . $dia;

                $noches[$clave] = [
                    'id_habitacion' => $idHabitacion,
                    'fecha' => $dia,
                    'precio' => round(max(0, $precio), 2),
                    'usada' => false,
                    'documentada' => false,
                ];
            }
        }

        return [
            'noches' => $noches,
            'fecha_inicio' => $fechaInicio,
            'fecha_prevista' => $fechaPrevista,
        ];
    }

    private function obtenerPrecioNoche($asignacion, int $idHabitacion, string $desde, string $hasta): float
    {
        $precio = (float) ($asignacion->precio_aplicado ?? 0);

        if ($precio > 0) {
            return $precio;
        }

        $cantidadDias = max(1, count($this->diasRango($desde, $hasta)));
        $subtotal = (float) ($asignacion->subtotal ?? 0);

        if ($subtotal > 0) {
            return $subtotal / $cantidadDias;
        }

        /*
         * Esto está bien en el Service.
         * No está en Helper.
         * El Service puede consultar otro Model para completar datos necesarios.
         */
        $habitacion = $this->habitacionModel->obtenerPorId($idHabitacion) ?? [];

        return (float) ($habitacion['precio'] ?? 0);
    }

    private function marcarNochesUsadas(array &$noches, string $fechaCancelacion): void
    {
        foreach ($noches as &$noche) {
            /*
             * La noche del mismo día de cancelación también se cobra.
             */
            if ($noche['fecha'] <= $fechaCancelacion) {
                $noche['usada'] = true;
            }
        }

        unset($noche);
    }

    private function marcarNochesDocumentadas(array &$noches, $documentos): void
    {
        foreach ($documentos as $documento) {
            $idsHabitaciones = $this->habitacionesDocumento($documento);
            $desde = $this->fecha((string) ($documento->fecha_desde ?? ''));
            $hasta = $this->fecha((string) ($documento->fecha_hasta ?? ''));

            foreach ($this->diasRango($desde, $hasta) as $dia) {
                foreach ($idsHabitaciones as $idHabitacion) {
                    $clave = $idHabitacion . '|' . $dia;

                    if (isset($noches[$clave])) {
                        $noches[$clave]['documentada'] = true;
                    }
                }
            }
        }
    }

    private function habitacionesDocumento(object $documento): array
    {
        $habitaciones = json_decode(
            (string) ($documento->habitaciones_json ?? ''),
            true
        );

        if (!is_array($habitaciones)) {
            return [];
        }

        $ids = [];

        foreach ($habitaciones as $habitacion) {
            $id = (int) ($habitacion['id'] ?? $habitacion['id_habitacion'] ?? 0);

            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    private function calcularMontosPorNoches(array $noches, float $totalReserva): array
    {
        $montoUsado = 0.0;
        $montoDocumentado = 0.0;
        $montoBaseNoReembolsable = 0.0;

        $fechasUsadas = [];
        $fechasDocumentadas = [];
        $fechasReembolsables = [];

        foreach ($noches as $noche) {
            if ($noche['usada']) {
                $montoUsado += $noche['precio'];
                $fechasUsadas[$noche['fecha']] = true;
            }

            if ($noche['documentada']) {
                $montoDocumentado += $noche['precio'];
                $fechasDocumentadas[$noche['fecha']] = true;
            }

            if ($noche['usada'] || $noche['documentada']) {
                $montoBaseNoReembolsable += $noche['precio'];
            } else {
                $fechasReembolsables[$noche['fecha']] = true;
            }
        }

        return [
            'monto_usado' => round(min($totalReserva, $montoUsado), 2),
            'monto_documentado' => round(min($totalReserva, $montoDocumentado), 2),
            'monto_base_no_reembolsable' => round(min($totalReserva, $montoBaseNoReembolsable), 2),

            'fechas_usadas' => $fechasUsadas,
            'fechas_documentadas' => $fechasDocumentadas,
            'fechas_reembolsables' => $fechasReembolsables,
        ];
    }
}
