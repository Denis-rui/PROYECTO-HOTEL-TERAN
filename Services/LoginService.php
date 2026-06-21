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

    public function autenticar($usuario, $contrasenia)
    {
        try {
            $user = $this->usuarioModel->obtenerPorNombreUsuario($usuario);
            if (!$user) {
                return [];
            }
            return [
                'id' => $user->id,
                'nombre_usuario' => $user->nombre_usuario,
                'rol' => $user->rol->rol ?? '',
                'permisos' => $user->rol ? $user->rol->permisos
                    ->where('activo', 1)
                    ->pluck('codigo')
                    ->values()
                    ->all()
                    : [],
                'contrasenia' => $user->contrasenia,

            ];
        } catch (\Throwable $e) {
            error_log('LOGIN ERROR obtenerUsuarios: ' . $e->getMessage());
            return [];
        }
    }
}
