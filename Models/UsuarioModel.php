<?php

namespace Models;

use Models\Entities\Rol;
use Models\Entities\Usuario;

class UsuarioModel
{
    public function obtenerPorNombreUsuario($nombreUsuario)
    {
        return Usuario::with(['rol.permisos'])
            ->where('nombre_usuario', $nombreUsuario)
            ->where('estado', 1)
            ->first();
    }

    public function obtenerPorId(int $id)
    {
        return Usuario::find($id);
    }

    public function listarActivos()
    {
        return Usuario::with('rol')
            ->where('estado', 1)
            ->orderBy('id', 'asc')
            ->get();
    }

    public function buscarIdRolPorNombre(string $nombreRol)
    {
        return Rol::where('rol', $nombreRol)->value('id');
    }

    // Método genérico para que el Servicio pregunte si un dato ya existe
    public function existeValorUnico(string $campo, string $valor, ?int $ignorarId = null): bool
    {
        if (trim($valor) === '') return false;

        $query = Usuario::where($campo, trim($valor));
        if ($ignorarId !== null) {
            $query->where('id', '<>', $ignorarId);
        }
        return $query->exists();
    }

    public function crear(array $datos)
    {
        return Usuario::create($datos);
    }

    public function actualizar(int $id, array $datos)
    {
        $usuario = Usuario::find($id);
        if (!$usuario) return false;

        $usuario->fill($datos);
        return $usuario->save();
    }

    public function desactivar(int $id)
    {
        $usuario = Usuario::find($id);
        if (!$usuario) return false;

        $usuario->estado = 0;
        return $usuario->save();
    }
}
