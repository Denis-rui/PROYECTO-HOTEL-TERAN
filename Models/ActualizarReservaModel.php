<?php

namespace Models;

use Illuminate\Database\Capsule\Manager as DB;
use Models\Entities\Habitacion;
use Models\HabitacionModel;
use Models\Entities\Reserva;
use Models\Entities\ReservaHabitacion;
use Models\ReporteOcupacionModel;
use Helpers\ReservaHelper;

class ActualizarReservaModel extends ReservaModel
{
    private const ESTADOS_ACTIVOS = ['pendiente', 'confirmada', 'checkin_realizado', 'en_estadia', 'checkout_pendiente'];
    public function actualizarReserva($datos, $idUsuario = null)
    {
        try {
            $idUsuarioActual = $idUsuario ?? ($_SESSION['id_usuario'] ?? null);
            $idReserva = (int) ($datos['id_reserva'] ?? 0);
            if ($idReserva <= 0) {
                return ['exito' => false, 'mensaje' => 'No se recibió el ID de la reserva.'];
            }

            $reservaActual = Reserva::with(['pagos', 'reservaHabitacion.habitacion'])->find($idReserva);
            if (!$reservaActual) {
                return ['exito' => false, 'mensaje' => 'Reserva no encontrada.'];
            }

            $estadoReserva = strtolower(trim((string) $reservaActual->estado));
            if (in_array($estadoReserva, ['en_estadia', 'checkout_pendiente'], true)) {
                return $this->actualizarEstadiaActiva($reservaActual, $datos, $idUsuarioActual);
            }

            if ($estadoReserva !== 'confirmada') {
                return ['exito' => false, 'mensaje' => 'Solo se puede editar una reserva confirmada o una estadía activa.'];
            }

            $habitacionModel = new HabitacionModel();
            $reporteOcupacionModel = new ReporteOcupacionModel();
            $checkIn = ReservaHelper::combinarFechaHora($datos['checkIn'] ?? null, $datos['horaEntrada'] ?? null);
            $checkOut = ReservaHelper::combinarFechaHora($datos['checkOut'] ?? null, $datos['horaSalida'] ?? null);

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
            $dias = ReservaHelper::obtenerDiasEstadia($checkIn, $checkOut);

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
                    $disponibilidad = $reporteOcupacionModel->validarDisponibilidadHabitacion(
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
                fn($item) => (int) $item['id'],
                $habitacionesNormalizadas
            )));

            DB::connection()->beginTransaction();

            $reservaActual->update([
                'id_cliente' => (int) ($datos['cliente'] ?? $datos['id_cliente'] ?? $reservaActual->id_cliente),
                'total' => $totalCalculado,
                'observaciones' => $datos['observaciones'] ?? $reservaActual->observaciones,
            ]);

            ReservaHabitacion::where('id_reserva', $idReserva)
                ->delete();

            foreach ($habitacionesNormalizadas as $habitacionNormalizada) {
                ReservaHabitacion::create([
                    'id_reserva'    => $idReserva,
                    'id_habitacion' => $habitacionNormalizada['id'],
                    'check_in'      => $checkIn,
                    'check_out'     => $checkOut,
                    'activo'        => 1,
                    'tipo_asignacion' => 'original',
                    'estado' => 'activa',
                    'precio_aplicado' => $habitacionNormalizada['precio'],
                    'subtotal' => $habitacionNormalizada['precio'] * $dias,
                    'id_usuario_movimiento' => $idUsuarioActual,
                    'fecha_movimiento' => date('Y-m-d H:i:s'),
                ]);
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
                    Habitacion::where('id', $idHabitacionAnterior)->update([
                        'estado' => $nuevoEstado,
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

    private function actualizarEstadiaActiva(Reserva $reservaActual, array $datos, $idUsuario = null): array
    {
        try {
            $idUsuarioActual = $idUsuario ?? ($_SESSION['id_usuario'] ?? null);
            $idReserva = (int) $reservaActual->id;
            $checkOut = ReservaHelper::combinarFechaHora($datos['checkOut'] ?? null, $datos['horaSalida'] ?? null);
            if (!$checkOut) {
                return ['exito' => false, 'mensaje' => 'Debe indicar la fecha de salida.'];
            }

            $clienteNuevo = (int) ($datos['cliente'] ?? $datos['id_cliente'] ?? $reservaActual->id_cliente);
            if ($clienteNuevo !== (int) $reservaActual->id_cliente) {
                return ['exito' => false, 'mensaje' => 'No se puede cambiar el cliente cuando la reserva está en estadía.'];
            }

            $habitacionesIngresadas = $datos['habitaciones'] ?? [];
            if (is_string($habitacionesIngresadas)) {
                $decoded = json_decode($habitacionesIngresadas, true);
                $habitacionesIngresadas = is_array($decoded) ? $decoded : [];
            }

            $idsSolicitados = [];
            foreach ($habitacionesIngresadas as $habitacionIngresada) {
                $idHabitacion = is_array($habitacionIngresada)
                    ? (int) ($habitacionIngresada['id'] ?? $habitacionIngresada['id_habitacion'] ?? 0)
                    : (int) $habitacionIngresada;
                if ($idHabitacion > 0) {
                    $idsSolicitados[] = $idHabitacion;
                }
            }
            $idsSolicitados = array_values(array_unique($idsSolicitados));

            $relacionesActivas = [];
            $idsActivos = [];
            foreach (($reservaActual->reservaHabitacion ?? []) as $relacion) {
                if ((int) ($relacion->activo ?? 1) === 1 && (($relacion->estado ?? 'activa') === 'activa')) {
                    $relacionesActivas[] = $relacion;
                    $idsActivos[] = (int) $relacion->id_habitacion;
                }
            }

            foreach ($idsActivos as $idActivo) {
                if (!in_array($idActivo, $idsSolicitados, true)) {
                    return ['exito' => false, 'mensaje' => 'No se puede quitar habitaciones durante una estadía. Use Cambiar habitación.'];
                }
            }

            $idsNuevos = array_values(array_diff($idsSolicitados, $idsActivos));
            $habitacionModel = new HabitacionModel();
            $reporteOcupacionModel = new ReporteOcupacionModel();
            $fechaAlta = (new \DateTimeImmutable('now', new \DateTimeZone('America/Lima')))->format('Y-m-d H:i:s');
            $totalCalculado = 0;

            DB::connection()->beginTransaction();

            foreach ($relacionesActivas as $relacion) {
                $disponibilidad = $reporteOcupacionModel->validarDisponibilidadHabitacion(
                    (int) $relacion->id_habitacion,
                    $relacion->check_in,
                    $checkOut,
                    $idReserva
                );
                if (!$disponibilidad['disponible']) {
                    DB::connection()->rollBack();
                    return ['exito' => false, 'mensaje' => $disponibilidad['mensaje']];
                }

                $precioAplicado = (float) ($relacion->precio_aplicado ?: 0);
                if ($precioAplicado <= 0) {
                    $habitacionActual = $habitacionModel->obtenerPorId((int) $relacion->id_habitacion);
                    $precioAplicado = (float) ($habitacionActual['precio'] ?? 0);
                }

                $dias = ReservaHelper::obtenerDiasEstadia($relacion->check_in, $checkOut);
                $subtotal = $precioAplicado * $dias;
                $relacion->check_out = $checkOut;
                $relacion->precio_aplicado = $precioAplicado;
                $relacion->subtotal = $subtotal;
                $relacion->save();
                $totalCalculado += $subtotal;
            }

            foreach ($idsNuevos as $idHabitacionNueva) {
                $disponibilidad = $reporteOcupacionModel->validarDisponibilidadHabitacion(
                    $idHabitacionNueva,
                    $fechaAlta,
                    $checkOut,
                    $idReserva
                );
                if (!$disponibilidad['disponible']) {
                    DB::connection()->rollBack();
                    return ['exito' => false, 'mensaje' => $disponibilidad['mensaje']];
                }

                $habitacionNueva = $habitacionModel->obtenerPorId($idHabitacionNueva);
                if (!$habitacionNueva) {
                    DB::connection()->rollBack();
                    return ['exito' => false, 'mensaje' => 'No se encontró una de las habitaciones seleccionadas.'];
                }

                $precio = (float) ($habitacionNueva['precio'] ?? 0);
                $dias = ReservaHelper::obtenerDiasEstadia($fechaAlta, $checkOut);
                $subtotal = $precio * $dias;
                ReservaHabitacion::create([
                    'id_reserva' => $idReserva,
                    'id_habitacion' => $idHabitacionNueva,
                    'check_in' => $fechaAlta,
                    'check_out' => $checkOut,
                    'activo' => 1,
                    'tipo_asignacion' => 'agregada',
                    'estado' => 'activa',
                    'motivo_cambio' => 'Habitación agregada durante estadía',
                    'id_usuario_movimiento' => $idUsuarioActual,
                    'fecha_movimiento' => $fechaAlta,
                    'precio_aplicado' => $precio,
                    'subtotal' => $subtotal,
                ]);
                Habitacion::where('id', $idHabitacionNueva)->update(['estado' => 'Ocupada']);
                $totalCalculado += $subtotal;
            }

            $reservaActual->total = $totalCalculado;
            $reservaActual->observaciones = $datos['observaciones'] ?? $reservaActual->observaciones;
            $reservaActual->save();

            DB::connection()->commit();
            return [
                'exito' => true,
                'mensaje' => 'Estadía actualizada correctamente.',
                'id_reserva' => $idReserva,
                'reserva' => $this->obtenerReservaPorId($idReserva),
            ];
        } catch (\Throwable $e) {
            $conexion = DB::connection();
            if ($conexion->getPdo()->inTransaction()) {
                $conexion->rollBack();
            }
            return ['exito' => false, 'mensaje' => 'Error al actualizar estadía: ' . $e->getMessage()];
        }
    }

    public function extenderEstadia($idReserva, $nuevoCheckOut, $idUsuario = null)
    {
        try {
            $reservaActual = Reserva::with(['reservaHabitacion.habitacion'])->find((int) $idReserva);
            if (!$reservaActual) {
                return ['exito' => false, 'mensaje' => 'Reserva no encontrada.'];
            }

            if (!in_array($reservaActual->estado, ['en_estadia', 'checkout_pendiente'], true)) {
                return ['exito' => false, 'mensaje' => 'Solo se puede extender una estadía activa.'];
            }

            $idHabitacionPrincipal = (int) ($reservaActual->reservaHabitacion->first()->id_habitacion ?? 0);
            $checkIn = $reservaActual->reservaHabitacion->first()->check_in ?? null;

            $reporteOcupacionModel = new ReporteOcupacionModel();
            $disponibilidad = $reporteOcupacionModel->validarDisponibilidadHabitacion(
                $idHabitacionPrincipal,
                $checkIn,
                $nuevoCheckOut,
                (int) $idReserva
            );

            if (!$disponibilidad['disponible']) {
                return ['exito' => false, 'mensaje' => 'No se puede extender porque existe una reserva futura cruzada.'];
            }

            $habitacionModel = new HabitacionModel();
            $nuevoTotal = $reporteOcupacionModel->calcularTotalReserva($idHabitacionPrincipal, $checkIn, $nuevoCheckOut);

            $reservaHabitacion = $reservaActual->reservaHabitacion->first();
            if (!$reservaHabitacion) {
                return ['exito' => false, 'mensaje' => 'No se encontró la habitación asociada a la reserva.'];
            }

            $reservaHabitacion->check_out = $nuevoCheckOut;
            $reservaHabitacion->save();

            $reservaActual->total = $nuevoTotal;
            $reservaActual->estado = 'en_estadia';
            $ok = $reservaActual->save();

            if ($ok) {
                $estadoOperativo = $reservaActual->reservaHabitacion->first()->habitacion->estado_operativo ?? 'ocupada';
                $estadoLimpieza = $reservaActual->reservaHabitacion->first()->habitacion->estado_limpieza ?? 'limpia';
                $habitacionModel->registrarHistorial($idHabitacionPrincipal, (int) $idReserva, $estadoOperativo, $estadoOperativo, $estadoLimpieza, $estadoLimpieza, 'extension_estadia', 'Checkout extendido hasta ' . $nuevoCheckOut, $idUsuario);
            }

            return ['exito' => $ok, 'mensaje' => $ok ? 'Estadía extendida correctamente.' : 'No se pudo extender la estadía.', 'total' => $nuevoTotal];
        } catch (\Exception $e) {
            return ['exito' => false, 'mensaje' => 'Error al extender estadía: ' . $e->getMessage()];
        }
    }

    private function recalcularTotalDesdeHabitaciones(int $idReserva): float
    {
        return (float) DB::table('reserva_habitacion')
            ->where('id_reserva', $idReserva)
            ->sum('subtotal');
    }

    private function obtenerAhoraHotelero(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('America/Lima')))->format('Y-m-d H:i:s');
    }

    public function cambiarHabitacion($idReserva, $idHabitacionActual, $idHabitacionNueva, $tipoMotivo, $motivo, $idUsuario = null)
    {
        try {
            $idUsuarioActual = $idUsuario ?? ($_SESSION['id_usuario'] ?? null);
            $reservaActual = Reserva::with(['reservaHabitacion.habitacion', 'pagos'])->find((int) $idReserva);
            if (!$reservaActual || !in_array($reservaActual->estado, ['en_estadia', 'checkout_pendiente'], true)) {
                return ['exito' => false, 'mensaje' => 'Solo se puede cambiar habitación de una estadía activa.'];
            }

            $tipoMotivo = strtolower(trim((string) $tipoMotivo));
            if (!in_array($tipoMotivo, ['falla_hotel', 'solicitud_cliente'], true)) {
                return ['exito' => false, 'mensaje' => 'Seleccione si el cambio es por falla del hotel o solicitud del cliente.'];
            }

            if (trim((string) $motivo) === '') {
                return ['exito' => false, 'mensaje' => 'Debe indicar motivo del cambio de habitación.'];
            }

            $habitacionModel = new HabitacionModel();
            $reporteOcupacionModel = new ReporteOcupacionModel();

            $relacionActual = null;
            foreach ($reservaActual->reservaHabitacion as $itemHabitacion) {
                if (
                    (int) $itemHabitacion->id_habitacion === (int) $idHabitacionActual
                    && (int) ($itemHabitacion->activo ?? 1) === 1
                    && (($itemHabitacion->estado ?? 'activa') === 'activa')
                ) {
                    $relacionActual = $itemHabitacion;
                    break;
                }
            }

            if (!$relacionActual) {
                return ['exito' => false, 'mensaje' => 'No se encontró la habitación activa que desea cambiar.'];
            }

            $fechaCambio = $this->obtenerAhoraHotelero();
            $checkOut = $relacionActual->check_out ?? null;
            if (
                $tipoMotivo === 'solicitud_cliente'
                && substr((string) $fechaCambio, 0, 10) === substr((string) $checkOut, 0, 10)
            ) {
                return ['exito' => false, 'mensaje' => 'No se puede cambiar la habitación por solicitud del cliente el mismo día de salida. Primero actualice la fecha de checkout.'];
            }

            $disponibilidad = $reporteOcupacionModel->validarDisponibilidadHabitacion($idHabitacionNueva, $fechaCambio, $checkOut, (int) $idReserva);
            if (!$disponibilidad['disponible']) {
                return ['exito' => false, 'mensaje' => $disponibilidad['mensaje']];
            }

            $habitacionAnterior = $habitacionModel->obtenerPorId((int) $idHabitacionActual);
            $habitacionNueva = $habitacionModel->obtenerPorId((int) $idHabitacionNueva);
            if (!$habitacionAnterior || !$habitacionNueva) {
                return ['exito' => false, 'mensaje' => 'No se encontró la habitación seleccionada.'];
            }

            $precioAnterior = (float) ($relacionActual->precio_aplicado ?: ($habitacionAnterior['precio'] ?? 0));
            $precioNuevoReal = (float) ($habitacionNueva['precio'] ?? 0);
            $precioNuevoAplicado = $tipoMotivo === 'falla_hotel' ? $precioAnterior : $precioNuevoReal;
            $diasHabitacionAnterior = ReservaHelper::obtenerDiasEstadia($relacionActual->check_in, $fechaCambio);
            $diasHabitacionNueva = ReservaHelper::obtenerDiasEstadia($fechaCambio, $checkOut);
            $subtotalAnterior = $precioAnterior * $diasHabitacionAnterior;
            $subtotalNuevo = $precioNuevoAplicado * $diasHabitacionNueva;

            DB::connection()->beginTransaction();

            $relacionActual->check_out = $fechaCambio;
            $relacionActual->activo = 0;
            $relacionActual->estado = 'cambiada';
            $relacionActual->motivo_cambio = $tipoMotivo . ': ' . trim((string) $motivo);
            $relacionActual->id_usuario_movimiento = $idUsuarioActual;
            $relacionActual->fecha_movimiento = $fechaCambio;
            $relacionActual->subtotal = $subtotalAnterior;
            $relacionActual->save();

            ReservaHabitacion::create([
                'id_reserva' => (int) $idReserva,
                'id_habitacion' => (int) $idHabitacionNueva,
                'check_in' => $fechaCambio,
                'check_out' => $checkOut,
                'activo' => 1,
                'tipo_asignacion' => 'cambio',
                'estado' => 'activa',
                'motivo_cambio' => $tipoMotivo . ': ' . trim((string) $motivo),
                'id_usuario_movimiento' => $idUsuarioActual,
                'fecha_movimiento' => $fechaCambio,
                'precio_aplicado' => $precioNuevoAplicado,
                'subtotal' => $subtotalNuevo,
            ]);

            Habitacion::where('id', (int) $idHabitacionActual)->update([
                'estado' => 'Mantenimiento',
                'limpieza_inicio' => $fechaCambio,
            ]);
            Habitacion::where('id', $idHabitacionNueva)->update([
                'estado' => 'Ocupada',
            ]);

            $nuevoTotal = $this->recalcularTotalDesdeHabitaciones((int) $idReserva);
            $reservaActual->total = $nuevoTotal;
            $reservaActual->observaciones = trim((string) ($reservaActual->observaciones ?? '') . "\nCambio de habitación: Hab. " . ($habitacionAnterior['numero_habitacion'] ?? $idHabitacionActual) . " por Hab. " . ($habitacionNueva['numero_habitacion'] ?? $idHabitacionNueva) . ". Motivo: " . $motivo);
            $reservaActual->save();

            $habitacionModel->registrarHistorial((int) $idHabitacionActual, (int) $idReserva, $habitacionAnterior['estado'] ?? 'Ocupada', 'Mantenimiento', null, null, 'cambio_habitacion_salida', $motivo, $idUsuarioActual);
            $habitacionModel->registrarHistorial((int) $idHabitacionNueva, (int) $idReserva, $habitacionNueva['estado'] ?? 'Disponible', 'Ocupada', null, null, 'cambio_habitacion_entrada', $motivo, $idUsuarioActual);

            DB::connection()->commit();
            return [
                'exito' => true,
                'mensaje' => 'Cambio de habitación registrado correctamente.',
                'total_anterior' => (float) ($reservaActual->getOriginal('total') ?? 0),
                'total_nuevo' => $nuevoTotal,
                'diferencia' => max(0, $nuevoTotal - (float) ($reservaActual->getOriginal('total') ?? 0)),
                'reserva' => $this->obtenerReservaPorId((int) $idReserva),
            ];
        } catch (\Exception $e) {
            $con = DB::connection();
            if ($con->getPdo()->inTransaction()) {
                $con->rollBack();
            }
            return ['exito' => false, 'mensaje' => 'Error al cambiar habitación: ' . $e->getMessage()];
        }
    }
}
