<?php

namespace Controllers;

use Libraries\Core\Controller;
use Services\UsuarioService;

class PerfilController extends Controller
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
        header('Content-Type: application/json');
        if (!isset($_SESSION['usuario'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'No autenticado']);
            exit();
        }

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

        // Adaptamos la respuesta al formato que esperaba tu JS en este módulo específico
        echo json_encode([
            'success' => $respuesta['exito'],
            'message' => $respuesta['mensaje']
        ]);
        exit();
    }

    public function cambiarClave($params = '')
    {
        header('Content-Type: application/json');
        if (!isset($_SESSION['usuario'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'No autenticado']);
            exit();
        }

        $claveActual = $_POST['clave_actual']    ?? '';
        $claveNueva  = $_POST['clave_nueva']     ?? '';
        $confirmar   = $_POST['confirmar_clave'] ?? '';

        $respuesta = $this->usuarioService->cambiarContrasenia($_SESSION['usuario'], $claveActual, $claveNueva, $confirmar);

        echo json_encode([
            'success' => $respuesta['exito'],
            'message' => $respuesta['mensaje']
        ]);
        exit();
    }
}
