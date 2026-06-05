<?php
namespace Models\Entities;

use Illuminate\Database\Eloquent\Model as Eloquent;

class Permiso extends Eloquent
{
    protected $table = 'permiso';

    public $timestamps = false;

    protected $fillable = [
        'codigo',
        'nombre',
        'modulo',
        'descripcion',
        'activo'
    ];

    public function roles()
    {
        return $this->belongsToMany(
            Rol::class,
            'rol_permiso',
            'id_permiso',
            'id_rol'
        );
    }
}
