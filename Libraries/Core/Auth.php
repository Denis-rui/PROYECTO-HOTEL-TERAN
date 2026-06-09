<?php

namespace Libraries\Core;

class Auth
{
    private const PERMISOS_POR_CONTROLADOR = [
        'Dashboard' => 'dashboard.ver',
        'Reserva' => 'reservas.ver',
        'Habitacion' => 'habitaciones.ver',
        'Cliente' => 'clientes.ver',
        'Devolucion' => 'devoluciones.ver',
        'Comprobante' => 'comprobantes.ver',
        'Usuario' => 'usuarios.ver',
        'Configuracion' => 'configuracion.ver',
        'Perfil' => 'perfil.ver',
    ];

    private const PERMISOS_POR_ACCION = [
        'Reserva' => [
            'registrar' => 'reservas.crear',
            'actualizar' => 'reservas.editar',
            'pago' => 'pagos.registrar',
            'checkin' => 'reservas.checkin',
            'checkout' => 'reservas.checkout',
            'marcarAusente' => 'reservas.ausencia',
            'marcarRegreso' => 'reservas.ausencia',
            'actualizarEstado' => 'reservas.editar',
            'calcularTotal' => 'reservas.crear',
            'extender' => 'reservas.extender',
            'consumo' => 'reservas.editar',
            'cancelar' => 'reservas.cancelar',
            'calcularCancelacion' => 'reservas.cancelar',
            'cambiarHabitacion' => 'reservas.cambiar_habitacion',
            'emitirDocumentoElectronico' => 'comprobantes.emitir',
        ],
        'Habitacion' => [
            'registrar' => 'habitaciones.crear',
            'editar' => 'habitaciones.editar',
            'eliminar' => 'habitaciones.eliminar',
            'actualizarEstado' => 'habitaciones.cambiar_estado',
            'terminarLimpieza' => 'habitaciones.finalizar_limpieza',
        ],
        'Cliente' => [
            'registrar' => 'clientes.crear',
            'actualizar' => 'clientes.editar',
            'eliminar' => 'clientes.desactivar',
            'habilitar' => 'clientes.reactivar',
        ],
        'Devolucion' => [
            'registrar' => 'devoluciones.crear',
            'actualizar' => 'devoluciones.editar',
            'eliminar' => 'devoluciones.eliminar',
        ],
        'Usuario' => [
            'crear' => 'usuarios.crear',
            'actualizarAdmin' => 'usuarios.asignar_rol',
            'eliminar' => 'usuarios.desactivar',
            'perfil' => 'perfil.ver',
            'actualizar' => 'perfil.editar',
        ],
        'Configuracion' => [
            'actualizar' => 'configuracion.editar',
            'guardarTipo' => 'configuracion.editar',
        ],
        'Perfil' => [
            'actualizarPerfil' => 'perfil.editar',
            'cambiarClave' => 'perfil.editar',
        ],
    ];


    public static function estaAutenticado(): bool
    {
        return !empty($_SESSION['usuario']) && !empty($_SESSION['id_usuario']);
    }

    public static function tienePermiso(string $codigo): bool
    {
        $permisos = $_SESSION['permisos'] ?? [];

        return is_array($permisos) && in_array($codigo, $permisos, true);
    }

    public static function permisoRequerido(string $controlador, string $metodo): ?string
    {
        return self::PERMISOS_POR_ACCION[$controlador][$metodo]
            ?? self::PERMISOS_POR_CONTROLADOR[$controlador]
            ?? null;
    }

    public static function autorizarRuta(string $controlador, string $metodo): void
    {
        if (in_array($controlador, ['Login', 'Error'], true)) {
            return;
        }

        if (!self::estaAutenticado()) {
            header('Location: ' . BASE_URL . 'Login/index');
            exit();
        }

        $permiso = self::permisoRequerido($controlador, $metodo);

        if ($permiso !== null && !self::tienePermiso($permiso)) {
            self::responderAccesoDenegado($permiso);
        }

        self::validarCsrf();
    }

    private static function responderAccesoDenegado(string $permiso): void
    {
        http_response_code(403);

        $aceptaJson = stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;
        $esAjax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
        $esEscritura = ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET';

        if ($aceptaJson || $esAjax || $esEscritura) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'exito' => false,
                'error' => 'No tiene permiso para realizar esta acción.',
                'permiso_requerido' => $permiso,
            ]);
            exit();
        }

        if ($permiso === 'dashboard.ver') {
            echo '<h1>403 - Acceso denegado</h1>';
            echo '<p>No tiene permiso para acceder a esta sección.</p>';
            exit();
        }

        header('Location: ' . BASE_URL . 'Dashboard/index&error=sin_permiso');
        exit();
    }

    public static function generarTokenCsrf(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function tokenCsrfInput(): string
    {
        $token = self::generarTokenCsrf();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    public static function validarCsrf(): void
    {
        $metodo = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'); //strtoupper para asegurar mayúsculas.
        if ($metodo !== 'POST') {
            return;
        }

        $esAjax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'; //strtolower para asegurar minúsculas.
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        $esJson = stripos($contentType, 'application/json') !== false;

        if ($esAjax || $esJson) {
            return; // Las peticiones JSON/AJAX no llevan CSRF
        }

        $tokenRecibido = trim((string) ($_POST['csrf_token'] ?? ''));
        $tokenSesion   = (string) ($_SESSION['csrf_token'] ?? '');

        if ($tokenSesion === '' || !hash_equals($tokenSesion, $tokenRecibido)) { //hash_equals compara dos cadenas de texto.
            http_response_code(419);
            header('Content-Type: text/html; charset=utf-8');
            echo '<h1>419 - Token CSRF inválido</h1>';
            echo '<p>La solicitud fue rechazada por seguridad. <a href="' . BASE_URL . '">Volver al inicio</a></p>';
            exit();
        }

        // Rotar el token después de cada uso exitoso (más seguro)
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }



    public static function xss(mixed $valor): string
    {
        return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function sanitizarEntradas(array $datos): array
    {
        $resultado = [];
        foreach ($datos as $clave => $valor) {
            if (is_array($valor)) {
                $resultado[$clave] = self::sanitizarEntradas($valor);
            } else {
                // Elimina etiquetas HTML y caracteres de control peligrosos
                $limpio = strip_tags((string) $valor);
                $limpio = trim($limpio);
                $resultado[$clave] = $limpio;
            }
        }
        return $resultado;
    }
}
