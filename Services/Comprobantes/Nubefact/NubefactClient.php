<?php

namespace Services\Comprobantes\Nubefact;

class NubefactClient
{
    public function enviarComprobante(array $payload): array
    {
        $apiUrl = defined('NUBEFACT_API_URL') ? (string) constant('NUBEFACT_API_URL') : '';
        $apiToken = defined('NUBEFACT_API_TOKEN') ? (string) constant('NUBEFACT_API_TOKEN') : '';

        if ($apiUrl === '' || $apiToken === '') {
            return [
                'exito' => false,
                'mensaje' => 'Falta configurar la ruta y el token de la cuenta emisora de NubeFact.'
            ];
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
            return ['exito' => false, 'mensaje' => 'No se pudo conectar con NubeFact: ' . $error];
        }

        $datos = json_decode($respuesta, true);

        if (!is_array($datos)) {
            return ['exito' => false, 'mensaje' => 'NubeFact devolvió una respuesta inválida.'];
        }

        if ($codigoHttp >= 400 || isset($datos['errors']) || isset($datos['codigo'])) {
            $mensaje = (string) ($datos['errors'] ?? 'NubeFact devolvió un error.');
            if (stripos($mensaje, 'serie') !== false) {
                $mensaje .= ' Verifique las constantes de serie en Config/Config.php.';
            }
            return ['exito' => false, 'mensaje' => $mensaje];
        }

        return ['exito' => true, 'respuesta' => $datos];
    }
}
