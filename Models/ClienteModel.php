<?php
namespace Models;

use Models\Entities\Cliente;
use Illuminate\Database\Eloquent\Model as Eloquent;
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
                'c.fecha_creacion'
            )
            ->where('c.activo', 1);

        if (!empty($nombre)) {
            $query->where(function($q) use ($nombre) {
                $q->where('c.nombre_completo', 'LIKE', "%$nombre%")
                  ->orWhere('c.documento', 'LIKE', "%$nombre%");
            });
        }

        return $query->orderBy('c.id', 'ASC')->get()->toArray();
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
            return Cliente::create([
                'nombre_completo' => $data['nombre_completo'] ?? $data['nombre'] ?? '',
                'id_tipo_documento' => $data['id_tipo_documento'] ?? '',
                'documento' => $data['documento'] ?? '',
                'correo_electronico' => $data['correo_electronico'] ?? $data['gmail'] ?? '',
                'procedencia' => $data['procedencia'] ?? '',
                'telefono' => $data['telefono'] ?? '',
                'observaciones' => $data['observaciones'] ?? '',
                'reservaciones' => 0,
                'activo' => 1
            ]);
        } catch (\Exception $e) {
            throw new \Exception('Error al crear cliente: ' . $e->getMessage());
        }
    }

    public function actualizarCliente($data)
    {
        try {
            $cliente = Cliente::findOrFail($data['id']);
            $cliente->update([
                'nombre_completo' => $data['nombre_completo'] ?? $data['nombre'] ?? '',
                'id_tipo_documento' => $data['id_tipo_documento'] ?? '',
                'documento' => $data['documento'] ?? '',
                'correo_electronico' => $data['correo_electronico'] ?? $data['gmail'] ?? '',
                'procedencia' => $data['procedencia'] ?? '',
                'telefono' => $data['telefono'] ?? '',
                'observaciones' => $data['observaciones'] ?? ''
            ]);
            return true;
        } catch (\Exception $e) {
            throw new \Exception('Error al actualizar cliente: ' . $e->getMessage());
        }
    }

    public function eliminarCliente($id)
    {
        try {
            $cliente = Cliente::findOrFail($id);
            $cliente->update(['activo' => 0]);
            return true;
        } catch (\Exception $e) {
            throw new \Exception('Error al inhabilitar cliente: ' . $e->getMessage());
        }
    }
}
