<?php
$filtros = $data['filtros'] ?? [];
$valorBusqueda = (string) ($filtros['busqueda'] ?? '');
$valorEstado = (string) ($filtros['estado'] ?? '');
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

      <select id="filtroHoyReserva" name="filtro_hoy" class="filtro-hoy-reserva">
        <option value="">Hoy</option>
        <option value="checkin_hoy">Check-in hoy</option>
        <option value="checkout_hoy">Checkout hoy</option>
        <option value="checkout_vencido">Checkout vencido</option>
        <option value="checkins_realizados_hoy">Check-ins realizados hoy</option>
        <option value="checkouts_realizados_hoy">Checkouts realizados hoy</option>
        <option value="pagos_realizados_hoy">Pagos realizados hoy</option>
      </select>

      <button type="button" class="btn btn-limpiar-filtros">
        Limpiar filtros
      </button>

    </form>

  </div>

  <div class="tabla">
    <table id="tablaReservas" class="tbl-reservas">
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
      </tbody>
    </table>
  </div>

  <!-- INCLUSIÓN DE MODALES -->
  <?php require_once("Views/Template/Modals/Modal-NuevaReserva.php"); ?>
  <?php require_once("Views/Template/Modals/Modal-Pago.php"); ?>
  <?php require_once("Views/Template/Modals/Modal-Comprobante.php"); ?>
  <?php require_once("Views/Template/Modals/Modal-DocumentoElectronico.php"); ?>
  <?php require_once("Views/Template/Modals/Modal-VerDetalles.php"); ?>
  <?php require_once("Views/Template/Modals/Modal-Clientes.php"); ?>
</section>
