<?php

class UsuarioModel extends Model
{
    protected $table      = 'usuario';
    protected $primaryKey = 'id';

    public function __construct()
    {
        parent::__construct();
    }

    // Obtener usuario para login (con rol)
    public function obtenerUsuarios($usuario)
    {
        try {
            $sqlUsuario = "SELECT id, nombre_usuario, estado, id_rol, contrasenia
                           FROM usuario
                           WHERE nombre_usuario = ? AND estado = 1
                           LIMIT 1";
            $stmtUsuario = $this->conectar()->prepare($sqlUsuario);
            $stmtUsuario->execute([$usuario]);
            $user = $stmtUsuario->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                return [];
            }

            $sqlRol = "SELECT rol FROM rol WHERE id = ? LIMIT 1";
            $stmtRol = $this->conectar()->prepare($sqlRol);
            $stmtRol->execute([$user['id_rol']]);
            $rol = $stmtRol->fetchColumn();

            return [
                'id' => $user['id'],
                'nombre_usuario' => $user['nombre_usuario'],
                'rol' => $rol ?: '',
                'contrasenia' => $user['contrasenia'],
            ];
        } catch (\Throwable $e) {
            error_log('LOGIN ERROR obtenerUsuarios: ' . $e->getMessage());
            return [];
        }
    }

    // Leer perfil de un usuario por nombre
    public function read(string $nombreUsuario)
    {
        $sql  = "SELECT u.nombre_completo, u.nombre_usuario, u.correo,
                        u.telefono, r.rol
                 FROM usuario u
                 JOIN rol r ON u.id_rol = r.id
                 WHERE u.nombre_usuario = ?";
        $stmt = $this->conectar()->prepare($sql);
        $stmt->execute([$nombreUsuario]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Listar todos los usuarios activos
    public function listar()
    {
        return $this->readAll();
    }

    public function readAll()
    {
        $sql  = "SELECT u.id, u.nombre_completo, u.nombre_usuario, u.correo,
                        u.telefono, u.dni, r.rol
                 FROM usuario u
                 JOIN rol r ON u.id_rol = r.id
                 WHERE u.estado = 1
                 ORDER BY u.id ASC";
        $stmt = $this->conectar()->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Crear usuario
    public function create($datos)
    {
        $stmtRol = $this->conectar()->prepare("SELECT id FROM rol WHERE rol = ?");
        $stmtRol->execute([$datos['rol'] ?? '']);
        $rol = $stmtRol->fetch(PDO::FETCH_ASSOC);

        if (!$rol) {
            throw new \Exception("Rol no encontrado");
        }

        $sql  = "INSERT INTO usuario
                    (nombre_completo, nombre_usuario, correo, telefono, dni, fecha_nacimiento, contrasenia, estado, id_rol)
                 VALUES
                    (:nombre_completo, :nombre_usuario, :correo, :telefono, :dni, :fecha_nacimiento, :contrasenia, 1, :id_rol)";
        $stmt = $this->conectar()->prepare($sql);
        return $stmt->execute([
            ':nombre_completo'  => $datos['nombre_completo']  ?? '',
            ':nombre_usuario'   => $datos['nombre_usuario']   ?? '',
            ':correo'           => $datos['correo']           ?? '',
            ':telefono'         => $datos['telefono']         ?? '',
            ':dni'              => $datos['dni']              ?? '',
            ':fecha_nacimiento' => $datos['fecha_nacimiento'] ?? '',
            ':contrasenia'      => password_hash($datos['contrasenia'] ?? '', PASSWORD_DEFAULT),
            ':id_rol'           => $rol['id'],
        ]);
    }

    // Actualizar perfil propio
    public function update($nombreUsuario, $datos)
    {
        $sql  = "UPDATE usuario SET
                    nombre_completo = :nombre_completo,
                    nombre_usuario  = :nombre_usuario,
                    correo          = :correo,
                    telefono        = :telefono
                 WHERE nombre_usuario = :nombre_usuario_actual";
        $stmt = $this->conectar()->prepare($sql);
        return $stmt->execute([
            ':nombre_completo'       => $datos['nombre_completo'] ?? '',
            ':nombre_usuario'        => $datos['nombre_usuario']  ?? '',
            ':correo'                => $datos['correo']          ?? '',
            ':telefono'              => $datos['telefono']        ?? '',
            ':nombre_usuario_actual' => $nombreUsuario,
        ]);
    }

    // Actualizar usuario por ID (admin)
    public function updateById(int $id, $datos)
    {
        $stmtRol = $this->conectar()->prepare("SELECT id FROM rol WHERE rol = ?");
        $stmtRol->execute([$datos['rol'] ?? '']);
        $rol = $stmtRol->fetch(PDO::FETCH_ASSOC);

        if (!$rol) {
            throw new \Exception("Rol no encontrado");
        }

        $sql  = "UPDATE usuario SET
                    nombre_completo = :nombre_completo,
                    nombre_usuario  = :nombre_usuario,
                    correo          = :correo,
                    telefono        = :telefono,
                    dni             = :dni,
                    id_rol          = :id_rol
                 WHERE id = :id";
        $stmt = $this->conectar()->prepare($sql);
        return $stmt->execute([
            ':nombre_completo' => $datos['nombre_completo'] ?? '',
            ':nombre_usuario'  => $datos['nombre_usuario']  ?? '',
            ':correo'          => $datos['correo']          ?? '',
            ':telefono'        => $datos['telefono']        ?? '',
            ':dni'             => $datos['dni']             ?? '',
            ':id_rol'          => $rol['id'],
            ':id'              => $id,
        ]);
    }

    // Eliminar (desactivar) usuario
    public function deleteUsuario(int $id)
    {
        $sql  = "UPDATE usuario SET estado = 0 WHERE id = ?";
        $stmt = $this->conectar()->prepare($sql);
        return $stmt->execute([$id]);
    }
}
