<?php

namespace Controllers;

use Libraries\Core\Controller;

class ClienteController extends Controller
{
    private function responderJson(array $payload, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($payload);
    }

    private function obtenerPayloadJson(): ?array
    {
        $datos = json_decode(file_get_contents('php://input'), true);
        return is_array($datos) ? $datos : null;
    }

    public function index($params = '')
    {
        if (!isset($_SESSION['usuario'])) {
            header('Location: ' . BASE_URL . 'Login/index');
            exit();
        }

        $nombre = $_GET['nombre'] ?? '';
        $data['page_title'] = 'Gestion de Clientes';
        $data['clientes'] = $this->model->listar($nombre);
        $data['page_js'] = ['Modal-Clientes.js', 'Clientes.js'];
        $this->views->render($this, 'index', $data);
    }

    public function listar($params = '')
    {
        $this->responderJson($this->model->obtenerClientes());
    }

    public function buscar($params = '')
    {
        $texto = trim((string) ($_GET['q'] ?? ''));
        $clientes = $this->model->obtenerClientesParaReserva($texto);
        $clienteInhabilitado = null;

        if ($texto !== '' && ctype_digit($texto)) {
            $clienteInhabilitado = $this->model->buscarClienteInhabilitadoPorDocumento($texto);
        }

        $this->responderJson([
            'clientes' => $clientes,
            'cliente_inhabilitado' => $clienteInhabilitado
        ]);
    }

    public function consultarApiPeru($params = '')
    {
        $tipo = strtolower(trim((string) ($_GET['tipo'] ?? 'dni')));
        $documento = preg_replace('/\D+/', '', trim((string) ($_GET['documento'] ?? '')));
        $token = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJlbWFpbCI6ImRlbmlzcnVpbWUuMjBAZ21haWwuY29tIn0.D61eKsOn1hVPtzBFRhHrylY4Wa6b_-OUn1lnzkrp7qU';

        if (!in_array($tipo, ['dni', 'ruc'], true)) {
            $this->responderJson(['success' => false, 'message' => 'Tipo de documento invalido'], 422);
            return;
        }

        if ($documento === '' || ($tipo === 'dni' && strlen($documento) !== 8) || ($tipo === 'ruc' && strlen($documento) !== 11)) {
            $this->responderJson(['success' => false, 'message' => 'Documento invalido'], 422);
            return;
        }

        $url = 'https://dniruc.apisperu.com/api/v1/' . $tipo . '/' . $documento . '?token=' . urlencode($token);

        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);

        $respuesta = curl_exec($curl);
        $errorCurl = curl_error($curl);
        $codigoHttp = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($respuesta === false || $errorCurl) {
            $this->responderJson(['success' => false, 'message' => 'No se pudo consultar Apis Peru'], 500);
            return;
        }

        $datos = json_decode($respuesta, true);
        if ($codigoHttp >= 400) {
            $mensaje = 'No se encontró información para ese documento.';

            if (json_last_error() === JSON_ERROR_NONE && is_array($datos)) {
                $mensajeApi = trim((string) ($datos['message'] ?? $datos['mensaje'] ?? ''));
                if ($mensajeApi !== '' && stripos($mensajeApi, 'ocurrió un error') === false) {
                    $mensaje = $mensajeApi;
                }
            } elseif (is_string($respuesta) && stripos($respuesta, 'Ocurrió un Error') === false) {
                $mensaje = 'No se pudo consultar Apis Peru.';
            }

            $this->responderJson(['success' => false, 'message' => $mensaje], 200);
            return;
        }

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->responderJson(['success' => false, 'message' => 'Respuesta invalida de Apis Peru'], 500);
            return;
        }

        http_response_code($codigoHttp > 0 ? $codigoHttp : 200);
        header('Content-Type: application/json');
        echo json_encode($datos);
    }

    public function registrar($params = '')
    {
        $datos = $this->obtenerPayloadJson();
        if ($datos === null) {
            $this->responderJson(['exito' => false, 'mensaje' => 'JSON invalido'], 400);
            return;
        }

        if (empty($datos['nombre'])) {
            $this->responderJson(['exito' => false, 'mensaje' => 'El nombre es obligatorio'], 422);
            return;
        }

        if (empty($datos['documento'])) {
            $this->responderJson(['exito' => false, 'mensaje' => 'El documento es obligatorio'], 422);
            return;
        }

        if (!is_numeric($datos['documento'])) {
            $this->responderJson(['exito' => false, 'mensaje' => 'El documento solo puede contener numeros'], 422);
            return;
        }

        if (empty($datos['gmail'])) {
            $this->responderJson(['exito' => false, 'mensaje' => 'El correo es obligatorio'], 422);
            return;
        }

        if (!filter_var($datos['gmail'], FILTER_VALIDATE_EMAIL)) {
            $this->responderJson(['exito' => false, 'mensaje' => 'Correo invalido'], 422);
            return;
        }

        if (empty($datos['telefono'])) {
            $this->responderJson(['exito' => false, 'mensaje' => 'El telefono es obligatorio'], 422);
            return;
        }

        if (!is_numeric($datos['telefono'])) {
            $this->responderJson(['exito' => false, 'mensaje' => 'El telefono solo puede contener numeros'], 422);
            return;
        }

        if (empty($datos['procedencia'])) {
            $this->responderJson(['exito' => false, 'mensaje' => 'La procedencia es obligatoria'], 422);
            return;
        }

        if (empty($datos['id_tipo_documento']) || !is_numeric($datos['id_tipo_documento']) || (int) $datos['id_tipo_documento'] <= 0) {
            $this->responderJson(['exito' => false, 'mensaje' => 'Seleccione un tipo de documento valido'], 422);
            return;
        }

        try {
            $ok = $this->model->crearCliente($datos);
            $this->responderJson([
                'exito' => (bool) $ok,
                'mensaje' => $ok ? 'Cliente creado correctamente' : 'No se pudo crear el cliente'
            ]);
        } catch (\Exception $e) {
            $this->responderJson(['exito' => false, 'mensaje' => $e->getMessage()], 500);
        }
    }

    public function actualizar($params = '')
    {
        $datos = $this->obtenerPayloadJson();
        if ($datos === null) {
            $this->responderJson(['exito' => false, 'mensaje' => 'JSON invalido'], 400);
            return;
        }

        if (empty($datos['id'])) {
            $this->responderJson(['exito' => false, 'mensaje' => 'ID requerido'], 422);
            return;
        }

        if (empty($datos['nombre'])) {
            $this->responderJson(['exito' => false, 'mensaje' => 'El nombre es obligatorio'], 422);
            return;
        }

        if (empty($datos['documento'])) {
            $this->responderJson(['exito' => false, 'mensaje' => 'El documento es obligatorio'], 422);
            return;
        }

        if (!is_numeric($datos['documento'])) {
            $this->responderJson(['exito' => false, 'mensaje' => 'El documento solo puede contener numeros'], 422);
            return;
        }

        if (empty($datos['gmail'])) {
            $this->responderJson(['exito' => false, 'mensaje' => 'El correo es obligatorio'], 422);
            return;
        }

        if (!filter_var($datos['gmail'], FILTER_VALIDATE_EMAIL)) {
            $this->responderJson(['exito' => false, 'mensaje' => 'Correo invalido'], 422);
            return;
        }

        if (empty($datos['telefono'])) {
            $this->responderJson(['exito' => false, 'mensaje' => 'El telefono es obligatorio'], 422);
            return;
        }

        if (!is_numeric($datos['telefono'])) {
            $this->responderJson(['exito' => false, 'mensaje' => 'El telefono solo puede contener numeros'], 422);
            return;
        }

        if (empty($datos['procedencia'])) {
            $this->responderJson(['exito' => false, 'mensaje' => 'La procedencia es obligatoria'], 422);
            return;
        }

        if (empty($datos['id_tipo_documento']) || !is_numeric($datos['id_tipo_documento']) || (int) $datos['id_tipo_documento'] <= 0) {
            $this->responderJson(['exito' => false, 'mensaje' => 'Seleccione un tipo de documento valido'], 422);
            return;
        }

        try {
            $ok = $this->model->actualizarCliente($datos);
            $this->responderJson([
                'exito' => (bool) $ok,
                'mensaje' => $ok ? 'Cliente actualizado' : 'No se pudo actualizar'
            ]);
        } catch (\Exception $e) {
            $this->responderJson(['exito' => false, 'mensaje' => $e->getMessage()], 500);
        }
    }

    public function eliminar($params = '')
    {
        $datos = $this->obtenerPayloadJson();
        if ($datos === null) {
            $this->responderJson(['exito' => false, 'mensaje' => 'JSON invalido'], 400);
            return;
        }

        $idCliente = (int) ($datos['id'] ?? 0);
        if ($idCliente <= 0) {
            $this->responderJson(['exito' => false, 'mensaje' => 'ID de cliente invalido'], 422);
            return;
        }

        try {
            $ok = $this->model->eliminarCliente($idCliente);
            $this->responderJson([
                'exito' => (bool) $ok,
                'mensaje' => $ok ? 'Cliente inhabilitado correctamente' : 'No se pudo inhabilitar'
            ]);
        } catch (\Exception $e) {
            $this->responderJson(['exito' => false, 'mensaje' => $e->getMessage()], 500);
        }
    }

    public function habilitar($params = '')
    {
        $datos = $this->obtenerPayloadJson();
        if ($datos === null) {
            $this->responderJson(['exito' => false, 'mensaje' => 'JSON invalido'], 400);
            return;
        }

        $idCliente = (int) ($datos['id'] ?? 0);
        if ($idCliente <= 0) {
            $this->responderJson(['exito' => false, 'mensaje' => 'ID de cliente invalido'], 422);
            return;
        }

        try {
            $ok = $this->model->habilitarCliente($idCliente);
            $this->responderJson([
                'exito' => (bool) $ok,
                'mensaje' => $ok ? 'Cliente habilitado correctamente' : 'No se pudo habilitar'
            ]);
        } catch (\Exception $e) {
            $this->responderJson(['exito' => false, 'mensaje' => $e->getMessage()], 500);
        }
    }
}
