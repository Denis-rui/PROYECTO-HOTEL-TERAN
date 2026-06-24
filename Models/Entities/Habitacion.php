<?php
namespace Models\Entities;

use Illuminate\Database\Eloquent\Model as Eloquent;

class Habitacion extends Eloquent
{
    protected $table = 'habitacion';
    public $timestamps = false;
    protected $fillable = [
        'numero_habitacion', 'piso', 'id_tipo_habitacion', 'estado',
        'descripcion_habitacion', 'capacidad', 'activo' 
    ];

    public function reservas()
    {
        return $this->hasMany(Reserva::class, 'id_habitacion');
    }

    public function reservaHabitaciones()
    {
        return $this->hasMany(ReservaHabitacion::class, 'id_habitacion');
    }
    
    //Accedemos al tipo de habitación (y su precio_base) sin JOIN manual.
    public function tipoHabitacion()
    {
        return $this->belongsTo(TipoHabitacion::class, 'id_tipo_habitacion');
    }
}
