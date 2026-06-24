<?php

namespace Models\Entities;

use Illuminate\Database\Eloquent\Model as Eloquent;

// Creamos esta nueva entidad para la tabla documento y eloquent pueda usarlo
 
class DocumentoElectronico extends Eloquent
{
    protected $table      = 'documento_electronico_reserva';
    public    $timestamps = false;

    protected $fillable = [
        'id_reserva',
        'id_usuario',
        'tipo_documento',
        'tipo_de_comprobante',
        'serie',
        'numero',
        'codigo_unico',
        'fecha_emision',
        'fecha_desde',
        'fecha_hasta',
        'cliente_tipo_documento',
        'cliente_numero_documento',
        'cliente_denominacion',
        'cliente_email',
        'cliente_direccion',
        'habitaciones_json',
        'detalle_json',
        'total_gravada',
        'total_igv',
        'total',
        'estado_sunat',
        'enlace',
        'enlace_del_pdf',
        'enlace_del_xml',
        'enlace_del_cdr',
        'cadena_para_codigo_qr',
        'codigo_hash',
        'sunat_description',
        'sunat_note',
        'sunat_responsecode',
        'sunat_soap_error',
        'respuesta_json',
        'payload_json',
        'created_at',
    ];

    // Relaciones

    public function reserva()
    {
        return $this->belongsTo(Reserva::class, 'id_reserva');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario');
    }
}
