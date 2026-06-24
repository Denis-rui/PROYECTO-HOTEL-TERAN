<!doctype html>
<html lang="es">

<head>
    <?php $page_title = $data['page_title'] ?? 'Dashboard'; ?>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="icon" href="<?= BASE_URL ?>public/assets/img/image.jpeg" />
    <title>Hotel Teran - <?= $page_title ?></title>

    <script>
        const BASE_URL = "<?= BASE_URL ?>";
        const CSRF_TOKEN = "<?= \Libraries\Core\Csrf::generar() ?>";

        const fetchOriginal = window.fetch.bind(window);
        window.fetch = (input, init = {}) => {
            const metodo = (init.method || (input instanceof Request ? input.method : 'GET')).toUpperCase();
            const url = new URL(typeof input === 'string' ? input : input.url, window.location.href);

            if (metodo === 'GET' || metodo === 'HEAD' || url.origin !== window.location.origin) {
                return fetchOriginal(input, init);
            }

            const headers = new Headers(init.headers || (input instanceof Request ? input.headers : undefined));
            headers.set('X-CSRF-Token', CSRF_TOKEN);

            return fetchOriginal(input, {
                ...init,
                headers
            });
        };
    </script>

    <?php
    $cssVersion = static function (string $path): int {
        $fullPath = __DIR__ . '/../../' . ltrim($path, '/');
        return file_exists($fullPath) ? filemtime($fullPath) : time();
    };
    ?>
    <link rel="stylesheet" href="<?= BASE_URL ?>public/css/variables.css?v=<?= $cssVersion('public/css/variables.css') ?>" />
    <link rel="stylesheet" href="<?= BASE_URL ?>style.css?v=<?= $cssVersion('style.css') ?>" />
    <link rel="stylesheet" href="<?= BASE_URL ?>public/css/Nav.css?v=<?= $cssVersion('public/css/Nav.css') ?>" />
    <link rel="stylesheet" href="<?= BASE_URL ?>public/css/Usuarios.css?v=<?= $cssVersion('public/css/Usuarios.css') ?>" />
    <link rel="stylesheet" href="<?= BASE_URL ?>public/css/Modal-Usuarios.css?v=<?= $cssVersion('public/css/Modal-Usuarios.css') ?>" />
    <link rel="stylesheet" href="<?= BASE_URL ?>public/css/Configuraciones.css?v=<?= $cssVersion('public/css/Configuraciones.css') ?>" />
    <link rel="stylesheet" href="<?= BASE_URL ?>public/css/Dashboard.css?v=<?= $cssVersion('public/css/Dashboard.css') ?>" />
    <link rel="stylesheet" href="<?= BASE_URL ?>public/css/Modal-NuevaReserva.css?v=<?= $cssVersion('public/css/Modal-NuevaReserva.css') ?>" />
    <link rel="stylesheet" href="<?= BASE_URL ?>public/css/Reservas.css?v=<?= $cssVersion('public/css/Reservas.css') ?>" />
    <link rel="stylesheet" href="<?= BASE_URL ?>public/css/Perfil.css?v=<?= $cssVersion('public/css/Perfil.css') ?>" />
    <link rel="stylesheet" href="<?= BASE_URL ?>public/css/Habitaciones.css?v=<?= $cssVersion('public/css/Habitaciones.css') ?>" />
    <link rel="stylesheet" href="<?= BASE_URL ?>public/css/Modal-Habitaciones.css?v=<?= $cssVersion('public/css/Modal-Habitaciones.css') ?>" />
    <link rel="stylesheet" href="<?= BASE_URL ?>public/css/Clientes.css?v=<?= $cssVersion('public/css/Clientes.css') ?>" />
    <link rel="stylesheet" href="<?= BASE_URL ?>public/css/Modal-Clientes.css?v=<?= $cssVersion('public/css/Modal-Clientes.css') ?>" />
    <link rel="stylesheet" href="<?= BASE_URL ?>public/css/Pago.css?v=<?= $cssVersion('public/css/Pago.css') ?>" />
    <link rel="stylesheet" href="<?= BASE_URL ?>public/css/Modal-Comprobante.css?v=<?= $cssVersion('public/css/Modal-Comprobante.css') ?>" />
    <link rel="stylesheet" href="<?= BASE_URL ?>public/css/Modal-VerDetalles.css?v=<?= $cssVersion('public/css/Modal-VerDetalles.css') ?>" />
    <link rel="stylesheet" href="<?= BASE_URL ?>public/css/Modal-DocumentoElectronico.css?v=<?= $cssVersion('public/css/Modal-DocumentoElectronico.css') ?>" />
    <link rel="stylesheet" href="<?= BASE_URL ?>public/css/Notificaciones.css?v=<?= $cssVersion('public/css/Notificaciones.css') ?>" />
    <link rel="stylesheet" href="<?= BASE_URL ?>public/css/Devoluciones.css?v=<?= $cssVersion('public/css/Devoluciones.css') ?>" />
    <link rel="stylesheet" href="<?= BASE_URL ?>public/css/Modal-TipoHabitacion.css?v=<?= $cssVersion('public/css/Modal-TipoHabitacion.css') ?>" />
    <link rel="stylesheet" href="https://cdn.datatables.net/2.3.8/css/dataTables.dataTables.css" />
 
</head>

<body>
    <div id="nav">
        <?php require_once("Views/Template/nav.php"); ?>
    </div>
    <div id="app">
