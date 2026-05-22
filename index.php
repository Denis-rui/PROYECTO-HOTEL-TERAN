<?php
ob_start();
session_start();

// Parsear la URL: ?url=Controller/metodo/param
$url = $_GET['url'] ?? 'Login';

$arrUrl = explode('/', $url);

$controller = ucwords($arrUrl[0]) ?: 'Login';
$method     = $arrUrl[1] ?? 'index';
$params     = '';

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
