<?php
namespace Models;

use Models\Entities\Cliente;
use Libraries\Core\Model;
use PDO;

class ClienteModel extends Model
{
    protected $table = 'cliente';

    public function __construct()
    {
        parent::__construct();
    }

    public function listar($nombre = '')
    {
        $sql = "SELECT id, nombre_completo, documento, correo_electronico, telefono, procedencia, reservaciones, metodoPago,
                       activo, observaciones, preferencias, fecha_creacion
                FROM cliente
                WHERE 1=1";
        $params = [];
        if (!empty($nombre)) {
            $sql .= " AND nombre_completo LIKE ?";
            $params[] = "%$nombre%";
        }
        $sql .= " ORDER BY id ASC";
        $stmt = $this->conectar()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerClientes()
    {
        return $this->listar();
    }
// va a servir para llamr a los clientes desde la reserva, para mostrar un listado de clientes y poder seleccionar uno
    public function obtenerClientesParaReserva($textoBusqueda = '')
    {
        $textoBusqueda = trim((string) $textoBusqueda);

        if ($textoBusqueda === '') {
            return [];
        }
        return Cliente::query()->select(['id', 'nombre_completo as nombre', 'correo_electronico as correo',])->where('nombre_completo', 'like', '%' . $textoBusqueda . '%')->orderBy('nombre_completo', 'asc')->limit(50)->get()->toArray();
    }

    public function crearCliente($data)
    {
        $sql = "INSERT INTO cliente
                (nombre_completo, documento, correo_electronico, telefono, procedencia, reservaciones, metodoPago, observaciones, preferencias, fecha_creacion)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $this->conectar()->prepare($sql);
        return $stmt->execute([
            $data['nombre_completo'] ?? $data['nombre'] ?? '',
            $data['documento'] ?? '',
            $data['correo_electronico'] ?? $data['gmail'] ?? '',
            $data['telefono'] ?? '',
            $data['procedencia'] ?? $data['nacionalidad'] ?? '',
            $data['reservaciones'] ?? 0,
            $data['metodoPago'] ?? null,
            $data['observaciones'] ?? null,
            $data['preferencias'] ?? null
        ]);
    }

    public function actualizarCliente($data)
    {
        $sql = "UPDATE cliente SET
                nombre_completo = ?, 
                documento = ?, 
                correo_electronico = ?, 
                telefono = ?, 
                procedencia = ?, 
                reservaciones = ?, 
                metodoPago = ?,
                observaciones = ?,
                preferencias = ?
                WHERE id = ?";
        $stmt = $this->conectar()->prepare($sql);
        return $stmt->execute([
            $data['nombre_completo'] ?? $data['nombre'] ?? '',
            $data['documento'] ?? '',
            $data['correo_electronico'] ?? $data['gmail'] ?? '',
            $data['telefono'] ?? '',
            $data['procedencia'] ?? $data['nacionalidad'] ?? '',
            $data['reservaciones'] ?? 0,
            $data['metodoPago'] ?? null,
            $data['observaciones'] ?? null,
            $data['preferencias'] ?? null,
            $data['id']
        ]);
    }

    public function eliminarCliente($id)
    {
        $sql = "DELETE FROM cliente WHERE id = ?";
        $stmt = $this->conectar()->prepare($sql);
        return $stmt->execute([$id]);
    }
}
