<?php

namespace Services\Comprobantes;

use Helpers\FechaHotelHelper;
use Models\ComprobanteModel;
use Models\DocumentoElectronicoModel;
use Models\Entities\Pago;
use Models\HabitacionModel;

class ComprobanteService
{
    private ComprobanteModel $comprobanteModel;
    private DocumentoElectronicoModel $documentoElectronicoModel;

    public function __construct()
    {
        $this->comprobanteModel = new ComprobanteModel();
        $this->documentoElectronicoModel = new DocumentoElectronicoModel();
    }

    public function crearDesdePago(
        Pago $pago,
        array $reserva,
        array $habitaciones = [],
        ?int $idUsuario = null
    ) {
        $idUsuarioActual = $idUsuario ?? ($_SESSION['id_usuario'] ?? null);

        $totalReserva = (float) ($reserva['total'] ?? 0);
        $monto = (float) ($pago->monto ?? 0);
        $idMetodo = (int) ($pago->id_metodo_pago ?? 0);

        $montoPagadoAcumulado = $this->comprobanteModel->sumarPagosPorReserva(
            (int) ($pago->id_reserva ?? 0)
        );

        $descripcion = self::formatearDescripcionPago(
            $totalReserva,
            $montoPagadoAcumulado
        );

        return $this->comprobanteModel->crear([
            'id_pago' => (int) $pago->id,
            'numero_ticket' => $this->comprobanteModel->generarNumeroTicket((int) $pago->id),
            'fecha_emision' => FechaHotelHelper::ahora(),
            'descripcion' => $descripcion,
            'total' => $monto,
            'id_forma_pago' => $idMetodo,
            'id_usuario' => $idUsuarioActual,
        ]);
    }

    public function obtenerPorPago(int $idPago): ?array
    {
        $comprobante = $this->comprobanteModel->obtenerEntidadPorPago($idPago);

        return $comprobante
            ? self::formatearComprobante($comprobante)
            : null;
    }

    public function obtenerEmitidosPorReserva(int $idReserva): array
    {
        try {
            $tickets = $this->comprobanteModel
                ->obtenerTicketsPorReserva($idReserva)
                ->map(fn($ticket) => self::formatearTicketEmitido($ticket))
                ->toArray();

            $documentosElectronicos = $this->documentoElectronicoModel
                ->obtenerPorReserva($idReserva);

            $unificados = array_merge($tickets, $documentosElectronicos);

            usort($unificados, static function (array $a, array $b): int {
                return strcmp(
                    (string) ($a['fecha'] ?? ''),
                    (string) ($b['fecha'] ?? '')
                );
            });

            return $unificados;
        } catch (\Throwable $e) {
            return [];
        }
    }

    public static function formatearDescripcionPago(float $totalReserva, float $montoPagadoAcumulado): string
    {
        $montoPagadoAcumulado = max(0.0, $montoPagadoAcumulado);
        $saldoPendiente = max(0.0, $totalReserva - $montoPagadoAcumulado);

        $porcentajePagado = $totalReserva > 0
            ? min(100.0, round(($montoPagadoAcumulado / $totalReserva) * 100, 0))
            : 0.0;

        $estadoPago = $saldoPendiente <= 0.00001
            ? 'Pago total'
            : 'Pago parcial';

        return implode("\n", [
            $estadoPago,
            'Avance de pago: ' . number_format($porcentajePagado, 0) . '%',
            'Saldo pendiente: S/ ' . number_format($saldoPendiente, 2),
        ]);
    }

    public static function formatearTicketEmitido($comprobante): array
    {
        return [
            'id' => (int) $comprobante->id,
            'id_pago' => (int) $comprobante->id_pago,
            'es_documento_electronico' => false,
            'tipo' => 'Ticket',
            'numero' => $comprobante->numero_ticket,
            'fecha' => $comprobante->pago->fecha_pago ?? $comprobante->fecha_emision,
            'estado' => 'emitido',
            'monto' => (float) $comprobante->total,
            'descripcion' => $comprobante->descripcion ?? '',
            'id_forma_pago' => $comprobante->id_forma_pago ?? null,
            'id_usuario' => $comprobante->id_usuario ?? null,
            'enlace' => '',
            'enlace_del_pdf' => '',
            'enlace_del_xml' => '',
            'enlace_del_cdr' => '',
        ];
    }

    public static function formatearComprobante($comprobante): array
    {
        $pago = $comprobante->pago ?? null;
        $reserva = $pago->reserva ?? null;
        $cliente = $reserva->cliente ?? null;
        $usuario = $comprobante->usuario ?? null;

        $habitacionModel = new HabitacionModel();

        $habitaciones = [];

        foreach (($reserva->reservaHabitacion ?? []) as $itemHabitacion) {
            if (!$itemHabitacion) {
                continue;
            }

            $habitacion = $itemHabitacion->habitacion ?? null;

            if (!$habitacion) {
                continue;
            }

            $info = $habitacionModel->obtenerPorId((int) $habitacion->id) ?? [];

            $habitaciones[] = [
                'id' => $habitacion->id,
                'numero_habitacion' => $habitacion->numero_habitacion,
                'piso' => $habitacion->piso,
                'tipo_nombre' => $habitacion->tipo_nombre ?? ($info['tipo_nombre'] ?? ''),
                'precio' => (float) ($info['precio'] ?? 0),
                'dias' => 1,
            ];
        }

        return [
            'id' => $comprobante->id,
            'id_pago' => $comprobante->id_pago,
            'numero_ticket' => $comprobante->numero_ticket,
            'fecha_emision' => $comprobante->fecha_emision,
            'descripcion' => $comprobante->descripcion,
            'total' => (float) $comprobante->total,
            'id_forma_pago' => $comprobante->id_forma_pago,
            'id_usuario' => $comprobante->id_usuario,

            'cliente' => $cliente->nombre_completo ?? '',
            'correo_electronico' => $cliente->correo_electronico ?? '',
            'usuario' => $usuario->nombre_completo ?? '',

            'reserva' => $reserva ? [
                'id' => $reserva->id,
                'codigo_reserva' => $reserva->codigo_reserva,
                'estado' => $reserva->estado,
                'check_in' => $reserva->check_in,
                'check_out' => $reserva->check_out,
                'total' => (float) $reserva->total,
                'habitaciones' => $habitaciones,
            ] : null,

            'pago' => $pago ? [
                'id' => $pago->id,
                'monto' => (float) $pago->monto,
                'fecha_pago' => $pago->fecha_pago,
                'id_metodo_pago' => $pago->id_metodo_pago,
                'descripcion' => $pago->descripcion,
            ] : null,
        ];
    }
}
