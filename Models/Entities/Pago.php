<?php
namespace Models\Entities;

use Illuminate\Database\Eloquent\Model as Eloquent;

class Pago extends Eloquent
{
    protected $table = 'pago';
    public $timestamps = false;
    protected $fillable = [
        'id_reserva', 'monto', 'descripcion', 'fecha_pago', 'id_metodo_pago','id_usuario'
    ];

    public function reserva()
    {
        return $this->belongsTo(Reserva::class, 'id_reserva');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario');
    }

    //Un pago genera un comprobante.

    public function comprobante()
    {
        return $this->hasOne(Comprobante::class, 'id_pago');
    }
}
