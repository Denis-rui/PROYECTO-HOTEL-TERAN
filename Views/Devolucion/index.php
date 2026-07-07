<section class="devoluciones">
  <header class="header-devoluciones">
    <h2>Devoluciones</h2>
  </header>

  <div class="buscar-filtro devoluciones-filtros">
    <form id="formFiltrosDevolucion" action="<?= BASE_URL ?>" method="GET">
      <input type="hidden" name="url" value="Devolucion/index">
      <input id="inputBuscarDevolucion" class="buscar" name="busqueda" type="text" placeholder="🔍 Buscar por cliente o N° reserva"
        value="<?= htmlspecialchars($_GET['busqueda'] ?? '') ?>" />
      <label class="filtro-fecha-devolucion">
        Desde
        <input id="fechaDesdeDevolucion" name="fecha_desde" type="date" />
      </label>
      <label class="filtro-fecha-devolucion">
        Hasta
        <input id="fechaHastaDevolucion" name="fecha_hasta" type="date" />
      </label>
      <button id="btnAplicarFiltrosDevolucion" type="submit" class="btn-filtro-devolucion">Aplicar</button>
      <button id="btnDevolucionesHoy" type="button" class="btn-filtro-devolucion btn-filtro-secundario" title="Devoluciones realizadas hoy">
        Hoy
      </button>
      <button id="btnLimpiarFiltrosDevolucion" type="button" class="btn-filtro-devolucion btn-filtro-limpiar">
        Limpiar filtros
      </button>
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
