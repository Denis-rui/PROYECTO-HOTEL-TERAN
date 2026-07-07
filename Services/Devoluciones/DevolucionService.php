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

    public function listarParaDataTable(array $parametros): array
    {
        $resultado = $this->devolucionModel->obtenerDevolucionesDataTable($parametros);

        return [
            'draw' => (int) ($parametros['draw'] ?? 0),
            'recordsTotal' => (int) ($resultado['total'] ?? 0),
            'recordsFiltered' => (int) ($resultado['filtrados'] ?? 0),
            'data' => $resultado['items'] ?? [],
        ];
    }

    public function registrarDevolucion(array $data, ?int $idUsuario): array
    {
        try {
            $idReserva = (int) ($data['id_reserva'] ?? 0);
            if ($idReserva <= 0) {
                return $this->respuesta(false, 'VALIDACION_ERROR', 'Seleccione una reserva válida.', null, [
                    'id_reserva' => 'La reserva es obligatoria.',
                ]);
            }

            $reservaModel = new ReservaModel();
            $reserva = $reservaModel->obtenerReservaSimple($idReserva);

            // 1. Validaciones de Negocio
            if (!$reserva) {
                return $this->respuesta(false, 'NO_ENCONTRADO', 'Reserva no encontrada.');
            }

            if ($reserva->estado !== 'cancelada') {
                return $this->respuesta(false, 'CONFLICTO', 'Solo corresponde a reservas canceladas.');
            }
            if (!empty($reserva->checkout_real) || $reserva->estado === 'checkout_realizado') {
                return $this->respuesta(false, 'CONFLICTO', 'La reserva ya tiene un checkout realizado.');
            }

            // 2. Llamar al otro servicio para el cálculo
            $calculo = $this->calculoService->calcular($idReserva, $data['fecha_cancelacion'] ?? null);

            if (!($calculo['exito'] ?? false)) {
                return $this->respuesta(
                    false,
                    (string) ($calculo['codigo'] ?? 'ERROR_INTERNO'),
                    'Error en el cálculo: ' . ($calculo['mensaje'] ?? 'Cálculo fallido.')
                );
            }

            $datosCalculo = $calculo['data'] ?? [];
            if (!$this->calculoTieneDatosRequeridos($datosCalculo)) {
                return $this->respuesta(false, 'ERROR_INTERNO', 'El cálculo de devolución no devolvió datos completos.');
            }

            // 3. Preparar datos y guardar en BD
            $datosGuardar = [
                'id_reserva' => $idReserva,
                'fecha_cancelacion' => $datosCalculo['fecha_cancelacion'],
                'fecha_inicio' => $datosCalculo['fecha_inicio'],
                'fecha_prevista' => $datosCalculo['fecha_prevista'],
                'dias_usados' => $datosCalculo['dias_usados'],
                'dias_no_usados' => $datosCalculo['dias_no_usados'],
                'total_no_ocupado' => $datosCalculo['total_no_ocupado'],
                'porcentaje_penalidad' => $datosCalculo['porcentaje_penalidad'],
                'monto_penalidad' => $datosCalculo['monto_penalidad'],
                'monto_devuelto' => $datosCalculo['monto_devuelto'],
                'id_usuario' => $idUsuario,
            ];

            $guardado = $this->devolucionModel->guardar($idReserva, $datosGuardar);

            if (!$guardado) {
                return $this->respuesta(false, 'ERROR_GUARDADO', 'No se pudo guardar la devolución en la base de datos.');
            }

            return $this->respuesta(true, 'CREADO', 'Devolución registrada con el cálculo vigente.', [
                'id_reserva' => $idReserva,
                'calculo' => $datosCalculo,
            ]);
        } catch (Exception $e) {
            error_log('Error al registrar devolución: ' . $e->getMessage());
            return $this->respuesta(false, 'ERROR_INTERNO', 'Error inesperado al registrar la devolución.');
        }
    }

    public function actualizarDevolucion(array $data, ?int $idUsuario): array
    {
        try {
            $id = (int) ($data['id'] ?? 0);
            if ($id <= 0) {
                return $this->respuesta(false, 'VALIDACION_ERROR', 'Seleccione una devolución válida.', null, [
                    'id' => 'La devolución es obligatoria.',
                ]);
            }

            $devolucion = $this->devolucionModel->obtenerDevolucion($id);

            if (!$devolucion) {
                return $this->respuesta(false, 'NO_ENCONTRADO', 'No se encontró la devolución a actualizar.');
            }

            $calculo = $this->calculoService->calcular(
                (int) $devolucion->id_reserva,
                $data['fecha_cancelacion'] ?? $devolucion->fecha_cancelacion
            );

            if (!($calculo['exito'] ?? false)) {
                return $this->respuesta(
                    false,
                    (string) ($calculo['codigo'] ?? 'ERROR_INTERNO'),
                    'Error en el cálculo: ' . ($calculo['mensaje'] ?? 'Cálculo fallido.')
                );
            }

            $datosCalculo = $calculo['data'] ?? [];
            if (!$this->calculoTieneDatosRequeridos($datosCalculo)) {
                return $this->respuesta(false, 'ERROR_INTERNO', 'El cálculo de devolución no devolvió datos completos.');
            }

            $datosActualizar = [
                'fecha_cancelacion' => $datosCalculo['fecha_cancelacion'],
                'dias_usados' => $datosCalculo['dias_usados'],
                'dias_no_usados' => $datosCalculo['dias_no_usados'],
                'total_no_ocupado' => $datosCalculo['total_no_ocupado'],
                'porcentaje_penalidad' => $datosCalculo['porcentaje_penalidad'],
                'monto_penalidad' => $datosCalculo['monto_penalidad'],
                'monto_devuelto' => $datosCalculo['monto_devuelto'],
                'id_usuario' => $idUsuario,
            ];

            $actualizado = $this->devolucionModel->actualizar($id, $datosActualizar);

            if (!$actualizado) {
                return $this->respuesta(false, 'ERROR_GUARDADO', 'No se pudo actualizar la devolución.');
            }

            return $this->respuesta(true, 'ACTUALIZADO', 'Devolución recalculada correctamente.', [
                'id' => $id,
                'id_reserva' => (int) $devolucion->id_reserva,
                'calculo' => $datosCalculo,
            ]);
        } catch (Exception $e) {
            error_log('Error al actualizar devolución: ' . $e->getMessage());
            return $this->respuesta(false, 'ERROR_INTERNO', 'Error inesperado al actualizar la devolución.');
        }
    }

    public function eliminarDevolucion(int $id): array
    {
        try {
            if ($id <= 0) {
                return $this->respuesta(false, 'VALIDACION_ERROR', 'Seleccione una devolución válida.', null, [
                    'id' => 'La devolución es obligatoria.',
                ]);
            }

            $exito = $this->devolucionModel->eliminar($id);
            return $exito
                ? $this->respuesta(true, 'ELIMINADO', 'Devolución eliminada.', ['id' => $id])
                : $this->respuesta(false, 'NO_ENCONTRADO', 'No se encontró la devolución a eliminar.');
        } catch (Exception $e) {
            error_log('Error al eliminar devolución: ' . $e->getMessage());
            return $this->respuesta(false, 'ERROR_INTERNO', 'Error inesperado al eliminar.');
        }
    }

    private function respuesta(bool $exito, string $codigo, string $mensaje, mixed $data = null, array $errores = []): array
    {
        return [
            'exito' => $exito,
            'codigo' => $codigo,
            'mensaje' => $mensaje,
            'data' => $data,
            'errores' => array_filter($errores, fn($error) => $error !== null),
        ];
    }

    private function calculoTieneDatosRequeridos(array $calculo): bool
    {
        $campos = [
            'fecha_cancelacion',
            'fecha_inicio',
            'fecha_prevista',
            'dias_usados',
            'dias_no_usados',
            'total_no_ocupado',
            'porcentaje_penalidad',
            'monto_penalidad',
            'monto_devuelto',
        ];

        foreach ($campos as $campo) {
            if (!array_key_exists($campo, $calculo)) {
                return false;
            }
        }

        return true;
    }
}
