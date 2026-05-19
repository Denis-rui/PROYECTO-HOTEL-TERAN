<?php

class ReservaModel extends Model
{
    protected $table = 'reserva';
    private const ESTADOS_ACTIVOS = ['pendiente', 'confirmada', 'checkin_realizado', 'en_estadia', 'checkout_pendiente'];

    public function __construct()
    {
        parent::__construct();
    }

    public function listarReservas()
    {
        return $this->obtenerReservas();
    }

    public function obtenerReserva($id)
    {
        return $this->obtenerReservaPorId($id);
    }

    public function obtenerReservas()
    {
        try {
            $sql = "SELECT r.id, r.codigo_reserva, c.id AS id_cliente, c.nombre_completo AS cliente, c.correo_electronico AS correo_electronico,
                           h.id AS id_habitacion, h.numero_habitacion AS habitacion, h.piso,
                           rh.check_in, rh.check_out,
                           r.minutos_demora_checkout, r.cargo_checkout_tarde,
                           r.total, r.estado, r.observaciones,
                           IFNULL(p.total_pagado, 0) AS total_pagado,
                           (r.total + r.cargo_checkout_tarde - IFNULL(p.total_pagado, 0)) AS saldo_pendiente,
                           CASE
                              WHEN r.total + r.cargo_checkout_tarde = 0 THEN 0
                              ELSE ROUND((IFNULL(p.total_pagado, 0) / (r.total + r.cargo_checkout_tarde)) * 100, 0)
                           END AS porcentaje_pago,
                           CASE
                              WHEN r.estado IN ('en_estadia', 'checkout_pendiente') AND NOW() > rh.check_out AND r.check_out_real IS NULL
                              THEN TIMESTAMPDIFF(MINUTE, rh.check_out, NOW())
                              ELSE 0
                           END AS minutos_checkout_vencido
                    FROM reserva r
                    JOIN cliente c ON r.id_cliente = c.id
                    JOIN reserva_habitacion rh ON r.id = rh.id_reserva
                    JOIN habitacion h ON rh.id_habitacion = h.id
                    LEFT JOIN (
                        SELECT id_reserva, SUM(monto) AS total_pagado
                        FROM pago
                        GROUP BY id_reserva
                    ) p ON p.id_reserva = r.id
                    ORDER BY rh.check_in DESC";

            $statement = $this->conectar()->prepare($sql);
            $statement->execute();
            return $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            throw new \Exception("Error al obtener reservas: " . $e->getMessage());
        }
    }

    public function obtenerReservaPorId($idReserva)
    {
        $stmt = $this->conectar()->prepare("SELECT r.*, c.nombre_completo AS cliente, c.correo_electronico AS correo_electronico,
                h.numero_habitacion,
                IFNULL(p.total_pagado, 0) AS total_pagado,
                (r.total + r.cargo_checkout_tarde - IFNULL(p.total_pagado, 0)) AS saldo_pendiente
            FROM reserva r
            JOIN cliente c ON c.id = r.id_cliente
            JOIN habitacion h ON h.id = r.id_habitacion
            LEFT JOIN (SELECT id_reserva, SUM(monto) AS total_pagado FROM pago GROUP BY id_reserva) p ON p.id_reserva = r.id
            WHERE r.id = ?");
        $stmt->execute([(int) $idReserva]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function registrarReserva($reserva)
    {
        try {
            $habitacionModel = new HabitacionModel();
            $disponibilidad = $habitacionModel->validarDisponibilidadHabitacion(
                $reserva['habitacion'] ?? null,
                $reserva['checkIn'] ?? null,
                $reserva['checkOut'] ?? null
            );

            if (!$disponibilidad['disponible']) {
                return ['exito' => false, 'mensaje' => $disponibilidad['mensaje']];
            }

            $con = $this->conectar();
            $con->beginTransaction();

            $sql = "INSERT INTO reserva
                    (id_cliente, id_habitacion, check_in, check_out, total, estado, codigo_reserva, id_usuario, observaciones)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $statement = $con->prepare($sql);
            $ok = $statement->execute([
                $reserva['cliente'] ?? null,
                $reserva['habitacion'] ?? null,
                $reserva['checkIn'] ?? null,
                $reserva['checkOut'] ?? null,
                $reserva['total'] ?? null,
                $reserva['estado'] ?: 'confirmada',
                $reserva['codigoReserva'] ?? null,
                $reserva['usuario'] ?: 1,
                $reserva['observaciones'] ?: null,
            ]);

            $idReserva = (int) $con->lastInsertId();

            $habitacionActual = $habitacionModel->obtenerPorId((int) ($reserva['habitacion'] ?? 0));
            $habitacionModel->registrarHistorial(
                (int) ($reserva['habitacion'] ?? 0),
                $idReserva,
                $habitacionActual['estado_operativo'] ?? 'disponible',
                $habitacionActual['estado_operativo'] ?? 'disponible',
                $habitacionActual['estado_limpieza'] ?? 'limpia',
                $habitacionActual['estado_limpieza'] ?? 'limpia',
                'crear_reserva',
                'Reserva creada',
                $reserva['usuario'] ?: null
            );

            $con->commit();
            return ['exito' => $ok, 'mensaje' => 'Reserva registrada correctamente.', 'id_reserva' => $idReserva];
        } catch (\Exception $e) {
            $con = $this->conectar();
            if ($con->inTransaction()) {
                $con->rollBack();
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

            $fecha = $fechaPago ? $fechaPago . ' ' . date('H:i:s') : date('Y-m-d H:i:s');
            $stmt = $this->conectar()->prepare("INSERT INTO pago (id_reserva, monto, descripcion, fecha_pago, id_metodo_pago)
                VALUES (?, ?, ?, ?, ?)");
            $ok = $stmt->execute([(int) $idReserva, $monto, $descripcion, $fecha, (int) $idMetodoPago]);

            return ['exito' => $ok, 'mensaje' => $ok ? 'Pago registrado correctamente.' : 'No se pudo registrar el pago.'];
        } catch (\PDOException $e) {
            return ['exito' => false, 'mensaje' => 'Error al registrar pago: ' . $e->getMessage()];
        }
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

            $nuevoTotal = $this->calcularTotalReserva((int) $reserva['id_habitacion'], $reserva['check_in'], $nuevoCheckOut);
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

    public function obtenerEstadisticasDashboard()
    {
        $stats = [];
        $con = $this->conectar();
        $stats['habitaciones_disponibles'] = (int) $con->query("SELECT COUNT(*) FROM habitacion WHERE activo = 1 AND estado = 'Disponible'")->fetchColumn();
        $stats['habitaciones_ocupadas'] = (int) $con->query("SELECT COUNT(*) FROM habitacion WHERE activo = 1 AND estado = 'Ocupada'")->fetchColumn();
        $stats['habitaciones_reservadas'] = (int) $con->query("SELECT COUNT(*) FROM habitacion WHERE activo = 1 AND estado = 'Reservada'")->fetchColumn();
        $stats['habitaciones_mantenimiento'] = (int) $con->query("SELECT COUNT(*) FROM habitacion WHERE activo = 1 AND estado = 'Mantenimiento'")->fetchColumn();
        $stats['reservas_activas'] = (int) $con->query("SELECT COUNT(*) FROM reserva WHERE estado IN ('pendiente','confirmada','checkin_realizado','en_estadia','checkout_pendiente')")->fetchColumn();
        $stats['checkins_hoy'] = (int) $con->query("SELECT COUNT(*) FROM reserva_habitacion rh JOIN reserva r ON r.id = rh.id_reserva WHERE DATE(rh.check_in) = CURDATE() AND r.estado IN ('confirmada','en_estadia')
        ")->fetchColumn();
        $stats['checkouts_hoy'] = (int) $con->query("SELECT COUNT(*) FROM reserva_habitacion rh JOIN reserva r ON r.id = rh.id_reserva WHERE DATE(rh.check_out) = CURDATE() AND r.estado IN ('en_estadia','checkout_pendiente','checkout_realizado')
        ")->fetchColumn();
$stats['checkouts_vencidos'] = (int) $con->query("SELECT COUNT(*) FROM reserva_habitacion rh JOIN reserva r ON r.id = rh.id_reserva WHERE r.estado IN ('en_estadia','checkout_pendiente') AND rh.check_out IS NOT NULL AND NOW() > rh.check_out AND rh.activo = 1
")->fetchColumn();
        $stats['ingreso_dia'] = (float) $con->query("SELECT IFNULL(SUM(monto),0) FROM pago WHERE DATE(fecha_pago) = CURDATE()")->fetchColumn();
        
        // NUEVOS INDICADORES SOLICITADOS
        $stats['total_procedencias'] = (int) $con->query("SELECT COUNT(DISTINCT procedencia) FROM cliente WHERE procedencia IS NOT NULL AND procedencia != ''")->fetchColumn();
        
    $stats['estancia_minima'] = (int) $con->query("SELECT IFNULL( MIN(DATEDIFF(rh.check_out, rh.check_in)), 0) FROM reserva_habitacion rh
        JOIN reserva r ON r.id = rh.id_reserva WHERE r.estado NOT IN ('cancelada', 'no_show') AND rh.check_out IS NOT NULL
    ")->fetchColumn();

        $totalHabitaciones = max(1, (int) $con->query("SELECT COUNT(*) FROM habitacion WHERE activo = 1")->fetchColumn());
        $stats['ocupacion_porcentual'] = round(($stats['habitaciones_ocupadas'] / $totalHabitaciones) * 100, 1);

        // DETALLES DE MANTENIMIENTO (Usando descripcion_habitacion como motivo)
        $sqlMante = "SELECT numero_habitacion, COALESCE(descripcion_habitacion, 'Sin motivo especificado') as motivo 
                     FROM habitacion 
                     WHERE estado = 'Mantenimiento' AND activo = 1";
        $stats['detalles_mantenimiento'] = $con->query($sqlMante)->fetchAll(PDO::FETCH_ASSOC);

        return $stats;
    }

    public function calcularTotalReserva($idHabitacion, $checkIn, $checkOut)
    {
        $stmt = $this->conectar()->prepare("SELECT COALESCE(NULLIF(h.precio, 0), t.precio_base) AS precio
            FROM habitacion h
            JOIN tipo_habitacion t ON t.id = h.id_tipo_habitacion
            WHERE h.id = ?");
        $stmt->execute([(int) $idHabitacion]);
        $precio = (float) $stmt->fetchColumn();
        $segundos = max(1, strtotime($checkOut) - strtotime($checkIn));
        $dias = max(1, (int) ceil($segundos / 86400));
        return $dias * $precio;
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
