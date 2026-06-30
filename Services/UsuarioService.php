<?php

namespace Services;

use Models\UsuarioModel;
use Exception;
use DateTime;

class UsuarioService
{
    private UsuarioModel $usuarioModel;

    public function __construct()
    {
        $this->usuarioModel = new UsuarioModel();
    }

    // ── MÉTODOS DE LECTURA ──

    public function listarUsuarios(): array
    {
        try {
            $usuarios = $this->usuarioModel->listarActivos()
                ->map(fn($user) => $this->mapearUsuario($user))
                ->toArray();

            return ['exito' => true, 'data' => $usuarios];
        } catch (Exception $e) {
            error_log('Error listarUsuarios: ' . $e->getMessage());
            return ['exito' => false, 'mensaje' => 'Error al cargar los usuarios.', 'data' => []];
        }
    }

    public function buscarUsuarios(string $termino): array
    {
        try {
            $termino = trim($termino);

            if ($termino === '') {
                return $this->listarUsuarios();
            }

            $usuarios = $this->usuarioModel->buscarPorTermino($termino)
                ->map(fn($user) => $this->mapearUsuario($user))
                ->toArray();

            return ['exito' => true, 'data' => $usuarios];
        } catch (Exception $e) {
            error_log('Error buscarUsuarios: ' . $e->getMessage());
            return ['exito' => false, 'mensaje' => 'Error al buscar usuarios.', 'data' => []];
        }
    }

    private function mapearUsuario($user): array
    {
        return [
            'id'               => $user->id,
            'nombre_completo'  => $user->nombre_completo,
            'nombre_usuario'   => $user->nombre_usuario,
            'correo'           => $user->correo,
            'telefono'         => $user->telefono,
            'dni'              => $user->dni,
            'fecha_nacimiento' => $user->fecha_nacimiento,
            'rol'              => $user->rol->rol ?? '',
            'estado'           => $user->estado ? 'activo' : 'inactivo',
        ];
    }

    public function obtenerPerfil(string $nombreUsuario): array
    {
        try {
            $user = $this->usuarioModel->obtenerPorNombreUsuario($nombreUsuario);
            if (!$user) return ['exito' => false, 'mensaje' => 'Usuario no encontrado.'];

            $perfil = [
                'nombre_completo' => $user->nombre_completo,
                'nombre_usuario'  => $user->nombre_usuario,
                'correo'          => $user->correo,
                'telefono'        => $user->telefono,
                'rol'             => $user->rol->rol ?? '',
            ];
            return ['exito' => true, 'data' => $perfil];
        } catch (Exception $e) {
            return ['exito' => false, 'mensaje' => 'Error al cargar perfil.'];
        }
    }

    // ── MÉTODOS DE ESCRITURA ──

    public function crearUsuario(array $datos): array
    {
        try {
            $rolId = $this->usuarioModel->buscarIdRolPorNombre($datos['rol'] ?? '');
            if (!$rolId) return ['exito' => false, 'mensaje' => 'El rol seleccionado no es válido.'];

            $errorValidacion = $this->validarReglasNegocio($datos);
            if ($errorValidacion) return ['exito' => false, 'mensaje' => $errorValidacion];

            $datosGuardar = [
                'nombre_completo'  => $datos['nombre_completo'] ?? '',
                'nombre_usuario'   => $datos['nombre_usuario'] ?? '',
                'correo'           => $datos['correo'] ?? '',
                'telefono'         => $datos['telefono'] ?? '',
                'dni'              => $datos['dni'] ?? '',
                'fecha_nacimiento' => $datos['fecha_nacimiento'] ?? null,
                'contrasenia'      => $this->normalizarContrasenia($datos['contrasenia'] ?? ''),
                'estado'           => 1,
                'id_rol'           => $rolId,
            ];

            $this->usuarioModel->crear($datosGuardar);
            return ['exito' => true, 'mensaje' => 'Usuario creado correctamente.'];
        } catch (Exception $e) {
            return $this->manejarExcepcion($e, 'crear usuario');
        }
    }

    public function actualizarPerfilPropio(string $nombreUsuarioActual, array $datos): array
    {
        try {
            $user = $this->usuarioModel->obtenerPorNombreUsuario($nombreUsuarioActual);
            if (!$user) return ['exito' => false, 'mensaje' => 'Usuario no encontrado.'];

            $errorValidacion = $this->validarReglasNegocio($datos, $user->id);
            if ($errorValidacion) return ['exito' => false, 'mensaje' => $errorValidacion];

            $datosActualizar = [
                'nombre_completo' => $datos['nombre_completo'] ?? $user->nombre_completo,
                'nombre_usuario'  => $datos['nombre_usuario'] ?? $user->nombre_usuario,
                'correo'          => $datos['correo'] ?? $user->correo,
                'telefono'        => $datos['telefono'] ?? $user->telefono,
            ];

            // Solo actualizar si envían contrasenia
            $nuevaClave = $this->normalizarContrasenia($datos['contrasenia'] ?? $datos['password'] ?? null);
            if ($nuevaClave) $datosActualizar['contrasenia'] = $nuevaClave;

            $this->usuarioModel->actualizar($user->id, $datosActualizar);

            return [
                'exito' => true,
                'mensaje' => 'Perfil actualizado correctamente.',
                'nuevo_usuario' => $datosActualizar['nombre_usuario'] // Para actualizar la sesión
            ];
        } catch (Exception $e) {
            return $this->manejarExcepcion($e, 'actualizar perfil');
        }
    }

    public function actualizarUsuarioAdmin(int $id, array $datos): array
    {
        try {
            $user = $this->usuarioModel->obtenerPorId($id);
            if (!$user) return ['exito' => false, 'mensaje' => 'Usuario no encontrado.'];

            $rolId = $this->usuarioModel->buscarIdRolPorNombre($datos['rol'] ?? '');
            if (!$rolId) return ['exito' => false, 'mensaje' => 'El rol seleccionado no es válido.'];

            // Combinar con la fecha antigua por si no la envían
            $datosParaValidar = $datos;
            $datosParaValidar['fecha_nacimiento'] = $datos['fecha_nacimiento'] ?? $user->fecha_nacimiento;

            $errorValidacion = $this->validarReglasNegocio($datosParaValidar, $id);
            if ($errorValidacion) return ['exito' => false, 'mensaje' => $errorValidacion];

            $datosActualizar = [
                'nombre_completo'  => $datos['nombre_completo'] ?? $user->nombre_completo,
                'nombre_usuario'   => $datos['nombre_usuario'] ?? $user->nombre_usuario,
                'correo'           => $datos['correo'] ?? $user->correo,
                'telefono'         => $datos['telefono'] ?? $user->telefono,
                'dni'              => $datos['dni'] ?? $user->dni,
                'fecha_nacimiento' => $datosParaValidar['fecha_nacimiento'],
                'id_rol'           => $rolId,
            ];

            $nuevaClave = $this->normalizarContrasenia($datos['contrasenia'] ?? $datos['password'] ?? null);
            if ($nuevaClave) $datosActualizar['contrasenia'] = $nuevaClave;

            $this->usuarioModel->actualizar($id, $datosActualizar);
            return ['exito' => true, 'mensaje' => 'Usuario actualizado correctamente.'];
        } catch (Exception $e) {
            return $this->manejarExcepcion($e, 'actualizar usuario');
        }
    }

    public function cambiarContrasenia(string $nombreUsuario, string $claveActual, string $claveNueva, string $confirmar): array
    {
        if ($claveNueva !== $confirmar) {
            return ['exito' => false, 'mensaje' => 'Las contraseñas nuevas no coinciden.'];
        }

        $user = $this->usuarioModel->obtenerPorNombreUsuario($nombreUsuario);
        if (!$user) return ['exito' => false, 'mensaje' => 'Usuario no encontrado.'];

        // Lógica de verificación segura centralizada
        $claveValida = is_string($user->contrasenia) && (
            password_verify($claveActual, $user->contrasenia) ||
            hash_equals($user->contrasenia, md5($claveActual)) ||
            hash_equals($user->contrasenia, $claveActual)
        );

        if (!$claveValida) return ['exito' => false, 'mensaje' => 'La contraseña actual es incorrecta.'];

        $this->usuarioModel->actualizar($user->id, [
            'contrasenia' => md5($claveNueva) // Mantenemos tu estándar de encriptación
        ]);

        return ['exito' => true, 'mensaje' => 'Contraseña actualizada correctamente.'];
    }

    public function eliminarUsuario(int $id): array
    {
        try {
            $exito = $this->usuarioModel->desactivar($id);
            return [
                'exito' => $exito,
                'mensaje' => $exito ? 'Usuario eliminado.' : 'No se pudo eliminar el usuario.'
            ];
        } catch (Exception $e) {
            return ['exito' => false, 'mensaje' => 'Error al intentar eliminar.'];
        }
    }

    // ── MÉTODOS PRIVADOS (REGLAS DE NEGOCIO) ──

    private function validarReglasNegocio(array $datos, ?int $ignorarId = null): ?string
    {
        // 1. Validar Mayoría de Edad
        if (!empty($datos['fecha_nacimiento'])) {
            $fecha = DateTime::createFromFormat('Y-m-d', $datos['fecha_nacimiento']);
            if (!$fecha || $fecha->format('Y-m-d') !== $datos['fecha_nacimiento']) {
                return 'La fecha de nacimiento no es válida.';
            }
            $edad = $fecha->diff(new DateTime('today'))->y;
            if ($edad < 18) return 'El usuario debe ser mayor de edad (18+).';
        }

        // 2. Validar Unicidad
        if ($this->usuarioModel->existeValorUnico('nombre_usuario', $datos['nombre_usuario'] ?? '', $ignorarId)) {
            return 'El nombre de usuario ya está registrado.';
        }
        if ($this->usuarioModel->existeValorUnico('correo', $datos['correo'] ?? '', $ignorarId)) {
            return 'El correo electrónico ya está registrado.';
        }
        if ($this->usuarioModel->existeValorUnico('dni', $datos['dni'] ?? '', $ignorarId)) {
            return 'El DNI ya está registrado en el sistema.';
        }

        return null; // Todo en orden
    }

    private function normalizarContrasenia(?string $contrasenia): ?string
    {
        $contrasenia = trim((string) $contrasenia);
        return $contrasenia === '' ? null : md5($contrasenia);
    }

    private function manejarExcepcion(Exception $e, string $accion): array
    {
        $mensaje = $e->getMessage();
        error_log("Error al $accion: " . $mensaje);

        // Si fallan las reglas de unicidad a nivel base de datos
        if (stripos($mensaje, 'Duplicate entry') !== false || stripos($mensaje, 'Integrity constraint violation') !== false) {
            return ['exito' => false, 'mensaje' => 'Ya existe un usuario con ese Usuario, Correo o DNI.'];
        }

        return ['exito' => false, 'mensaje' => 'Ocurrió un error inesperado al procesar la solicitud.'];
    }
}
