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
        $apiUrl = defined('API_PERU_URL') ? (string) constant('API_PERU_URL') : '';
        $token = defined('API_PERU_TOKEN') ? (string) constant('API_PERU_TOKEN') : '';

        if (!in_array($tipo, ['dni', 'ruc'], true)) {
            return ['success' => false, 'message' => 'Tipo de documento inválido', 'code' => 422];
        }

        if ($documento === '' || ($tipo === 'dni' && strlen($documento) !== 8) || ($tipo === 'ruc' && strlen($documento) !== 11)) {
            return ['success' => false, 'message' => 'Documento inválido', 'code' => 422];
        }

        if ($apiUrl === '' || $token === '') {
            return ['success' => false, 'message' => 'Falta configurar la API de consulta de documentos.', 'code' => 500];
        }

        $url = rtrim($apiUrl, '/') . '/' . $tipo . '/' . $documento . '?token=' . urlencode($token);

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
        // Validar tipo de documento de forma temprana
        if (empty($datos['id_tipo_documento']) || !is_numeric($datos['id_tipo_documento']) || (int)$datos['id_tipo_documento'] <= 0) {
            return ['exito' => false, 'mensaje' => 'Seleccione un tipo de documento válido', 'code' => 422];
        }

        $tipoDoc = (int)$datos['id_tipo_documento'];
        $esRuc = ($tipoDoc === 6);

        $v = new Validator($datos);

        // Validaciones comunes a cualquier tipo de cliente
        $v->requerido('nombres', 'Nombres')
            ->requerido('correo_electronico', 'Correo electrónico')
            ->requerido('telefono', 'Teléfono')
            ->requerido('procedencia', 'Procedencia')
            ->numerico('telefono', 'Teléfono')
            ->email('correo_electronico', 'Correo electrónico');

        // Validación condicional basada en el tipo de documento
        if ($esRuc) {
            // Si es RUC, se vuelve obligatorio el campo 'ruc' y debe ser numérico
            $v->requerido('ruc', 'RUC')
                ->numerico('ruc', 'RUC');
        } else {
            // Si es DNI o Carnet de Extranjería, se exigen los apellidos y el número de documento
            $v->requerido('apellido_paterno', 'Apellido paterno')
                ->requerido('apellido_materno', 'Apellido materno')
                ->requerido('documento', 'Número de documento')
                ->numerico('documento', 'Número de documento');
        }

        if ($v->falla()) {
            return ['exito' => false, 'mensaje' => $v->primerError(), 'code' => 422];
        }

        try {
            // Limpieza y preparación de la estructura de persistencia
            $datosGuardar = [
                'id_tipo_documento'  => $tipoDoc,
                'documento'          => !$esRuc ? trim($datos['documento']) : null,
                'ruc'                => $esRuc ? trim($datos['ruc']) : (!empty($datos['ruc']) ? trim($datos['ruc']) : null),
                'nombres'            => trim($datos['nombres'] ?? ''),
                'apellido_paterno'   => !$esRuc ? trim($datos['apellido_paterno'] ?? '') : null,
                'apellido_materno'   => !$esRuc ? trim($datos['apellido_materno'] ?? '') : null,
                'correo_electronico' => trim($datos['correo_electronico'] ?? ''),
                'procedencia'        => trim($datos['procedencia'] ?? ''),
                'telefono'           => trim($datos['telefono'] ?? ''),
                'observaciones'      => trim($datos['observaciones'] ?? ''),
                'reservaciones'      => isset($datos['reservaciones']) ? (int)$datos['reservaciones'] : 0,
                'activo'             => 1
            ];

            $this->clienteModel->crear($datosGuardar);
            return ['exito' => true, 'mensaje' => 'Cliente creado correctamente', 'code' => 200];
        } catch (Exception $e) {
            error_log('Error registrarCliente: ' . $e->getMessage());
            return ['exito' => false, 'mensaje' => 'No se pudo crear el cliente. Verifica si el documento o RUC ya existe.', 'code' => 500];
        }
    }

    public function actualizarCliente(array $datos): array
    {
        // Validar ID de forma temprana
        if (empty($datos['id'])) {
            return ['exito' => false, 'mensaje' => 'ID requerido', 'code' => 422];
        }

        // Validar tipo de documento de forma temprana
        if (empty($datos['id_tipo_documento']) || !is_numeric($datos['id_tipo_documento']) || (int)$datos['id_tipo_documento'] <= 0) {
            return ['exito' => false, 'mensaje' => 'Seleccione un tipo de documento válido', 'code' => 422];
        }

        $tipoDoc = (int)$datos['id_tipo_documento'];
        $esRuc = ($tipoDoc === 6);

        $v = new Validator($datos);

        // Validaciones comunes a cualquier tipo de cliente al actualizar
        $v->requerido('nombres', 'Nombres')
            ->requerido('correo_electronico', 'Correo electrónico')
            ->requerido('telefono', 'Teléfono')
            ->requerido('procedencia', 'Procedencia')
            ->numerico('telefono', 'Teléfono')
            ->email('correo_electronico', 'Correo electrónico');

        // Validación condicional basada en el tipo de documento
        if ($esRuc) {
            $v->requerido('ruc', 'RUC')
                ->numerico('ruc', 'RUC');
        } else {
            $v->requerido('apellido_paterno', 'Apellido p_aterno')
                ->requerido('apellido_materno', 'Apellido materno')
                ->requerido('documento', 'Número de documento')
                ->numerico('documento', 'Número de documento');
        }

        if ($v->falla()) {
            return ['exito' => false, 'mensaje' => $v->primerError(), 'code' => 422];
        }

        try {
            // Limpieza y preparación de la estructura para actualización
            $datosActualizar = [
                'id_tipo_documento'  => $tipoDoc,
                'documento'          => !$esRuc ? trim($datos['documento']) : null,
                'ruc'                => $esRuc ? trim($datos['ruc']) : (!empty($datos['ruc']) ? trim($datos['ruc']) : null),
                'nombres'            => trim($datos['nombres'] ?? ''),
                'apellido_paterno'   => !$esRuc ? trim($datos['apellido_paterno'] ?? '') : null,
                'apellido_materno'   => !$esRuc ? trim($datos['apellido_materno'] ?? '') : null,
                'correo_electronico' => trim($datos['correo_electronico'] ?? ''),
                'procedencia'        => trim($datos['procedencia'] ?? ''),
                'telefono'           => trim($datos['telefono'] ?? ''),
                'observaciones'      => trim($datos['observaciones'] ?? '')
                // Nota: No incluimos 'reservaciones' ni 'activo' a menos que tu lógica de negocio permita alterarlos aquí
            ];

            $this->clienteModel->actualizar((int)$datos['id'], $datosActualizar);
            return ['exito' => true, 'mensaje' => 'Cliente actualizado correctamente', 'code' => 200];
        } catch (Exception $e) {
            error_log('Error actualizarCliente: ' . $e->getMessage());
            return ['exito' => false, 'mensaje' => 'No se pudo actualizar el cliente. Verifica si el documento o RUC ya existe en otro registro.', 'code' => 500];
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
