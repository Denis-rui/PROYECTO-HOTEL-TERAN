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
        $respuesta = $this->usuarioService->listarUsuarios();
        $this->responderJson($respuesta['exito'] ? $respuesta['data'] : []);
    }

    public function perfil($params = '')
    {
        $nombreUsuario = $_SESSION['usuario'] ?? $_SESSION['nombreUsuario'] ?? ''; // Cubrimos ambas variables por seguridad

        if (empty($nombreUsuario)) {
            $this->responderJson(['error' => 'No hay sesión activa'], 401);
        }

        $respuesta = $this->usuarioService->obtenerPerfil($nombreUsuario);
        $this->responderJson($respuesta['exito'] ? $respuesta['data'] : []);
    }

    public function crear($params = '')
    {
        $datos = $this->obtenerPayloadJson() ?? [];
        $this->responderJson($this->usuarioService->crearUsuario($datos));
    }

    public function actualizar($params = '')
    {
        $datos = $this->obtenerPayloadJson() ?? [];
        $nombreUsuario = $_SESSION['usuario'] ?? $_SESSION['nombreUsuario'] ?? '';

        if (empty($nombreUsuario)) {
            $this->responderJson(['exito' => false, 'mensaje' => 'No hay sesión activa'], 401);
        }

        $respuesta = $this->usuarioService->actualizarPerfilPropio($nombreUsuario, $datos);

        // Actualizar la sesión si se cambió el usuario
        if ($respuesta['exito'] && isset($respuesta['nuevo_usuario'])) {
            $_SESSION['usuario'] = $respuesta['nuevo_usuario'];
            $_SESSION['nombreUsuario'] = $respuesta['nuevo_usuario'];
        }

        $this->responderJson($respuesta);
    }

    public function actualizarAdmin($params = '')
    {
        $datos = $this->obtenerPayloadJson() ?? [];
        $this->responderJson($this->usuarioService->actualizarUsuarioAdmin((int)($datos['id'] ?? 0), $datos));
    }

    public function eliminar($params = '')
    {
        $datos = $this->obtenerPayloadJson() ?? [];
        $this->responderJson($this->usuarioService->eliminarUsuario((int)($datos['id'] ?? 0)));
    }
}
