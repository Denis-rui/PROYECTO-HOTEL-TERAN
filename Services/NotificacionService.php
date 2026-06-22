<?php

namespace Services;

use Models\NotificacionModel;
use Exception;

class NotificacionService
{
    private NotificacionModel $notificacionModel;

    public function __construct()
    {
        $this->notificacionModel = new NotificacionModel();
    }

    public function crearNotificacion($tipo, $titulo, $mensaje, $idReserva = null, $idHabitacion = null, $idCliente = null, $prioridad = 'media'): array
    {
        try {
            $datosIdentificadores = [
                'tipo' => $tipo,
                'id_reserva' => $idReserva ? (int) $idReserva : null,
                'id_habitacion' => $idHabitacion ? (int) $idHabitacion : null,
                'leida' => 0,
            ];

            $datosActualizar = [
                'tipo' => $tipo,
                'titulo' => $titulo,
                'mensaje' => $mensaje,
                'id_reserva' => $idReserva ? (int) $idReserva : null,
                'id_habitacion' => $idHabitacion ? (int) $idHabitacion : null,
                'id_cliente' => $idCliente ? (int) $idCliente : null,
                'leida' => 0,
                'prioridad' => $prioridad,
            ];

            $guardado = $this->notificacionModel->guardarNotificacion($datosIdentificadores, $datosActualizar);

            return [
                'exito' => $guardado,
                'mensaje' => $guardado ? 'Notificación creada.' : 'No se pudo crear la notificación.'
            ];
        } catch (Exception $e) {
            error_log('Error crear notificacion: ' . $e->getMessage());
            return ['exito' => false, 'mensaje' => 'Error inesperado al crear notificación.'];
        }
    }

    public function obtenerPendientes($limite = 30): array
    {
        $limite = max(1, (int) $limite);
        $clavesActivasCheckout = $this->notificacionModel->obtenerClavesActivasCheckout();
        $notificacionesDb = $this->notificacionModel->obtenerNoLeidas(max(200, $limite * 10));

        $resultado = [];
        $clavesAgregadas = [];

        foreach ($notificacionesDb as $item) {
            $tipo = (string) $item['tipo'];

            if (strpos($tipo, 'checkout_') === 0) {
                $claveCheckout = (int) $item['id_reserva'] . '|' . (int) $item['id_habitacion'];
                if (!isset($clavesActivasCheckout[$claveCheckout])) {
                    continue; // Ignorar si no está activa
                }
            }

            $claveNotificacion = $tipo . '|' . (int) $item['id_reserva'] . '|' . (int) $item['id_habitacion'];

            if (isset($clavesAgregadas[$claveNotificacion])) {
                continue; // Evitar duplicados
            }

            $clavesAgregadas[$claveNotificacion] = true;
            $resultado[] = $item;

            if (count($resultado) >= $limite) {
                break;
            }
        }

        return $resultado;
    }

    public function obtenerNotificacionesCheckout(): array
    {
        try {
            $reservas = $this->notificacionModel->obtenerReservasEnCheckout();
            $notificaciones = $this->obtenerPendientes(30);

            // Filtrar reservas según minutos
            $data = [
                'proximos' => array_values(array_filter($reservas, fn($r) => (int) $r['minutos_faltantes'] >= 0 && (int) $r['minutos_faltantes'] <= 120)),
                'vencidos' => array_values(array_filter($reservas, fn($r) => (int) $r['minutos_excedidos'] > 0)),
                'notificaciones' => $notificaciones,
            ];

            return ['exito' => true, 'mensaje' => 'Notificaciones cargadas.', 'data' => $data];
        } catch (Exception $e) {
            error_log('Error obtenerNotificacionesCheckout: ' . $e->getMessage());

            // Retornar estructura vacía segura para no romper la vista
            return [
                'exito' => false,
                'mensaje' => 'Error al cargar las notificaciones.',
                'data' => [
                    'proximos' => [],
                    'vencidos' => [],
                    'notificaciones' => []
                ]
            ];
        }
    }
}
