<?php $clientes = $data['clientes'] ?? []; ?>
<section class="usuarios">
  <header class="header-usuarios">
    <h2>Clientes</h2>
    <button id="btnNuevoCliente" type="button" class="boton-nuevo-usuario">
      Nuevo Cliente
    </button>
  </header>

  <div class="buscar">
    <form action="<?= BASE_URL ?>" method="GET">
      <input type="hidden" name="url" value="Cliente/index">
      <input
        id="inputBuscarCliente"
        name="nombre"
        type="text"
        placeholder="🔍 Buscar cliente"
        value="<?= htmlspecialchars($_GET['nombre'] ?? '') ?>"
      />
    </form>
  </div>

  <div class="tabla">
    <table class="tbl-usuarios">
      <thead>
        <tr>
          <th>ID</th>
          <th>Nombre</th>
          <th>Tipo Documento</th>
          <th>Documento</th>
          <th>Correo</th>
          <th>Telefono</th>
          <th>Procedencia</th>
          <th>Reservaciones</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody id="tabla-clientes-body">
        <?php if (!empty($clientes)): ?>
          <?php foreach ($clientes as $cliente): ?>
            <tr>
              <td><?= $cliente['id'] ?></td>
              <td><?= htmlspecialchars($cliente['nombre_completo'] ?? '') ?></td>
              <td><?= htmlspecialchars($cliente['id_tipo_documento'] ?? '') ?></td>
              <td><?= htmlspecialchars($cliente['documento'] ?? '') ?></td>
              <td><?= htmlspecialchars($cliente['correo_electronico'] ?? '') ?></td>
              <td><?= htmlspecialchars($cliente['telefono'] ?? '') ?></td>
              <td><?= htmlspecialchars($cliente['procedencia'] ?? '') ?></td>
              <td><?= $cliente['reservaciones'] ?? 0 ?></td>
              <td>
                <button type="button" class="btnEditarCliente" data-id="<?= $cliente['id'] ?>">✏️</button>
                <button type="button" class="btnEliminarCliente" data-id="<?= $cliente['id'] ?>">🗑️</button>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="9" style="text-align:center">No se encontraron clientes.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- INCLUSIÓN DEL MODAL -->
  <?php require_once("Views/Template/Modals/Modal-Clientes.php"); ?>
</section>
