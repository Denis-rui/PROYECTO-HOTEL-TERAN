<?php
namespace Models;

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Models\HabitacionModel;
use Models\Entities\Comprobante;
use Models\Entities\Pago;
use Models\Entities\Reserva;

class ComprobanteModel extends Eloquent
{
    protected $table = 'comprobante';
    public $timestamps = false;
    protected $fillable = [
        'id_pago', 'numero_ticket', 'fecha_emision', 'descripcion', 'total', 'id_forma_pago', 'id_usuario'
    ];

    public function pago()
    {
        return $this->belongsTo(Pago::class, 'id_pago');
    }

    public function usuario()
    {
        return $this->belongsTo(\Models\Entities\Usuario::class, 'id_usuario');
    }

    public function generarNumeroTicket(): string
    {
        $anio = date('Y');
        $prefijo = 'TCK-' . $anio . '-';

        $ultimo = self::where('numero_ticket', 'like', $prefijo . '%')
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
            'numero_ticket' => $this->generarNumeroTicket(),
            'fecha_emision' => date('Y-m-d H:i:s'),
            'descripcion' => $descripcion,
            'total' => $monto,
            'id_forma_pago' => $idMetodo,
            'id_usuario' => $idUsuario,
        ]);
    }

    public function obtenerPorPago($idPago)
    {
        $comprobante = Comprobante::with(['pago.reserva.reservaHabitacion.habitacion', 'usuario'])
            ->where('id_pago', (int) $idPago)
            ->first();

        return $comprobante ? $this->formatearComprobante($comprobante) : null;
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
