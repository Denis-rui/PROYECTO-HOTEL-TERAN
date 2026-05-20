<?php
namespace Models\Entities;

use Illuminate\Database\Eloquent\Model as Eloquent;

class TipoHabitacion extends Eloquent
{
    protected $table      = 'tipo_habitacion';
    protected $primaryKey = 'id';
    public    $timestamps = false;
    protected $fillable   = ['tipo', 'precio_base'];
}