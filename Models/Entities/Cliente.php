<?php

namespace Models\Entities;

use Illuminate\Database\Eloquent\Model as Eloquent;

class Cliente extends Eloquent
{
    protected $table = 'cliente';
    public $timestamps = false;
    protected $fillable = [
        'nombres',
        'apellido_paterno',
        'apellido_materno',
        'id_tipo_documento',
        'documento',
        'ruc',
        'correo_electronico',
        'telefono',
        'procedencia',
        'reservaciones',
        'activo',
        'observaciones',
        'fecha_creacion',
        'alerta_interna'
    ];

    public function reservas()
    {
        return $this->hasMany(Reserva::class, 'id_cliente');
    }
}
