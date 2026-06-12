<?php
ob_start();
session_start([
    'cookie_lifetime' => 0,          // Dura hasta cerrar el navegador
    'cookie_path' => '/',            // Válido para todo el sitio
    'cookie_domain' => 'localhost',   // Cambia a tu dominio real en producción
    'cookie_secure' => false,        // Ponlo en 'true' cuando tengas HTTPS (SSL)
    'cookie_httponly' => true,       // ¡AQUÍ ESTÁ LA MAGIA! Bloquea el acceso a JavaScript
    'cookie_samesite' => 'Lax'       // Protección extra contra ataques CSRF
]);
date_default_timezone_set('America/Lima');

// Parsear la URL: ?url=Controller/metodo/param
$url = $_GET['url'] ?? 'Login';

$arrUrl = explode('/', $url);

$controller = ucwords($arrUrl[0]) ?: 'Login';
$method = $arrUrl[1] ?? 'index';
$params = '';

if (!empty($arrUrl[2])) {
    for ($i = 2; $i < count($arrUrl); $i++) {
        $params .= $arrUrl[$i] . ',';
    }
    $params = trim($params, ',');
}

require_once 'Config/Config.php';
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/Config/eloquent.php';
require_once 'Libraries/Core/Load.php';
