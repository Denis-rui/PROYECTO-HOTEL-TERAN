<section class="usuarios">
  <header class="header-usuarios">
    <h2>Usuarios</h2>
    <button id="btnNuevoUsuario" type="button" class="boton-nuevo-usuario">
      Nuevo Usuario
    </button>
  </header>

  <div class="tabla">
    <table class="tbl-usuarios">
      <thead>
        <tr>
          <th>ID</th>
          <th>Nombre Usuario</th>
          <th>Nombre Completo</th>
          <th>Rol</th>
          <th>Estado</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody id="tabla-usuarios-body">
        <?php if (!empty($usuarios)): ?>
          <?php foreach ($usuarios as $usuario): ?>
            <tr>
              <td><?= $usuario['id'] ?></td>
              <td><?= htmlspecialchars($usuario['nombre_usuario']) ?></td>
              <td><?= htmlspecialchars($usuario['nombre_completo']) ?></td>
              <td><?= htmlspecialchars($usuario['rol']) ?></td>
              <td>
                <span class="badge <?= $usuario['estado'] == 'activo' ? 'badge-activo' : 'badge-inactivo' ?>">
                  <?= ucfirst($usuario['estado']) ?>
                </span>
              </td>
              <td>
                <button type="button" class="btnEditarUsuario" data-id="<?= $usuario['id'] ?>">✏️</button>
                <button type="button" class="btnEliminarUsuario" data-id="<?= $usuario['id'] ?>">🗑️</button>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="6" style="text-align:center">No se encontraron usuarios.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- INCLUSIÓN DEL MODAL DE USUARIOS -->
  <?php require_once("Views/Template/Modals/Modal-Usuario.php"); ?>
</section>
