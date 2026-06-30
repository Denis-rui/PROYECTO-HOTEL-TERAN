<?php
namespace Models\Entities;

use Illuminate\Database\Eloquent\Model as Eloquent;

class Reserva extends Eloquent
{
    public const ESTADOS_ACTIVOS = [
        'pendiente',
        'confirmada',
        'checkin_realizado',
        'en_estadia',
        'checkout_pendiente',
    ];

    public const ESTADOS_BLOQUEANTES = [
        'pendiente',
        'confirmada',
        'checkin_realizado',
        'en_estadia',
        'checkout_pendiente',
        'ausente',
    ];

    public const ESTADOS_OCUPACION_ACTUAL = [
        'checkin_realizado',
        'en_estadia',
        'checkout_pendiente',
        'ausente',
    ];

    public const ESTADOS_PRE_CHECKIN = [
        'pendiente',
        'confirmada',
    ];

    public const ESTADOS_EN_ESTADIA = [
        'en_estadia',
        'checkout_pendiente',
    ];

    protected $table = 'reserva';
    public $timestamps = false;
    protected $fillable = [
        'id_cliente' , 'total', 'estado',
        'codigo_reserva', 'id_usuario', 'observaciones',
        'fecha_creacion',
        'minutos_demora_checkout', 'cargo_checkout_tarde',
        'checkin_real', 'checkout_real'
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
        return $this->hasMany(ReservaHabitacion::class, 'id_reserva');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario');
    }
}
