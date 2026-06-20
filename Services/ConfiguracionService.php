<?php

namespace Services;

use Models\ConfiguracionModel;

class ConfiguracionService
{
    private ConfiguracionModel $configuracionModel;

    public function __construct()
    {
        $this->configuracionModel = new ConfiguracionModel();
    }

    public function obtenerHotel(int $id = 1): array
    {
        $hotel = $this->configuracionModel->find($id);

        if (!$hotel) {
            return [
                'exito' => false,
                'codigo' => 'NO_ENCONTRADO',
                'mensaje' => 'Configuración no encontrada.',
                'data' => null,
            ];
        }

        return [
            'exito' => true,
            'codigo' => 'OK',
            'mensaje' => 'Configuración encontrada.',
            'data' => $hotel ? $hotel->toArray() : null,
        ];
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
                    'data' => $hotel->toArray() // Opcional: devolver los datos actualizados
                ];
            }

            return [
                'exito' => false,
                'codigo' => 'ERROR_GUARDADO',
                'mensaje' => 'No se pudieron guardar los cambios en la base de datos.',
            ];
        } catch (\Exception $e) {
            error_log('Error al actualizar configuración: ' . $e->getMessage());
            return [
                'exito' => false,
                'codigo' => 'EXCEPCION',
                'mensaje' => 'Ocurrió un error al actualizar la configuración: ' . $e->getMessage(),
            ];
        }
    }
}
