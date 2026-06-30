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
            $estadoNorm = $this->normalizarEstado($datos['estado'] ?? 'Disponible');

            $datosGuardar = [
                'numero_habitacion' => $datos['numero_habitacion'] ?? '',
                'piso' => (int) ($datos['piso'] ?? 1),
                'id_tipo_habitacion' => $datos['id_tipo_habitacion'] ?? null,
                'estado' => $estadoNorm,
                'descripcion_habitacion' => $datos['descripcion_habitacion'] ?? $datos['descripcion'] ?? '',
                'capacidad' => (int) ($datos['capacidad'] ?? 1),
                'activo' => (int) ($datos['activo'] ?? 1),
            ];

            $habitacion = $this->habitacionModel->crear($datosGuardar);
            return ['exito' => true, 'mensaje' => 'Habitación registrada correctamente.', 'data' => $habitacion];
        } catch (Exception $e) {
            // Manejo de número duplicado (Error 1062 en SQL)
            if ($e->getCode() == 23000 || strpos($e->getMessage(), '1062') !== false) {
                return ['exito' => false, 'mensaje' => "La habitación número " . ($datos['numero_habitacion'] ?? '') . " ya está registrada."];
            }
            error_log("Error al registrar habitación: " . $e->getMessage());
            return ['exito' => false, 'mensaje' => 'Error inesperado al registrar la habitación.'];
        }
    }

    public function editar(array $datos): array
    {
        try {
            $id = (int) ($datos['id'] ?? 0);
            $habitacion = $this->habitacionModel->find($id);

            if (!$habitacion) return ['exito' => false, 'mensaje' => 'Habitación no encontrada.'];

            // 1. Bloquear si tiene reservas activas
            if ($this->habitacionModel->obtenerReservaActiva($id)) {
                return ['exito' => false, 'mensaje' => 'No se puede editar la habitación porque está reservada.'];
            }

            // 2. Bloquear si está en mantenimiento
            if (strtolower($habitacion->estado) === 'mantenimiento') {
                return ['exito' => false, 'mensaje' => 'No se puede editar porque está en mantenimiento.'];
            }

            $datosActualizar = [
                'numero_habitacion' => $datos['numero_habitacion'] ?? $habitacion->numero_habitacion,
                'piso' => (int) ($datos['piso'] ?? $habitacion->piso),
                'id_tipo_habitacion' => $datos['id_tipo_habitacion'] ?? $habitacion->id_tipo_habitacion,
                'estado' => $this->normalizarEstado($datos['estado'] ?? $habitacion->estado),
                'descripcion_habitacion' => $datos['descripcion_habitacion'] ?? $datos['descripcion'] ?? $habitacion->descripcion_habitacion,
                'capacidad' => (int) ($datos['capacidad'] ?? $habitacion->capacidad),
            ];

            $this->habitacionModel->actualizar($id, $datosActualizar);
            return ['exito' => true, 'mensaje' => 'Habitación actualizada correctamente.'];
        } catch (Exception $e) {
            if ($e->getCode() == 23000 || strpos($e->getMessage(), '1062') !== false) {
                return ['exito' => false, 'mensaje' => 'El número de habitación ya está en uso.'];
            }
            error_log("Error al editar habitación: " . $e->getMessage());
            return ['exito' => false, 'mensaje' => 'Error al actualizar la habitación.'];
        }
    }

    public function eliminar(int $id): array
    {
        try {
            $habitacion = $this->habitacionModel->find($id);
            if (!$habitacion) return ['exito' => false, 'mensaje' => 'Habitación no encontrada.'];

            if ($this->habitacionModel->obtenerReservaActiva($id)) {
                return ['exito' => false, 'mensaje' => 'No se puede eliminar la habitación porque está reservada. Primero cambia su estado.'];
            }

            if (strtolower($habitacion->estado) === 'mantenimiento') {
                return ['exito' => false, 'mensaje' => 'No se puede eliminar porque está en mantenimiento.'];
            }

            $this->habitacionModel->darDeBaja($id);
            return ['exito' => true, 'mensaje' => 'Habitación eliminada correctamente.'];
        } catch (Exception $e) {
            error_log("Error al eliminar habitación: " . $e->getMessage());
            return ['exito' => false, 'mensaje' => 'Error al intentar eliminar.'];
        }
    }

    public function actualizarEstado(int $id, string $estado, string $motivo = ''): array
    {
        try {
            $nuevoEstado = $this->normalizarEstado($estado);
            $habitacion = $this->habitacionModel->find($id);

            if (!$habitacion) return ['exito' => false, 'mensaje' => 'Habitación no encontrada.'];

            if ($nuevoEstado === 'Disponible') {
                $bloqueante = $this->habitacionModel->obtenerBloqueante($id);
                if ($bloqueante) {
                    $detalle = (array) $bloqueante;
                    if (!empty($detalle['check_out'])) {
                        $checkOutTs = strtotime($detalle['check_out']);
                        $detalle['minutos_faltantes'] = $checkOutTs > time() ? (int) floor(($checkOutTs - time()) / 60) : 0;
                    }
                    return ['exito' => false, 'mensaje' => 'Existe una reserva bloqueante.', 'reserva_bloqueante' => $detalle];
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

            return ['exito' => true, 'mensaje' => 'Estado actualizado correctamente.'];
        } catch (Exception $e) {
            error_log("Error actualizar estado: " . $e->getMessage());
            return ['exito' => false, 'mensaje' => 'Error al actualizar estado.'];
        }
    }

    public function buscar(string $numero, string $tipo, string $estado, string $piso): array
    {
        try {
            $estadoNorm = $estado ? $this->normalizarEstado($estado) : '';
            $datos = $this->habitacionModel->buscar($numero, $tipo, $estadoNorm, $piso);
            return ['exito' => true, 'data' => $datos];
        } catch (Exception $e) {
            error_log("Error en buscar: " . $e->getMessage());
            return ['exito' => false, 'data' => []];
        }
    }

    public function obtenerFiltros(): array
    {
        try {
            return ['exito' => true, 'data' => $this->habitacionModel->obtenerFiltros()];
        } catch (Exception $e) {
            error_log("Error al obtener filtros de habitaciones: " . $e->getMessage());
            return ['exito' => false, 'data' => []];
        }
    }

    public function disponiblesPorRango(
        string $checkIn,
        string $checkOut,
        ?string $tipo = null,
        ?string $piso = null,
        array $referencia = []
    ): array {
        try {
            return [
                'exito' => true,
                'data' => $this->reporteOcupacionModel->obtenerDisponiblesPorRango(
                    $checkIn,
                    $checkOut,
                    $tipo,
                    $piso,
                    $referencia
                ),
            ];
        } catch (Exception $e) {
            error_log("Error al obtener habitaciones disponibles: " . $e->getMessage());
            return ['exito' => false, 'data' => [], 'mensaje' => 'No se pudo consultar la disponibilidad.'];
        }
    }

    public function terminarLimpieza(int $id): array
    {
        try {
            $habitacion = $this->habitacionModel->find($id);
            if (!$habitacion) return ['exito' => false, 'mensaje' => 'Habitación no encontrada.'];

            $estadoActual = strtolower($habitacion->estado);
            if ($estadoActual !== 'mantenimiento' && $estadoActual !== 'en limpieza') {
                return ['exito' => false, 'mensaje' => 'La habitación no está en Mantenimiento o Limpieza.'];
            }

            $this->habitacionModel->actualizar($id, [
                'estado' => 'Disponible',
                'limpieza_inicio' => null,
                'descripcion_habitacion' => ''
            ]);

            // agregamos para que se actualicen las notificaciones

            $this->notificacionModel->marcarCheckoutLeidoPorHabitacion($id);
            $this->notificacionModel->marcarLimpiezaLeidaPorHabitacion($id);

            return ['exito' => true, 'mensaje' => 'Limpieza finalizada. Habitación disponible.'];
        } catch (Exception $e) {
            return ['exito' => false, 'mensaje' => 'Error al finalizar limpieza.'];
        }
    }

    public function notificarLimpiezaVencida(int $id): array
    {
        try {
            $habitacion = $this->habitacionModel->find($id);
            if (!$habitacion) return ['exito' => false, 'mensaje' => 'Habitacion no encontrada.'];

            if (strtolower((string) $habitacion->estado) !== 'en limpieza') {
                return ['exito' => false, 'mensaje' => 'La habitacion no esta en limpieza.'];
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

            return [
                'exito' => (bool) $guardado,
                'mensaje' => $guardado ? 'Alerta de limpieza vencida registrada.' : 'No se pudo registrar la alerta de limpieza.',
            ];
        } catch (Exception $e) {
            error_log("Error al notificar limpieza vencida: " . $e->getMessage());
            return ['exito' => false, 'mensaje' => 'Error al registrar la alerta de limpieza.'];
        }
    }

    public function extenderLimpieza(int $id, int $minutos = 15): array
    {
        try {
            $habitacion = $this->habitacionModel->find($id);
            if (!$habitacion) return ['exito' => false, 'mensaje' => 'Habitacion no encontrada.'];

            if (strtolower((string) $habitacion->estado) !== 'en limpieza') {
                return ['exito' => false, 'mensaje' => 'La habitacion no esta en limpieza.'];
            }

            $minutos = max(5, min(120, $minutos));
            $nuevoInicio = date('Y-m-d H:i:s', time() - (3600 - ($minutos * 60)));

            $this->habitacionModel->actualizar($id, [
                'limpieza_inicio' => $nuevoInicio,
            ]);
            $this->notificacionModel->marcarLimpiezaLeidaPorHabitacion($id);

            return ['exito' => true, 'mensaje' => 'Limpieza extendida por ' . $minutos . ' minutos.'];
        } catch (Exception $e) {
            error_log("Error al extender limpieza: " . $e->getMessage());
            return ['exito' => false, 'mensaje' => 'Error al extender la limpieza.'];
        }
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
            'reservada' => 'Reservada',
            'reservado' => 'Reservada',
        ];
        return $mapa[$estado] ?? 'Disponible';
    }
}
