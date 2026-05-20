<?php
namespace Models\Entities;

use Illuminate\Database\Eloquent\Model as Eloquent;

class Devolucion extends Eloquent
{
    protected $table = 'devolucion';
    public $timestamps = false;
    protected $fillable = [
        'id_reserva', 'fecha_cancelacion', 'dias_usados', 'dias_no_usados',
        'total_no_ocupado', 'porcentaje_penalidad', 'monto_penalidad', 'monto_devuelto'
    ];

    public function reserva()
    {
        return $this->belongsTo(Reserva::class, 'id_reserva');
    }
}
