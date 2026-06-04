<?php
namespace Models\Entities;

use Illuminate\Database\Eloquent\Model as Eloquent;

class ReservaHabitacion extends Eloquent
{
    protected $table = 'reserva_habitacion';
    public $timestamps = false;
    protected $fillable = [
        'id_reserva',
        'id_habitacion',
        'check_in',
        'check_out',
        'activo',
        'tipo_asignacion',
        'estado',
        'motivo_cambio',
        'id_usuario_movimiento',
        'fecha_movimiento',
        'precio_aplicado',
        'subtotal'
    ];

    public function reserva()
    {
        return $this->belongsTo(Reserva::class, 'id_reserva');
    }

    public function habitacion()
    {
        return $this->belongsTo(Habitacion::class, 'id_habitacion');
    }
}
