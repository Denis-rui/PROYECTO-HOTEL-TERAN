<?php

// Cargar configuración primero
require_once 'Config/Config.php';

// Instanciar el controlador correcto según la URL
$controllerFile  = "Controllers/{$controller}Controller.php";

if (file_exists($controllerFile)) {
    require_once $controllerFile;
    $controllerClass  = "{$controller}Controller";
    $controllerObject = new $controllerClass();

    if (method_exists($controllerObject, $method)) {
        $controllerObject->$method($params);
    } else {
        require_once 'Controllers/ErrorController.php';
    }
} else {
    require_once 'Controllers/ErrorController.php';
}
