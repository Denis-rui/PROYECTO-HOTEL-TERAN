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

    public static function prepararRespuestaReserva(array $resultado, int $codigoExito = 200): array
    {
        $codigoHttp = self::resultadoReserva($resultado, $codigoExito);
        unset($resultado['codigo_http']);
        return [$resultado, $codigoHttp];
    }
    public static function resultadoReserva(array $resultado, int $codigoExito = 200): int
    {
        if (isset($resultado['codigo_http']))   return (int) $resultado['codigo_http'];
        if (($resultado['exito'] ?? true) === true) return $codigoExito;
        $codigo = strtoupper((string) ($resultado['codigo'] ?? ''));
        if (isset(self::CODIGOS_HTTP[$codigo])) return self::CODIGOS_HTTP[$codigo];
        if ($codigo === 'NO_ENCONTRADO')  return 404;
        if ($codigo === 'CONFLICTO') return 409;
        if ($codigo === 'ERROR_INTERNO') return 500;
        $mensaje = strtolower((string) ($resultado['mensaje'] ?? ''));
        if (
            str_contains($mensaje, 'no encontrada') || str_contains($mensaje, 'no encontrado')  || str_contains($mensaje, 'no se encontro') || str_contains($mensaje, 'no se encontró') || str_contains($mensaje, 'no fue encontrada')
        ) {
            return 404;
        }
        if (
            str_contains($mensaje, 'no se pudo') || str_contains($mensaje, 'error interno') || str_contains($mensaje, 'ocurrio un error') || str_contains($mensaje, 'ocurrió un error')
        ) {
            return 500;
        }
        if (
            str_contains($mensaje, 'solo se puede') || str_contains($mensaje, 'no se puede') || str_contains($mensaje, 'ocupada') || str_contains($mensaje, 'reservada') || str_contains($mensaje, 'ya existe') || str_contains($mensaje, 'ya tiene') || str_contains($mensaje, 'ya fue') || str_contains($mensaje, 'saldo pendiente') || str_contains($mensaje, 'checkout realizado')
        ) {
            return 409;
        }
        if (
            str_contains($mensaje, 'debe') || str_contains($mensaje, 'invalido') || str_contains($mensaje, 'inválido') || str_contains($mensaje, 'valido') || str_contains($mensaje, 'válido') || str_contains($mensaje, 'monto') || str_contains($mensaje, 'total')  || str_contains($mensaje, 'fecha')  || str_contains($mensaje, 'rango') || str_contains($mensaje, 'parametros incompletos')  || str_contains($mensaje, 'parámetros incompletos') || str_contains($mensaje, 'pertenece')
        ) {
            return 422;
        }
        return 400;
    }
}
