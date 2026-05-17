<?php

class HabitacionModel extends Model
{
    protected $table = 'habitacion';
    private const ESTADOS = ['Disponible', 'Ocupada', 'Mantenimiento', 'Reservada'];

    public function __construct()
    {
        parent::__construct();
    }

    public function registrar($datos)
    {
        try {
            $estado = $this->normalizarEstado($datos['estado'] ?? 'Disponible');

            $sql = "INSERT INTO habitacion
                    (numero_habitacion, piso, id_tipo_habitacion, precio, estado, descripcion_habitacion, capacidad, activo)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

            $statement = $this->conectar()->prepare($sql);
            $ok = $statement->execute([
                $datos['numero_habitacion'] ?? '',
                (int) ($datos['piso'] ?? 1),
                $datos['id_tipo_habitacion'] ?? null,
                $datos['precio'] ?? 0,
                $estado,
                $datos['descripcion'] ?? '',
                (int) ($datos['capacidad'] ?? 1),
                (int) ($datos['activo'] ?? 1),
            ]);

            return $ok;
        } catch (\PDOException $e) {
            throw new \Exception("Error al registrar habitación: " . $e->getMessage());
        }
    }

    public function buscar($numero, $tipo, $estado, $piso)
    {
        try {
            $sql = "SELECT h.id, h.numero_habitacion, h.piso, h.id_tipo_habitacion,
                           t.tipo AS tipo_nombre,
                           COALESCE(NULLIF(h.descripcion_habitacion, ''), '') AS descripcion,
                           h.precio,
                           h.estado, h.capacidad, h.activo,
                           ra.id AS reserva_actual_id,
                           c.nombre_completo AS cliente_actual
                    FROM habitacion h
                    JOIN tipo_habitacion t ON t.id = h.id_tipo_habitacion
                    LEFT JOIN reserva ra ON ra.id_habitacion = h.id
                        AND ra.estado IN ('en_estadia', 'checkout_pendiente')
                        AND ra.check_out_real IS NULL
                    LEFT JOIN cliente c ON c.id = ra.id_cliente
                    WHERE h.activo = 1";

            $params = [];

            if ($numero !== null && $numero !== '') {
                $sql .= " AND h.numero_habitacion LIKE ?";
                $params[] = "%" . $numero . "%";
            }

            if ($tipo) {
                $sql .= " AND h.id_tipo_habitacion = ?";
                $params[] = $tipo;
            }

            if ($estado) {
                $sql .= " AND h.estado = ?";
                $params[] = $this->normalizarEstado($estado);
            }

            if ($piso) {
                $sql .= " AND h.piso = ?";
                $params[] = (int) $piso;
            }

            $sql .= " ORDER BY h.piso ASC, h.numero_habitacion ASC";

            $statement = $this->conectar()->prepare($sql);
            $statement->execute($params);

            return $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            throw new \Exception("Error al buscar habitaciones: " . $e->getMessage());
        }
    }

    public function actualizarEstado($id, $estado, $motivo = '')
    {
        try {
            $nuevoEstado = $this->normalizarEstado($estado);
            
            if (strtolower($nuevoEstado) === 'mantenimiento') {
                $sql = "UPDATE habitacion SET estado = ?, descripcion_habitacion = ? WHERE id = ?";
                $params = [$nuevoEstado, $motivo, (int) $id];
            } else {
                $sql = "UPDATE habitacion SET estado = ? WHERE id = ?";
                $params = [$nuevoEstado, (int) $id];
            }
            
            $statement = $this->conectar()->prepare($sql);
            $ok = $statement->execute($params);

            return ['exito' => $ok, 'mensaje' => $ok ? 'Estado actualizado correctamente.' : 'No se pudo actualizar el estado.'];
        } catch (\PDOException $e) {
            return ['exito' => false, 'mensaje' => 'Error al actualizar estado: ' . $e->getMessage()];
        }
    }

    public function obtenerFiltros()
    {
        $filtros = [];

        $stmtTipos = $this->conectar()->prepare("SELECT id, tipo, precio_base, capacidad_maxima FROM tipo_habitacion WHERE activo = 1 ORDER BY tipo ASC");
        $stmtTipos->execute();
        $filtros['tipos'] = $stmtTipos->fetchAll(PDO::FETCH_ASSOC);

        $stmtPisos = $this->conectar()->prepare("SELECT DISTINCT piso FROM habitacion WHERE activo = 1 ORDER BY piso ASC");
        $stmtPisos->execute();
        $filtros['pisos'] = $stmtPisos->fetchAll(PDO::FETCH_COLUMN);

        // FILTRO INTELIGENTE: Obtener solo los estados que existen actualmente
        $stmtEstados = $this->conectar()->prepare("SELECT DISTINCT estado FROM habitacion WHERE activo = 1 ORDER BY estado ASC");
        $stmtEstados->execute();
        $filtros['estados'] = $stmtEstados->fetchAll(PDO::FETCH_COLUMN);

        return $filtros;
    }

    public function obtenerPorId($id)
    {
        $stmt = $this->conectar()->prepare("SELECT * FROM habitacion WHERE id = ?");
        $stmt->execute([(int) $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function normalizarEstado($estado)
    {
        $estado = strtolower(trim((string) $estado));
        $mapa = [
            'disponible' => 'Disponible',
            'ocupada' => 'Ocupada',
            'ocupado' => 'Ocupada',
            'mantenimiento' => 'Mantenimiento',
            'mantenimie' => 'Mantenimiento',
            'reservada' => 'Reservada',
            'reservado' => 'Reservada',
        ];

        return $mapa[$estado] ?? 'Disponible';
    }
}
