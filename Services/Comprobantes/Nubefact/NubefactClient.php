<?php

namespace Services\Comprobantes\Nubefact;

class NubefactClient
{
    public function enviarComprobante(array $payload): array
    {
        $apiUrl = defined('NUBEFACT_API_URL') ? (string) constant('NUBEFACT_API_URL') : '';
        $apiToken = defined('NUBEFACT_API_TOKEN') ? (string) constant('NUBEFACT_API_TOKEN') : '';

        if ($apiUrl === '' || $apiToken === '') {
            return $this->respuesta(false, 'DATOS_INCOMPLETOS', 'Falta configurar la ruta y el token de la cuenta emisora de NubeFact.');
        }

        $curl = curl_init($apiUrl);

        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: ' . $apiToken,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT => 45,
        ]);

        $respuesta = curl_exec($curl);
        $error = curl_error($curl);
        $codigoHttp = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);

        if ($respuesta === false) {
            error_log('NubefactClient::enviarComprobante -> ' . $error);
            return $this->respuesta(false, 'ERROR_INTERNO', 'No se pudo conectar con el servicio de facturación. Intente nuevamente.');
        }

        $datos = json_decode($respuesta, true);

        if (!is_array($datos)) {
            return $this->respuesta(false, 'ERROR_INTERNO', 'NubeFact devolvió una respuesta inválida.');
        }

        if ($codigoHttp >= 400 || isset($datos['errors']) || isset($datos['codigo'])) {
            $mensaje = $this->extraerMensajeError($datos);
            error_log('NubefactClient::enviarComprobante -> ' . $mensaje);
            return $this->respuesta(false, 'CONFLICTO', 'NubeFact no pudo procesar el comprobante: ' . $mensaje, [
                'codigo_error' => $this->esErrorDocumentoExistente($mensaje)
                    ? 'documento_existente'
                    : 'nubefact_error',
                'respuesta' => $datos,
            ]);
        }

        return $this->respuesta(true, 'OK', 'Comprobante aceptado por NubeFact.', [
            'respuesta' => $datos,
        ]);
    }

    private function extraerMensajeError(array $datos): string
    {
        $error = $datos['errors']
            ?? $datos['error']
            ?? $datos['mensaje']
            ?? $datos['message']
            ?? $datos['codigo']
            ?? 'Error no especificado.';

        if (is_array($error)) {
            return json_encode($error, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: 'Error no especificado.';
        }

        return trim((string) $error) !== '' ? trim((string) $error) : 'Error no especificado.';
    }

    private function esErrorDocumentoExistente(string $mensaje): bool
    {
        return stripos($mensaje, 'ya existe') !== false
            && stripos($mensaje, 'documento') !== false;
    }

    private function respuesta(bool $exito, string $codigo, string $mensaje, mixed $data = null, array $errores = []): array
    {
        $respuesta = [
            'exito' => $exito,
            'codigo' => $codigo,
            'mensaje' => $mensaje,
            'data' => $data,
            'errores' => $errores,
        ];

        return is_array($data) ? array_merge($respuesta, $data) : $respuesta;
    }
}
