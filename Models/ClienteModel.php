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
            ->select([
                'c.id',
                'c.nombre_completo',
                'td.nombre as id_tipo_documento',
                'c.documento',
                'c.correo_electronico',
                'c.telefono',
                'c.procedencia',
                'c.reservaciones',
                'c.activo',
                'c.observaciones',
                'c.fecha_creacion'
            ]);

        if (!empty($nombre)) {
            $query->where('c.nombre_completo', 'like', "%{$nombre}%");
        }

        return $query->orderBy('c.id', 'asc')
            ->get()
            ->map(fn($item) => (array) $item)
            ->toArray();
    }

    public function obtenerClientes()
    {
        return $this->listar();
    }

    // va a servir para llamar a los clientes desde la reserva, para mostrar un listado de clientes y poder seleccionar uno
    public function obtenerClientesParaReserva($textoBusqueda = '')
    {
        $textoBusqueda = trim((string) $textoBusqueda);

        $query = Cliente::query()
            ->select([
                'id',
                'nombre_completo as nombre',
                'correo_electronico as correo',
            ])
            ->orderBy('nombre_completo', 'asc')
            ->limit(50);

        if ($textoBusqueda !== '') {
            $query->where('nombre_completo', 'like', '%' . $textoBusqueda . '%');
        }

        return $query->get()->toArray();
    }

    public function crearCliente($data)
    {
        try {
            $cliente = Cliente::create([
                'nombre_completo'    => $data['nombre_completo']    ?? $data['nombre'] ?? '',
                'id_tipo_documento'  => !empty($data['id_tipo_documento']) ? (int) $data['id_tipo_documento'] : null,
                'documento'          => $data['documento']          ?? '',
                'correo_electronico' => $data['correo_electronico'] ?? $data['gmail'] ?? '',
                'telefono'           => $data['telefono']           ?? '',
                'procedencia'        => $data['procedencia']        ?? $data['nacionalidad'] ?? '',
                'reservaciones'      => (int) ($data['reservaciones'] ?? 0),
                'activo'             => 1,
                'observaciones'      => $data['observaciones']      ?? null,
                'fecha_creacion'     => date('Y-m-d H:i:s')
            ]);
            return $cliente !== null;
        } catch (\Throwable $e) {
            error_log('Error al crear cliente: ' . $e->getMessage());
            throw $e;
        }
    }

    public function actualizarCliente($data)
    {
        try {
            $cliente = Cliente::find($data['id']);
            if (!$cliente) {
                return false;
            }

            return $cliente->update([
                'nombre_completo'    => $data['nombre_completo']    ?? $data['nombre'] ?? $cliente->nombre_completo,
                'id_tipo_documento'  => !empty($data['id_tipo_documento']) ? (int) $data['id_tipo_documento'] : $cliente->id_tipo_documento,
                'documento'          => $data['documento']          ?? $cliente->documento,
                'correo_electronico' => $data['correo_electronico'] ?? $data['gmail'] ?? $cliente->correo_electronico,
                'telefono'           => $data['telefono']           ?? $cliente->telefono,
                'procedencia'        => $data['procedencia']        ?? $data['nacionalidad'] ?? $cliente->procedencia,
                'reservaciones'      => (int) ($data['reservaciones'] ?? $cliente->reservaciones),
                'observaciones'      => $data['observaciones']      ?? $cliente->observaciones,
            ]);
        } catch (\Throwable $e) {
            error_log('Error al actualizar cliente: ' . $e->getMessage());
            throw $e;
        }
    }

    public function eliminarCliente($id)
    {
        try {
            $cliente = Cliente::find($id);
            if (!$cliente) {
                return false;
            }
            return $cliente->delete();
        } catch (\Throwable $e) {
            error_log('Error al eliminar cliente: ' . $e->getMessage());
            throw $e;
        }
    }
}

