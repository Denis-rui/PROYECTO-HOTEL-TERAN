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

    public function autenticar($usuario, $contrasenia, $tipousuario): array
    {
        try {
            $user = $this->usuarioModel->obtenerPorNombreUsuario($usuario);

            //Validar si el usuario existe
            if (!$user) {
                return $this->respuesta(false, 'NO_ENCONTRADO', 'Usuario no encontrado');
            }

            // Validar la contraseña 
            $contraseniaGuardada = $user->contrasenia;
            $contraseniaValida =
                password_verify($contrasenia, $contraseniaGuardada)
                || (is_string($contraseniaGuardada) && hash_equals($contraseniaGuardada, md5($contrasenia)))
                || (is_string($contraseniaGuardada) && hash_equals($contraseniaGuardada, $contrasenia));

            if (!$contraseniaValida) {
                return $this->respuesta(false, 'VALIDACION_ERROR', 'Contraseña incorrecta');
            }

            // Validar el rol 
            $rolUsuario = $user->rol->rol ?? '';
            if (strcasecmp(trim($tipousuario), trim((string) $rolUsuario)) !== 0) {
                return $this->respuesta(false, 'NO_AUTORIZADO', 'Rol de usuario no coincide');
            }
            // Migración Silenciosa de Hash
            if (
                !password_verify($contrasenia, $contraseniaGuardada) || 
                password_needs_rehash($contraseniaGuardada, PASSWORD_DEFAULT)
            ) {
                $nuevoHash = password_hash($contrasenia, PASSWORD_DEFAULT);
                $this->usuarioModel->actualizar($user->id, ['contrasenia' => $nuevoHash]);
            }
            // 4. Retornar el arreglo de éxito solo con los datos necesarios para la sesión
            return $this->respuesta(true, 'OK', 'Autenticación correcta.', [
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
            ]);
        } catch (\Throwable $e) {
            error_log('LOGIN ERROR autenticar: ' . $e->getMessage());
            return $this->respuesta(false, 'ERROR_INTERNO', 'Error interno del servidor');
        }
    }

    private function respuesta(bool $exito, string $codigo, string $mensaje, mixed $data = null, array $errores = []): array
    {
        $respuesta = [
            'exito' => $exito,
            'codigo' => $codigo,
            'mensaje' => $mensaje,
            'data' => $data,
            'errores' => $errores,
        ];

        return is_array($data) ? array_merge($respuesta, $data) : $respuesta;
    }
}
