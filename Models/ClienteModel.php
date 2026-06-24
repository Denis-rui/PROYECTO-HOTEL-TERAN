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
                'c.nombre_completo',
                'td.id as id_tipo_documento',
                'td.nombre as tipo_documento_nombre',
                'c.documento',
                'c.correo_electronico',
                'c.telefono',
                'c.procedencia',
                'c.observaciones',
                'c.reservaciones',
                'c.activo',
                'c.fecha_creacion');

        if (!empty($nombre)) {
            $query->where(function ($q) use ($nombre) {
                $q->where('c.nombre_completo', 'LIKE', "%$nombre%")
                    ->orWhere('c.documento', 'LIKE', "%$nombre%");
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
                'c.nombre_completo as nombre',
                'c.documento',
                'c.procedencia',
                'c.correo_electronico as correo',
                'c.id_tipo_documento',
                'td.nombre as tipo_documento_nombre',
            ])
            ->where('c.activo', 1)
            ->orderBy('c.nombre_completo', 'asc')
            ->limit(20);

        if ($textoBusqueda !== '') {
            $query->where(function ($q) use ($textoBusqueda) {
                $q->where('nombre_completo', 'like', '%' . $textoBusqueda . '%')
                    ->orWhere('documento', 'like', '%' . $textoBusqueda . '%');
            });
        }

        return $query->get()->toArray();
    }

    public function buscarInhabilitadoPorDocumento(string $documento)
    {
        $cliente = Cliente::select(['id', 'nombre_completo as nombre', 'documento', 'activo'])
            ->where('documento', trim($documento))
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
