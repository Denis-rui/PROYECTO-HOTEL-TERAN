<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Database\Capsule\Manager as DB;

class UsuarioModel extends Eloquent
{
    protected $table      = 'usuario';
    protected $primaryKey = 'id';
    public $timestamps    = false;
    protected $fillable   = [
        'nombre_completo', 'nombre_usuario', 'correo', 'telefono', 'dni',
        'fecha_nacimiento', 'contrasenia', 'estado', 'id_rol'
    ];

    // Obtener usuario para login (con rol)
    public function obtenerUsuarios($usuario)
    {
        try {
            $user = self::where('nombre_usuario', $usuario)->where('estado', 1)->first();

            if (!$user) {
                return [];
            }

            $rol = DB::table('rol')->where('id', $user->id_rol)->value('rol');

            return [
                'id' => $user->id,
                'nombre_usuario' => $user->nombre_usuario,
                'rol' => $rol ?: '',
                'contrasenia' => $user->contrasenia,
            ];
        } catch (\Throwable $e) {
            error_log('LOGIN ERROR obtenerUsuarios: ' . $e->getMessage());
            return [];
        }
    }

    // Leer perfil de un usuario por nombre
    public function read(string $nombreUsuario)
    {
        return DB::table('usuario as u')->join('rol as r', 'u.id_rol', '=', 'r.id')->where('u.nombre_usuario', $nombreUsuario)->select('u.nombre_completo', 'u.nombre_usuario', 'u.correo', 'u.telefono', 'r.rol')->first();
    }

    // Listar todos los usuarios activos
    public function listar()
    {
        return $this->readAll();
    }

    public function readAll()
    {
        return DB::table('usuario as u')->join('rol as r', 'u.id_rol', '=', 'r.id')->where('u.estado', 1)->select('u.id', 'u.nombre_completo', 'u.nombre_usuario', 'u.correo', 'u.telefono', 'u.dni', 'r.rol')->orderBy('u.id', 'asc')->get()->toArray();
    }

    // Crear usuario
    public function create($datos)
    {
        $rolId = DB::table('rol')->where('rol', $datos['rol'] ?? '')->value('id');

        if (!$rolId) {
            throw new \Exception('Rol no encontrado');
        }

        $userData = [
            'nombre_completo'  => $datos['nombre_completo']  ?? '',
            'nombre_usuario'   => $datos['nombre_usuario']   ?? '',
            'correo'           => $datos['correo']           ?? '',
            'telefono'         => $datos['telefono']         ?? '',
            'dni'              => $datos['dni']              ?? '',
            'fecha_nacimiento' => $datos['fecha_nacimiento'] ?? null,
            'contrasenia'      => password_hash($datos['contrasenia'] ?? '', PASSWORD_DEFAULT),
            'estado'           => 1,
            'id_rol'           => $rolId,
        ];

        return self::insert($userData);
    }

    // Actualizar perfil propio por nombre de usuario
    public function updateByNombreUsuario($nombreUsuario, $datos)
    {
        return DB::table('usuario')->where('nombre_usuario', $nombreUsuario)->update([
                'nombre_completo' => $datos['nombre_completo'] ?? '',
                'nombre_usuario'  => $datos['nombre_usuario']  ?? '',
                'correo'          => $datos['correo']          ?? '',
                'telefono'        => $datos['telefono']        ?? '',
            ]);
    }

    // Nota: no definir `update` para no sobrescribir el método de Eloquent

    // Actualizar usuario por ID (admin)
    public function updateById(int $id, $datos)
    {
        $rolId = DB::table('rol')->where('rol', $datos['rol'] ?? '')->value('id');

        if (!$rolId) {
            throw new \Exception('Rol no encontrado');
        }

        return DB::table('usuario')->where('id', $id)->update([
                'nombre_completo' => $datos['nombre_completo'] ?? '',
                'nombre_usuario'  => $datos['nombre_usuario']  ?? '',
                'correo'          => $datos['correo']          ?? '',
                'telefono'        => $datos['telefono']        ?? '',
                'dni'             => $datos['dni']             ?? '',
                'id_rol'          => $rolId,
            ]);
    }

    // Eliminar (desactivar) usuario
    public function deleteUsuario(int $id)
    {
        return DB::table('usuario')->where('id', $id)->update(['estado' => 0]);
    }
}
