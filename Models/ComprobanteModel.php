<?php
namespace Models;

use Illuminate\Database\Capsule\Manager as DB;
use Models\HabitacionModel;
use Models\Entities\Comprobante;
use Models\Entities\Pago;
use Models\Entities\Reserva;
use Models\DocumentoElectronicoModel;

class ComprobanteModel
{

    public function generarNumeroTicket(?int $idPago = null): string
    {
        $anio = date('Y');
        $prefijo = 'TCK-' . $anio . '-';

        if ($idPago !== null && $idPago > 0) {
            return $prefijo . str_pad((string) $idPago, 6, '0', STR_PAD_LEFT);
        }

        $ultimo = Comprobante::where('numero_ticket', 'like', $prefijo . '%')
            ->orderBy('id', 'desc')
            ->first();

        $numero = 1;
        if ($ultimo && !empty($ultimo->numero_ticket)) {
            $partes = explode('-', $ultimo->numero_ticket);
            $numero = ((int) end($partes)) + 1;
        }

        return $prefijo . str_pad((string) $numero, 6, '0', STR_PAD_LEFT);
    }

    private function obtenerHabitacionesDescripcion(array $habitaciones): array
    {
        $habitacionModel = new HabitacionModel();
        $lineas = [];

        foreach ($habitaciones as $habitacion) {
            if (!is_array($habitacion)) {
                continue;
            }

            $datosHabitacion = $habitacion['habitacion'] ?? $habitacion;
            $idHabitacion = (int) ($habitacion['id'] ?? $datosHabitacion['id'] ?? 0);
            $info = $idHabitacion > 0 ? ($habitacionModel->obtenerPorId($idHabitacion) ?? []) : [];

            $numero = $datosHabitacion['numero_habitacion'] ?? ($info['numero_habitacion'] ?? '');
            $piso = $datosHabitacion['piso'] ?? ($info['piso'] ?? '');
            $tipo = $datosHabitacion['tipo_nombre'] ?? ($info['tipo_nombre'] ?? '');
            $precio = (float) ($habitacion['precio'] ?? ($info['precio'] ?? 0));
            $dias = (int) ($habitacion['dias'] ?? 1);

            $texto = trim(
                'Hab. ' . $numero .
                ($piso !== '' ? ' - Piso ' . $piso : '') .
                ($tipo !== '' ? ' - ' . $tipo : '') .
                ' | Precio unitario/día: S/ ' . number_format($precio, 2) .
                ($dias > 1 ? ' | Días: ' . $dias : '')
            );

            if ($texto !== '') {
                $lineas[] = $texto;
            }
        }

        return $lineas;
    }

    private function formatearDescripcionPago(float $totalReserva, float $montoPagadoAcumulado): string
    {
        $montoPagadoAcumulado = max(0.0, $montoPagadoAcumulado);
        $saldoPendiente = max(0.0, $totalReserva - $montoPagadoAcumulado);
        $porcentajePagado = $totalReserva > 0
            ? min(100.0, round(($montoPagadoAcumulado / $totalReserva) * 100, 0))
            : 0.0;

        $estadoPago = $saldoPendiente <= 0.00001 ? 'Pago total' : 'Pago parcial';

        return implode("\n", [
            $estadoPago,
            'Avance de pago: ' . number_format($porcentajePagado, 0) . '%',
            'Saldo pendiente: S/ ' . number_format($saldoPendiente, 2),
        ]);
    }

    public function crearDesdePago(Pago $pago, array $reserva, array $habitaciones = [], ?int $idUsuario = null): ?Comprobante
    {
        $idUsuarioActual = $idUsuario ?? ($_SESSION['id_usuario'] ?? null);
        $totalReserva = (float) ($reserva['total'] ?? 0);
        $monto = (float) ($pago->monto ?? 0);
        $idMetodo = (int) ($pago->id_metodo_pago ?? 0);
        $metodos = [
            1 => 'Efectivo',
            2 => 'Tarjeta',
            3 => 'Yape / Transferencia',
        ];
        $montoPagadoAcumulado = (float) DB::table('pago')
            ->where('id_reserva', (int) ($pago->id_reserva ?? 0))
            ->sum('monto');

        $descripcion = $this->formatearDescripcionPago(
            $totalReserva,
            $montoPagadoAcumulado
        );

        return Comprobante::create([
            'id_pago' => (int) $pago->id,
            'numero_ticket' => $this->generarNumeroTicket((int) $pago->id),
            'fecha_emision' => date('Y-m-d H:i:s'),
            'descripcion' => $descripcion,
            'total' => $monto,
            'id_forma_pago' => $idMetodo,
            'id_usuario' => $idUsuarioActual,
        ]);
    }

    public function obtenerPorPago($idPago)
    {
        $comprobante = Comprobante::with(['pago.reserva.reservaHabitacion.habitacion', 'usuario'])
            ->where('id_pago', (int) $idPago)
            ->first();

        return $comprobante ? $this->formatearComprobante($comprobante) : null;
    }

    public function obtenerEmitidosPorReserva($idReserva): array
    {
        try {
            $tickets = DB::table('comprobante as c')
                ->join('pago as p', 'p.id', '=', 'c.id_pago')
                ->where('p.id_reserva', (int) $idReserva)
                ->orderBy('p.fecha_pago', 'asc')
                ->orderBy('c.id', 'asc')
                ->select([
                    'c.id',
                    'c.id_pago',
                    'c.numero_ticket',
                    'c.fecha_emision',
                    'c.descripcion',
                    'c.total',
                    'c.id_forma_pago',
                    'c.id_usuario',
                    'p.fecha_pago',
                ])
                ->get()
                ->map(function ($comprobante) {
                    return [
                        'id' => (int) $comprobante->id,
                        'id_pago' => (int) $comprobante->id_pago,
                        'es_documento_electronico' => false,
                        'tipo' => 'Ticket',
                        'numero' => $comprobante->numero_ticket,
                        'fecha' => $comprobante->fecha_pago ?: $comprobante->fecha_emision,
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
                })
                ->toArray();

            $documentoElectronicoModel = new DocumentoElectronicoModel();
            $documentosElectronicos = $documentoElectronicoModel->obtenerEmitidosPorReserva($idReserva);

            $unificados = array_merge($tickets, $documentosElectronicos);
            usort($unificados, static function (array $a, array $b): int {
                return strcmp((string) ($a['fecha'] ?? ''), (string) ($b['fecha'] ?? ''));
            });

            return $unificados;
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function formatearComprobante($comprobante): array
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
