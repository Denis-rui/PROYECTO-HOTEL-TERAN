<?php

namespace Models;

use Illuminate\Database\Capsule\Manager as DB;
use Models\Entities\Hotel;
use Models\Entities\Reserva;

class CalculoDevolucionModel
{
    private function fecha(?string $valor): string
    {
        return substr(trim((string) $valor), 0, 10);
    }

    private function diasRango(string $desde, string $hasta): array
    {
        if ($desde === '' || $hasta === '' || $hasta <= $desde) {
            return [];
        }

        $dias = [];
        $actual = new \DateTimeImmutable($desde);
        $fin = new \DateTimeImmutable($hasta);
        while ($actual < $fin) {
            $dias[] = $actual->format('Y-m-d');
            $actual = $actual->modify('+1 day');
        }

        return $dias;
    }

    private function habitacionesDocumento(object $documento): array
    {
        $habitaciones = json_decode((string) ($documento->habitaciones_json ?? ''), true);
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

    public function calcular(int $idReserva, ?string $fechaCancelacion = null): array
    {
        $reserva = Reserva::with(['reservaHabitacion', 'pagos'])->find($idReserva);
        if (!$reserva) {
            return ['exito' => false, 'mensaje' => 'Reserva no encontrada.'];
        }

        if ($reserva->estado === 'checkout_realizado' || !empty($reserva->checkout_real)) {
            return [
                'exito' => false,
                'mensaje' => 'No se puede cancelar ni devolver dinero de una reserva con checkout realizado.',
            ];
        }

        $fechaCancelacion = $this->fecha($fechaCancelacion ?: date('Y-m-d'));
        $hotel = Hotel::first();
        $porcentaje = max(0.0, min(100.0, (float) ($hotel->porcentaje_penalidad_cancelacion ?? 25)));
        $totalReserva = round((float) $reserva->total + (float) ($reserva->cargo_checkout_tarde ?? 0), 2);
        $montoPagado = round((float) $reserva->pagos->sum('monto'), 2);
        $huboHospedaje = !empty($reserva->checkin_real);

        $noches = [];
        $fechaInicio = '';
        $fechaPrevista = '';
        $habitacionModel = new HabitacionModel();
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

            $fechaInicio = $fechaInicio === '' || $desde < $fechaInicio ? $desde : $fechaInicio;
            $fechaPrevista = $fechaPrevista === '' || $hasta > $fechaPrevista ? $hasta : $fechaPrevista;
            $precio = (float) ($asignacion->precio_aplicado ?? 0);
            if ($precio <= 0) {
                $cantidadDias = max(1, count($this->diasRango($desde, $hasta)));
                $precio = (float) ($asignacion->subtotal ?? 0) / $cantidadDias;
            }
            if ($precio <= 0) {
                $habitacion = $habitacionModel->obtenerPorId($idHabitacion) ?? [];
                $precio = (float) ($habitacion['precio'] ?? 0);
            }

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

        if ($huboHospedaje) {
            foreach ($noches as &$noche) {
                // La noche del mismo día de cancelación también se cobra.
                if ($noche['fecha'] <= $fechaCancelacion) {
                    $noche['usada'] = true;
                }
            }
            unset($noche);
        }

        $documentos = [];
        try {
            $documentos = DB::table('documento_electronico_reserva')
                ->where('id_reserva', $idReserva)
                ->orderBy('id')
                ->get();
        } catch (\Throwable $e) {
            error_log('CalculoDevolucionModel documentos -> ' . $e->getMessage());
        }

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

        $montoUsado = round(min($totalReserva, $montoUsado), 2);
        $montoDocumentado = round(min($totalReserva, $montoDocumentado), 2);
        $montoBaseNoReembolsable = round(min($totalReserva, $montoBaseNoReembolsable), 2);

        if ($huboHospedaje) {
            $basePenalizable = max(0, $totalReserva - $montoBaseNoReembolsable);
            $montoPenalidad = round($basePenalizable * ($porcentaje / 100), 2);
            $montoNoReembolsable = round(min($totalReserva, $montoBaseNoReembolsable + $montoPenalidad), 2);
        } else {
            $montoPenalidad = round($totalReserva * ($porcentaje / 100), 2);
            $montoNoReembolsable = round(min($totalReserva, max($montoDocumentado, $montoPenalidad)), 2);
        }

        $montoDevuelto = round(max(0, $montoPagado - $montoNoReembolsable), 2);
        $totalNoOcupado = round(max(0, $totalReserva - $montoBaseNoReembolsable), 2);

        return [
            'exito' => true,
            'id_reserva' => $idReserva,
            'fecha_cancelacion' => $fechaCancelacion . ' ' . date('H:i:s'),
            'fecha_inicio' => $fechaInicio !== '' ? $fechaInicio . ' 00:00:00' : null,
            'fecha_prevista' => $fechaPrevista !== '' ? $fechaPrevista . ' 00:00:00' : null,
            'hubo_hospedaje' => $huboHospedaje,
            'dias_usados' => count($fechasUsadas),
            'dias_documentados' => count($fechasDocumentadas),
            'dias_no_usados' => count($fechasReembolsables),
            'total_reserva' => $totalReserva,
            'monto_pagado' => $montoPagado,
            'monto_usado' => $montoUsado,
            'monto_documentado' => $montoDocumentado,
            'total_no_ocupado' => $totalNoOcupado,
            'porcentaje_penalidad' => $porcentaje,
            'monto_penalidad' => $montoPenalidad,
            'monto_no_reembolsable' => $montoNoReembolsable,
            'monto_devuelto' => $montoDevuelto,
            'fechas_documentadas' => array_keys($fechasDocumentadas),
        ];
    }
}
