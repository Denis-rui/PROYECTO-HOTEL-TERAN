<?php

namespace Services;

use Models\ClienteModel;
use Libraries\Core\Validator;
use Exception;

class ClienteService
{
    private ClienteModel $clienteModel;

    public function __construct()
    {
        $this->clienteModel = new ClienteModel();
    }

    public function buscarParaReserva(string $texto): array
    {
        $texto = trim($texto);
        $clientes = $this->clienteModel->obtenerClientesParaReserva($texto);
        $clienteInhabilitado = null;

        if ($texto !== '' && ctype_digit($texto)) {
            $clienteInhabilitado = $this->clienteModel->buscarInhabilitadoPorDocumento($texto);
        }

        return [
            'exito' => true,
            'data' => [
                'clientes' => $clientes,
                'cliente_inhabilitado' => $clienteInhabilitado
            ]
        ];
    }

    public function listarClientes(string $nombre = ''): array
    {
        return $this->clienteModel->listar(trim($nombre));
    }

    // ¡La llamada externa ahora vive en el servicio!
    public function consultarApiExterna(string $tipo, string $documento): array
    {
        $tipo = strtolower(trim($tipo));
        $documento = preg_replace('/\D+/', '', trim($documento));
        $token = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJlbWFpbCI6ImRlbmlzcnVpbWUuMjBAZ21haWwuY29tIn0.D61eKsOn1hVPtzBFRhHrylY4Wa6b_-OUn1lnzkrp7qU';

        if (!in_array($tipo, ['dni', 'ruc'], true)) {
            return ['success' => false, 'message' => 'Tipo de documento inválido', 'code' => 422];
        }

        if ($documento === '' || ($tipo === 'dni' && strlen($documento) !== 8) || ($tipo === 'ruc' && strlen($documento) !== 11)) {
            return ['success' => false, 'message' => 'Documento inválido', 'code' => 422];
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
            return ['success' => false, 'message' => 'No se pudo completar la consulta', 'code' => 500];
        }

        $datos = json_decode($respuesta, true);

        if ($codigoHttp >= 400) {
            $mensaje = 'No se encontró información para ese documento.';
            if (json_last_error() === JSON_ERROR_NONE && is_array($datos)) {
                $mensajeApi = trim((string) ($datos['message'] ?? $datos['mensaje'] ?? ''));
                if ($mensajeApi !== '' && stripos($mensajeApi, 'ocurrió un error') === false) {
                    $mensaje = $mensajeApi;
                }
            }
            return ['success' => false, 'message' => $mensaje, 'code' => 200];
        }

        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['success' => false, 'message' => 'No se pudo procesar la respuesta', 'code' => 500];
        }

        // Si todo sale bien, devolvemos la data tal cual la espera el frontend
        return array_merge($datos, ['success' => true, 'code' => $codigoHttp]);
    }

    public function registrarCliente(array $datos): array
    {
        $v = new Validator($datos);
        $v->requerido('nombre', 'Nombre')
            ->requerido('documento', 'Documento')
            ->requerido('gmail', 'Correo electrónico')
            ->requerido('telefono', 'Teléfono')
            ->requerido('procedencia', 'Procedencia')
            ->numerico('documento', 'Documento')
            ->numerico('telefono', 'Teléfono')
            ->email('gmail', 'Correo electrónico');

        if (empty($datos['id_tipo_documento']) || !is_numeric($datos['id_tipo_documento']) || (int)$datos['id_tipo_documento'] <= 0) {
            return ['exito' => false, 'mensaje' => 'Seleccione un tipo de documento válido', 'code' => 422];
        }

        if ($v->falla()) {
            return ['exito' => false, 'mensaje' => $v->primerError(), 'code' => 422];
        }

        try {
            $datosGuardar = [
                'nombre_completo' => $datos['nombre_completo'] ?? $datos['nombre'] ?? '',
                'id_tipo_documento' => $datos['id_tipo_documento'] ?? '',
                'documento' => $datos['documento'] ?? '',
                'correo_electronico' => $datos['correo_electronico'] ?? $datos['gmail'] ?? '',
                'procedencia' => $datos['procedencia'] ?? '',
                'telefono' => $datos['telefono'] ?? '',
                'observaciones' => $datos['observaciones'] ?? '',
                'reservaciones' => 0,
                'activo' => 1
            ];

            $this->clienteModel->crear($datosGuardar);
            return ['exito' => true, 'mensaje' => 'Cliente creado correctamente', 'code' => 200];
        } catch (Exception $e) {
            error_log('Error crearCliente: ' . $e->getMessage());
            return ['exito' => false, 'mensaje' => 'No se pudo crear el cliente. Verifica si el documento ya existe.', 'code' => 500];
        }
    }

    public function actualizarCliente(array $datos): array
    {
        if (empty($datos['id'])) {
            return ['exito' => false, 'mensaje' => 'ID requerido', 'code' => 422];
        }

        $v = new Validator($datos);
        $v->requerido('nombre', 'Nombre')
            ->requerido('documento', 'Documento')
            ->numerico('documento', 'Documento')
            ->requerido('gmail', 'Correo electrónico')
            ->email('gmail', 'Correo electrónico')
            ->requerido('telefono', 'Teléfono')
            ->numerico('telefono', 'Teléfono')
            ->requerido('procedencia', 'Procedencia');

        if (empty($datos['id_tipo_documento']) || !is_numeric($datos['id_tipo_documento']) || (int)$datos['id_tipo_documento'] <= 0) {
            return ['exito' => false, 'mensaje' => 'Seleccione un tipo de documento válido', 'code' => 422];
        }

        if ($v->falla()) {
            return ['exito' => false, 'mensaje' => $v->primerError(), 'code' => 422];
        }

        try {
            $datosActualizar = [
                'nombre_completo' => $datos['nombre_completo'] ?? $datos['nombre'] ?? '',
                'id_tipo_documento' => $datos['id_tipo_documento'] ?? '',
                'documento' => $datos['documento'] ?? '',
                'correo_electronico' => $datos['correo_electronico'] ?? $datos['gmail'] ?? '',
                'procedencia' => $datos['procedencia'] ?? '',
                'telefono' => $datos['telefono'] ?? '',
                'observaciones' => $datos['observaciones'] ?? ''
            ];

            $this->clienteModel->actualizar((int)$datos['id'], $datosActualizar);
            return ['exito' => true, 'mensaje' => 'Cliente actualizado', 'code' => 200];
        } catch (Exception $e) {
            error_log('Error actualizarCliente: ' . $e->getMessage());
            return ['exito' => false, 'mensaje' => 'No se pudo actualizar el cliente.', 'code' => 500];
        }
    }

    public function cambiarEstado(int $id, int $estado): array
    {
        if ($id <= 0) {
            return ['exito' => false, 'mensaje' => 'ID de cliente inválido', 'code' => 422];
        }

        try {
            $this->clienteModel->cambiarEstado($id, $estado);
            $mensaje = $estado === 1 ? 'Cliente habilitado correctamente' : 'Cliente inhabilitado correctamente';
            return ['exito' => true, 'mensaje' => $mensaje, 'code' => 200];
        } catch (Exception $e) {
            error_log('Error cambiarEstado cliente: ' . $e->getMessage());
            return ['exito' => false, 'mensaje' => 'Error al actualizar el estado del cliente', 'code' => 500];
        }
    }
}
