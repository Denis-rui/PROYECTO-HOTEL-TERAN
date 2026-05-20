<?php $devoluciones = $data['devoluciones'] ?? []; ?>
<section class="devoluciones">
  <header class="header-devoluciones">
    <h2>Devoluciones</h2>
  </header>

  <div class="buscar">
    <form action="<?= BASE_URL ?>" method="GET">
      <input type="hidden" name="url" value="Devolucion/index">
      <input
        id="inputBuscarDevolucion"
        name="busqueda"
        type="text"
        placeholder="🔍 Buscar por cliente o N° reserva"
        value="<?= htmlspecialchars($_GET['busqueda'] ?? '') ?>"
      />
    </form>
  </div>

  <div class="tabla">
    <table class="tbl-devoluciones">
      <thead>
        <tr>
          <th>ID</th>
          <th>N° Reserva</th>
          <th>Cliente</th>
          <th>Fecha Cancelación</th>
          <th>Días Usados</th>
          <th>Días No Usados</th>
          <th>Total No Ocupado</th>
          <th>% Penalidad</th>
          <th>Monto Penalidad</th>
          <th>Monto Devuelto</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($devoluciones)): ?>
          <?php foreach ($devoluciones as $d): ?>
            <tr>
              <td><?= $d['id'] ?></td>
              <td>#<?= $d['id_reserva'] ?></td>
              <td><?= htmlspecialchars($d['cliente'] ?? '—') ?></td>
              <td><?= $d['fecha_cancelacion'] ?></td>
              <td><?= $d['dias_usados'] ?></td>
              <td><?= $d['dias_no_usados'] ?></td>
              <td>S/ <?= number_format($d['total_no_ocupado'], 2) ?></td>
              <td><?= $d['porcentaje_penalidad'] ?>%</td>
              <td>S/ <?= number_format($d['monto_penalidad'], 2) ?></td>
              <td>S/ <?= number_format($d['monto_devuelto'], 2) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="10" style="text-align:center">No se encontraron devoluciones.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
