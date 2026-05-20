<section class="devoluciones">
  <header class="header-devoluciones">
    <h2>Devoluciones</h2>
    <button id="btnNuevaDevolucion" type="button" class="boton-nueva-devolucion">
      + Nueva Devolución
    </button>
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
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody id="tabla-devoluciones-body">
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
              <td>
                <button type="button" class="btnEditarDevolucion"
                  data-id="<?= $d['id'] ?>"
                  data-reserva="<?= $d['id_reserva'] ?>"
                  data-fecha="<?= $d['fecha_cancelacion'] ?>"
                  data-dias-usados="<?= $d['dias_usados'] ?>"
                  data-dias-no-usados="<?= $d['dias_no_usados'] ?>"
                  data-total="<?= $d['total_no_ocupado'] ?>"
                  data-porcentaje="<?= $d['porcentaje_penalidad'] ?>"
                  data-penalidad="<?= $d['monto_penalidad'] ?>"
                  data-devuelto="<?= $d['monto_devuelto'] ?>">✏️</button>
                <button type="button" class="btnEliminarDevolucion" data-id="<?= $d['id'] ?>">🗑️</button>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="11" style="text-align:center">No se encontraron devoluciones.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php require_once("Views/Template/Modals/Modal-Devoluciones.php"); ?>
</section>
