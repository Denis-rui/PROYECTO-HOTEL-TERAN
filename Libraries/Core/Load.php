<?php

// Cargar configuración primero
// Instanciar el controlador correcto según la URL usando PSR-4 por carpeta
$controllerClass = "Controllers\\{$controller}Controller";

if (class_exists($controllerClass)) {
    $controllerObject = new $controllerClass();
    if (method_exists($controllerObject, $method)) {
        \Libraries\Core\Auth::autorizarRuta($controller, $method);
        $controllerObject->$method($params);
    } else {
        $errorClass = 'Controllers\\ErrorController';
        if (class_exists($errorClass)) {
            $err = new $errorClass();
            $err->index();
        } else {
            echo "Error: controlador o método no encontrado.";
        }
    }
} else {
    $errorClass = 'Controllers\\ErrorController';
    if (class_exists($errorClass)) {
        $err = new $errorClass();
        $err->index();
    } else {
        echo "Error: controlador no encontrado.";
    }
}
