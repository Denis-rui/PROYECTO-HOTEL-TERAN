<?php
namespace Models;

use Models\Entities\Hotel;

class HotelModel
{
    public function find($id){
        $hotel = Hotel::find($id);
        return $hotel ? $hotel->toArray() : [];
    }

    public function actualizarHotel($datos)
    {
        $hotel = Hotel::find(1);

        if (!$hotel) return false;

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

        return $hotel->save();
    }
}