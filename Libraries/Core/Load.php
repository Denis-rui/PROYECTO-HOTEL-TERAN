<?php

// Cargar configuración primero
// Instanciar el controlador correcto según la URL usando PSR-4 App\Controllers
$controllerClass = "App\\Controllers\\{$controller}Controller";

if (class_exists($controllerClass)) {
    $controllerObject = new $controllerClass();
    if (method_exists($controllerObject, $method)) {
        $controllerObject->$method($params);
    } else {
        $errorClass = 'App\\Controllers\\ErrorController';
        if (class_exists($errorClass)) {
            $err = new $errorClass();
            $err->index();
        } else {
            echo "Error: controlador o método no encontrado.";
        }
    }
} else {
    $errorClass = 'App\\Controllers\\ErrorController';
    if (class_exists($errorClass)) {
        $err = new $errorClass();
        $err->index();
    } else {
        echo "Error: controlador no encontrado.";
    }
}
