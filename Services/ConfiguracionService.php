<?php

namespace Services;

use Models\ConfiguracionModel;
use Models\TipoHabitacionModel;

class ConfiguracionService
{
    private ConfiguracionModel $configuracionModel;
    private TipoHabitacionModel $tipoHabitacionModel;

    public function __construct()
    {
        $this->configuracionModel = new ConfiguracionModel();
        $this->tipoHabitacionModel = new TipoHabitacionModel();
    }

    public function obtenerHotel(int $id = 1): array
    {
        try {
            $hotel = $this->configuracionModel->find($id);

            if (!$hotel) {
                return [
                    'exito' => false,
                    'codigo' => 'NO_ENCONTRADO',
                    'mensaje' => 'Configuración no encontrada.',
                    'data' => null,
                    'errores' => [],
                ];
            }

            return [
                'exito' => true,
                'codigo' => 'OK',
                'mensaje' => 'Configuración encontrada.',
                'data' => $hotel->toArray(),
                'errores' => [],
            ];
        } catch (\Throwable $e) {
            error_log('Error al obtener configuración: ' . $e->getMessage());
            return [
                'exito' => false,
                'codigo' => 'ERROR_INTERNO',
                'mensaje' => 'Ocurrió un error al cargar la configuración.',
                'data' => null,
                'errores' => [],
            ];
        }
    }

    public function actualizarHotel(array $datos): array
    {
        try {
            $hotel = $this->configuracionModel->find(1);

            if (!$hotel) {
                return [
                    'exito' => false,
                    'codigo' => 'NO_ENCONTRADO',
                    'mensaje' => 'Configuración no encontrada.',
                    'data' => null,
                    'errores' => [],
                ];
            }

            $hotel->nombre                          = $datos['nombre']               ?? $hotel->nombre;
            $hotel->ruc                             = $datos['ruc']                  ?? $hotel->ruc;
            $hotel->telefono                        = $datos['telefono']             ?? $hotel->telefono;
            $hotel->email                           = $datos['email']                ?? $hotel->email;
            $hotel->direccion                       = $datos['direccion']            ?? $hotel->direccion;
            $hotel->ciudad_region                   = $datos['ciudad_region']        ?? $hotel->ciudad_region;
            $hotel->descripcion                     = $datos['descripcion']          ?? $hotel->descripcion;
            $hotel->moneda                          = $datos['monedas']              ?? $hotel->moneda;
            $hotel->check_in                        = $datos['check_in']             ?? $hotel->check_in;
            $hotel->check_out                       = $datos['check_out']            ?? $hotel->check_out;
            $hotel->web                             = $datos['web_redes']            ?? $hotel->web;
            $hotel->porcentaje_adelanto             = $datos['porcentaje_adelanto']  ?? $hotel->porcentaje_adelanto;
            $hotel->porcentaje_penalidad_cancelacion = $datos['porcentaje_penalidad'] ?? $hotel->porcentaje_penalidad_cancelacion;

            $exito = $this->configuracionModel->actualizarHotel($hotel);
            if ($exito) {
                return [
                    'exito' => true,
                    'codigo' => 'ACTUALIZADO',
                    'mensaje' => 'Configuración actualizada correctamente.',
                    'data' => $hotel->toArray(),
                    'errores' => [],
                ];
            }

            return [
                'exito' => false,
                'codigo' => 'ERROR_GUARDADO',
                'mensaje' => 'No se pudieron guardar los cambios en la base de datos.',
                'data' => null,
                'errores' => [],
            ];
        } catch (\Exception $e) {
            error_log('Error al actualizar configuración: ' . $e->getMessage());
            return [
                'exito' => false,
                'codigo' => 'ERROR_INTERNO',
                'mensaje' => 'Ocurrió un error al actualizar la configuración. Intente nuevamente.',
                'data' => null,
                'errores' => [],
            ];
        }
    }

    public function obtenerTiposHabitacion(): array
    {
        return $this->tipoHabitacionModel->listar();
    }

    public function guardarTipoHabitacion(array $datos): array
    {
        try {
            $id = isset($datos['id']) && $datos['id'] !== '' ? (int) $datos['id'] : null;
            $tipo = trim((string) ($datos['tipo'] ?? ''));
            $precio = $datos['precio_base'] ?? null;

            if ($tipo === '' || !is_numeric($precio) || (float) $precio <= 0) {
                return [
                    'exito' => false,
                    'codigo' => 'VALIDACION_ERROR',
                    'mensaje' => 'Ingrese un tipo de habitación y un precio válido.',
                    'data' => null,
                    'errores' => [
                        'tipo' => $tipo === '' ? 'El tipo de habitación es obligatorio.' : null,
                        'precio_base' => (!is_numeric($precio) || (float) $precio <= 0) ? 'El precio debe ser mayor a cero.' : null,
                    ],
                ];
            }

            $guardado = $this->tipoHabitacionModel->guardar($id, [
                'tipo' => $tipo,
                'precio_base' => (float) $precio,
            ]);

            if (!$guardado) {
                return [
                    'exito' => false,
                    'codigo' => 'ERROR_GUARDADO',
                    'mensaje' => 'No se pudo guardar el tipo de habitación.',
                    'data' => null,
                    'errores' => [],
                ];
            }

            return [
                'exito' => true,
                'codigo' => $id !== null ? 'ACTUALIZADO' : 'CREADO',
                'mensaje' => $id !== null ? 'Actualizado correctamente' : 'Creado correctamente',
                'data' => [
                    'id' => $id,
                    'tipo' => $tipo,
                    'precio_base' => (float) $precio,
                ],
                'errores' => [],
            ];
        } catch (\Throwable $e) {
            error_log('Error al guardar tipo de habitación: ' . $e->getMessage());
            return [
                'exito' => false,
                'codigo' => 'ERROR_INTERNO',
                'mensaje' => 'Ocurrió un error al guardar el tipo de habitación.',
                'data' => null,
                'errores' => [],
            ];
        }
    }
}
