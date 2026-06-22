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
        $contrasenia = $_POST['contrasena'] ?? '';

        $loginService = new LoginService();

        // Delegamos TODA la lógica de negocio al Service
        $resultado = $loginService->autenticar($usuario, $contrasenia, $tipousuario);

        // Si la autenticación es exitosa, creamos la sesión
        if ($resultado['exito']) {
            session_regenerate_id(true);
            $user = $resultado['usuario'];

            $_SESSION['usuario']       = $user['nombre_usuario'];
            $_SESSION['nombreUsuario'] = $user['nombre_usuario'];
            $_SESSION['rol']           = $user['rol'];
            $_SESSION['id_usuario']    = $user['id'];
            $_SESSION['permisos']      = $user['permisos'] ?? [];

            header('Location: ' . BASE_URL . 'Dashboard/index');
            exit();
        }

        // Si falla la autenticación, redirigimos con error
        // Opcional: podrías pasar $resultado['mensaje'] a la vista para dar feedback más específico
        header('Location: ' . BASE_URL . 'Login/index?error=1');
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
