<?php

namespace Models\Entities;

use Illuminate\Database\Eloquent\Model as Eloquent;

class Hotel extends Eloquent {
    protected $table = 'hotel';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'nombre', 'ruc', 'telefono', 'email', 'direccion',
        'ciudad_region', 'descripcion', 'moneda', 'check_in',
        'check_out', 'web', 'porcentaje_adelanto',
        'porcentaje_penalidad_cancelacion',
    ];
}