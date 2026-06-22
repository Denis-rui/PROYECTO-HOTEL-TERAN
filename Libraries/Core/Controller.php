<?php
namespace Libraries\Core;

class Controller
{
    protected $views;
    protected $model;

    public function __construct()
    {
        $this->views = new Views();
        $this->loadModel();
    }

    public function loadModel()
    {
        // Deduce el nombre del Model a partir del Controller short name:
        $shortName = (new \ReflectionClass($this))->getShortName();
        $modelName = str_replace('Controller', 'Model', $shortName);
        $modelClass = "Models\\{$modelName}";

        if (class_exists($modelClass)) {
            $this->model = new $modelClass();
        }
    }

    protected function responderJson(mixed $payload, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }

    protected function obtenerPayloadJson(): ?array
    {
        $datos = json_decode(file_get_contents('php://input'), true);
        return is_array($datos) ? $datos : null;
    }

    protected function validarCsrf(): void
    {
        try {
            \Libraries\Core\Csrf::validar();
        } catch (\Exception $e) {
            error_log('Error de validación CSRF: ' . $e->getMessage());
            $this->responderJson(['exito' => false, 'mensaje' => 'No se pudo validar la solicitud.'], 403);
        }
    }
}
