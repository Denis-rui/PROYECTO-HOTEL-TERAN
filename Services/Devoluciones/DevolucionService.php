<?php

namespace Services\Devoluciones;

use Models\DevolucionModel;
use Services\Devoluciones\CalculoDevolucionService;
use Models\ReservaModel;
use Exception;

class DevolucionService
{
    private DevolucionModel $devolucionModel;
    private CalculoDevolucionService $calculoService;

    public function __construct()
    {
        $this->devolucionModel = new DevolucionModel();
        $this->calculoService = new CalculoDevolucionService();
    }

    public function listarDevoluciones(string $busqueda = ''): array
    {
        try {
            $datos = $this->devolucionModel->listar($busqueda);
            return ['exito' => true, 'mensaje' => 'Listado correcto', 'data' => $datos];
        } catch (Exception $e) {
            error_log('Error al listar devoluciones: ' . $e->getMessage());
            return ['exito' => false, 'mensaje' => 'Error al cargar las devoluciones', 'data' => []];
        }
    }

    public function registrarDevolucion(array $data, ?int $idUsuario): array
    {
        try {
            $idReserva = (int) ($data['id_reserva'] ?? 0);
            $reservaModel = new ReservaModel();
            $reserva = $reservaModel->obtenerReservaSimple($idReserva);

            // 1. Validaciones de Negocio
            if (!$reserva || $reserva->estado !== 'cancelada') {
                return ['exito' => false, 'mensaje' => 'Solo corresponde a reservas canceladas.'];
            }
            if (!empty($reserva->checkout_real) || $reserva->estado === 'checkout_realizado') {
                return ['exito' => false, 'mensaje' => 'La reserva ya tiene un checkout realizado.'];
            }

            // 2. Llamar al otro servicio para el cálculo
            $calculo = $this->calculoService->calcular($idReserva, $data['fecha_cancelacion'] ?? null);

            if (!($calculo['exito'] ?? false)) {
                return ['exito' => false, 'mensaje' => 'Error en el cálculo: ' . ($calculo['mensaje'] ?? 'Cálculo fallido.')];
            }

            // 3. Preparar datos y guardar en BD
            $datosGuardar = [
                'id_reserva' => $idReserva,
                'fecha_cancelacion' => $calculo['fecha_cancelacion'],
                'fecha_inicio' => $calculo['fecha_inicio'],
                'fecha_prevista' => $calculo['fecha_prevista'],
                'dias_usados' => $calculo['dias_usados'],
                'dias_no_usados' => $calculo['dias_no_usados'],
                'total_no_ocupado' => $calculo['total_no_ocupado'],
                'porcentaje_penalidad' => $calculo['porcentaje_penalidad'],
                'monto_penalidad' => $calculo['monto_penalidad'],
                'monto_devuelto' => $calculo['monto_devuelto'],
                'id_usuario' => $idUsuario,
            ];

            $guardado = $this->devolucionModel->guardar($idReserva, $datosGuardar);

            return $guardado
                ? ['exito' => true, 'mensaje' => 'Devolución registrada con el cálculo vigente.']
                : ['exito' => false, 'mensaje' => 'No se pudo guardar la devolución en la base de datos.'];
        } catch (Exception $e) {
            error_log('Error al registrar devolución: ' . $e->getMessage());
            return ['exito' => false, 'mensaje' => 'Error inesperado al registrar la devolución.'];
        }
    }

    public function actualizarDevolucion(array $data, ?int $idUsuario): array
    {
        try {
            $id = (int) ($data['id'] ?? 0);
            $devolucion = $this->devolucionModel->obtenerDevolucion($id);

            if (!$devolucion) {
                return ['exito' => false, 'mensaje' => 'No se encontró la devolución a actualizar.'];
            }

            $calculo = $this->calculoService->calcular(
                (int) $devolucion->id_reserva,
                $data['fecha_cancelacion'] ?? $devolucion->fecha_cancelacion
            );

            if (!($calculo['exito'] ?? false)) {
                return ['exito' => false, 'mensaje' => 'Error en el cálculo: ' . ($calculo['mensaje'] ?? 'Cálculo fallido.')];
            }

            $datosActualizar = [
                'fecha_cancelacion' => $calculo['fecha_cancelacion'],
                'dias_usados' => $calculo['dias_usados'],
                'dias_no_usados' => $calculo['dias_no_usados'],
                'total_no_ocupado' => $calculo['total_no_ocupado'],
                'porcentaje_penalidad' => $calculo['porcentaje_penalidad'],
                'monto_penalidad' => $calculo['monto_penalidad'],
                'monto_devuelto' => $calculo['monto_devuelto'],
                'id_usuario' => $idUsuario,
            ];

            $actualizado = $this->devolucionModel->actualizar($id, $datosActualizar);

            return $actualizado
                ? ['exito' => true, 'mensaje' => 'Devolución recalculada correctamente.']
                : ['exito' => false, 'mensaje' => 'No se pudo actualizar la devolución.'];
        } catch (Exception $e) {
            error_log('Error al actualizar devolución: ' . $e->getMessage());
            return ['exito' => false, 'mensaje' => 'Error inesperado al actualizar la devolución.'];
        }
    }

    public function eliminarDevolucion(int $id): array
    {
        try {
            $exito = $this->devolucionModel->eliminar($id);
            return $exito
                ? ['exito' => true, 'mensaje' => 'Devolución eliminada.']
                : ['exito' => false, 'mensaje' => 'No se pudo eliminar la devolución.'];
        } catch (Exception $e) {
            error_log('Error al eliminar devolución: ' . $e->getMessage());
            return ['exito' => false, 'mensaje' => 'Error inesperado al eliminar.'];
        }
    }
}
