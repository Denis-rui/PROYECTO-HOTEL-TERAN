<?php
namespace Libraries\Core;

class Csrf {
    private const SESSION_KEY = 'csrf_token';
    private const TOKEN_LENGTH = 32;

    public static function generar(): string
    {
        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(self::TOKEN_LENGTH));
        }
        return $_SESSION[self::SESSION_KEY];
    }

    public static function campo(string $nombre = 'csrf_token'): string
    {
        return sprintf(
            '<input type="hidden" name="%s" value="%s" />',
            htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars(self::generar(), ENT_QUOTES, 'UTF-8')
        );
    }

    public static function validar(string $nombreParametro = 'csrf_token'): bool
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return true;
        }

        $tokenSesion = $_SESSION[self::SESSION_KEY] ?? '';
        $tokenRecibido = trim(
            $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? $_POST[$nombreParametro]
            ?? ''
        );

        if (empty($tokenSesion) || !hash_equals($tokenSesion, $tokenRecibido)) {
            throw new \RuntimeException('Token CSRF inválido');
        }

        return true;
    }
}
