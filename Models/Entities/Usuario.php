<?php
namespace Models\Entities;

use Illuminate\Database\Eloquent\Model as Eloquent;

class Usuario extends Eloquent
{
    protected $table = 'usuario';
    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $fillable = [
        'nombre_completo', 'nombre_usuario', 'correo', 'telefono', 'dni',
        'fecha_nacimiento', 'contrasenia', 'estado', 'id_rol'
    ];

    public function rol()
    {
        return $this->belongsTo(Rol::class, 'id_rol');
    }
}
