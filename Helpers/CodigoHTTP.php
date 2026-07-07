<?php

namespace Helpers;

class CodigoHTTP
{
    private const CODIGOS_HTTP = [
        'OK' => 200,
        'CREADO' => 201,
        'ACTUALIZADO' => 200,
        'ELIMINADO' => 200,
        'NO_AUTENTICADO' => 401,
        'NO_AUTORIZADO' => 403,
        'NO_ENCONTRADO' => 404,
        'METODO_NO_PERMITIDO' => 405,
        'CONFLICTO' => 409,
        'VALIDACION_ERROR' => 422,
        'DATOS_INCOMPLETOS' => 422,
        'ERROR_GUARDADO' => 500,
        'EXCEPCION' => 500,
        'ERROR_INTERNO' => 500,
    ];

    public static function prepararRespuesta(array $resultado, int $codigoExito = 200): array
    {
        $codigoHttp = self::resultado($resultado, $codigoExito);
        unset($resultado['codigo_http']);
        return [$resultado, $codigoHttp];
    }

    public static function resultado(array $resultado, int $codigoExito = 200): int
    {
        if (isset($resultado['codigo_http'])) return (int) $resultado['codigo_http'];

        $codigo = strtoupper((string) ($resultado['codigo'] ?? ''));
        if (isset(self::CODIGOS_HTTP[$codigo])) {
            return self::CODIGOS_HTTP[$codigo];
        }

        if (($resultado['exito'] ?? true) === true) return $codigoExito;

        return 400;
    }
}
