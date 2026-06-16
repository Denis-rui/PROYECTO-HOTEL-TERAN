<?php

$env = parse_ini_file(__DIR__ . '/../.env');

define('BASE_URL', $env['BASE_URL']);
define('DB_HOST', $env['DB_HOST']);
define('DB_PORT', $env['DB_PORT']);
define('DB_NAME', $env['DB_NAME']);
define('DB_USER', $env['DB_USER']);
define('DB_PASS', $env['DB_PASS']);
define('DB_CHARSET', $env['DB_CHARSET']);

define('APP_TIMEZONE', $env['APP_TIMEZONE']);

define('NUBEFACT_SERIE_BOLETA', $env['NUBEFACT_SERIE_BOLETA']);
define('NUBEFACT_SERIE_FACTURA', $env['NUBEFACT_SERIE_FACTURA']);
define('NUBEFACT_API_URL', $env['NUBEFACT_API_URL']);
define('NUBEFACT_API_TOKEN', $env['NUBEFACT_API_TOKEN']);
define('HOTEL_TITULAR', $env['HOTEL_TITULAR']);
