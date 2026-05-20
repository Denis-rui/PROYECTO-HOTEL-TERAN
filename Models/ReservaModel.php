<?php
namespace Models;
use Illuminate\Database\Capsule\Manager as DB;
use Libraries\Core\Model;
use Models\ComprobanteModel;
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

        $inicioTexto = substr(trim((string) $checkIn), 0, 10);
        $finTexto = substr(trim((string) $checkOut), 0, 10);

        $inicio = \DateTime::createFromFormat('Y-m-d', $inicioTexto);
        $fin = \DateTime::createFromFormat('Y-m-d', $finTexto);

        if (!$inicio || !$fin || $fin <= $inicio) {
            return 0;
        }

        return (int) $inicio->diff($fin)->days;
    }

    private function combinarFechaHora($fecha, $hora = null)
    {
        $fecha = trim((string) $fecha);
        $hora = trim((string) $hora);

        if ($fecha === '') {
            return null;
        }

        if ($hora === '') {
            return $fecha . ' 12:00:00';
        }

        return $fecha . ' ' . $hora . ':00';
    }

    public function obtenerReservas()
    {
        try {
            return Reserva::with(['cliente', 'pagos', 'reservaHabitacion.habitacion'])
                ->where('estado', '!=', 'cancelada')
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

    public function registrarReserva($reserva, $idUsuario = null)
    {
        try {
            $habitacionModel = new HabitacionModel();
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

                // No cambiar el estado de la habitación al crear la reserva.
                // La habitación pasará a 'Ocupada' cuando se confirme el check-in.

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

                    $pago = Pago::create([
                        'id_reserva'     => $idReserva,
                        'monto'          => $montoPagoInicial,
                        'descripcion'    => $pagoInicial['descripcion'] ?? 'Pago inicial',
                        'fecha_pago'     => $pagoInicial['fecha_pago'] ?? date('Y-m-d H:i:s'),
                        'id_metodo_pago' => (int) ($pagoInicial['id_metodo_pago'] ?? 0),
                    ]);

                    if (!$pago) {
                        throw new \RuntimeException('No se pudo registrar el pago inicial.');
                    }

                    $comprobante = $comprobanteModel->crearDesdePago(
                        $pago,
                        ['total' => $totalFinal],
                        $habitacionesNormalizadas,
                        (int) ($idUsuario ?? $reservaCreada->id_usuario ?? 1)
                    );

                    if (!$comprobante) {
                        throw new \RuntimeException('No se pudo generar el comprobante del pago inicial.');
                    }

                    $comprobanteData = $comprobanteModel->obtenerPorPago((int) $pago->id);
                }
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

    public function actualizarReserva($datos)
    {
        try {
            $idReserva = (int) ($datos['id_reserva'] ?? 0);
            if ($idReserva <= 0) {
                return ['exito' => false, 'mensaje' => 'No se recibió el ID de la reserva.'];
            }

            $reservaActual = Reserva::with(['pagos', 'reservaHabitacion.habitacion'])->find($idReserva);
            if (!$reservaActual) {
                return ['exito' => false, 'mensaje' => 'Reserva no encontrada.'];
            }

            $habitacionModel = new HabitacionModel();
            $checkIn = $this->combinarFechaHora($datos['checkIn'] ?? null, $datos['horaEntrada'] ?? null);
            $checkOut = $this->combinarFechaHora($datos['checkOut'] ?? null, $datos['horaSalida'] ?? null);

            $habitacionesActuales = $reservaActual->reservaHabitacion ?? [];
            $idsHabitacionesActuales = [];
            foreach ($habitacionesActuales as $itemHabitacionActual) {
                if ($itemHabitacionActual && !empty($itemHabitacionActual->id_habitacion)) {
                    $idsHabitacionesActuales[] = (int) $itemHabitacionActual->id_habitacion;
                }
            }

            $habitacionesIngresadas = $datos['habitaciones'] ?? [];
            if (is_string($habitacionesIngresadas)) {
                $decoded = json_decode($habitacionesIngresadas, true);
                $habitacionesIngresadas = is_array($decoded) ? $decoded : [];
            }

            if (empty($habitacionesIngresadas) && !empty($datos['habitacion'])) {
                $habitacionesIngresadas = [$datos['habitacion']];
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

                $esHabitacionYaAsignada = in_array($idHabitacion, $idsHabitacionesActuales, true);
                if (!$esHabitacionYaAsignada) {
                    $disponibilidad = $habitacionModel->validarDisponibilidadHabitacion(
                        $idHabitacion,
                        $checkIn,
                        $checkOut,
                        $idReserva
                    );

                    if (!$disponibilidad['disponible']) {
                        return ['exito' => false, 'mensaje' => $disponibilidad['mensaje']];
                    }
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

            $totalPagado = (float) ($reservaActual->pagos->sum('monto') ?? 0);
            if ($totalPagado > $totalCalculado + 0.00001) {
                return [
                    'exito' => false,
                    'mensaje' => 'No se puede dejar un total menor al monto ya pagado. Total pagado: S/ ' . number_format($totalPagado, 2)
                ];
            }

            $habitacionesAnteriores = $reservaActual->reservaHabitacion ?? [];
            $idsHabitacionesAnteriores = [];
            $mapHabitacionFechas = [];
            foreach ($habitacionesAnteriores as $itemHabitacion) {
                if ($itemHabitacion && !empty($itemHabitacion->id_habitacion)) {
                    $idHab = (int) $itemHabitacion->id_habitacion;
                    $idsHabitacionesAnteriores[] = $idHab;
                    $mapHabitacionFechas[$idHab] = [
                        'check_in' => $itemHabitacion->check_in ?? null,
                        'check_out' => $itemHabitacion->check_out ?? null,
                    ];
                }
            }

            $idsHabitacionesNuevas = array_values(array_unique(array_map(
                fn ($item) => (int) $item['id'],
                $habitacionesNormalizadas
            )));

            DB::connection()->beginTransaction();

            $reservaActual->update([
                'id_cliente' => (int) ($datos['cliente'] ?? $datos['id_cliente'] ?? $reservaActual->id_cliente),
                'total' => $totalCalculado,
                'observaciones' => $datos['observaciones'] ?? $reservaActual->observaciones,
            ]);

            DB::table('reserva_habitacion')
                ->where('id_reserva', $idReserva)
                ->delete();

            foreach ($habitacionesNormalizadas as $habitacionNormalizada) {
                ReservaHabitacion::create([
                    'id_reserva'    => $idReserva,
                    'id_habitacion' => $habitacionNormalizada['id'],
                    'check_in'      => $checkIn,
                    'check_out'     => $checkOut,
                    'activo'        => 1,
                ]);

                    // No cambiar el estado de la habitación al actualizar la reserva.
                    // El estado se actualiza al confirmar el check-in.
            }

            // Decidir estado de habitaciones que fueron removidas de la reserva
            foreach ($idsHabitacionesAnteriores as $idHabitacionAnterior) {
                if (in_array($idHabitacionAnterior, $idsHabitacionesNuevas, true)) {
                    continue;
                }

                $sigueOcupada = DB::table('reserva_habitacion as rh')
                    ->join('reserva as r', 'r.id', '=', 'rh.id_reserva')
                    ->where('rh.id_habitacion', $idHabitacionAnterior)
                    ->where('rh.activo', 1)
                    ->whereIn('r.estado', self::ESTADOS_ACTIVOS)
                    ->exists();

                if ($sigueOcupada) {
                    // Hay otra reserva activa que ocupa o reservó la habitación: no cambiamos su estado aquí.
                    continue;
                }

                // Determinar si la habitación removida corresponde a fechas actuales (durante la estadía)
                $fechas = $mapHabitacionFechas[$idHabitacionAnterior] ?? null;
                $hoy = date('Y-m-d');
                $nuevoEstado = null; // null => no cambiar

                if ($fechas && !empty($fechas['check_in']) && !empty($fechas['check_out'])) {
                    $checkInFecha = substr(trim((string) $fechas['check_in']), 0, 10);
                    $checkOutFecha = substr(trim((string) $fechas['check_out']), 0, 10);

                    if ($checkInFecha !== '' && $checkOutFecha !== '' && $hoy >= $checkInFecha && $hoy < $checkOutFecha) {
                        // Si la eliminación ocurre dentro del periodo original de la reserva, enviar a mantenimiento
                        $nuevoEstado = 'Mantenimiento';
                    }
                }

                // Solo actualizar el estado si determinamos un nuevo estado (p.ej. Mantenimiento).
                // Si $nuevoEstado es null, dejamos la habitación tal como está.
                if ($nuevoEstado !== null) {
                    DB::table('habitacion')
                        ->where('id', $idHabitacionAnterior)
                        ->update([
                            'estado' => $nuevoEstado
                        ]);

                    // Registrar historial de movimiento de habitación
                    try {
                        $habitacionActual = $habitacionModel->obtenerPorId((int) $idHabitacionAnterior) ?? [];
                        $estadoAnterior = $habitacionActual['estado'] ?? 'Ocupada';
                        $habitacionModel->registrarHistorial(
                            (int) $idHabitacionAnterior,
                            (int) $idReserva,
                            $estadoAnterior,
                            $nuevoEstado,
                            null,
                            null,
                            'editar_reserva_quitar_habitacion',
                            'Habitación removida de reserva: estado ajustado según fechas.'
                        );
                    } catch (\Throwable $e) {
                       
                    }
                }
            }

            DB::connection()->commit();

            return [
                'exito' => true,
                'mensaje' => 'Reserva actualizada correctamente.',
                'id_reserva' => $idReserva,
                'reserva' => $this->obtenerReservaPorId($idReserva),
            ];
        } catch (\Throwable $e) {
            $conexion = DB::connection();
            if ($conexion->getPdo()->inTransaction()) {
                $conexion->rollBack();
            }

            return ['exito' => false, 'mensaje' => 'Error al actualizar reserva: ' . $e->getMessage()];
        }
    }

    public function registrarPago($idReserva, $monto, $idMetodoPago, $descripcion = '', $fechaPago = null, $idUsuario = null)
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
            $comprobanteModel = new ComprobanteModel();

            DB::connection()->beginTransaction();

            $pago = Pago::create([
                'id_reserva'      => (int) $idReserva,
                'monto'           => $monto,
                'descripcion'     => $descripcion,
                'fecha_pago'      => $fecha,
                'id_metodo_pago'  => (int) $idMetodoPago,
            ]);

            if (!$pago) {
                throw new \RuntimeException('No se pudo registrar el pago.');
            }

            $habitaciones = $reserva['habitaciones'] ?? [];
            $comprobante = $comprobanteModel->crearDesdePago(
                $pago,
                $reserva,
                $habitaciones,
                (int) ($idUsuario ?? $reserva['id_usuario'] ?? 1)
            );

            if (!$comprobante) {
                throw new \RuntimeException('No se pudo generar el comprobante.');
            }

            DB::connection()->commit();

            return [
                'exito' => true,
                'mensaje' => 'Pago registrado correctamente.',
                'pago_id' => (int) $pago->id,
                'comprobante' => $comprobanteModel->obtenerPorPago((int) $pago->id),
            ];
        } catch (\Throwable $e) {
            $conexion = DB::connection();
            if ($conexion->getPdo()->inTransaction()) {
                $conexion->rollBack();
            }
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
        $estadoNormalizado = strtolower(trim((string) $nuevoEstado));
        $estadosPermitidos = ['confirmada', 'en_estadia', 'checkout_realizado', 'cancelada'];
        $ordenEstados = [
            'confirmada' => 1,
            'en_estadia' => 2,
            'checkout_realizado' => 3,
        ];

        if (!in_array($estadoNormalizado, $estadosPermitidos, true)) {
            return ['exito' => false, 'mensaje' => 'Estado no permitido.'];
        }

        try {
            $reserva = Reserva::with(['reservaHabitacion.habitacion'])->find((int) $idReserva);
            if (!$reserva) {
                return ['exito' => false, 'mensaje' => 'Reserva no encontrada.'];
            }

            $estadoActual = strtolower(trim((string) $reserva->estado));
            if (isset($ordenEstados[$estadoActual], $ordenEstados[$estadoNormalizado]) && $ordenEstados[$estadoNormalizado] < $ordenEstados[$estadoActual]) {
                return ['exito' => false, 'mensaje' => 'No se permite volver a un estado anterior.'];
            }

            $reserva->estado = $estadoNormalizado;
            $reserva->save();

            if ($estadoNormalizado === 'checkout_realizado') {
                $habitacionesRelacionadas = $reserva->reservaHabitacion ?? [];
                foreach ($habitacionesRelacionadas as $itemHabitacion) {
                    if (!$itemHabitacion || empty($itemHabitacion->id_habitacion)) {
                        continue;
                    }

                    DB::table('habitacion')
                        ->where('id', (int) $itemHabitacion->id_habitacion)
                        ->update([
                            'estado' => 'Mantenimiento',
                        ]);

                    $habitacionModel = new HabitacionModel();
                    $habitacionActual = $habitacionModel->obtenerPorId((int) $itemHabitacion->id_habitacion) ?? [];
                    $estadoAnterior = $itemHabitacion->habitacion->estado ?? 'Ocupada';
                    $habitacionModel->registrarHistorial(
                        (int) $itemHabitacion->id_habitacion,
                        (int) $idReserva,
                        $estadoAnterior,
                        'Mantenimiento',
                        null,
                        null,
                        'checkout_realizado',
                        'Habitación enviada a mantenimiento tras checkout realizado.'
                    );
                }
            }

            return ['exito' => true, 'mensaje' => 'Estado de la reserva actualizado correctamente.'];
        } catch (\Throwable $e) {
            return ['exito' => false, 'mensaje' => 'Error al actualizar estado: ' . $e->getMessage()];
        }
    }

    public function actualizarEstado($idReserva, $nuevoEstado)
    {
        return $this->actualizarEstadoReserva($idReserva, $nuevoEstado);
    }

    public function cancelarReserva($idReserva, $motivo = '', $idUsuario = null)
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

            // Registrar devolución en tabla `devolucion` (aunque el monto sea 0, se guarda el registro)
            try {
                $checkIn = $reserva['check_in'] ?? null;
                $checkOut = $reserva['check_out'] ?? null;
                $diasUsados = 0;
                $diasNoUsados = 0;
                $totalNoOcupado = 0.0;

                if ($checkIn && $checkOut) {
                    $tsIn = strtotime($checkIn);
                    $tsOut = strtotime($checkOut);
                    $totalDias = max(1, (int) ceil(($tsOut - $tsIn) / 86400));
                    $now = time();

                    if ($now <= $tsIn) {
                        $diasUsados = 0;
                    } elseif ($now >= $tsOut) {
                        $diasUsados = $totalDias;
                    } else {
                        $diasUsados = (int) floor(($now - $tsIn) / 86400);
                        if ($diasUsados < 0) $diasUsados = 0;
                        if ($diasUsados > $totalDias) $diasUsados = $totalDias;
                    }

                    $diasNoUsados = max(0, $totalDias - $diasUsados);
                    $precioPorDia = $totalDias > 0 ? ((float) $reserva['total'] / $totalDias) : 0.0;
                    $totalNoOcupado = round($precioPorDia * $diasNoUsados, 2);
                }

                $stmtDev = $con->prepare("INSERT INTO devolucion (fecha_cancelacion, dias_usados, dias_no_usados, total_no_ocupado, porcentaje_penalidad, monto_penalidad, monto_devuelto, id_reserva, id_usuario, descripcion) VALUES (NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmtDev->execute([
                    (int) $diasUsados,
                    (int) $diasNoUsados,
                    (float) $totalNoOcupado,
                    (int) $porcentajePenalidad,
                    (float) $penalidad,
                    (float) $reembolso,
                    (int) $idReserva,
                    $idUsuario ? (int) $idUsuario : null,
                    $motivo
                ]);
            } catch (\Throwable $e) {
                // No abortar todo el proceso por un error al insertar devolución, registrar y continuar
                error_log('Error al registrar devolucion: ' . $e->getMessage());
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
            // Usar el método existente que devuelve la reserva en estadía para la habitación
            $ocupada = $habitacionModel->obtenerReser_EstadiaHab((int) $reserva['id_habitacion']);
            if ($ocupada && (int) $ocupada['id'] !== (int) $idReserva) {
                return ['exito' => false, 'mensaje' => 'La habitación está ocupada por otra reserva.'];
            }

            $con = $this->conectar();
            $con->beginTransaction();

            // Actualizar estado de la reserva. No asumimos existencia de columna `check_in_real`.
            $stmt = $con->prepare("UPDATE reserva SET estado = 'en_estadia' WHERE id = ?");
            $stmt->execute([(int) $idReserva]);

            // Registrar el check-in real en la tabla intermedia `reserva_habitacion` si aplica.
            try {
                if (!empty($reserva['id_habitacion'])) {
                    $stmtRh = $con->prepare("UPDATE reserva_habitacion SET check_in = NOW() WHERE id_reserva = ? AND id_habitacion = ?");
                    $stmtRh->execute([(int) $idReserva, (int) $reserva['id_habitacion']]);
                }
            } catch (\Throwable $e) {
                // No interrumpir el flujo si falla el update en reserva_habitacion
            }

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
