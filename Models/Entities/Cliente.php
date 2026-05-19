<?php
namespace Models\Entities;

use Illuminate\Database\Eloquent\Model as Eloquent;

class Cliente extends Eloquent
{
    protected $table = 'cliente';
    public $timestamps = false;
    protected $fillable = [
        'nombre_completo', 'documento', 'correo_electronico', 'telefono', 'procedencia',
        'reservaciones', 'metodoPago', 'activo', 'observaciones', 'preferencias', 'fecha_creacion'
    ];

    public function reservas()
    {
        return $this->hasMany(Reserva::class, 'id_cliente');
    }
}
