<?php

namespace Models;

use Models\Entities\Cliente;
use Illuminate\Database\Capsule\Manager as DB;

class ClienteModel
{
    public function listar($nombre = '')
    {
        $query = DB::table('cliente as c')
            ->join('tipo_documento as td', 'c.id_tipo_documento', '=', 'td.id')
            ->select(
                'c.id',
                // Reconstruye el nombre completo para mantener la compatibilidad con el frontend
                DB::raw("CONCAT_WS(' ', c.nombres, c.apellido_paterno, c.apellido_materno) as nombre_completo"),
                'td.id as id_tipo_documento',
                'td.nombre as tipo_documento_nombre',
                'c.documento',
                'c.ruc', // Se añade por buena práctica si se necesita en los listados
                'c.correo_electronico',
                'c.telefono',
                'c.procedencia',
                'c.observaciones',
                'c.reservaciones',
                'c.activo',
                'c.fecha_creacion',
                'c.alerta_interna'
            );

        if (!empty($nombre)) {
            $query->where(function ($q) use ($nombre) {
                $q->where('c.nombres', 'LIKE', "%$nombre%")
                    ->orWhere('c.apellido_paterno', 'LIKE', "%$nombre%")
                    ->orWhere('c.apellido_materno', 'LIKE', "%$nombre%")
                    ->orWhere('c.documento', 'LIKE', "%$nombre%")
                    ->orWhere('c.ruc', 'LIKE', "%$nombre%") // También busca por RUC si aplica
                    ->orWhereRaw(
                        "CONCAT_WS(' ', c.nombres, c.apellido_paterno, c.apellido_materno) LIKE ?",
                        ['%' . $nombre . '%']
                    );
            });
        }

        return $query->orderBy('c.id', 'ASC')->get()->toArray();
    }
    public function obtenerClientesParaReserva($textoBusqueda = '')
    {
        $textoBusqueda = trim((string) $textoBusqueda);

        $query = DB::table('cliente as c')
            ->leftJoin('tipo_documento as td', 'c.id_tipo_documento', '=', 'td.id')
            ->select([
                'c.id',
                // Mantenemos el alias 'nombre' concatenando la nueva estructura
                DB::raw("CONCAT_WS(' ', c.nombres, c.apellido_paterno, c.apellido_materno) as nombre"),
                'c.documento',
                'c.ruc', // Útil si buscan por RUC para la facturación de la reserva
                'c.procedencia',
                'c.correo_electronico as correo',
                'c.id_tipo_documento',
                'td.nombre as tipo_documento_nombre',
                'c.alerta_interna' // Añadido por si necesitas pintar un badge de advertencia al seleccionarlo
            ])
            ->where('c.activo', 1)
            // Ordenamos por los campos reales en lugar de la función virtual para mejorar rendimiento
            ->orderBy('c.nombres', 'asc')
            ->orderBy('c.apellido_paterno', 'asc')
            ->limit(20);

        if ($textoBusqueda !== '') {
            $query->where(function ($q) use ($textoBusqueda) {
                $q->where('c.nombres', 'like', '%' . $textoBusqueda . '%')
                    ->orWhere('c.apellido_paterno', 'like', '%' . $textoBusqueda . '%')
                    ->orWhere('c.apellido_materno', 'like', '%' . $textoBusqueda . '%')
                    ->orWhere('c.documento', 'like', '%' . $textoBusqueda . '%')
                    ->orWhere('c.ruc', 'like', '%' . $textoBusqueda . '%')
                    ->orWhereRaw(
                        "CONCAT_WS(' ', c.nombres, c.apellido_paterno, c.apellido_materno) LIKE ?",
                        ['%' . $textoBusqueda . '%']
                    );
            });
        }

        return $query->get()->toArray();
    }

    public function buscarInhabilitadoPorDocumento(string $documento)
    {
        $documentoLimpio = trim($documento);

        $cliente = Cliente::select([
            'id',
            // Reconstruimos el nombre bajo el alias esperado por tu lógica de negocio
            DB::raw("CONCAT_WS(' ', nombres, apellido_paterno, apellido_materno) as nombre"),
            'documento',
            'ruc',
            'activo'
        ])
            ->where(function ($q) use ($documentoLimpio) {
                $q->where('documento', $documentoLimpio)
                    ->orWhere('ruc', $documentoLimpio); // Soporte para búsqueda por RUC
            })
            ->where('activo', 0)
            ->first();

        return $cliente ? $cliente->toArray() : null;
    }

    public function crear(array $data)
    {
        return Cliente::create($data);
    }

    public function actualizar(int $id, array $data)
    {
        return Cliente::where('id', $id)->update($data);
    }

    public function cambiarEstado(int $id, int $estado)
    {
        return Cliente::where('id', $id)->update(['activo' => $estado]);
    }
}
