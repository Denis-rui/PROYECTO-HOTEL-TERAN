<?php

namespace Controllers;

use Libraries\Core\ApiController;
use Helpers\CodigoHTTP;
use Services\UsuarioService;

class UsuarioController extends ApiController
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
        $data['page_js'] = ['Modal-Usuario.js', 'Usuarios.js', 'Busqueda-Usuarios.js'];

        $this->views->render($this, 'index', $data);
    }

    public function listar($params = '')
    {
        $respuesta = $this->usuarioService->listarUsuarios();
        $this->responderJson($respuesta['exito'] ? $respuesta['data'] : []);
    }

    public function buscar($params = '')
    {
        if (!isset($_SESSION['usuario'])) {
            $this->responderJson($this->respuestaSesionInvalida(), 401);
        }

        $termino = $_GET['q'] ?? '';
        $respuesta = $this->usuarioService->buscarUsuarios($termino);
        $this->responderJson($respuesta['exito'] ? $respuesta['data'] : []);
    }

    public function perfil($params = '')
    {
        $nombreUsuario = $_SESSION['usuario'] ?? $_SESSION['nombreUsuario'] ?? ''; // Cubrimos ambas variables por seguridad

        if (empty($nombreUsuario)) {
            $this->responderJson($this->respuestaSesionInvalida(), 401);
        }

        $respuesta = $this->usuarioService->obtenerPerfil($nombreUsuario);
        $this->responderJson($respuesta['exito'] ? $respuesta['data'] : []);
    }

    public function crear($params = '')
    {
        $this->validarCsrf();
        $datos = $this->obtenerPayloadJson() ?? [];
        $this->responderServicio($this->usuarioService->crearUsuario($datos));
    }

    public function actualizar($params = '')
    {
        $this->validarCsrf();
        $datos = $this->obtenerPayloadJson() ?? [];
        $nombreUsuario = $_SESSION['usuario'] ?? $_SESSION['nombreUsuario'] ?? '';

        if (empty($nombreUsuario)) {
            $this->responderJson($this->respuestaSesionInvalida(), 401);
        }

        $respuesta = $this->usuarioService->actualizarPerfilPropio($nombreUsuario, $datos);

        // Actualizar la sesión si se cambió el usuario
        if ($respuesta['exito'] && isset($respuesta['nuevo_usuario'])) {
            $_SESSION['usuario'] = $respuesta['nuevo_usuario'];
            $_SESSION['nombreUsuario'] = $respuesta['nuevo_usuario'];
        }

        $this->responderServicio($respuesta);
    }

    public function actualizarAdmin($params = '')
    {
        $this->validarCsrf();
        $datos = $this->obtenerPayloadJson() ?? [];
        $this->responderServicio($this->usuarioService->actualizarUsuarioAdmin((int)($datos['id'] ?? 0), $datos));
    }

    public function eliminar($params = '')
    {
        $this->validarCsrf();
        $datos = $this->obtenerPayloadJson() ?? [];
        $this->responderServicio($this->usuarioService->eliminarUsuario((int)($datos['id'] ?? 0)));
    }

    private function responderServicio(array $respuesta): void
    {
        [$payload, $codigoHttp] = CodigoHTTP::prepararRespuesta($respuesta);
        $this->responderJson($payload, $codigoHttp);
    }

    private function respuestaSesionInvalida(): array
    {
        return [
            'exito' => false,
            'codigo' => 'NO_AUTENTICADO',
            'mensaje' => 'No hay sesión activa',
            'data' => null,
            'errores' => [],
            'error' => 'No hay sesión activa',
        ];
    }
}
