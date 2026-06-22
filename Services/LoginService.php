<?php

namespace Services;

use Models\UsuarioModel;

class LoginService
{
    private UsuarioModel $usuarioModel;

    public function __construct()
    {
        $this->usuarioModel = new UsuarioModel();
    }

    public function autenticar($usuario, $contrasenia, $tipousuario)
    {
        try {
            $user = $this->usuarioModel->obtenerPorNombreUsuario($usuario);

            // 1. Validar si el usuario existe
            if (!$user) {
                return ['exito' => false, 'mensaje' => 'Usuario no encontrado'];
            }

            // 2. Validar la contraseña (incluyendo legacy MD5 y texto plano)
            $contraseniaGuardada = $user->contrasenia;
            $contraseniaValida =
                password_verify($contrasenia, $contraseniaGuardada)
                || (is_string($contraseniaGuardada) && hash_equals($contraseniaGuardada, md5($contrasenia)))
                || (is_string($contraseniaGuardada) && hash_equals($contraseniaGuardada, $contrasenia));

            if (!$contraseniaValida) {
                return ['exito' => false, 'mensaje' => 'Contraseña incorrecta'];
            }

            // 3. Validar el rol (insensible a mayúsculas y espacios)
            $rolUsuario = $user->rol->rol ?? '';
            if (strcasecmp(trim($tipousuario), trim((string)$rolUsuario)) !== 0) {
                return ['exito' => false, 'mensaje' => 'Rol de usuario no coincide'];
            }

            // 4. Retornar el arreglo de éxito solo con los datos necesarios para la sesión
            return [
                'exito' => true,
                'usuario' => [
                    'id' => $user->id,
                    'nombre_usuario' => $user->nombre_usuario,
                    'rol' => $rolUsuario,
                    'permisos' => $user->rol ? $user->rol->permisos
                        ->where('activo', 1)
                        ->pluck('codigo')
                        ->values()
                        ->all()
                        : []
                ]
            ];
        } catch (\Throwable $e) {
            error_log('LOGIN ERROR autenticar: ' . $e->getMessage());
            return ['exito' => false, 'mensaje' => 'Error interno del servidor'];
        }
    }
}
