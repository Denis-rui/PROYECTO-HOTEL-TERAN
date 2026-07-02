<?php

ob_start();

session_start([
    'cookie_lifetime' => 0,
    'cookie_path' => '/',
    'cookie_domain' => 'localhost',
    'cookie_secure' => false,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax'
]);

require_once 'Config/Config.php';

date_default_timezone_set(APP_TIMEZONE);

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/Config/eloquent.php';
require_once 'Libraries/Core/Router.php';

use Libraries\Core\Router;

$router = new Router();


$router->get('Login/index', ['Login', 'index']);
$router->post('Login/entrar', ['Login', 'entrar']);
$router->get('Login/salir', ['Login', 'salir']);

$router->get('Dashboard/index', ['Dashboard', 'index']);

// CLIENTES
$router->get('Cliente/index', ['Cliente', 'index']);
$router->get('Cliente/listar', ['Cliente', 'listar']);
$router->get('Cliente/buscar', ['Cliente', 'buscar']);
$router->get('Cliente/consultarApiPeru', ['Cliente', 'consultarApiPeru']);

$router->post('Cliente/registrar', ['Cliente', 'registrar']);
$router->put('Cliente/actualizar', ['Cliente', 'actualizar']);
$router->delete('Cliente/eliminar', ['Cliente', 'eliminar']);
$router->put('Cliente/habilitar', ['Cliente', 'habilitar']);


// HABITACIONES

$router->get('Habitacion/index', ['Habitacion', 'index']);
$router->get('Habitacion/buscar', ['Habitacion', 'buscar']);

$router->post('Habitacion/registrar', ['Habitacion', 'registrar']);
$router->put('Habitacion/editar', ['Habitacion', 'editar']);
$router->delete('Habitacion/eliminar', ['Habitacion', 'eliminar']);
$router->post('Habitacion/actualizarEstado', ['Habitacion', 'actualizarEstado']);
$router->post('Habitacion/terminarLimpieza', ['Habitacion', 'terminarLimpieza']);
$router->post('Habitacion/notificarLimpiezaVencida', ['Habitacion', 'notificarLimpiezaVencida']);
$router->post('Habitacion/extenderLimpieza', ['Habitacion', 'extenderLimpieza']);

$router->get('Habitacion/disponiblesPorRango', ['Habitacion', 'disponiblesPorRango']);
$router->get('Habitacion/obtenerFiltros', ['Habitacion', 'obtenerFiltros']);

// RESERVAS

// Vistas y consultas
$router->get('Reserva/index', ['Reserva', 'index']);
$router->get('Reserva/obtener', ['Reserva', 'obtener']);
$router->get('Reserva/dashboard', ['Reserva', 'dashboard']);
$router->get('Reserva/notificaciones', ['Reserva', 'notificaciones']);

// Operaciones de escritura
$router->post('Reserva/datatable', ['Reserva', 'datatable']);
$router->post('Reserva/registrar', ['Reserva', 'registrar']);
$router->put('Reserva/actualizar', ['Reserva', 'actualizar']);
$router->post('Reserva/pago', ['Reserva', 'pago']);
$router->patch('Reserva/checkin', ['Reserva', 'checkin']);
$router->patch('Reserva/checkout', ['Reserva', 'checkout']);
$router->patch('Reserva/marcarAusente', ['Reserva', 'marcarAusente']);
$router->patch('Reserva/marcarRegreso', ['Reserva', 'marcarRegreso']);
$router->post('Reserva/cancelar', ['Reserva', 'cancelar']);
$router->post('Reserva/calcularCancelacion', ['Reserva', 'calcularCancelacion']);
$router->patch('Reserva/cambiarHabitacion', ['Reserva', 'cambiarHabitacion']);
$router->post('Reserva/emitirDocumentoElectronico', ['Reserva', 'emitirDocumentoElectronico']);

// COMPROBANTES


$router->get('Comprobante/obtenerPorPago', ['Comprobante', 'obtenerPorPago']);

$router->get('Comprobante/emitidosPorReserva', ['Comprobante', 'emitidosPorReserva']);



// DEVOLUCIONES


$router->get('Devolucion/index', ['Devolucion', 'index']);

$router->post('Devolucion/registrar', ['Devolucion', 'registrar']);
$router->put('Devolucion/actualizar', ['Devolucion', 'actualizar']);
$router->delete('Devolucion/eliminar', ['Devolucion', 'eliminar']);



// USUARIOS


$router->get('Usuario/index', ['Usuario', 'index']);
$router->get('Usuario/listar', ['Usuario', 'listar']);
$router->get('Usuario/perfil', ['Usuario', 'perfil']);
$router->get('Usuario/buscar', ['Usuario', 'buscar']);

$router->post('Usuario/crear', ['Usuario', 'crear']);
$router->put('Usuario/actualizar', ['Usuario', 'actualizar']);
$router->put('Usuario/actualizarAdmin', ['Usuario', 'actualizarAdmin']);
$router->delete('Usuario/eliminar', ['Usuario', 'eliminar']);


// CONFIGURACION



$router->get('Configuracion/index', ['Configuracion', 'index']);
$router->get('Configuracion/obtener', ['Configuracion', 'obtener']);

$router->post('Configuracion/actualizar', ['Configuracion', 'actualizar']);
$router->post('Configuracion/guardarTipo', ['Configuracion', 'guardarTipo']);



// PERFIL


$router->get('Perfil/index', ['Perfil', 'index']);

$router->post('Perfil/actualizarPerfil', ['Perfil', 'actualizarPerfil']);
$router->post('Perfil/cambiarClave', ['Perfil', 'cambiarClave']);
$router->post('Reserva/calcularTotal', ['Reserva', 'calcularTotal']);
$route = $router->resolve();

$controller = $route['controller'];
$method = $route['method'];
$params = $route['params'];

require_once 'Libraries/Core/Load.php';
