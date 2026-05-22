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
            <tr>
              <td><?= $cliente['id'] ?></td>
              <td><?= htmlspecialchars($cliente['nombre_completo'] ?? '') ?></td>
              <td><?= htmlspecialchars($cliente['tipo_documento_nombre'] ?? '') ?></td>
              <td><?= htmlspecialchars($cliente['documento'] ?? '') ?></td>
              <td><?= htmlspecialchars($cliente['correo_electronico'] ?? '') ?></td>
              <td><?= htmlspecialchars($cliente['procedencia'] ?? '') ?></td>
              <td><?= htmlspecialchars($cliente['telefono'] ?? '') ?></td>
              <td><?= htmlspecialchars($cliente['reservaciones'] ?? '') ?></td>
              <td><?= htmlspecialchars($cliente['observaciones'] ?? '') ?></td>
              <td>
                <button type="button" class="btnVerPerfil" data-id="<?= $cliente['id'] ?>" title="Ver perfil">👁️</button>
                <button type="button" class="btnEditarCliente" data-id="<?= $cliente['id'] ?>" data-tipo-documento="<?= $cliente['id_tipo_documento'] ?? '' ?>" title="Editar">✏️</button>
                <button type="button" class="btnInhabilitarCliente" data-id="<?= $cliente['id'] ?>" title="Inhabilitar">🚫</button>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="10" style="text-align:center">No se encontraron clientes.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- INCLUSIÓN DEL MODAL -->
  <?php require_once("Views/Template/Modals/Modal-Clientes.php"); ?>
</section>
