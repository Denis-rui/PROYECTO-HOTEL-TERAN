<?php

use Illuminate\Database\Capsule\Manager as Capsule;

// Requiere que Config.php ya haya sido incluido antes (define DB_* constants)
// Asegúrate de incluir vendor/autoload.php y este archivo desde index.php

$capsule = new Capsule;

$capsule->addConnection([
    'driver'    => 'mysql',
    'host'      => DB_HOST,
    'port'      => DB_PORT,
    'database'  => DB_NAME,
    'username'  => DB_USER,
    'password'  => DB_PASS,
    'charset'   => DB_CHARSET,
    'collation' => (defined('DB_CHARSET') && DB_CHARSET === 'utf8mb4') ? 'utf8mb4_unicode_ci' : 'utf8_unicode_ci',
    'prefix'    => '',
]);

$capsule->setAsGlobal();
$capsule->bootEloquent();
