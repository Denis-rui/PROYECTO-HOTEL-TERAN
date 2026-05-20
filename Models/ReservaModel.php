<?php
namespace Models;
use Illuminate\Database\Capsule\Manager as DB;
use Libraries\Core\Model;
use Models\Entities\Habitacion;
use Models\HabitacionModel;
use Models\Entities\Pago;
use Models\Entities\Reserva;
use Models\Entities\ReservaHabitacion;
use PDO;

class ReservaModel extends Model
{
    protected $table = 'reserva';
    private const ESTADOS_ACTIVOS = ['pendiente', 'confirmada', 'checkin_realizado', 'en_estadia', 'checkout_pendiente'];

    public function __construct()
    {
        parent::__construct();
    }

    private function obtenerDiasEstadia($checkIn, $checkOut)
    {
        if (!$checkIn || !$checkOut) {
            return 0;
        }

        $inicio = strtotime((string) $checkIn);
        $fin = strtotime((string) $checkOut);

        if ($inicio === false || $fin === false || $fin <= $inicio) {
            return 0;
        }

        return max(1, (int) ceil(($fin - $inicio) / 86400));
    }

    private function combinarFechaHora($fecha, $hora = null)
    {
        $fecha = trim((string) $fecha);
        $hora = trim((string) $hora);

        if ($fecha === '') {
            return null;
        }

        if ($hora === '') {
            return $fecha;
        }

        return $fecha . ' ' . $hora . ':00';
    }

    public function obtenerReservas()
    {
        try {
            return Reserva::with(['cliente', 'pagos', 'reservaHabitacion.habitacion'])
                ->orderByDesc('id')
                ->get()
                ->map(fn ($reserva) => $this->formatearReserva($reserva))
                ->sortByDesc('check_in')
                ->values()
                ->all();
        } catch (\Throwable $e) {
            throw new \Exception("Error al obtener reservas: " . $e->getMessage());
        }
    }

    public function obtenerReservaPorId($idReserva)
    {
        $reserva = Reserva::with(['cliente', 'habitacion', 'pagos', 'reservaHabitacion'])
            ->find((int) $idReserva);

        return $reserva ? $this->formatearReserva($reserva) : null;
    }

    public function registrarReserva($reserva)
    {
        try {
            $habitacionModel = new HabitacionModel();
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

                $disponibilidad = $habitacionModel->validarDisponibilidadHabitacion(
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

            $totalFinal = $totalCalculado;

            // La fecha de check-in/check-out se guarda por habitación en `reserva_habitacion`.
            $reservaCreada = Reserva::create([
                'id_cliente'      => $reserva['cliente'] ?? null,
                'total'           => $totalFinal,
                'estado'          => $reserva['estado'] ?? 'confirmada',
                'codigo_reserva'  => $reserva['codigoReserva'] ?? $this->generarCodigoReserva(),
                'id_usuario'      => $reserva['usuario'] ?? 1,
                'observaciones'   => $reserva['observaciones'] ?? null,
            ]);

            $ok = (bool) $reservaCreada;
            $idReserva = (int) $reservaCreada->id;

            foreach ($habitacionesNormalizadas as $habitacionNormalizada) {
                ReservaHabitacion::create([
                    'id_reserva'   => $idReserva,
                    'id_habitacion'=> $habitacionNormalizada['id'],
                    'check_in'     => $checkIn,
                    'check_out'    => $checkOut,
                    'activo'       => 1,
                ]);

                DB::table('habitacion')
                    ->where('id', $habitacionNormalizada['id'])
                    ->update([
                        'estado' => 'Ocupada'
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

            if (is_array($pagoInicial)) {
                if ($montoPagoInicial > 0) {
                    if ($montoPagoInicial > $totalFinal) {
                        return ['exito' => false, 'mensaje' => 'El pago inicial no puede ser mayor al total de la reserva.'];
                    }

                    Pago::create([
                        'id_reserva'     => $idReserva,
                        'monto'          => $montoPagoInicial,
                        'descripcion'    => $pagoInicial['descripcion'] ?? 'Pago inicial',
                        'fecha_pago'     => $pagoInicial['fecha_pago'] ?? date('Y-m-d H:i:s'),
                        'id_metodo_pago' => (int) ($pagoInicial['id_metodo_pago'] ?? 0),
                    ]);
                }
            }

            DB::connection()->commit();
            return ['exito' => $ok, 'mensaje' => 'Reserva registrada correctamente.', 'id_reserva' => $idReserva];
        } catch (\Exception $e) {
            $conexion = DB::connection();
            if ($conexion->getPdo()->inTransaction()) {
                $conexion->rollBack();
            }
            return ['exito' => false, 'mensaje' => 'Error al registrar reserva: ' . $e->getMessage()];
        }
    }

    public function registrarPago($idReserva, $monto, $idMetodoPago, $descripcion = '', $fechaPago = null)
    {
        try {
            $reserva = $this->obtenerReservaPorId($idReserva);
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
            $pago = Pago::create([
                'id_reserva'      => (int) $idReserva,
                'monto'           => $monto,
                'descripcion'     => $descripcion,
                'fecha_pago'      => $fecha,
                'id_metodo_pago'  => (int) $idMetodoPago,
            ]);
            $ok = (bool) $pago;

            return ['exito' => $ok, 'mensaje' => $ok ? 'Pago registrado correctamente.' : 'No se pudo registrar el pago.'];
        } catch (\Throwable $e) {
            return ['exito' => false, 'mensaje' => 'Error al registrar pago: ' . $e->getMessage()];
        }
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

    private function formatearReserva($reserva)
    {


    // temporañl
        $cliente = $reserva->cliente;
        
        $reservaHabitacion = $reserva->reservaHabitacion;
        $habitacionesRelacionadas = is_iterable($reservaHabitacion) ? $reservaHabitacion : [$reservaHabitacion];
        $habitaciones = [];
        foreach ($habitacionesRelacionadas as $itemHabitacion) {
            if (!$itemHabitacion) {
                continue;
            }

            $habitacion = $itemHabitacion->habitacion ?? null;
            if ($habitacion) {
                $habitacionModel = new HabitacionModel();
                $info = $habitacionModel->obtenerPorId($habitacion->id) ?? [];
                $precioHabit = (float) ($info['precio'] ?? 0);
                $habitaciones[] = [
                    'id' => $habitacion->id,
                    'numero_habitacion' => $habitacion->numero_habitacion,
                    'piso' => $habitacion->piso,
                    'tipo_nombre' => $habitacion->tipo_nombre ?? '',
                    'precio' => $precioHabit,
                ];
            }
        }

        $pagos = [];
        foreach (($reserva->pagos ?? []) as $pago) {
            $pagos[] = [
                'id' => $pago->id,
                'monto' => (float) $pago->monto,
                'fecha_pago' => $pago->fecha_pago,
                'id_metodo_pago' => $pago->id_metodo_pago ?? null,
                'descripcion' => $pago->descripcion ?? '',
            ];
        }

        $habitacionPrincipal = $habitaciones[0] ?? null;
        $totalPagado = (float) ($reserva->pagos->sum('monto') ?? 0);
        $checkIn = $reserva->check_in ?? ($habitacionesRelacionadas[0]->check_in ?? null);
        $checkOut = $reserva->check_out ?? ($habitacionesRelacionadas[0]->check_out ?? null);
        $estado = $reserva->estado ?? '';
        $total = (float) ($reserva->total ?? 0);
        $cargoTarde = (float) ($reserva->cargo_checkout_tarde ?? 0);
        $saldoPendiente = $total + $cargoTarde - $totalPagado;

        $minutosCheckoutVencido = 0;
        if (
            in_array($estado, ['en_estadia', 'checkout_pendiente'], true)
            && $checkOut
            && strtotime((string) $checkOut) < time()
            && empty($reserva->check_out_real)
        ) {
            $minutosCheckoutVencido = (int) floor((time() - strtotime((string) $checkOut)) / 60);
        }

        return [
            'id' => $reserva->id,
            'codigo_reserva' => $reserva->codigo_reserva,
            'id_cliente' => $reserva->id_cliente,
            'cliente' => $cliente->nombre_completo ?? '',
            'correo_electronico' => $cliente->correo_electronico ?? '',
            'id_habitacion' => $habitacionPrincipal['id'] ?? null,
                'habitacion' => $habitacionPrincipal ? 'Hab. ' . $habitacionPrincipal['numero_habitacion'] . ' - Piso ' . $habitacionPrincipal['piso'] : '',
                'habitaciones' => $habitaciones,
                'piso' => $habitacionPrincipal['piso'] ?? null,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'minutos_demora_checkout' => $reserva->minutos_demora_checkout ?? 0,
            'cargo_checkout_tarde' => $cargoTarde,
            'total' => $total,
            'estado' => $estado,
            'observaciones' => $reserva->observaciones ?? '',
            'total_pagado' => $totalPagado,
            'saldo_pendiente' => $saldoPendiente,
            'porcentaje_pago' => $total + $cargoTarde > 0 ? round(($totalPagado / ($total + $cargoTarde)) * 100, 0) : 0,
            'pagos' => $pagos,
            'minutos_checkout_vencido' => $minutosCheckoutVencido,
        ];
    }

    public function actualizarEstadoReserva($idReserva, $nuevoEstado)
    {
        $stmt = $this->conectar()->prepare("UPDATE reserva SET estado = ? WHERE id = ?");
        return $stmt->execute([$nuevoEstado, (int) $idReserva]);
    }

    public function cancelarReserva($idReserva, $motivo = '')
    {
        try {
            $reserva = $this->obtenerReservaPorId($idReserva);
            if (!$reserva) {
                return ['exito' => false, 'mensaje' => 'Reserva no encontrada.'];
            }

            if (in_array($reserva['estado'], ['cancelada', 'checkout_realizado'])) {
                return ['exito' => false, 'mensaje' => 'No se puede cancelar una reserva en este estado.'];
            }

            // Obtener políticas
            $hotelStmt = $this->conectar()->query("SELECT porcentaje_penalidad_cancelacion FROM hotel LIMIT 1");
            $politica = $hotelStmt->fetch(PDO::FETCH_ASSOC);
            $porcentajePenalidad = (int) ($politica['porcentaje_penalidad_cancelacion'] ?? 25);

            $penalidad = (float) $reserva['total'] * ($porcentajePenalidad / 100);
            $montoPagado = (float) $reserva['total_pagado'];
            
            $reembolso = max(0, $montoPagado - $penalidad);

            $con = $this->conectar();
            $con->beginTransaction();

            // Actualizar estado de reserva
            $stmt = $con->prepare("UPDATE reserva SET estado = 'cancelada', observaciones = CONCAT(COALESCE(observaciones,''), '\nCancelada: ', ?) WHERE id = ?");
            $stmt->execute([$motivo . " (Penalidad aplicada: S/ " . $penalidad . ")", (int) $idReserva]);

            // Liberar habitación
            $stmtHab = $con->prepare("UPDATE habitacion SET estado = 'Disponible' WHERE id = ?");
            $stmtHab->execute([(int) $reserva['id_habitacion']]);

            // Registrar historial
            $habitacionModel = new HabitacionModel();
            $habitacionModel->registrarHistorial((int) $reserva['id_habitacion'], (int) $idReserva, $reserva['estado'], 'Disponible', null, null, 'cancelar_reserva', 'Reserva cancelada. Penalidad: S/ ' . $penalidad);

            if ($reembolso > 0) {
                // Opcional: Registrar un "pago" negativo o una devolución en una tabla de devoluciones si existe
                // Por ahora solo lo mencionamos en observaciones
            }

            $con->commit();
            return ['exito' => true, 'mensaje' => 'Reserva cancelada. Penalidad administrativa: S/ ' . number_format($penalidad, 2)];

        } catch (\Exception $e) {
            $con = $this->conectar();
            if ($con->inTransaction()) $con->rollBack();
            return ['exito' => false, 'mensaje' => 'Error al cancelar reserva: ' . $e->getMessage()];
        }
    }

    public function confirmarCheckIn($idReserva, $idUsuario = null)
    {
        try {
            $reserva = $this->obtenerReservaPorId($idReserva);
            if (!$reserva) {
                return ['exito' => false, 'mensaje' => 'Reserva no encontrada.'];
            }

            if ($reserva['estado'] !== 'confirmada') {
                return ['exito' => false, 'mensaje' => 'Solo se puede confirmar check-in de reservas confirmadas.'];
            }

            $habitacionModel = new HabitacionModel();
            $ocupada = $habitacionModel->obtenerReservaEnEstadiaPorHabitacion((int) $reserva['id_habitacion']);
            if ($ocupada && (int) $ocupada['id'] !== (int) $idReserva) {
                return ['exito' => false, 'mensaje' => 'La habitación está ocupada por otra reserva.'];
            }

            $con = $this->conectar();
            $con->beginTransaction();

            $stmt = $con->prepare("UPDATE reserva SET estado = 'en_estadia', check_in_real = NOW() WHERE id = ?");
            $stmt->execute([(int) $idReserva]);

            $stmtHab = $con->prepare("UPDATE habitacion SET estado = 'Ocupada' WHERE id = ?");
            $stmtHab->execute([(int) $reserva['id_habitacion']]);

            $habitacionModel->registrarHistorial((int) $reserva['id_habitacion'], (int) $idReserva, $reserva['estado'], 'Ocupada', null, null, 'check_in', 'Check-in manual confirmado', $idUsuario);

            $con->commit();
            return ['exito' => true, 'mensaje' => 'Check-in confirmado correctamente.'];
        } catch (\Exception $e) {
            $con = $this->conectar();
            if ($con->inTransaction()) {
                $con->rollBack();
            }
            return ['exito' => false, 'mensaje' => 'Error al confirmar check-in: ' . $e->getMessage()];
        }
    }

    public function confirmarCheckout($idReserva, $idUsuario = null, $autorizarSaldo = false, $motivoAutorizacion = '')
    {
        try {
            $reserva = $this->obtenerReservaPorId($idReserva);
            if (!$reserva) {
                return ['exito' => false, 'mensaje' => 'Reserva no encontrada.'];
            }

            if (!in_array($reserva['estado'], ['en_estadia', 'checkout_pendiente'], true)) {
                return ['exito' => false, 'mensaje' => 'Solo se puede hacer checkout de reservas en estadía o checkout pendiente.'];
            }

            $minutosDemora = max(0, (int) floor((time() - strtotime($reserva['check_out'])) / 60));
            $cargoTarde = $this->calcularCargoCheckoutTarde($minutosDemora, (float) $reserva['total']);
            $saldoFinal = (float) $reserva['saldo_pendiente'] + $cargoTarde;

            if ($saldoFinal > 0.01 && !$autorizarSaldo) {
                return ['exito' => false, 'mensaje' => 'No se puede confirmar checkout porque existe saldo pendiente de S/ ' . number_format($saldoFinal, 2) . '.'];
            }

            $con = $this->conectar();
            $con->beginTransaction();

            $stmt = $con->prepare("UPDATE reserva
                SET estado = 'checkout_realizado',
                    check_out_real = NOW(),
                    minutos_demora_checkout = ?,
                    cargo_checkout_tarde = ?,
                    observaciones = TRIM(CONCAT(COALESCE(observaciones, ''), '\n', ?))
                WHERE id = ?");
            $stmt->execute([
                $minutosDemora,
                $cargoTarde,
                $motivoAutorizacion ? 'Checkout autorizado: ' . $motivoAutorizacion : '',
                (int) $idReserva,
            ]);

            $stmtHab = $con->prepare("UPDATE habitacion
                SET estado = 'Disponible'
                WHERE id = ?");
            $stmtHab->execute([(int) $reserva['id_habitacion']]);

            $habitacionModel = new HabitacionModel();
            $habitacionModel->registrarHistorial((int) $reserva['id_habitacion'], (int) $idReserva, $reserva['estado'], 'Disponible', null, null, 'checkout', 'Checkout manual confirmado.', $idUsuario);
            $this->crearNotificacion('habitacion_limpieza_pendiente', 'Habitación pendiente de limpieza', 'La habitación ' . $reserva['numero_habitacion'] . ' quedó sucia después del checkout.', (int) $idReserva, (int) $reserva['id_habitacion'], (int) $reserva['id_cliente'], 'alta');

            $con->commit();
            return ['exito' => true, 'mensaje' => 'Checkout confirmado. La habitación quedó bloqueada y sucia hasta limpieza.', 'cargo_checkout_tarde' => $cargoTarde, 'minutos_demora' => $minutosDemora];
        } catch (\Exception $e) {
            $con = $this->conectar();
            if ($con->inTransaction()) {
                $con->rollBack();
            }
            return ['exito' => false, 'mensaje' => 'Error al confirmar checkout: ' . $e->getMessage()];
        }
    }

    public function extenderEstadia($idReserva, $nuevoCheckOut, $idUsuario = null)
    {
        try {
            $reserva = $this->obtenerReservaPorId($idReserva);
            if (!$reserva) {
                return ['exito' => false, 'mensaje' => 'Reserva no encontrada.'];
            }

            if (!in_array($reserva['estado'], ['en_estadia', 'checkout_pendiente'], true)) {
                return ['exito' => false, 'mensaje' => 'Solo se puede extender una estadía activa.'];
            }

            if ($this->existeCruceHabitacion((int) $reserva['id_habitacion'], $reserva['check_in'], $nuevoCheckOut, (int) $idReserva)) {
                return ['exito' => false, 'mensaje' => 'No se puede extender porque existe una reserva futura cruzada.'];
            }

            $habitacionModel = new HabitacionModel();

            $habitacionModel = new HabitacionModel();
            $nuevoTotal = $habitacionModel->calcularTotalReserva((int) $reserva['id_habitacion'], $reserva['check_in'], $nuevoCheckOut);
            $stmt = $this->conectar()->prepare("UPDATE reserva SET check_out = ?, total = ?, estado = 'en_estadia' WHERE id = ?");
            $ok = $stmt->execute([$nuevoCheckOut, $nuevoTotal, (int) $idReserva]);

            if ($ok) {
                $habitacionModel->registrarHistorial((int) $reserva['id_habitacion'], (int) $idReserva, $reserva['estado_operativo'], $reserva['estado_operativo'], $reserva['estado_limpieza'], $reserva['estado_limpieza'], 'extension_estadia', 'Checkout extendido hasta ' . $nuevoCheckOut, $idUsuario);
            }

            return ['exito' => $ok, 'mensaje' => $ok ? 'Estadía extendida correctamente.' : 'No se pudo extender la estadía.', 'total' => $nuevoTotal];
        } catch (\Exception $e) {
            return ['exito' => false, 'mensaje' => 'Error al extender estadía: ' . $e->getMessage()];
        }
    }

    public function cambiarHabitacion($idReserva, $idHabitacionNueva, $motivo, $idUsuario = null)
    {
        try {
            $reserva = $this->obtenerReservaPorId($idReserva);
            if (!$reserva || !in_array($reserva['estado'], ['en_estadia', 'checkout_pendiente'], true)) {
                return ['exito' => false, 'mensaje' => 'Solo se puede cambiar habitación de una estadía activa.'];
            }

            if (trim((string) $motivo) === '') {
                return ['exito' => false, 'mensaje' => 'Debe indicar motivo del cambio de habitación.'];
            }

            $habitacionModel = new HabitacionModel();
            $disponibilidad = $habitacionModel->validarDisponibilidadHabitacion($idHabitacionNueva, date('Y-m-d H:i:s'), $reserva['check_out']);
            if (!$disponibilidad['disponible']) {
                return ['exito' => false, 'mensaje' => $disponibilidad['mensaje']];
            }

            $habitacionAnterior = $habitacionModel->obtenerPorId((int) $reserva['id_habitacion']);
            $habitacionNueva = $habitacionModel->obtenerPorId((int) $idHabitacionNueva);

            $con = $this->conectar();
            $con->beginTransaction();

            $con->prepare("UPDATE reserva SET id_habitacion = ? WHERE id = ?")
                ->execute([(int) $idHabitacionNueva, (int) $idReserva]);
            $con->prepare("UPDATE habitacion SET estado_operativo = 'bloqueada', estado = 'Bloqueada', estado_limpieza = 'sucia' WHERE id = ?")
                ->execute([(int) $reserva['id_habitacion']]);
            $con->prepare("UPDATE habitacion SET estado_operativo = 'ocupada', estado = 'Ocupada' WHERE id = ?")
                ->execute([(int) $idHabitacionNueva]);

            $habitacionModel->registrarHistorial((int) $reserva['id_habitacion'], (int) $idReserva, $habitacionAnterior['estado_operativo'], 'bloqueada', $habitacionAnterior['estado_limpieza'], 'sucia', 'cambio_habitacion_salida', $motivo, $idUsuario);
            $habitacionModel->registrarHistorial((int) $idHabitacionNueva, (int) $idReserva, $habitacionNueva['estado_operativo'], 'ocupada', $habitacionNueva['estado_limpieza'], $habitacionNueva['estado_limpieza'], 'cambio_habitacion_entrada', $motivo, $idUsuario);

            $con->commit();
            return ['exito' => true, 'mensaje' => 'Cambio de habitación registrado correctamente.'];
        } catch (\Exception $e) {
            $con = $this->conectar();
            if ($con->inTransaction()) {
                $con->rollBack();
            }
            return ['exito' => false, 'mensaje' => 'Error al cambiar habitación: ' . $e->getMessage()];
        }
    }

    public function obtenerNotificacionesCheckout()
    {
        $sql = "SELECT r.id AS id_reserva, c.id AS id_cliente, c.nombre_completo AS cliente, h.id AS id_habitacion, h.numero_habitacion AS habitacion,
        rh.check_out, TIMESTAMPDIFF(MINUTE, NOW(), rh.check_out) AS minutos_faltantes,
            CASE 
                WHEN NOW() > rh.check_out 
                THEN TIMESTAMPDIFF(MINUTE, rh.check_out, NOW())
                ELSE 0 
            END AS minutos_excedidos
        FROM reserva_habitacion rh
        JOIN reserva r ON r.id = rh.id_reserva
        JOIN cliente c ON c.id = r.id_cliente
        JOIN habitacion h ON h.id = rh.id_habitacion
        WHERE r.estado IN ('en_estadia', 'checkout_pendiente')
        AND rh.check_out IS NOT NULL
        AND rh.activo = 1
        ORDER BY rh.check_out ASC;";
        $stmt = $this->conectar()->prepare($sql);
        $stmt->execute();
        $reservas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($reservas as $reserva) {
            $faltan = (int) $reserva['minutos_faltantes'];
            $excede = (int) $reserva['minutos_excedidos'];

            if ($excede > 0) {
                $this->crearNotificacion('checkout_vencido', 'Checkout vencido', 'La reserva de ' . $reserva['cliente'] . ' excedió el checkout programado.', (int) $reserva['id_reserva'], (int) $reserva['id_habitacion'], (int) $reserva['id_cliente'], 'critica');
            } elseif ($faltan <= 15 && $faltan >= 0) {
                $this->crearNotificacion('checkout_proximo_15m', 'Checkout en 15 minutos', 'La habitación ' . $reserva['habitacion'] . ' tiene checkout próximo.', (int) $reserva['id_reserva'], (int) $reserva['id_habitacion'], (int) $reserva['id_cliente'], 'alta');
            } elseif ($faltan <= 60 && $faltan > 15) {
                $this->crearNotificacion('checkout_proximo_1h', 'Checkout en 1 hora', 'La habitación ' . $reserva['habitacion'] . ' tiene checkout en menos de 1 hora.', (int) $reserva['id_reserva'], (int) $reserva['id_habitacion'], (int) $reserva['id_cliente'], 'media');
            } elseif ($faltan <= 120 && $faltan > 60) {
                $this->crearNotificacion('checkout_proximo_2h', 'Checkout en 2 horas', 'La habitación ' . $reserva['habitacion'] . ' tiene checkout en menos de 2 horas.', (int) $reserva['id_reserva'], (int) $reserva['id_habitacion'], (int) $reserva['id_cliente'], 'media');
            }
        }

        $notificaciones = $this->conectar()->query("SELECT * FROM notificacion WHERE leida = 0 ORDER BY fecha_creacion DESC LIMIT 30")->fetchAll(PDO::FETCH_ASSOC);

        return [
            'proximos' => array_values(array_filter($reservas, fn($r) => (int) $r['minutos_faltantes'] >= 0 && (int) $r['minutos_faltantes'] <= 120)),
            'vencidos' => array_values(array_filter($reservas, fn($r) => (int) $r['minutos_excedidos'] > 0)),
            'notificaciones' => $notificaciones,
        ];
    }

    private function calcularCargoCheckoutTarde($minutosDemora, $totalReserva)
    {
        if ($minutosDemora <= 30) {
            return 0;
        }

        if ($minutosDemora <= 120) {
            return 50;
        }

        return round(max(50, $totalReserva / 2), 2);
    }

    private function existeCruceHabitacion($idHabitacion, $checkIn, $checkOut, $idReservaExcluir = null)
    {
        $params = [$idHabitacion, $checkOut, $checkIn];
        $sql = "SELECT COUNT(*)
                FROM reserva
                WHERE id_habitacion = ?
                  AND estado IN ('pendiente', 'confirmada', 'checkin_realizado', 'en_estadia', 'checkout_pendiente')
                  AND check_in < ?
                  AND check_out > ?";
        if ($idReservaExcluir) {
            $sql .= " AND id <> ?";
            $params[] = $idReservaExcluir;
        }

        $stmt = $this->conectar()->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function crearNotificacion($tipo, $titulo, $mensaje, $idReserva = null, $idHabitacion = null, $idCliente = null, $prioridad = 'media')
    {
        $stmt = $this->conectar()->prepare("INSERT IGNORE INTO notificacion
            (tipo, titulo, mensaje, id_reserva, id_habitacion, id_cliente, leida, fecha_creacion, prioridad)
            VALUES (?, ?, ?, ?, ?, ?, 0, NOW(), ?)");
        return $stmt->execute([$tipo, $titulo, $mensaje, $idReserva, $idHabitacion, $idCliente, $prioridad]);
    }
}
