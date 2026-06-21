<?php

namespace Controllers;

use Libraries\Core\Controller;
use Services\LoginService;

class LoginController extends Controller
{
    // Muestra el formulario de login
    public function index($params = '')
    {
        $this->views->render($this, 'index');
    }

    // Procesa el formulario POST del login
    public function entrar($params = '')
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . 'Login/index');
            exit();
        }

        // Sanitización de entradas para prevenir XSS
        $post = \Libraries\Core\Auth::sanitizarEntradas($_POST);

        $tipousuario = $post['tipousuario'] ?? '';
        $usuario     = $post['usuario']     ?? '';
        $contrasenia = $_POST['contrasena'] ?? ''; // Contraseña NO se sanitiza para preservar caracteres especiales
        $loginService = new LoginService();
        $user = $loginService->autenticar($usuario, $contrasenia);
        $contraseniaGuardada = $user['contrasenia'] ?? '';
        $contraseniaValida   =
            password_verify($contrasenia, $contraseniaGuardada)
            || (is_string($contraseniaGuardada) && hash_equals($contraseniaGuardada, md5($contrasenia)))
            || (is_string($contraseniaGuardada) && hash_equals($contraseniaGuardada, $contrasenia));

        // Comparación de rol insensible a mayúsculas y espacios
        $rolUsuario = isset($user['rol']) ? trim((string)$user['rol']) : '';
        if ($user && strcasecmp(trim($tipousuario), $rolUsuario) === 0 && $contraseniaValida) {
            session_regenerate_id(true);
            $_SESSION['usuario']       = $user['nombre_usuario'];
            $_SESSION['nombreUsuario'] = $user['nombre_usuario'];
            $_SESSION['rol']           = $user['rol'];
            $_SESSION['id_usuario']    = $user['id'];
            $_SESSION['permisos']      = $user['permisos'] ?? [];
            header('Location: ' . BASE_URL . 'Dashboard/index');
            exit();
        }

        header('Location: ' . BASE_URL . 'Login/index&error=1');
        exit();
    }

    // Cierra la sesión
    public function salir($params = '')
    {
        session_destroy();
        header('Location: ' . BASE_URL . 'Login/index');
        exit();
    }
}
