<?php
namespace Models\Entities;

use Illuminate\Database\Eloquent\Model as Eloquent;

class Cliente extends Eloquent
{
    protected $table = 'cliente';
    public $timestamps = false;
    protected $fillable = [
        'nombre_completo','id_tipo_documento', 'documento', 'correo_electronico', 'procedencia', 'telefono',
        'reservaciones', 'observaciones', 'activo','fecha_creacion'
    ];

    public function reservas()
    {
        return $this->hasMany(Reserva::class, 'id_cliente');
    }
}
