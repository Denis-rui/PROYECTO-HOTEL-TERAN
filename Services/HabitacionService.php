<?php

namespace Services;

use Models\HabitacionModel;
use Models\ReporteOcupacionModel;
use Exception;
use Models\NotificacionModel;

class HabitacionService
{
    private HabitacionModel $habitacionModel;
    private ReporteOcupacionModel $reporteOcupacionModel;
    private NotificacionModel $notificacionModel;

    public function __construct()
    {
        $this->habitacionModel = new HabitacionModel();
        $this->reporteOcupacionModel = new ReporteOcupacionModel(); // Integración limpia
        $this->notificacionModel = new NotificacionModel();
    }

    public function registrar(array $datos): array
    {
        try {
            $numeroHabitacion = trim((string) ($datos['numero_habitacion'] ?? ''));
            if ($numeroHabitacion === '') {
                return $this->respuesta(false, 'VALIDACION_ERROR', 'Ingrese el número de habitación.', null, [
                    'numero_habitacion' => 'El número de habitación es obligatorio.',
                ]);
            }

            $validacion = $this->validarDatosHabitacion($datos);
            if (!$validacion['valido']) {
                return $this->respuesta(false, 'VALIDACION_ERROR', $validacion['mensaje'], null, $validacion['errores']);
            }

            $estadoNorm = $this->normalizarEstado($datos['estado'] ?? 'Disponible');

            $datosGuardar = [
                'numero_habitacion' => $validacion['datos']['numero_habitacion'],
                'piso' => $validacion['datos']['piso'],
                'id_tipo_habitacion' => $datos['id_tipo_habitacion'] ?? null,
                'estado' => $estadoNorm,
                'descripcion_habitacion' => $datos['descripcion_habitacion'] ?? $datos['descripcion'] ?? '',
                'capacidad' => $validacion['datos']['capacidad'],
                'activo' => (int) ($datos['activo'] ?? 1),
            ];

            $habitacionExistente = $this->habitacionModel->obtenerPorNumero($validacion['datos']['numero_habitacion']);
            if ($habitacionExistente) {
                if ((int) $habitacionExistente->activo === 1) {
                    return $this->respuesta(false, 'CONFLICTO', "La habitación número " . $validacion['datos']['numero_habitacion'] . " ya está registrada.");
                }

                $datosGuardar['activo'] = 1;
                $this->habitacionModel->actualizar((int) $habitacionExistente->id, $datosGuardar);
                return $this->respuesta(true, 'CREADO', 'Habitación registrada correctamente.', [
                    'id' => (int) $habitacionExistente->id,
                ]);
            }

            $habitacion = $this->habitacionModel->crear($datosGuardar);
            return $this->respuesta(true, 'CREADO', 'Habitación registrada correctamente.', $habitacion);
        } catch (Exception $e) {
            // Manejo de número duplicado (Error 1062 en SQL)
            if ($e->getCode() == 23000 || strpos($e->getMessage(), '1062') !== false) {
                return $this->respuesta(false, 'CONFLICTO', "La habitación número " . ($datos['numero_habitacion'] ?? '') . " ya está registrada.");
            }
            error_log("Error al registrar habitación: " . $e->getMessage());
            return $this->respuesta(false, 'ERROR_INTERNO', 'Error inesperado al registrar la habitación.');
        }
    }

    public function editar(array $datos): array
    {
        try {
            $id = (int) ($datos['id'] ?? 0);
            if ($id <= 0) {
                return $this->respuesta(false, 'VALIDACION_ERROR', 'Seleccione una habitación válida.', null, [
                    'id' => 'La habitación es obligatoria.',
                ]);
            }

            $habitacion = $this->habitacionModel->find($id);

            if (!$habitacion) return $this->respuesta(false, 'NO_ENCONTRADO', 'Habitación no encontrada.');

            // 1. Bloquear si tiene reservas activas
            if ($this->habitacionModel->obtenerReservaActiva($id)) {
                return $this->respuesta(false, 'CONFLICTO', 'No se puede editar la habitación porque tiene reservas activas asociadas.');
            }

            // 2. Bloquear si está en mantenimiento
            if (strtolower($habitacion->estado) === 'mantenimiento') {
                return $this->respuesta(false, 'CONFLICTO', 'No se puede editar porque está en mantenimiento.');
            }

            $datosParaValidar = array_merge([
                'numero_habitacion' => $habitacion->numero_habitacion,
                'piso' => $habitacion->piso,
                'capacidad' => $habitacion->capacidad,
            ], $datos);
            $validacion = $this->validarDatosHabitacion($datosParaValidar);
            if (!$validacion['valido']) {
                return $this->respuesta(false, 'VALIDACION_ERROR', $validacion['mensaje'], null, $validacion['errores']);
            }

            $datosActualizar = [
                'numero_habitacion' => $validacion['datos']['numero_habitacion'],
                'piso' => $validacion['datos']['piso'],
                'id_tipo_habitacion' => $datos['id_tipo_habitacion'] ?? $habitacion->id_tipo_habitacion,
                'estado' => $this->normalizarEstado($datos['estado'] ?? $habitacion->estado),
                'descripcion_habitacion' => $datos['descripcion_habitacion'] ?? $datos['descripcion'] ?? $habitacion->descripcion_habitacion,
                'capacidad' => $validacion['datos']['capacidad'],
            ];

            $this->habitacionModel->actualizar($id, $datosActualizar);
            return $this->respuesta(true, 'ACTUALIZADO', 'Habitación actualizada correctamente.', ['id' => $id]);
        } catch (Exception $e) {
            if ($e->getCode() == 23000 || strpos($e->getMessage(), '1062') !== false) {
                return $this->respuesta(false, 'CONFLICTO', 'El número de habitación ya está en uso.');
            }
            error_log("Error al editar habitación: " . $e->getMessage());
            return $this->respuesta(false, 'ERROR_INTERNO', 'Error al actualizar la habitación.');
        }
    }

    public function eliminar(int $id): array
    {
        try {
            if ($id <= 0) {
                return $this->respuesta(false, 'VALIDACION_ERROR', 'Seleccione una habitación válida.', null, [
                    'id' => 'La habitación es obligatoria.',
                ]);
            }

            $habitacion = $this->habitacionModel->find($id);
            if (!$habitacion) return $this->respuesta(false, 'NO_ENCONTRADO', 'Habitación no encontrada.');

            if ($this->habitacionModel->obtenerReservaActiva($id)) {
                return $this->respuesta(false, 'CONFLICTO', 'No se puede eliminar la habitación porque tiene reservas activas asociadas.');
            }

            if (strtolower($habitacion->estado) === 'mantenimiento') {
                return $this->respuesta(false, 'CONFLICTO', 'No se puede eliminar porque está en mantenimiento.');
            }

            $this->habitacionModel->darDeBaja($id);
            return $this->respuesta(true, 'ELIMINADO', 'Habitación eliminada correctamente.', ['id' => $id]);
        } catch (Exception $e) {
            error_log("Error al eliminar habitación: " . $e->getMessage());
            return $this->respuesta(false, 'ERROR_INTERNO', 'Error al intentar eliminar.');
        }
    }

    public function actualizarEstado(int $id, string $estado, string $motivo = ''): array
    {
        try {
            $nuevoEstado = $this->normalizarEstado($estado);
            $habitacion = $this->habitacionModel->find($id);

            if (!$habitacion) return $this->respuesta(false, 'NO_ENCONTRADO', 'Habitación no encontrada.');

            if ($nuevoEstado === 'Disponible') {
                $bloqueante = $this->reporteOcupacionModel->obtenerReser_EstadiaHab($id);
                if ($bloqueante) {
                    $detalle = (array) $bloqueante;
                    if (!empty($detalle['check_out'])) {
                        $checkOutTs = strtotime($detalle['check_out']);
                        $detalle['minutos_faltantes'] = $checkOutTs > time() ? (int) floor(($checkOutTs - time()) / 60) : 0;
                    }
                    return $this->respuesta(false, 'CONFLICTO', 'Existe una reserva bloqueante.', null, [], [
                        'reserva_bloqueante' => $detalle,
                    ]);
                }
            }

            $updateData = ['estado' => $nuevoEstado];
            if (strtolower($nuevoEstado) === 'mantenimiento') {
                $updateData['descripcion_habitacion'] = $motivo;
            }

            $this->habitacionModel->actualizar($id, $updateData);

            if ($nuevoEstado === 'Disponible') {
                $this->notificacionModel->marcarCheckoutLeidoPorHabitacion($id);
                $this->notificacionModel->marcarLimpiezaLeidaPorHabitacion($id);
            }

            return $this->respuesta(true, 'ACTUALIZADO', 'Estado actualizado correctamente.', [
                'id' => $id,
                'estado' => $nuevoEstado,
            ]);
        } catch (Exception $e) {
            error_log("Error actualizar estado: " . $e->getMessage());
            return $this->respuesta(false, 'ERROR_INTERNO', 'Error al actualizar estado.');
        }
    }

    public function buscar(string $numero, string $tipo, string $estado, string $piso): array
    {
        try {
            $estadoNorm = $estado ? $this->normalizarEstado($estado) : '';
            $datos = $this->habitacionModel->buscar($numero, $tipo, $estadoNorm, $piso);
            return $this->respuesta(true, 'OK', 'Habitaciones cargadas correctamente.', $datos);
        } catch (Exception $e) {
            error_log("Error en buscar: " . $e->getMessage());
            return $this->respuesta(false, 'ERROR_INTERNO', 'No se pudieron cargar las habitaciones.', []);
        }
    }

    public function obtenerFiltros(): array
    {
        try {
            return $this->respuesta(true, 'OK', 'Filtros cargados correctamente.', $this->habitacionModel->obtenerFiltros());
        } catch (Exception $e) {
            error_log("Error al obtener filtros de habitaciones: " . $e->getMessage());
            return $this->respuesta(false, 'ERROR_INTERNO', 'No se pudieron cargar los filtros de habitaciones.', []);
        }
    }

    public function disponiblesPorRango(
        string $checkIn,
        string $checkOut,
        ?string $tipo = null,
        ?string $piso = null,
        array $referencia = [],
        ?int $idReservaExcluir = null
    ): array {
        try {
            return $this->respuesta(
                true,
                'OK',
                'Habitaciones disponibles cargadas correctamente.',
                $this->reporteOcupacionModel->obtenerDisponiblesPorRango(
                    $checkIn,
                    $checkOut,
                    $tipo,
                    $piso,
                    $referencia,
                    $idReservaExcluir
                )
            );
        } catch (Exception $e) {
            error_log("Error al obtener habitaciones disponibles: " . $e->getMessage());
            return $this->respuesta(false, 'ERROR_INTERNO', 'No se pudo consultar la disponibilidad.', []);
        }
    }

    public function terminarLimpieza(int $id): array
    {
        try {
            $habitacion = $this->habitacionModel->find($id);
            if (!$habitacion) return $this->respuesta(false, 'NO_ENCONTRADO', 'Habitación no encontrada.');

            $estadoActual = strtolower($habitacion->estado);
            if ($estadoActual !== 'en limpieza') {
                return $this->respuesta(false, 'CONFLICTO', 'La habitacion no esta en limpieza.');
            }

            if ($this->reporteOcupacionModel->obtenerReser_EstadiaHab($id)) {
                return $this->respuesta(false, 'CONFLICTO', 'La habitación todavía tiene una estancia activa.');
            }

            $this->habitacionModel->actualizar($id, [
                'estado' => 'Disponible',
                'limpieza_inicio' => null,
                'descripcion_habitacion' => ''
            ]);

            // agregamos para que se actualicen las notificaciones

            $this->notificacionModel->marcarCheckoutLeidoPorHabitacion($id);
            $this->notificacionModel->marcarLimpiezaLeidaPorHabitacion($id);

            return $this->respuesta(true, 'ACTUALIZADO', 'Limpieza finalizada. Habitación disponible.', ['id' => $id]);
        } catch (Exception $e) {
            return $this->respuesta(false, 'ERROR_INTERNO', 'Error al finalizar limpieza.');
        }
    }

    public function notificarLimpiezaVencida(int $id): array
    {
        try {
            $habitacion = $this->habitacionModel->find($id);
            if (!$habitacion) return $this->respuesta(false, 'NO_ENCONTRADO', 'Habitación no encontrada.');

            if (strtolower((string) $habitacion->estado) !== 'en limpieza') {
                return $this->respuesta(false, 'CONFLICTO', 'La habitación no está en limpieza.');
            }

            $numero = $habitacion->numero_habitacion ?? $id;
            $guardado = $this->notificacionModel->guardarNotificacion(
                [
                    'tipo' => 'limpieza_vencida',
                    'id_reserva' => null,
                    'id_habitacion' => $id,
                    'leida' => 0,
                ],
                [
                    'tipo' => 'limpieza_vencida',
                    'titulo' => 'Limpieza vencida - Hab. ' . $numero,
                    'mensaje' => 'La habitacion ' . $numero . ' supero el tiempo de limpieza. Confirma la limpieza o extiende el tiempo.',
                    'id_reserva' => null,
                    'id_habitacion' => $id,
                    'id_cliente' => null,
                    'leida' => 0,
                    'prioridad' => 'critica',
                ]
            );

            return $guardado
                ? $this->respuesta(true, 'CREADO', 'Alerta de limpieza vencida registrada.', ['id_habitacion' => $id])
                : $this->respuesta(false, 'ERROR_GUARDADO', 'No se pudo registrar la alerta de limpieza.');
        } catch (Exception $e) {
            error_log("Error al notificar limpieza vencida: " . $e->getMessage());
            return $this->respuesta(false, 'ERROR_INTERNO', 'Error al registrar la alerta de limpieza.');
        }
    }

    public function notificarLimpiezasVencidas(): array
    {
        try {
            $habitaciones = $this->habitacionModel
                ->whereRaw('LOWER(estado) = ?', ['en limpieza'])
                ->whereNotNull('limpieza_inicio')
                ->whereRaw('limpieza_inicio <= DATE_SUB(NOW(), INTERVAL 1 HOUR)')
                ->get();

            $registradas = 0;

            foreach ($habitaciones as $habitacion) {
                $numero = $habitacion->numero_habitacion ?? $habitacion->id;
                $guardado = $this->notificacionModel->guardarNotificacion(
                    [
                        'tipo' => 'limpieza_vencida',
                        'id_reserva' => null,
                        'id_habitacion' => (int) $habitacion->id,
                        'leida' => 0,
                    ],
                    [
                        'tipo' => 'limpieza_vencida',
                        'titulo' => 'Limpieza vencida - Hab. ' . $numero,
                        'mensaje' => 'La habitacion ' . $numero . ' supero el tiempo de limpieza. Confirma la limpieza o extiende el tiempo.',
                        'id_reserva' => null,
                        'id_habitacion' => (int) $habitacion->id,
                        'id_cliente' => null,
                        'leida' => 0,
                        'prioridad' => 'critica',
                    ]
                );

                if ($guardado) {
                    $registradas++;
                }
            }

            return $this->respuesta(true, 'OK', 'Alertas de limpieza vencida revisadas.', [
                'registradas' => $registradas,
            ]);
        } catch (Exception $e) {
            error_log("Error al revisar limpiezas vencidas: " . $e->getMessage());
            return $this->respuesta(false, 'ERROR_INTERNO', 'Error al revisar limpiezas vencidas.', [
                'registradas' => 0,
            ]);
        }
    }

    public function extenderLimpieza(int $id, int $minutos = 15): array
    {
        try {
            $habitacion = $this->habitacionModel->find($id);
            if (!$habitacion) return $this->respuesta(false, 'NO_ENCONTRADO', 'Habitación no encontrada.');

            if (strtolower((string) $habitacion->estado) !== 'en limpieza') {
                return $this->respuesta(false, 'CONFLICTO', 'La habitación no está en limpieza.');
            }

            $minutos = max(5, min(120, $minutos));
            $nuevoInicio = date('Y-m-d H:i:s', time() - (3600 - ($minutos * 60)));

            $this->habitacionModel->actualizar($id, [
                'limpieza_inicio' => $nuevoInicio,
            ]);
            $this->notificacionModel->marcarLimpiezaLeidaPorHabitacion($id);

            return $this->respuesta(true, 'ACTUALIZADO', 'Limpieza extendida por ' . $minutos . ' minutos.', [
                'id' => $id,
                'minutos' => $minutos,
            ]);
        } catch (Exception $e) {
            error_log("Error al extender limpieza: " . $e->getMessage());
            return $this->respuesta(false, 'ERROR_INTERNO', 'Error al extender la limpieza.');
        }
    }

    private function respuesta(
        bool $exito,
        string $codigo,
        string $mensaje,
        mixed $data = null,
        array $errores = [],
        array $extra = []
    ): array {
        return array_merge([
            'exito' => $exito,
            'codigo' => $codigo,
            'mensaje' => $mensaje,
            'data' => $data,
            'errores' => array_filter($errores, fn($error) => $error !== null),
        ], $extra);
    }

    private function validarDatosHabitacion(array $datos): array
    {
        $errores = [];
        $numeroHabitacion = trim((string) ($datos['numero_habitacion'] ?? ''));
        $piso = filter_var($datos['piso'] ?? null, FILTER_VALIDATE_INT);
        $capacidad = filter_var($datos['capacidad'] ?? null, FILTER_VALIDATE_INT);

        if ($numeroHabitacion === '') {
            $errores['numero_habitacion'] = 'El numero de habitacion es obligatorio.';
        } elseif (!preg_match('/^[A-Za-z0-9]+(?:-[A-Za-z0-9]+)*$/', $numeroHabitacion)) {
            $errores['numero_habitacion'] = 'El numero de habitacion solo puede contener letras, numeros y guiones internos.';
        }

        if ($piso === false || $piso < 1) {
            $errores['piso'] = 'El piso debe ser un numero entero mayor a cero.';
        }

        if ($capacidad === false || $capacidad < 1) {
            $errores['capacidad'] = 'La capacidad debe ser un numero entero mayor a cero.';
        }

        return [
            'valido' => empty($errores),
            'mensaje' => 'Verifique los datos de la habitacion.',
            'errores' => $errores,
            'datos' => [
                'numero_habitacion' => $numeroHabitacion,
                'piso' => $piso === false ? 0 : $piso,
                'capacidad' => $capacidad === false ? 0 : $capacidad,
            ],
        ];
    }

    // El normalizador se vino al servicio porque es lógica de formateo
    private function normalizarEstado($estado)
    {
        $estado = strtolower(trim((string) $estado));
        $mapa = [
            'disponible' => 'Disponible',
            'ocupada' => 'Ocupada',
            'ocupado' => 'Ocupada',
            'mantenimiento' => 'Mantenimiento',
            'mantenimie' => 'Mantenimiento',
            'en limpieza' => 'En Limpieza',
            'limpieza' => 'En Limpieza',
        ];
        return $mapa[$estado] ?? 'Disponible';
    }
}
