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

        return $this->respuesta(true, 'OK', 'Clientes cargados correctamente.', [
                'clientes' => $clientes,
                'cliente_inhabilitado' => $clienteInhabilitado
            ]);
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
            return $this->respuestaApiExterna(false, 'VALIDACION_ERROR', 'Tipo de documento inválido');
        }

        if ($documento === '' || ($tipo === 'dni' && strlen($documento) !== 8) || ($tipo === 'ruc' && strlen($documento) !== 11)) {
            return $this->respuestaApiExterna(false, 'VALIDACION_ERROR', 'Documento inválido');
        }

        if ($apiUrl === '' || $token === '') {
            return $this->respuestaApiExterna(false, 'ERROR_INTERNO', 'Falta configurar la API de consulta de documentos.');
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
            return $this->respuestaApiExterna(false, 'ERROR_INTERNO', 'No se pudo completar la consulta');
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
            return $this->respuestaApiExterna(false, 'NO_ENCONTRADO', $mensaje);
        }

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->respuestaApiExterna(false, 'ERROR_INTERNO', 'No se pudo procesar la respuesta');
        }

        // Si todo sale bien, devolvemos la data tal cual la espera el frontend
        return $this->respuestaApiExterna(true, 'OK', 'Documento encontrado.', $datos);
    }

    public function registrarCliente(array $datos): array
    {
        // Validar tipo de documento de forma temprana
        if (empty($datos['id_tipo_documento']) || !is_numeric($datos['id_tipo_documento']) || (int)$datos['id_tipo_documento'] <= 0) {
            return $this->respuesta(false, 'VALIDACION_ERROR', 'Seleccione un tipo de documento válido', null, [
                'id_tipo_documento' => 'Seleccione un tipo de documento válido.',
            ]);
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
            return $this->respuesta(false, 'VALIDACION_ERROR', $v->primerError());
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

            $cliente = $this->clienteModel->crear($datosGuardar);
            return $this->respuesta(true, 'CREADO', 'Cliente creado correctamente', $cliente);
        } catch (Exception $e) {
            error_log('Error registrarCliente: ' . $e->getMessage());
            if ($e->getCode() == 23000 || strpos($e->getMessage(), '1062') !== false) {
                return $this->respuesta(false, 'CONFLICTO', 'Ya existe un cliente con ese documento o RUC.');
            }

            return $this->respuesta(false, 'ERROR_INTERNO', 'No se pudo crear el cliente. Verifica si el documento o RUC ya existe.');
        }
    }

    public function actualizarCliente(array $datos): array
    {
        // Validar ID de forma temprana
        if (empty($datos['id'])) {
            return $this->respuesta(false, 'VALIDACION_ERROR', 'ID requerido', null, [
                'id' => 'El cliente es obligatorio.',
            ]);
        }

        // Validar tipo de documento de forma temprana
        if (empty($datos['id_tipo_documento']) || !is_numeric($datos['id_tipo_documento']) || (int)$datos['id_tipo_documento'] <= 0) {
            return $this->respuesta(false, 'VALIDACION_ERROR', 'Seleccione un tipo de documento válido', null, [
                'id_tipo_documento' => 'Seleccione un tipo de documento válido.',
            ]);
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
            return $this->respuesta(false, 'VALIDACION_ERROR', $v->primerError());
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
            return $this->respuesta(true, 'ACTUALIZADO', 'Cliente actualizado correctamente', [
                'id' => (int) $datos['id'],
            ]);
        } catch (Exception $e) {
            error_log('Error actualizarCliente: ' . $e->getMessage());
            if ($e->getCode() == 23000 || strpos($e->getMessage(), '1062') !== false) {
                return $this->respuesta(false, 'CONFLICTO', 'Ya existe otro cliente con ese documento o RUC.');
            }

            return $this->respuesta(false, 'ERROR_INTERNO', 'No se pudo actualizar el cliente. Verifica si el documento o RUC ya existe en otro registro.');
        }
    }

    public function cambiarEstado(int $id, int $estado): array
    {
        if ($id <= 0) {
            return $this->respuesta(false, 'VALIDACION_ERROR', 'ID de cliente inválido', null, [
                'id' => 'El cliente es obligatorio.',
            ]);
        }

        try {
            $this->clienteModel->cambiarEstado($id, $estado);
            $mensaje = $estado === 1 ? 'Cliente habilitado correctamente' : 'Cliente inhabilitado correctamente';
            return $this->respuesta(true, 'ACTUALIZADO', $mensaje, [
                'id' => $id,
                'activo' => $estado,
            ]);
        } catch (Exception $e) {
            error_log('Error cambiarEstado cliente: ' . $e->getMessage());
            return $this->respuesta(false, 'ERROR_INTERNO', 'Error al actualizar el estado del cliente');
        }
    }

    private function respuesta(bool $exito, string $codigo, string $mensaje, mixed $data = null, array $errores = []): array
    {
        return [
            'exito' => $exito,
            'codigo' => $codigo,
            'mensaje' => $mensaje,
            'data' => $data,
            'errores' => array_filter($errores, fn($error) => $error !== null),
        ];
    }

    private function respuestaApiExterna(bool $exito, string $codigo, string $mensaje, array $datos = []): array
    {
        return array_merge($datos, [
            'exito' => $exito,
            'codigo' => $codigo,
            'mensaje' => $mensaje,
            'data' => $datos,
            'errores' => [],
            'success' => $exito,
            'message' => $mensaje,
        ]);
    }
}
