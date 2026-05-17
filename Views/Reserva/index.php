<?php $reservas = $data['reservas'] ?? []; ?>
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
      <option value="pendiente">Pendiente</option>
      <option value="en_estadia">En estadía</option>
      <option value="checkout_pendiente">Checkout pendiente</option>
      <option value="checkout_realizado">Checkout realizado</option>
      <option value="cancelada">Cancelada</option>
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
          <tr data-id="<?= (int) $reserva["id"] ?>" data-estado="<?= htmlspecialchars($reserva["estado"]) ?>" data-porcentajepago="<?= htmlspecialchars($reserva["porcentaje_pago"]) ?>" data-total="<?= htmlspecialchars($reserva["total"]) ?>" data-cliente="<?= htmlspecialchars($reserva["cliente"]) ?>" data-habitacion="<?= htmlspecialchars($reserva["habitacion"]) ?>" data-checkin="<?= htmlspecialchars($reserva["check_in"]) ?>" data-checkout="<?= htmlspecialchars($reserva["check_out"]) ?>" data-email="<?= htmlspecialchars($reserva["correo_electronico"] ?? '') ?>">
            <td><?= htmlspecialchars($reserva["cliente"]) ?></td>
            <td><?= htmlspecialchars($reserva["habitacion"]) ?></td>
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
            <td><?= htmlspecialchars($reserva["estado"]) ?></td>
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
              <button class="boton-editar-reserva" data-id="<?= $reserva["id"] ?>">
                ✏️
              </button>
              <?php if ($reserva["estado"] === "confirmada"): ?>
                <button class="boton-checkin-reserva" data-id="<?= (int) $reserva["id"] ?>" title="Confirmar check-in">Check-in</button>
              <?php endif; ?>
              <?php if (in_array($reserva["estado"], ["en_estadia", "checkout_pendiente"], true)): ?>
                <button class="boton-checkout-reserva" data-id="<?= (int) $reserva["id"] ?>" title="Confirmar checkout">Checkout</button>
                <button class="boton-extender-reserva" data-id="<?= (int) $reserva["id"] ?>" title="Extender estadía">Extender</button>
                <button class="boton-consumo-reserva" data-id="<?= (int) $reserva["id"] ?>" title="Registrar consumo">Consumo</button>
                <button class="boton-cambio-habitacion" data-id="<?= (int) $reserva["id"] ?>" title="Cambiar habitación">Cambiar hab.</button>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- INCLUSIÓN DE MODALES -->
  <?php require_once("Views/Template/Modals/Modal-Dashboard.php"); ?>
  <?php require_once("Views/Template/Modals/Modal-Pago.php"); ?>
</section>
