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

            return $this->respuesta(true, 'OK', 'Usuarios cargados correctamente.', $usuarios);
        } catch (Exception $e) {
            error_log('Error listarUsuarios: ' . $e->getMessage());
            return $this->respuesta(false, 'ERROR_INTERNO', 'Error al cargar los usuarios.', []);
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

            return $this->respuesta(true, 'OK', 'Usuarios cargados correctamente.', $usuarios);
        } catch (Exception $e) {
            error_log('Error buscarUsuarios: ' . $e->getMessage());
            return $this->respuesta(false, 'ERROR_INTERNO', 'Error al buscar usuarios.', []);
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
            if (!$user) return $this->respuesta(false, 'NO_ENCONTRADO', 'Usuario no encontrado.');

            $perfil = [
                'nombre_completo' => $user->nombre_completo,
                'nombre_usuario'  => $user->nombre_usuario,
                'correo'          => $user->correo,
                'telefono'        => $user->telefono,
                'rol'             => $user->rol->rol ?? '',
            ];
            return $this->respuesta(true, 'OK', 'Perfil cargado correctamente.', $perfil);
        } catch (Exception $e) {
            error_log('Error obtenerPerfil: ' . $e->getMessage());
            return $this->respuesta(false, 'ERROR_INTERNO', 'Error al cargar perfil.');
        }
    }

    // ── MÉTODOS DE ESCRITURA ──

    public function crearUsuario(array $datos): array
    {
        try {
            $rolId = $this->usuarioModel->buscarIdRolPorNombre($datos['rol'] ?? '');
            if (!$rolId) return $this->respuesta(false, 'VALIDACION_ERROR', 'El rol seleccionado no es válido.', null, [
                'rol' => 'El rol seleccionado no es válido.',
            ]);

            $errorValidacion = $this->validarReglasNegocio($datos);
            if ($errorValidacion) return $this->respuesta(false, 'VALIDACION_ERROR', $errorValidacion);

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

            $usuario = $this->usuarioModel->crear($datosGuardar);
            return $this->respuesta(true, 'CREADO', 'Usuario creado correctamente.', $usuario);
        } catch (Exception $e) {
            return $this->manejarExcepcion($e, 'crear usuario');
        }
    }

    public function actualizarPerfilPropio(string $nombreUsuarioActual, array $datos): array
    {
        try {
            $user = $this->usuarioModel->obtenerPorNombreUsuario($nombreUsuarioActual);
            if (!$user) return $this->respuesta(false, 'NO_ENCONTRADO', 'Usuario no encontrado.');

            $errorValidacion = $this->validarReglasNegocio($datos, $user->id);
            if ($errorValidacion) return $this->respuesta(false, 'VALIDACION_ERROR', $errorValidacion);

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

            return $this->respuesta(true, 'ACTUALIZADO', 'Perfil actualizado correctamente.', [
                'id' => (int) $user->id,
            ], [], [
                'nuevo_usuario' => $datosActualizar['nombre_usuario'],
            ]);
        } catch (Exception $e) {
            return $this->manejarExcepcion($e, 'actualizar perfil');
        }
    }

    public function actualizarUsuarioAdmin(int $id, array $datos): array
    {
        try {
            $user = $this->usuarioModel->obtenerPorId($id);
            if (!$user) return $this->respuesta(false, 'NO_ENCONTRADO', 'Usuario no encontrado.');

            $rolId = $this->usuarioModel->buscarIdRolPorNombre($datos['rol'] ?? '');
            if (!$rolId) return $this->respuesta(false, 'VALIDACION_ERROR', 'El rol seleccionado no es válido.', null, [
                'rol' => 'El rol seleccionado no es válido.',
            ]);

            // Combinar con la fecha antigua por si no la envían
            $datosParaValidar = $datos;
            $datosParaValidar['fecha_nacimiento'] = $datos['fecha_nacimiento'] ?? $user->fecha_nacimiento;

            $errorValidacion = $this->validarReglasNegocio($datosParaValidar, $id);
            if ($errorValidacion) return $this->respuesta(false, 'VALIDACION_ERROR', $errorValidacion);

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
            return $this->respuesta(true, 'ACTUALIZADO', 'Usuario actualizado correctamente.', ['id' => $id]);
        } catch (Exception $e) {
            return $this->manejarExcepcion($e, 'actualizar usuario');
        }
    }

    public function cambiarContrasenia(string $nombreUsuario, string $claveActual, string $claveNueva, string $confirmar): array
    {
        if ($claveNueva !== $confirmar) {
            return $this->respuesta(false, 'VALIDACION_ERROR', 'Las contraseñas nuevas no coinciden.', null, [
                'confirmar_clave' => 'Las contraseñas nuevas no coinciden.',
            ]);
        }

        $errorComplejidad = $this->validarComplejidadContrasenia($claveNueva);
        if ($errorComplejidad) {
            return $this->respuesta(false, 'VALIDACION_ERROR', $errorComplejidad, null, [
                'clave_nueva' => $errorComplejidad,
            ]);
        }

        $user = $this->usuarioModel->obtenerPorNombreUsuario($nombreUsuario);
        if (!$user) return $this->respuesta(false, 'NO_ENCONTRADO', 'Usuario no encontrado.');

        // Lógica de verificación segura centralizada
        $claveValida = is_string($user->contrasenia) && (
            password_verify($claveActual, $user->contrasenia) ||
            hash_equals($user->contrasenia, md5($claveActual)) ||
            hash_equals($user->contrasenia, $claveActual)
        );

        if (!$claveValida) return $this->respuesta(false, 'VALIDACION_ERROR', 'La contraseña actual es incorrecta.', null, [
            'clave_actual' => 'La contraseña actual es incorrecta.',
        ]);

        $this->usuarioModel->actualizar($user->id, [
            'contrasenia' => password_hash($claveNueva, PASSWORD_DEFAULT)
        ]);

        return $this->respuesta(true, 'ACTUALIZADO', 'Contraseña actualizada correctamente.', ['id' => (int) $user->id]);
    }

    public function eliminarUsuario(int $id): array
    {
        try {
            if ($id <= 0) {
                return $this->respuesta(false, 'VALIDACION_ERROR', 'Seleccione un usuario válido.', null, [
                    'id' => 'El usuario es obligatorio.',
                ]);
            }

            $exito = $this->usuarioModel->desactivar($id);
            return $exito
                ? $this->respuesta(true, 'ELIMINADO', 'Usuario eliminado.', ['id' => $id])
                : $this->respuesta(false, 'NO_ENCONTRADO', 'Usuario no encontrado.');
        } catch (Exception $e) {
            error_log('Error eliminarUsuario: ' . $e->getMessage());
            return $this->respuesta(false, 'ERROR_INTERNO', 'Error al intentar eliminar.');
        }
    }

    // ── MÉTODOS PRIVADOS (REGLAS DE NEGOCIO) ──

    private function validarReglasNegocio(array $datos, ?int $ignorarId = null): ?string
    {
        $correo = trim((string) ($datos['correo'] ?? ''));

        if ($correo === '') {
            return 'El correo electrónico es obligatorio.';
        }

        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            return 'El correo electrónico no tiene un formato válido.';
        }

        $telefono = trim((string) ($datos['telefono'] ?? ''));

        if ($telefono === '') {
            return 'El teléfono es obligatorio.';
        }

        if (!preg_match('/^9\d{8}$/', $telefono)) {
            return 'El teléfono debe ser un celular peruano válido: debe empezar con 9 y tener 9 dígitos.';
        }

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
        if ($this->usuarioModel->existeValorUnico('correo', $correo, $ignorarId)) {
            return 'El correo electrónico ya está registrado.';
        }
        if ($this->usuarioModel->existeValorUnico('dni', $datos['dni'] ?? '', $ignorarId)) {
            return 'El DNI ya está registrado en el sistema.';
        }

        // 3. Validar Complejidad de Contraseña (si se envía)
        $clave = $datos['contrasenia'] ?? $datos['password'] ?? null;
        if (!empty($clave)) {
            $errorComplejidad = $this->validarComplejidadContrasenia($clave);
            if ($errorComplejidad) return $errorComplejidad;
        }

        return null; // Todo en orden
    }

    private function validarComplejidadContrasenia(string $contrasenia): ?string
    {
        if (strlen($contrasenia) < 8) {
            return 'La contraseña debe tener al menos 8 caracteres.';
        }
        if (!preg_match('/[A-Za-z]/', $contrasenia) || !preg_match('/\d/', $contrasenia)) {
            return 'La contraseña debe contener al menos una letra y un número.';
        }
        return null;
    }

    private function normalizarContrasenia(?string $contrasenia): ?string
    {
        $contrasenia = trim((string) $contrasenia);
        return $contrasenia === '' ? null : password_hash($contrasenia, PASSWORD_DEFAULT);
    }

    private function manejarExcepcion(Exception $e, string $accion): array
    {
        $mensaje = $e->getMessage();
        error_log("Error al $accion: " . $mensaje);

        // Si fallan las reglas de unicidad a nivel base de datos
        if (stripos($mensaje, 'Duplicate entry') !== false || stripos($mensaje, 'Integrity constraint violation') !== false) {
            return $this->respuesta(false, 'CONFLICTO', 'Ya existe un usuario con ese Usuario, Correo o DNI.');
        }

        return $this->respuesta(false, 'ERROR_INTERNO', 'Ocurrió un error inesperado al procesar la solicitud.');
    }

    private function respuesta(
        bool $exito,
        string $codigo,
        string $mensaje,
        mixed $data = null,
        array $errores = [],
        array $extra = []
    ): array {
        return array_merge([
            'exito' => $exito,
            'codigo' => $codigo,
            'mensaje' => $mensaje,
            'data' => $data,
            'errores' => array_filter($errores, fn($error) => $error !== null),
        ], $extra);
    }
}
