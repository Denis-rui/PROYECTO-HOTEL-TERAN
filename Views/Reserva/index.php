<?php
$reservas = $data['reservas'] ?? [];
$errorReservas = $data['error_reservas'] ?? '';

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

if (!function_exists('formatearEstadoReserva')) {
  function formatearEstadoReserva(string $estado): string
  {
    $estado = strtolower(trim($estado));
    $mapa = [
      'confirmada' => 'Confirmada',
      'en_estadia' => 'En estadía',
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
      'checkout_realizado' => 'estado-checkout-realizado',
      'cancelada' => 'estado-cancelada',
    ];

    return $mapa[$estado] ?? 'estado-reserva-desconocido';
  }
}

if (!function_exists('esEstadoBloqueadoReserva')) {
  function esEstadoBloqueadoReserva(string $estadoActual, string $opcion): bool
  {
    $orden = [
      'confirmada' => 1,
      'en_estadia' => 2,
      'checkout_realizado' => 3,
    ];

    $estadoActual = strtolower(trim($estadoActual));
    $opcion = strtolower(trim($opcion));

    if (!isset($orden[$estadoActual], $orden[$opcion])) {
      return false;
    }

    return $orden[$opcion] < $orden[$estadoActual];
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
    <form action="">
      <input
        class="buscar"
        id="inputBuscarReserva"
        type="text"
        placeholder="🔍 Buscar " />
      <select id="filtroEstado" class="filtro-estado">
        <option value="">Todos los estados</option>
        <option value="confirmada">Confirmada</option>
        <option value="en_estadia">En estadía</option>
        <option value="checkout_realizado">Checkout</option>
        <option value="cancelada">Cancelada</option>

      </select>

      <button class="btn">
        Aplicar filtros
      </button>

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
          <tr data-id="<?= (int) $reserva["id"] ?>" data-estado="<?= htmlspecialchars($reserva["estado"]) ?>" data-porcentajepago="<?= htmlspecialchars($reserva["porcentaje_pago"]) ?>" data-total="<?= htmlspecialchars($reserva["total"]) ?>" data-saldo-pendiente="<?= htmlspecialchars($reserva["saldo_pendiente"] ?? 0) ?>" data-cliente="<?= htmlspecialchars($reserva["cliente"]) ?>" data-habitacion="<?= htmlspecialchars($reserva["habitacion"]) ?>" data-habitaciones='<?= htmlspecialchars(json_encode($reserva["habitaciones"] ?? [], JSON_UNESCAPED_UNICODE), ENT_QUOTES, "UTF-8") ?>' data-historial='<?= htmlspecialchars(json_encode($reserva["historial"] ?? [], JSON_UNESCAPED_UNICODE), ENT_QUOTES, "UTF-8") ?>' data-documentos='<?= htmlspecialchars(json_encode($reserva["documentos"] ?? [], JSON_UNESCAPED_UNICODE), ENT_QUOTES, "UTF-8") ?>' data-checkin="<?= htmlspecialchars($reserva["check_in"]) ?>" data-checkout="<?= htmlspecialchars($reserva["check_out"]) ?>" data-email="<?= htmlspecialchars($reserva["correo_electronico"] ?? '') ?>">
            <td><?= htmlspecialchars($reserva["cliente"]) ?></td>
            <td><?= formatearListaHabitacionesReserva($reserva) ?></td>
            <td><?= htmlspecialchars($reserva["check_in"]) ?></td>
            <td>
              <?= htmlspecialchars($reserva["check_out"]) ?>
              <?php if ((int) ($reserva["minutos_checkout_vencido"] ?? 0) > 0): ?>
                <span class="badge-vencido" data-checkout="<?= htmlspecialchars($reserva["check_out"]) ?>">Checkout vencido</span>
              <?php endif; ?>
            </td>
            <td>
              <select
                class="estado-reserva <?= claseEstadoReserva((string) $reserva["estado"]) ?>"
                data-id="<?= (int) $reserva["id"] ?>"
                data-estado="<?= htmlspecialchars($reserva["estado"]) ?>"
                title="Cambiar estado de la reserva">
                <option value="confirmada" <?= $reserva["estado"] === "confirmada" ? 'selected' : '' ?> <?= esEstadoBloqueadoReserva((string) $reserva["estado"], "confirmada") ? 'disabled' : '' ?>>Confirmada</option>
                <option value="en_estadia" <?= $reserva["estado"] === "en_estadia" ? 'selected' : '' ?> <?= esEstadoBloqueadoReserva((string) $reserva["estado"], "en_estadia") ? 'disabled' : '' ?>>En estadía</option>
                <option value="checkout_realizado" <?= $reserva["estado"] === "checkout_realizado" ? 'selected' : '' ?> <?= esEstadoBloqueadoReserva((string) $reserva["estado"], "checkout_realizado") ? 'disabled' : '' ?>>Checkout realizado</option>
              </select>
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
                <?php elseif ($reserva["estado"] === "en_estadia"): ?>
                  <button class="boton-checkout-reserva" data-id="<?= (int) $reserva["id"] ?>" title="Confirmar checkout">Checkout</button>
                <?php endif; ?>

                <div class="menu-mas-opciones-wrap">
                  <button class="boton-mas-opciones" type="button" data-id="<?= (int) $reserva["id"] ?>" title="Más acciones" aria-label="Más acciones">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-three-dots-vertical" viewBox="0 0 16 16">
                      <path d="M9.5 13a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0m0-5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0m0-5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0" />
                    </svg>
                  </button>

                  <div class="menu-mas-opciones-panel" data-id="<?= (int) $reserva["id"] ?>">
                    <?php if ($reserva["estado"] === "confirmada"): ?>
                      <button type="button" class="item-menu-opcion accion-marcar-ausente" data-id="<?= (int) $reserva["id"] ?>">Marcar ausente</button>
                    <?php else: ?>
                      <button type="button" class="item-menu-opcion accion-marcar-ocupado" data-id="<?= (int) $reserva["id"] ?>">Marcar ocupado</button>
                    <?php endif; ?>

                    <button type="button" class="item-menu-opcion accion-ver-detalles" data-id="<?= (int) $reserva["id"] ?>">Ver detalles</button>
                    <button
                      type="button"
                      class="item-menu-opcion accion-cancelar-reserva"
                      data-id="<?= (int) $reserva["id"] ?>"
                      data-codigo="R-<?= (int) $reserva["id"] ?>"
                      data-cliente="<?= htmlspecialchars($reserva["cliente"], ENT_QUOTES, 'UTF-8') ?>"
                      data-checkin="<?= htmlspecialchars($reserva["check_in"], ENT_QUOTES, 'UTF-8') ?>">
                      Cancelar reserva
                    </button>
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

  <!-- INCLUSIÓN DE MODALES -->
  <?php require_once("Views/Template/Modals/Modal-NuevaReserva.php"); ?>
  <?php require_once("Views/Template/Modals/Modal-Pago.php"); ?>
  <?php require_once("Views/Template/Modals/Modal-Comprobante.php"); ?>
  <?php require_once("Views/Template/Modals/Modal-VerDetalles.php"); ?>
  <?php require_once("Views/Template/Modals/Modal-Clientes.php"); ?>
</section>