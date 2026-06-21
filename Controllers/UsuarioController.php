<?php

namespace Controllers;

use Libraries\Core\Controller;
use Services\UsuarioService;

class UsuarioController extends Controller
{
    private UsuarioService $usuarioService;

    public function __construct()
    {
        parent::__construct();
        $this->usuarioService = new UsuarioService();
    }

    public function index($params = '')
    {
        if (!isset($_SESSION['usuario'])) {
            header('Location: ' . BASE_URL . 'Login/index');
            exit();
        }

        $respuesta = $this->usuarioService->listarUsuarios();

        $data['page_title'] = "Gestión de Usuarios";
        $data['usuarios'] = $respuesta['exito'] ? $respuesta['data'] : [];
        $data['page_js'] = ['Modal-Usuario.js', 'Usuarios.js'];

        $this->views->render($this, 'index', $data);
    }

    public function listar($params = '')
    {
        header('Content-Type: application/json');
        $respuesta = $this->usuarioService->listarUsuarios();
        echo json_encode($respuesta['exito'] ? $respuesta['data'] : []);
    }

    public function perfil($params = '')
    {
        header('Content-Type: application/json');
        $nombreUsuario = $_SESSION['usuario'] ?? $_SESSION['nombreUsuario'] ?? ''; // Cubrimos ambas variables por seguridad

        if (empty($nombreUsuario)) {
            echo json_encode(['error' => 'No hay sesión activa']);
            exit;
        }

        $respuesta = $this->usuarioService->obtenerPerfil($nombreUsuario);
        echo json_encode($respuesta['exito'] ? $respuesta['data'] : []);
    }

    public function crear($params = '')
    {
        header('Content-Type: application/json');
        $datos = json_decode(file_get_contents('php://input'), true);
        echo json_encode($this->usuarioService->crearUsuario($datos));
    }

    public function actualizar($params = '')
    {
        header('Content-Type: application/json');
        $datos = json_decode(file_get_contents('php://input'), true);
        $nombreUsuario = $_SESSION['usuario'] ?? $_SESSION['nombreUsuario'] ?? '';

        if (empty($nombreUsuario)) {
            echo json_encode(['exito' => false, 'mensaje' => 'No hay sesión activa']);
            exit;
        }

        $respuesta = $this->usuarioService->actualizarPerfilPropio($nombreUsuario, $datos);

        // Actualizar la sesión si se cambió el usuario
        if ($respuesta['exito'] && isset($respuesta['nuevo_usuario'])) {
            $_SESSION['usuario'] = $respuesta['nuevo_usuario'];
            $_SESSION['nombreUsuario'] = $respuesta['nuevo_usuario'];
        }

        echo json_encode($respuesta);
    }

    public function actualizarAdmin($params = '')
    {
        header('Content-Type: application/json');
        $datos = json_decode(file_get_contents('php://input'), true);
        echo json_encode($this->usuarioService->actualizarUsuarioAdmin((int)($datos['id'] ?? 0), $datos));
    }

    public function eliminar($params = '')
    {
        header('Content-Type: application/json');
        $datos = json_decode(file_get_contents('php://input'), true);
        echo json_encode($this->usuarioService->eliminarUsuario((int)($datos['id'] ?? 0)));
    }
}
