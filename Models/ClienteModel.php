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
        $sql = "SELECT c.id, c.nombre_completo, td.id AS id_tipo_documento, td.nombre AS tipo_documento_nombre, c.documento, c.correo_electronico, c.telefono, c.reservaciones, c.activo, c.fecha_creacion
                FROM cliente c
                INNER JOIN tipo_documento td ON c.id_tipo_documento = td.id
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
        $sql = "INSERT INTO cliente
            (nombre_completo, id_tipo_documento, documento, correo_electronico, telefono, reservaciones, fecha_creacion)
            VALUES (?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $this->conectar()->prepare($sql);
        return $stmt->execute([
            $data['nombre_completo'] ?? $data['nombre'] ?? '',
            $data['id_tipo_documento'] ?? '',
            $data['documento'] ?? '',
            $data['correo_electronico'] ?? $data['gmail'] ?? '',
            $data['telefono'] ?? '',
            $data['reservaciones'] ?? 0
        ]);
    }

    public function actualizarCliente($data)
    {
        $sql = "UPDATE cliente SET
                nombre_completo = ?, 
                id_tipo_documento = ?,
                documento = ?, 
                correo_electronico = ?, 
                telefono = ?, 
                reservaciones = ?
                WHERE id = ?";
        $stmt = $this->conectar()->prepare($sql);
        return $stmt->execute([
            $data['nombre_completo'] ?? $data['nombre'] ?? '',
            $data['id_tipo_documento'] ?? '',
            $data['documento'] ?? '',
            $data['correo_electronico'] ?? $data['gmail'] ?? '',
            $data['telefono'] ?? '',
            $data['reservaciones'] ?? 0,
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
