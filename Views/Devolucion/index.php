<section class="devoluciones">
  <header class="header-devoluciones">
    <h2>Devoluciones</h2>
  </header>

  <div class="buscar">
    <form action="<?= BASE_URL ?>" method="GET">
      <input type="hidden" name="url" value="Devolucion/index">
      <input id="inputBuscarDevolucion" name="busqueda" type="text" placeholder="🔍 Buscar por cliente o N° reserva"
        value="<?= htmlspecialchars($_GET['busqueda'] ?? '') ?>" />
    </form>
  </div>

  <div class="tabla">
    <table id="tablaDevoluciones" class="tbl-devoluciones">
      <thead>
        <tr>
          <th>ID</th>
          <th>N° Reserva</th>
          <th>Cliente</th>
          <th>Fecha Inicio</th>
          <th>Fecha Prevista Checkout</th>
          <th>Fecha Cancelación</th>
          <th>Días Usados</th>
          <th>Días No Usados</th>
          <th>Total No Ocupado</th>
          <th>% Penalidad</th>
          <th>Monto Penalidad</th>
          <th>Monto Devuelto</th>
        </tr>
      </thead>
      <tbody id="tabla-devoluciones-body"></tbody>
    </table>
  </div>
</section>
