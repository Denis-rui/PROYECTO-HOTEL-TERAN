<?php
namespace Libraries\Core;

class ApiController extends Controller
{
    protected function responderJson(mixed $payload, int $statusCode = 200): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }

    protected function obtenerPayloadJson(): ?array
    {
        $contenido = file_get_contents('php://input');
        $datos = json_decode($contenido, true);

        return is_array($datos) ? $datos : null;
    }
}
