<?php $usuarios = $data['usuarios'] ?? []; ?>
<section class="usuarios">
  <header class="header-usuarios">
    <h2>Usuarios</h2>
    <div class="filtros-cli">
      <div class="filtro-usuarios">
        <select id="filtroEstadoUsuario">
          <option value="">Todos</option>
          <option value="activo">Activos</option>
          <option value="inactivo">Inactivos</option>
        </select>
      </div>
      <button id="btnNuevoUsuario" type="button" class="boton-nuevo-usuario">
        Nuevo Usuario
      </button>

    </div>

  </header>

  <div class="tabla">
    <table class="tbl-usuarios" id="tbl-usuarios">
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
            $correo = is_array($usuario) ? ($usuario['correo'] ?? '') : ($usuario->correo ?? '');
            $telefono = is_array($usuario) ? ($usuario['telefono'] ?? '') : ($usuario->telefono ?? '');
            $dni = is_array($usuario) ? ($usuario['dni'] ?? '') : ($usuario->dni ?? '');
            $fecha_nacimiento = is_array($usuario) ? ($usuario['fecha_nacimiento'] ?? '') : ($usuario->fecha_nacimiento ?? '');
            $rol = is_array($usuario) ? ($usuario['rol'] ?? '') : ($usuario->rol ?? '');
            // Si no viene 'estado' en el resultado, asumimos activo (la consulta filtra estado=1)
            $estado = is_array($usuario) ? ($usuario['estado'] ?? 'activo') : ($usuario->estado ?? 'activo');
            ?>

            <tr data-estado="<?= $estado ?>">

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
                <button
                  type="button"
                  class="btnEditarUsuario"
                  data-id="<?= (int) $id ?>"
                  data-nombre="<?= htmlspecialchars($nombre_completo, ENT_QUOTES, 'UTF-8') ?>"
                  data-usuario="<?= htmlspecialchars($nombre_usuario, ENT_QUOTES, 'UTF-8') ?>"
                  data-correo="<?= htmlspecialchars($correo, ENT_QUOTES, 'UTF-8') ?>"
                  data-telefono="<?= htmlspecialchars($telefono, ENT_QUOTES, 'UTF-8') ?>"
                  data-dni="<?= htmlspecialchars($dni, ENT_QUOTES, 'UTF-8') ?>"
                  data-fecha-nacimiento="<?= htmlspecialchars($fecha_nacimiento, ENT_QUOTES, 'UTF-8') ?>"
                  data-usuario-rol="<?= htmlspecialchars($rol, ENT_QUOTES, 'UTF-8') ?>">✏️</button>
                <button type="button" class="btnEliminarUsuario" data-id="<?= $id ?>">🗑️</button>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="6" style="text-align:center">No se encontraron usuarios.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- INCLUSIÓN DEL MODAL DE USUARIOS -->
  <?php require_once("Views/Template/Modals/Modal-Usuario.php"); ?>
</section>

<script>
  document.addEventListener('DOMContentLoaded', () => {

    const table = new DataTable('#tbl-usuarios', {
      pageLength: 10,
      order: [
        [0, 'asc']
      ],
      layout: {
        topStart: 'search',
        topEnd: 'pageLength'
      }
    });

    document.getElementById('filtroEstadoUsuario').addEventListener('change', function() {
      const value = this.value;

      table.rows().every(function() {
        const estado = this.node().getAttribute('data-estado');

        if (value === '' || estado === value) {
          this.node().style.display = '';
        } else {
          this.node().style.display = 'none';
        }
      });
    });

  });
</script>