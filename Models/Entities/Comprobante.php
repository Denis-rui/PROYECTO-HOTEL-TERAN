<?php
namespace Models\Entities;

use Illuminate\Database\Eloquent\Model as Eloquent;

class Comprobante extends Eloquent
{
    protected $table = 'comprobante';
    public $timestamps = false;
    protected $fillable = [
        'id_pago', 'numero_ticket', 'fecha_emision', 'descripcion', 'total', 'id_forma_pago', 'id_usuario'
    ];

    public function pago()
    {
        return $this->belongsTo(Pago::class, 'id_pago');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario');
    }
}
