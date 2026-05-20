<?php
$reservas = $data['reservas'] ?? [];

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
    <input
      class="buscar"
      id="inputBuscarReserva"
      type="text"
      placeholder="🔍 Buscar " />
    <select id="filtroEstado" class="filtro-estado">
      <option value="">Todos los estados</option>
      <option value="confirmada">Confirmada</option>
      <option value="en_estadia">En estadía</option>
      <option value="checkout_realizado">Checkout realizado</option>
    </select>
  </div>

  <div class="tabla">
    <table class="tbl-reservas">
      <thead>
        <tr>
          <th>Cliente</th>
          <th>Habitación</th>
          <th>Check-in</th>
          <th>Check-out</th>
          <th>Total</th>
          <th>Estado</th>
          <th>Pago</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody id="contenido-reservas">
        <?php foreach ($reservas as $reserva) : ?>
          <tr data-id="<?= (int) $reserva["id"] ?>" data-estado="<?= htmlspecialchars($reserva["estado"]) ?>" data-porcentajepago="<?= htmlspecialchars($reserva["porcentaje_pago"]) ?>" data-total="<?= htmlspecialchars($reserva["total"]) ?>" data-cliente="<?= htmlspecialchars($reserva["cliente"]) ?>" data-habitacion="<?= htmlspecialchars($reserva["habitacion"]) ?>" data-habitaciones='<?= htmlspecialchars(json_encode($reserva["habitaciones"] ?? [], JSON_UNESCAPED_UNICODE), ENT_QUOTES, "UTF-8") ?>' data-checkin="<?= htmlspecialchars($reserva["check_in"]) ?>" data-checkout="<?= htmlspecialchars($reserva["check_out"]) ?>" data-email="<?= htmlspecialchars($reserva["correo_electronico"] ?? '') ?>">
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
              S/ <?= htmlspecialchars($reserva["total"]) ?><br>
              <small>Saldo: S/ <?= htmlspecialchars(number_format((float) $reserva["saldo_pendiente"], 2)) ?></small>
            </td>
            <td>
              <select
                class="estado-reserva <?= claseEstadoReserva((string) $reserva["estado"]) ?>"
                data-id="<?= (int) $reserva["id"] ?>"
                data-estado="<?= htmlspecialchars($reserva["estado"]) ?>"
                title="Cambiar estado de la reserva"
              >
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
                    💳
                  </button>
                <?php endif; ?>
              </div>
            </td>
            <td>
              <button
                class="boton-editar-reserva"
                data-id="<?= $reserva["id"] ?>"
                <?= $reserva["estado"] === "checkout_realizado" ? 'disabled title="No se puede editar una reserva con checkout realizado"' : '' ?>
              >
                ✏️
              </button>
              <?php if ($reserva["estado"] === "confirmada"): ?>
                <button class="boton-checkin-reserva" data-id="<?= (int) $reserva["id"] ?>" title="Confirmar check-in">Check-in</button>
              <?php endif; ?>
              <?php if ($reserva["estado"] !== "cancelada"): ?>
                <button class="boton-cancelar-reserva" data-id="<?= (int) $reserva["id"] ?>" title="Cancelar reserva">Cancelar</button>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- INCLUSIÓN DE MODALES -->
  <?php require_once("Views/Template/Modals/Modal-NuevaReserva.php"); ?>
  <?php require_once("Views/Template/Modals/Modal-Pago.php"); ?>
  <?php require_once("Views/Template/Modals/Modal-Comprobante.php"); ?>
  <?php require_once("Views/Template/Modals/Modal-Clientes.php"); ?>
</section>
