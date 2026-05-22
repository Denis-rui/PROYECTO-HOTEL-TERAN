<?php
namespace Models\Entities;

use Illuminate\Database\Eloquent\Model as Eloquent;

class Notificacion extends Eloquent
{
    protected $table = 'notificacion';
    public $timestamps = false;

    protected $fillable = [
        'tipo',
        'titulo',
        'mensaje',
        'id_reserva',
        'id_habitacion',
        'id_cliente',
        'leida',
        'fecha_creacion',
        'prioridad',
    ];

    public function reserva()
    {
        return $this->belongsTo(Reserva::class, 'id_reserva');
    }

    public function habitacion()
    {
        return $this->belongsTo(Habitacion::class, 'id_habitacion');
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'id_cliente');
    }
}