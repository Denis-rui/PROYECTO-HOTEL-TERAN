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

    public function obtenerPendientes($limite = 30): array
    {
        $limite = max(1, (int) $limite);
        $clavesActivasCheckout = $this->notificacionModel->obtenerClavesActivasCheckout();
        $notificacionesDb = $this->notificacionModel->obtenerNoLeidas(max(200, $limite * 10));

        $resultado = [];
        $clavesAgregadas = [];

        foreach ($notificacionesDb as $item) {
            $tipo = (string) $item['tipo'];
            $titulo = strtolower((string) ($item['titulo'] ?? ''));
            $mensaje = strtolower((string) ($item['mensaje'] ?? ''));
            $idHabitacion = (int) ($item['id_habitacion'] ?? 0);
            $estadoHabitacion = strtolower((string) ($item['habitacion']['estado'] ?? ''));

            $esNotificacionLimpieza = strpos($tipo, 'limpieza') !== false
                || strpos($titulo, 'limpieza') !== false
                || strpos($mensaje, 'limpieza') !== false
                || strpos($mensaje, 'mantenimiento') !== false;

            if (
                $idHabitacion > 0
                && $esNotificacionLimpieza
                && !in_array($estadoHabitacion, ['mantenimiento', 'en limpieza'], true)
            ) {
                continue;
            }

            if ($esNotificacionLimpieza && $idHabitacion <= 0) {
                continue;
            }

            if (!$esNotificacionLimpieza && ($tipo === 'checkout' || strpos($tipo, 'checkout_') === 0)) {
                $claveCheckout = (int) $item['id_reserva'] . '|' . $idHabitacion;
                if (!isset($clavesActivasCheckout[$claveCheckout])) {
                    continue;
                }
            }

            $claveNotificacion = $esNotificacionLimpieza && $idHabitacion > 0
                ? 'limpieza|' . $idHabitacion
                : $tipo . '|' . (int) $item['id_reserva'] . '|' . $idHabitacion;

            if (isset($clavesAgregadas[$claveNotificacion])) {
                continue;
            }

            $item['titulo'] = $this->normalizarTextoNotificacion((string) ($item['titulo'] ?? ''));
            $item['mensaje'] = $this->normalizarTextoNotificacion((string) ($item['mensaje'] ?? ''));

            $clavesAgregadas[$claveNotificacion] = true;
            $resultado[] = $item;

            if (count($resultado) >= $limite) {
                break;
            }
        }

        return $resultado;
    }

    private function normalizarTextoNotificacion(string $texto): string
    {
        return strtr($texto, [
            'Ã¡' => 'a',
            'Ã©' => 'e',
            'Ã­' => 'i',
            'Ã³' => 'o',
            'Ãº' => 'u',
            'Ã±' => 'n',
            'Ã' => 'A',
            'Ã‰' => 'E',
            'Ã' => 'I',
            'Ã“' => 'O',
            'Ãš' => 'U',
            'Ã‘' => 'N',
            'Â¿' => '',
            'Â¡' => '',
            'Â°' => 'o',
        ]);
    }

    public function obtenerNotificacionesCheckout(): array
    {
        try {
            $reservas = $this->notificacionModel->obtenerReservasEnCheckout();
            $notificaciones = $this->obtenerPendientes(30);

            $data = [
                'proximos' => array_values(array_filter($reservas, fn($r) => (int) $r['minutos_faltantes'] >= 0 && (int) $r['minutos_faltantes'] <= 120)),
                'vencidos' => array_values(array_filter($reservas, fn($r) => (int) $r['minutos_excedidos'] > 0)),
                'notificaciones' => $notificaciones,
            ];

            return $this->respuesta(true, 'OK', 'Notificaciones cargadas.', $data);
        } catch (Exception $e) {
            error_log('Error obtenerNotificacionesCheckout: ' . $e->getMessage());

            return $this->respuesta(false, 'ERROR_INTERNO', 'Error al cargar las notificaciones.', [
                    'proximos' => [],
                    'vencidos' => [],
                    'notificaciones' => []
                ]);
        }
    }

    private function respuesta(bool $exito, string $codigo, string $mensaje, mixed $data = null, array $errores = []): array
    {
        $respuesta = [
            'exito' => $exito,
            'codigo' => $codigo,
            'mensaje' => $mensaje,
            'data' => $data,
            'errores' => $errores,
        ];

        return is_array($data) ? array_merge($respuesta, $data) : $respuesta;
    }
}
