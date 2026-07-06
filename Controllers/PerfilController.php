<?php

namespace Controllers;

use Libraries\Core\ApiController;
use Helpers\CodigoHTTP;
use Services\UsuarioService;

class PerfilController extends ApiController
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

        $respuesta = $this->usuarioService->obtenerPerfil($_SESSION['usuario']);

        if (!$respuesta['exito']) {
            header('Location: ' . BASE_URL . 'Login/index');
            exit();
        }

        $data['page_title'] = "Mi Perfil";
        $data['page_js']    = ['Perfil.js'];
        $data['perfil']     = $respuesta['data'];

        $this->views->render($this, 'index', $data);
    }

    public function actualizarPerfil($params = '')
    {
        if (!isset($_SESSION['usuario'])) {
            $this->responderJson($this->adaptarRespuestaPerfil([
                'exito' => false,
                'codigo' => 'NO_AUTENTICADO',
                'mensaje' => 'No autenticado',
                'data' => null,
                'errores' => [],
            ]), 401);
        }
        $this->validarCsrf();
        $datos = [
            'nombre_completo' => trim($_POST['nombre_completo'] ?? ''),
            'nombre_usuario'  => trim($_POST['usuario']         ?? ''),
            'correo'          => trim($_POST['email']           ?? ''),
            'telefono'        => trim($_POST['telefono']        ?? ''),
        ];

        $respuesta = $this->usuarioService->actualizarPerfilPropio($_SESSION['usuario'], $datos);

        if ($respuesta['exito'] && isset($respuesta['nuevo_usuario'])) {
            $_SESSION['usuario'] = $respuesta['nuevo_usuario'];
        }

        $this->responderPerfil($respuesta);
    }

    public function cambiarClave($params = '')
    {
        if (!isset($_SESSION['usuario'])) {
            $this->responderJson($this->adaptarRespuestaPerfil([
                'exito' => false,
                'codigo' => 'NO_AUTENTICADO',
                'mensaje' => 'No autenticado',
                'data' => null,
                'errores' => [],
            ]), 401);
        }
        $this->validarCsrf();
        $claveActual = $_POST['clave_actual']    ?? '';
        $claveNueva  = $_POST['clave_nueva']     ?? '';
        $confirmar   = $_POST['confirmar_clave'] ?? '';

        $respuesta = $this->usuarioService->cambiarContrasenia($_SESSION['usuario'], $claveActual, $claveNueva, $confirmar);

        $this->responderPerfil($respuesta);
    }

    private function responderPerfil(array $respuesta): void
    {
        [$payload, $codigoHttp] = CodigoHTTP::prepararRespuesta($respuesta);
        $this->responderJson($this->adaptarRespuestaPerfil($payload), $codigoHttp);
    }

    private function adaptarRespuestaPerfil(array $respuesta): array
    {
        return array_merge($respuesta, [
            'success' => (bool) ($respuesta['exito'] ?? false),
            'message' => (string) ($respuesta['mensaje'] ?? ''),
        ]);
    }
}
