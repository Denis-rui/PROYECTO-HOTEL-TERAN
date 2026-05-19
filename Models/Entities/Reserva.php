<?php
namespace Models\Entities;

use Illuminate\Database\Eloquent\Model as Eloquent;

class Reserva extends Eloquent
{
    protected $table = 'reserva';
    public $timestamps = false;
    protected $fillable = [
        'id_cliente', 'id_habitacion', 'check_in', 'check_out', 'total', 'estado',
        'codigo_reserva', 'id_usuario', 'observaciones', 'check_in_real', 'check_out_real',
        'minutos_demora_checkout', 'cargo_checkout_tarde'
    ];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'id_cliente');
    }

    public function habitacion()
    {
        return $this->belongsTo(Habitacion::class, 'id_habitacion');
    }

    public function pagos()
    {
        return $this->hasMany(Pago::class, 'id_reserva');
    }

    public function reservaHabitacion()
    {
        return $this->hasOne(ReservaHabitacion::class, 'id_reserva');
    }
}
