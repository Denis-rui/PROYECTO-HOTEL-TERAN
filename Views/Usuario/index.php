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
            <?php
              // Soporta objetos (Eloquent/stdClass) o arrays
              $id = is_array($usuario) ? ($usuario['id'] ?? '') : ($usuario->id ?? '');
              $nombre_usuario = is_array($usuario) ? ($usuario['nombre_usuario'] ?? '') : ($usuario->nombre_usuario ?? '');
              $nombre_completo = is_array($usuario) ? ($usuario['nombre_completo'] ?? '') : ($usuario->nombre_completo ?? '');
              $rol = is_array($usuario) ? ($usuario['rol'] ?? '') : ($usuario->rol ?? '');
              // Si no viene 'estado' en el resultado, asumimos activo (la consulta filtra estado=1)
              $estado = is_array($usuario) ? ($usuario['estado'] ?? 'activo') : ($usuario->estado ?? 'activo');
            ?>
            <tr>
              <td><?= $id ?></td>
              <td><?= htmlspecialchars($nombre_usuario) ?></td>
              <td><?= htmlspecialchars($nombre_completo) ?></td>
              <td><?= htmlspecialchars($rol) ?></td>
              <td>
                <span class="badge <?= $estado == 'activo' ? 'badge-activo' : 'badge-inactivo' ?>">
                  <?= ucfirst($estado) ?>
                </span>
              </td>
              <td>
                <button type="button" class="btnEditarUsuario" data-id="<?= $id ?>">✏️</button>
                <button type="button" class="btnEliminarUsuario" data-id="<?= $id ?>">🗑️</button>
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
