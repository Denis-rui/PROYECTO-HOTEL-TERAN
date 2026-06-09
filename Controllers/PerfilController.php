<?php
namespace Controllers;

use Libraries\Core\Controller;
use Models\UsuarioModel;

class PerfilController extends Controller
{
    private $usuarioModel;

    public function __construct()
    {
        parent::__construct();
        $this->usuarioModel = new UsuarioModel();
    }

    public function index($params = '')
    {
        if(!isset($_SESSION['usuario'])){
            header('Location: ' . BASE_URL . 'Login/index');
            exit();
        }

        $perfil = $this->usuarioModel->read($_SESSION['usuario']);

        if (!$perfil){
            header('Location: ' . BASE_URL . 'Login/index');
            exit();
        }

        $data['page_title'] = "Mi Perfil";
        $data['page_js']    = ['Perfil.js'];
        $data['perfil']     = $perfil;

        $this->views->render($this, 'index', $data);
    }

    public function actualizarPerfil($params = ''){
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

        try {
            $ok = $this->usuarioModel->updateByNombreUsuario($_SESSION['usuario'], $datos);

            if ($ok && !empty($datos['nombre_usuario'])) {
                $_SESSION['usuario'] = $datos['nombre_usuario'];
            }

            $response = [
                'success' => (bool) $ok,
                'message' => $ok ? 'Perfil actualizado correctamente' : 'Error al actualizar el perfil',
            ];
        } catch (\Throwable $e) {
            error_log('PerfilController actualizarPerfil error: ' . $e->getMessage());
            http_response_code(400);
            $response = [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }

        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }

     public function cambiarClave($params = '')
    {
        if (!isset($_SESSION['usuario'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'No autenticado']);
            exit();
        }

        $claveActual = $_POST['clave_actual']    ?? '';
        $claveNueva  = $_POST['clave_nueva']     ?? '';
        $confirmar   = $_POST['confirmar_clave'] ?? '';

        if ($claveNueva !== $confirmar) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Las contraseñas no coinciden']);
            exit();
        }

        $userRaw = $this->usuarioModel->obtenerUsuarios($_SESSION['usuario']);

        $contraseniaGuardada = $userRaw['contrasenia'] ?? '';
        $claveActualValida =
            is_string($contraseniaGuardada)
            && (
                password_verify($claveActual, $contraseniaGuardada)
                || hash_equals($contraseniaGuardada, md5($claveActual))
                || hash_equals($contraseniaGuardada, $claveActual)
            );

        if (!$userRaw || !$claveActualValida) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'La contraseña actual es incorrecta']);
            exit();
        }

        $ok = $this->usuarioModel->updateByNombreUsuario($_SESSION['usuario'], [
            'contrasenia' => $claveNueva,
        ]);

        header('Content-Type: application/json');
        echo json_encode([
            'success' => (bool) $ok,
            'message' => $ok ? 'Contraseña actualizada correctamente' : 'Error al actualizar la contraseña',
        ]);
        exit();
    }
}
