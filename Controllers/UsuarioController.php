<?php

class UsuarioController extends Controller
{
    // Responde JSON - lista todos los usuarios
    public function index($params = '')
    {
        if (!isset($_SESSION['usuario'])) {
            header('Location: ' . BASE_URL . '?url=Login/index');
            exit();
        }
        $data['page_title'] = "Gestión de Usuarios";
        $data['usuarios'] = $this->model->listar();
        $data['page_js'] = ['Modal-Usuario.js', 'Usuarios.js'];
        $this->views->render($this, 'index', $data);
    }

    public function listar($params = '')
    {
        header('Content-Type: application/json');
        echo json_encode($this->model->readAll());
    }

    public function perfil($params = '')
    {
        header('Content-Type: application/json');
        $nombreUsuario = $_SESSION['nombreUsuario'] ?? '';
        if (empty($nombreUsuario)) {
            echo json_encode(['error' => 'No hay sesión activa']);
            exit;
        }
        echo json_encode($this->model->read($nombreUsuario));
    }

    public function crear($params = '')
    {
        header('Content-Type: application/json');
        $datos = json_decode(file_get_contents('php://input'), true);
        $ok    = $this->model->create($datos);
        echo json_encode(['exito' => $ok]);
    }

    public function actualizar($params = '')
    {
        header('Content-Type: application/json');
        $datos         = json_decode(file_get_contents('php://input'), true);
        $nombreUsuario = $_SESSION['nombreUsuario'] ?? '';
        if (empty($nombreUsuario)) {
            echo json_encode(['error' => 'No hay sesión activa']);
            exit;
        }
        $ok = $this->model->update($nombreUsuario, $datos);
        if ($ok && isset($datos['nombre_usuario'])) {
            $_SESSION['nombreUsuario'] = $datos['nombre_usuario'];
        }
        echo json_encode(['exito' => $ok]);
    }

    public function actualizarAdmin($params = '')
    {
        header('Content-Type: application/json');
        $datos = json_decode(file_get_contents('php://input'), true);
        $ok    = $this->model->updateById((int) ($datos['id'] ?? 0), $datos);
        echo json_encode(['exito' => $ok]);
    }

    public function eliminar($params = '')
    {
        header('Content-Type: application/json');
        $datos = json_decode(file_get_contents('php://input'), true);
        $ok    = $this->model->deleteUsuario((int) ($datos['id'] ?? 0));
        echo json_encode(['exito' => $ok]);
    }
}
