<?php
namespace Models;

use Models\Entities\Rol;
use Models\Entities\Usuario;

class UsuarioModel
{
    private function normalizarContrasenia(?string $contrasenia): ?string
    {
        $contrasenia = trim((string) $contrasenia);

        if ($contrasenia === '') {
            return null;
        }

        return md5($contrasenia);
    }

    private function validarMayorEdad(?string $fechaNacimiento): void
    {
        if (empty($fechaNacimiento)) {
            throw new \Exception('La fecha de nacimiento es obligatoria');
        }

        $fecha = \DateTime::createFromFormat('Y-m-d', $fechaNacimiento);

        if (!$fecha || $fecha->format('Y-m-d') !== $fechaNacimiento) {
            throw new \Exception('La fecha de nacimiento no es valida');
        }

        $hoy = new \DateTime('today');
        $edad = $fecha->diff($hoy)->y;

        if ($edad < 18) {
            throw new \Exception('El usuario debe ser mayor de edad');
        }
    }

    private function validarUnicidadCampos(array $datos, ?int $ignorarId = null): void
    {
        $nombreUsuario = trim((string) ($datos['nombre_usuario'] ?? ''));
        $correo = trim((string) ($datos['correo'] ?? ''));
        $dni = trim((string) ($datos['dni'] ?? ''));

        if ($nombreUsuario !== '') {
            $query = Usuario::where('nombre_usuario', $nombreUsuario);
            if ($ignorarId !== null) {
                $query->where('id', '<>', $ignorarId);
            }
            if ($query->exists()) {
                throw new \Exception('El nombre de usuario ya existe');
            }
        }

        if ($correo !== '') {
            $query = Usuario::where('correo', $correo);
            if ($ignorarId !== null) {
                $query->where('id', '<>', $ignorarId);
            }
            if ($query->exists()) {
                throw new \Exception('El correo ya esta registrado');
            }
        }

        if ($dni !== '') {
            $query = Usuario::where('dni', $dni);
            if ($ignorarId !== null) {
                $query->where('id', '<>', $ignorarId);
            }
            if ($query->exists()) {
                throw new \Exception('El DNI ya esta registrado');
            }
        }
    }

    // Obtener usuario para login (con rol)
    public function obtenerUsuarios($usuario)
    {
        try {
            $user = Usuario::with('rol')
                ->where('nombre_usuario', $usuario)
                ->where('estado', 1)
                ->first();

            if (!$user) {
                return [];
            }

            return [
                'id' => $user->id,
                'nombre_usuario' => $user->nombre_usuario,
                'rol' => $user->rol->rol ?? '',
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
        $user = Usuario::with('rol')
            ->where('nombre_usuario', $nombreUsuario)
            ->where('estado', 1)
            ->first();

        if (!$user) {
            return null;
        }

        return [
            'nombre_completo' => $user->nombre_completo,
            'nombre_usuario'  => $user->nombre_usuario,
            'correo'          => $user->correo,
            'telefono'        => $user->telefono,
            'rol'             => $user->rol->rol ?? '',
        ];
    }

    // Listar todos los usuarios activos
    public function listar()
    {
        return $this->readAll();
    }

    public function readAll()
    {
        return Usuario::with('rol')
            ->where('estado', 1)
            ->orderBy('id', 'asc')
            ->get()
            ->map(function ($user) {
                return [
                    'id'              => $user->id,
                    'nombre_completo' => $user->nombre_completo,
                    'nombre_usuario'  => $user->nombre_usuario,
                    'correo'          => $user->correo,
                    'telefono'        => $user->telefono,
                    'dni'             => $user->dni,
                    'fecha_nacimiento'=> $user->fecha_nacimiento,
                    'rol'             => $user->rol->rol ?? '',
                    'estado'          => $user->estado ? 'activo' : 'inactivo',
                ];
            })
            ->all();
    }

    // Crear usuario
    public function crearUsuario($datos)
    {
        $rolId = Rol::where('rol', $datos['rol'] ?? '')->value('id');

        if (!$rolId) {
            throw new \Exception('Rol no encontrado');
        }

        $this->validarMayorEdad($datos['fecha_nacimiento'] ?? null);
    $this->validarUnicidadCampos($datos);

        $userData = [
            'nombre_completo'  => $datos['nombre_completo']  ?? '',
            'nombre_usuario'   => $datos['nombre_usuario']   ?? '',
            'correo'           => $datos['correo']           ?? '',
            'telefono'         => $datos['telefono']         ?? '',
            'dni'              => $datos['dni']              ?? '',
            'fecha_nacimiento' => $datos['fecha_nacimiento'] ?? null,
            'contrasenia'      => $this->normalizarContrasenia($datos['contrasenia'] ?? '') ?? md5(''),
            'estado'           => 1,
            'id_rol'           => $rolId,
        ];

        return Usuario::query()->create($userData);
    }

    // Actualizar perfil propio por nombre de usuario
    public function updateByNombreUsuario($nombreUsuario, $datos)
    {
        $user = Usuario::where('nombre_usuario', $nombreUsuario)->first();

        if (!$user) {
            return false;
        }

        $this->validarUnicidadCampos($datos, (int) $user->id);

        $user->nombre_completo = $datos['nombre_completo'] ?? $user->nombre_completo;
        $user->nombre_usuario  = $datos['nombre_usuario']  ?? $user->nombre_usuario;
        $user->correo          = $datos['correo']          ?? $user->correo;
        $user->telefono        = $datos['telefono']        ?? $user->telefono;

        if (!empty($datos['fecha_nacimiento'])) {
            $this->validarMayorEdad($datos['fecha_nacimiento']);
            $user->fecha_nacimiento = $datos['fecha_nacimiento'];
        }


        $nuevaContrasenia = $this->normalizarContrasenia($datos['contrasenia'] ?? $datos['password'] ?? null);

        if ($nuevaContrasenia !== null) {
            $user->contrasenia = $nuevaContrasenia;
        }

        return $user->save();
    }

    // Nota: no definir `update` para no sobrescribir el método de Eloquent

    // Actualizar usuario por ID (admin)
    public function updateById(int $id, $datos)
    {
        $rolId = Rol::where('rol', $datos['rol'] ?? '')->value('id');

        if (!$rolId) {
            throw new \Exception('Rol no encontrado');
        }

        $user = Usuario::find($id);

        if (!$user) {
            return false;
        }

        $this->validarUnicidadCampos($datos, (int) $user->id);

        $user->nombre_completo = $datos['nombre_completo'] ?? $user->nombre_completo;
        $user->nombre_usuario  = $datos['nombre_usuario']  ?? $user->nombre_usuario;
        $user->correo          = $datos['correo']          ?? $user->correo;
        $user->telefono        = $datos['telefono']        ?? $user->telefono;
        $user->dni             = $datos['dni']             ?? $user->dni;

        $fechaNacimiento = $datos['fecha_nacimiento'] ?? $user->fecha_nacimiento ?? null;
        $this->validarMayorEdad($fechaNacimiento);
        $user->fecha_nacimiento = $fechaNacimiento;

        $user->id_rol          = $rolId;

        $nuevaContrasenia = $this->normalizarContrasenia($datos['contrasenia'] ?? $datos['password'] ?? null);

        if ($nuevaContrasenia !== null) {
            $user->contrasenia = $nuevaContrasenia;
        }

        return $user->save();
    }

    // Eliminar (desactivar) usuario
    public function deleteUsuario(int $id)
    {
        $user = Usuario::find($id);

        if (!$user) {
            return false;
        }

        $user->estado = 0;

        return $user->save();
    }
}
