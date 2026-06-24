<?php
$clientes = $data['clientes'] ?? [];
$obtenerCampoCliente = static function ($cliente, string $campo, $defecto = '') {
  if (is_array($cliente)) {
    return $cliente[$campo] ?? $defecto;
  }
  if (is_object($cliente)) {
    return $cliente->$campo ?? $defecto;
  }
  return $defecto;
};
?>
<section class="usuarios">
  <header class="header-usuarios">
    <h2>Clientes</h2>
    <div class="filtros-cli">
      <div class="filtro-activo">
        <select id="filtroActivo">
          <option value="">Todos</option>
          <option value="1">Activos</option>
          <option value="0">Inactivos</option>
        </select>
      </div>
      <button id="btnNuevoCliente" type="button" class="boton-nuevo-usuario">
        Nuevo Cliente
      </button>
    </div>

  </header>

  <div class="buscar" hidden>
    <form action="<?= BASE_URL ?>" method="GET">
      <input type="hidden" name="url" value="Cliente/index">
      <input
        id="inputBuscarCliente"
        name="nombre"
        type="text"
        placeholder="&#128269; Buscar cliente"
        value="<?= htmlspecialchars($_GET['nombre'] ?? '') ?>" />
    </form>
  </div>

  <div class="tabla" id="tabla-cli">
    <table class="tbl-usuarios" id="tablaClientes">
      <thead>
        <tr>
          <th>ID</th>
          <th>Nombre</th>
          <th>Tipo Documento</th>
          <th>Documento</th>
          <th>Correo Electronico</th>
          <th>Procedencia</th>
          <th>Telefono</th>
          <th>Reservaciones</th>
          <th>Observaciones</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody id="tabla-clientes-body">
        <?php if (!empty($clientes)): ?>
          <?php foreach ($clientes as $cliente): ?>
            <?php $idCliente = (int) $obtenerCampoCliente($cliente, 'id', 0); ?>
            <?php $estaActivo = (int) $obtenerCampoCliente($cliente, 'activo', 0) === 1; ?>
            <tr class="<?= $estaActivo ? 'cliente-activo' : 'cliente-inactivo' ?>" data-activo="<?= $estaActivo ? '1' : '0' ?>">
              <td><?= $idCliente ?></td>
              <td><?= htmlspecialchars((string) $obtenerCampoCliente($cliente, 'nombre_completo', '')) ?></td>
              <td><?= htmlspecialchars((string) $obtenerCampoCliente($cliente, 'tipo_documento_nombre', '')) ?></td>
              <td><?= htmlspecialchars((string) $obtenerCampoCliente($cliente, 'documento', '')) ?></td>
              <td><?= htmlspecialchars((string) $obtenerCampoCliente($cliente, 'correo_electronico', '')) ?></td>
              <td><?= htmlspecialchars((string) $obtenerCampoCliente($cliente, 'procedencia', '')) ?></td>
              <td><?= htmlspecialchars((string) $obtenerCampoCliente($cliente, 'telefono', '')) ?></td>
              <td><?= htmlspecialchars((string) $obtenerCampoCliente($cliente, 'reservaciones', '')) ?></td>
              <td><?= htmlspecialchars((string) $obtenerCampoCliente($cliente, 'observaciones', '')) ?></td>
              <td>
                <button type="button" class="btnVerPerfil" data-id="<?= $idCliente ?>" title="Ver perfil">&#128065;</button>
                <button type="button" class="btnEditarCliente" data-id="<?= $idCliente ?>" data-tipo-documento="<?= htmlspecialchars((string) $obtenerCampoCliente($cliente, 'id_tipo_documento', '')) ?>" title="Editar">&#9998;</button>
                <?php if ($estaActivo): ?>
                  <button type="button" class="btnInhabilitarCliente" data-id="<?= $idCliente ?>" title="Inhabilitar">&#128683;</button>
                <?php else: ?>
                  <button type="button" class="btnHabilitarCliente" data-id="<?= $idCliente ?>" title="Habilitar">&#9989;</button>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="10" style="text-align:center">No se encontraron clientes.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>



  <?php require_once("Views/Template/Modals/Modal-Clientes.php"); ?>
</section>

<script>
  document.addEventListener('DOMContentLoaded', () => {

    // 1. CREAR UNA SOLA INSTANCIA
    const table = new DataTable('#tablaClientes', {
      pageLength: 10,
      order: [
        [0, 'asc']
      ],
      layout: {
        topStart: 'search',
        topEnd: 'pageLength'
      }
    });

    // 2. FILTRO ACTIVO/INACTIVO
    document.getElementById('filtroActivo').addEventListener('change', function() {
      const value = this.value;

      // filtro por data-activo (RECOMENDADO)
      table.search('').draw();

      if (value === '') {
        table.rows().every(function() {
          this.node().style.display = '';
        });
        return;
      }

      table.rows().every(function() {
        const activo = this.node().getAttribute('data-activo');

        if (activo === value) {
          this.node().style.display = '';
        } else {
          this.node().style.display = 'none';
        }
      });
    });

  });
</script>