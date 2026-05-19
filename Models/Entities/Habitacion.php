<?php
namespace Models\Entities;

use Illuminate\Database\Eloquent\Model as Eloquent;

class Habitacion extends Eloquent
{
    protected $table = 'habitacion';
    public $timestamps = false;
    protected $fillable = [
        'numero_habitacion', 'piso', 'id_tipo_habitacion', 'estado',
        'descripcion_habitacion', 'capacidad', 'activo', 'estado_operativo', 'estado_limpieza'
    ];

    public function reservas()
    {
        return $this->hasMany(Reserva::class, 'id_habitacion');
    }

    public function reservaHabitaciones()
    {
        return $this->hasMany(ReservaHabitacion::class, 'id_habitacion');
    }
}
