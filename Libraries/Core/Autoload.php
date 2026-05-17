<?php

spl_autoload_register(function ($className) {
    $directorios = [
        'Libraries/Core/',
        'Controllers/',
        'Models/',
    ];

    foreach ($directorios as $dir) {
        $filePath = $dir . $className . '.php';
        if (file_exists($filePath)) {
            require_once $filePath;
            return;
        }
    }
});
