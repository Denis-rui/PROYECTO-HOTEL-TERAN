<?php
namespace Models\Entities;

use Illuminate\Database\Eloquent\Model as Eloquent;

class Rol extends Eloquent
{
    protected $table = 'rol';

    public $timestamps = false;

    protected $fillable = ['rol'];

    public function usuarios()
    {
        return $this->hasMany(Usuario::class, 'id_rol');
    }

    public function permisos()
    {
        return $this->belongsToMany(
            Permiso::class,
            'rol_permiso',
            'id_rol',
            'id_permiso'
        );
    }
}
