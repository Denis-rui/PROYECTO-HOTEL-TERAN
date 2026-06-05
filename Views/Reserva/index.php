<?php
$reservas = $data['reservas'] ?? [];
$errorReservas = $data['error_reservas'] ?? '';
$filtros = $data['filtros'] ?? [];
$valorBusqueda = (string) ($filtros['busqueda'] ?? '');
$valorEstado = (string) ($filtros['estado'] ?? '');
$limiteActual = max(30, (int) ($data['limite'] ?? 30));
$hayMasReservas = (bool) ($data['hay_mas'] ?? false);
$totalReservas = (int) ($data['total_reservas'] ?? count($reservas));
$mostradasReservas = (int) ($data['mostradas_reservas'] ?? count($reservas));

$paramsVerMas = [
  'url' => 'Reserva/index',
  'limite' => $limiteActual + 30,
];
if ($valorBusqueda !== '') {
  $paramsVerMas['busqueda'] = $valorBusqueda;
}
if ($valorEstado !== '') {
  $paramsVerMas['estado'] = $valorEstado;
}
$urlVerMas = BASE_URL . '?' . http_build_query($paramsVerMas);

if (!function_exists('formatearListaHabitacionesReserva')) {
  function formatearListaHabitacionesReserva(array $reserva): string
  {
    if (!empty($reserva['habitaciones']) && is_array($reserva['habitaciones'])) {
      $partes = [];

      foreach ($reserva['habitaciones'] as $habitacion) {
        if (!is_array($habitacion)) {
          continue;
        }

        $numero = $habitacion['numero_habitacion'] ?? '';
        $piso = $habitacion['piso'] ?? '';
        $tipo = $habitacion['tipo_nombre'] ?? '';

        $texto = trim('Hab. ' . $numero . ($piso !== '' ? ' - Piso ' . $piso : '') . ($tipo !== '' ? ' - ' . $tipo : ''));
        if ($texto !== '') {
          $partes[] = htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');
        }
      }

      if (!empty($partes)) {
        return implode('<br>', $partes);
      }
    }

    return htmlspecialchars((string) ($reserva['habitacion'] ?? ''), ENT_QUOTES, 'UTF-8');
  }
}

if (!function_exists('formatearFechaReserva')) {
  function formatearFechaReserva(?string $fecha): string
  {
    $fecha = trim((string) $fecha);
    if ($fecha === '') {
      return '';
    }

    try {
      $formato = strlen($fecha) > 16 ? 'Y-m-d H:i:s' : 'Y-m-d H:i';
      $dateTime = DateTime::createFromFormat($formato, $fecha) ?: new DateTime($fecha);
      return $dateTime->format('Y-m-d H:i');
    } catch (Throwable $e) {
      return $fecha;
    }
  }
}

if (!function_exists('formatearEstadoReserva')) {
  function formatearEstadoReserva(string $estado): string
  {
    $estado = strtolower(trim($estado));
    $mapa = [
      'confirmada' => 'Confirmada',
      'en_estadia' => 'En estadía',
      'ausente' => 'Ausente',
      'checkout_pendiente' => 'Checkout pendiente',
      'checkout_realizado' => 'Checkout realizado',
      'cancelada' => 'Cancelada',
    ];

    return $mapa[$estado] ?? ucfirst($estado);
  }
}

if (!function_exists('claseEstadoReserva')) {
  function claseEstadoReserva(string $estado): string
  {
    $estado = strtolower(trim($estado));
    $mapa = [
      'confirmada' => 'estado-confirmada',
      'en_estadia' => 'estado-en-estadia',
      'ausente' => 'estado-ausente',
      'checkout_pendiente' => 'estado-checkout-pendiente',
      'checkout_realizado' => 'estado-checkout-realizado',
      'cancelada' => 'estado-cancelada',
    ];

    return $mapa[$estado] ?? 'estado-reserva-desconocido';
  }
}

?>
<section class="reservas">
  <header class="header-reservas">
    <h2 class="titulo-reservas">Reservas</h2>
    <button id="btnNuevaReserva" type="button" class="boton-nueva-reserva">
      Nueva Reserva
    </button>
  </header>

  <div class="buscar-filtro">
    <form action="<?= BASE_URL ?>" method="GET">
      <input type="hidden" name="url" value="Reserva/index">
      <input type="hidden" name="limite" value="30">
      <input
        class="buscar"

        id="inputBuscarReserva"
        name="busqueda"
        type="text"
        placeholder="🔍 Buscar por nombre o DNI"
        value="<?= htmlspecialchars($valorBusqueda, ENT_QUOTES, 'UTF-8') ?>" />
      <select id="filtroEstado" name="estado" class="filtro-estado">
        <option value="">Todos los estados</option>
        <option value="ausente" <?= $valorEstado === 'ausente' ? 'selected' : '' ?>>Ausente</option>
        <option value="confirmada" <?= $valorEstado === 'confirmada' ? 'selected' : '' ?>>Confirmada</option>
        <option value="en_estadia" <?= $valorEstado === 'en_estadia' ? 'selected' : '' ?>>En estadía</option>
        <option value="checkout_pendiente" <?= $valorEstado === 'checkout_pendiente' ? 'selected' : '' ?>>Checkout pendiente</option>
        <option value="checkout_realizado" <?= $valorEstado === 'checkout_realizado' ? 'selected' : '' ?>>Checkout</option>
        <option value="cancelada" <?= $valorEstado === 'cancelada' ? 'selected' : '' ?>>Cancelada</option>

      </select>

      <button type="submit" class="btn">
        Aplicar filtros
      </button>
      <a href="<?= BASE_URL ?>?url=Reserva/index" class="btn btn-limpiar-filtros">
        Limpiar filtros
      </a>

    </form>

  </div>

  <div class="tabla">
    <table class="tbl-reservas">
      <thead>
        <tr>
          <th>Cliente</th>
          <th>Habitación</th>
          <th>Check-in</th>
          <th>Check-out</th>
          <th>Estado</th>
          <th>Pago</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody id="contenido-reservas">
        <?php foreach ($reservas as $reserva) : ?>
          <tr data-id="<?= (int) $reserva["id"] ?>" data-estado="<?= htmlspecialchars($reserva["estado"]) ?>" data-porcentajepago="<?= htmlspecialchars($reserva["porcentaje_pago"]) ?>" data-total="<?= htmlspecialchars($reserva["total"]) ?>" data-saldo-pendiente="<?= htmlspecialchars($reserva["saldo_pendiente"] ?? 0) ?>" data-cliente="<?= htmlspecialchars($reserva["cliente"]) ?>" data-cliente-documento="<?= htmlspecialchars($reserva["documento"] ?? '') ?>" data-cliente-tipo-documento="<?= htmlspecialchars((string) ($reserva["id_tipo_documento"] ?? '')) ?>" data-cliente-direccion="<?= htmlspecialchars($reserva["cliente_direccion"] ?? $reserva["procedencia"] ?? '') ?>" data-habitacion="<?= htmlspecialchars($reserva["habitacion"]) ?>" data-habitaciones='<?= htmlspecialchars(json_encode($reserva["habitaciones"] ?? [], JSON_UNESCAPED_UNICODE), ENT_QUOTES, "UTF-8") ?>' data-checkin="<?= htmlspecialchars($reserva["check_in"]) ?>" data-checkout="<?= htmlspecialchars($reserva["check_out"]) ?>" data-email="<?= htmlspecialchars($reserva["correo_electronico"] ?? '') ?>" data-total-pagado="<?= htmlspecialchars($reserva["total_pagado"] ?? 0) ?>" data-dias-estadia="<?= htmlspecialchars($reserva["dias_estadia"] ?? 0) ?>">
            <td><?= htmlspecialchars($reserva["cliente"]) ?></td>
            <td><?= formatearListaHabitacionesReserva($reserva) ?></td>
            <td><?= htmlspecialchars(formatearFechaReserva($reserva["check_in"])) ?></td>
            <td>
                <?= htmlspecialchars(formatearFechaReserva($reserva["check_out"])) ?>
              <?php if ((int) ($reserva["minutos_checkout_vencido"] ?? 0) > 0): ?>
                <span class="badge-vencido" data-checkout="<?= htmlspecialchars($reserva["check_out"]) ?>">Checkout vencido</span>
              <?php elseif (!empty($reserva["checkout_hoy"])): ?>
                <span class="badge-checkout-hoy" data-checkout="<?= htmlspecialchars($reserva["check_out"]) ?>">Checkout hoy</span>
              <?php endif; ?>
            </td>
            <td>
              <span
                class="estado-reserva <?= claseEstadoReserva((string) $reserva["estado"]) ?>"
                data-estado="<?= htmlspecialchars($reserva["estado"]) ?>">
                <?= htmlspecialchars(formatearEstadoReserva((string) $reserva["estado"])) ?>
              </span>
            </td>
            <td>
              <div class="celda-pago">
                <span class="porcentaje-pago"><?= $reserva["porcentaje_pago"] ?>%</span>
                <?php if ($reserva["porcentaje_pago"] < 100) : ?>
                  <button class="boton-pago-tabla" data-id="<?= $reserva["id"] ?>" title="Registrar pago">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-credit-card" viewBox="0 0 16 16">
                      <path d="M0 4a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2zm2-1a1 1 0 0 0-1 1v1h14V4a1 1 0 0 0-1-1zm13 4H1v5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1z" />
                      <path d="M2 10a1 1 0 0 1 1-1h1a1 1 0 0 1 1 1v1a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1z" />
                    </svg>
                  </button>
                <?php endif; ?>
              </div>
            </td>
            <td>
              <div class="acciones-reserva-wrap">
                <button
                  class="boton-editar-reserva"
                  data-id="<?= $reserva["id"] ?>"
                  <?= $reserva["estado"] === "checkout_realizado" ? 'disabled title="No se puede editar una reserva con checkout realizado"' : '' ?>>
                  ✏️
                </button>

                <?php if ($reserva["estado"] === "confirmada"): ?>
                  <button class="boton-checkin-reserva" data-id="<?= (int) $reserva["id"] ?>" title="Confirmar check-in">Check-in</button>
                <?php elseif (in_array($reserva["estado"], ["en_estadia", "checkout_pendiente"], true)): ?>
                  <button class="boton-checkout-reserva" data-id="<?= (int) $reserva["id"] ?>" title="Confirmar checkout">Checkout</button>
                <?php endif; ?>

                <div class="menu-mas-opciones-wrap">
                  <button class="boton-mas-opciones" type="button" data-id="<?= (int) $reserva["id"] ?>" title="Más acciones" aria-label="Más acciones">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-three-dots-vertical" viewBox="0 0 16 16">
                      <path d="M9.5 13a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0m0-5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0m0-5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0" />
                    </svg>
                  </button>

                  <div class="menu-mas-opciones-panel" data-id="<?= (int) $reserva["id"] ?>">
                    <?php if ($reserva["estado"] === "en_estadia"): ?>
                      <button type="button" class="item-menu-opcion accion-marcar-ausente" data-id="<?= (int) $reserva["id"] ?>">Marcar ausente</button>
                    <?php elseif ($reserva["estado"] === "ausente"): ?>
                      <button type="button" class="item-menu-opcion accion-marcar-regreso" data-id="<?= (int) $reserva["id"] ?>">Marcar regreso</button>
                    <?php endif; ?>

                    <button type="button" class="item-menu-opcion accion-emitir-documento" data-id="<?= (int) $reserva["id"] ?>">Emitir boleta / factura</button>
                    <button type="button" class="item-menu-opcion accion-ver-detalles" data-id="<?= (int) $reserva["id"] ?>">Ver detalles</button>
                    <?php if (!in_array($reserva["estado"], ["cancelada", "checkout_realizado"], true)): ?>
                      <button
                        type="button"
                        class="item-menu-opcion accion-cancelar-reserva"
                        data-id="<?= (int) $reserva["id"] ?>"
                        data-codigo="R-<?= (int) $reserva["id"] ?>"
                        data-cliente="<?= htmlspecialchars($reserva["cliente"], ENT_QUOTES, 'UTF-8') ?>"
                        data-checkin="<?= htmlspecialchars($reserva["check_in"], ENT_QUOTES, 'UTF-8') ?>">
                        Cancelar reserva
                      </button>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if ($errorReservas !== '') : ?>
          <tr>
            <td colspan="7" style="text-align:center; color:#b42318; font-weight:600; padding:14px;">
              <?= htmlspecialchars($errorReservas, ENT_QUOTES, 'UTF-8') ?>
            </td>
          </tr>
        <?php elseif (empty($reservas)) : ?>
          <tr>
            <td colspan="7" style="text-align:center; color:#667085; padding:14px;">
              No hay reservas para mostrar.
            </td>
          </tr>
        <?php endif; ?>

      </tbody>
    </table>
  </div>

  <?php if ($hayMasReservas): ?>
    <div class="contenedor-ver-mas">
      <a href="<?= htmlspecialchars($urlVerMas, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-ver-mas-reservas">
        Ver más (<?= $mostradasReservas ?> de <?= $totalReservas ?>)
      </a>
    </div>
  <?php endif; ?>

  <!-- INCLUSIÓN DE MODALES -->
  <?php require_once("Views/Template/Modals/Modal-NuevaReserva.php"); ?>
  <?php require_once("Views/Template/Modals/Modal-Pago.php"); ?>
  <?php require_once("Views/Template/Modals/Modal-Comprobante.php"); ?>
  <?php require_once("Views/Template/Modals/Modal-DocumentoElectronico.php"); ?>
  <?php require_once("Views/Template/Modals/Modal-VerDetalles.php"); ?>
  <?php require_once("Views/Template/Modals/Modal-Clientes.php"); ?>
</section>
