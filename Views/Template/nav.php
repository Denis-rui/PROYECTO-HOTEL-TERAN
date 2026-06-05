<section class="menu">
  <aside class="sidebar">
    <canvas class="sidebar-particles" id="sidebarCanvas"></canvas>
    <div class="nav-info">
      <img
        class="imagen"
        src="<?= BASE_URL ?>public/assets/img/image.jpeg"
        alt="Logo Teran Hotel" />
      <div class="nombre-principal">Teran Hotel</div>
    </div>
    <?php
    $tienePermiso = static function (string $codigo): bool {
      $permisos = $_SESSION['permisos'] ?? [];
      return is_array($permisos) && in_array($codigo, $permisos, true);
    };
    ?>
    <nav class="nav">
      <ul>
        <?php if ($tienePermiso('dashboard.ver')): ?>
        <li class="<?= (isset($_GET['url']) && strpos($_GET['url'], 'Dashboard') !== false) ? 'activo' : '' ?>">
          <a href="<?= BASE_URL ?>Dashboard">
            <span class="nav-icon">⬡</span> Dashboard
          </a>
        </li>
        <?php endif; ?>
        <?php if ($tienePermiso('reservas.ver')): ?>
        <li class="<?= (isset($_GET['url']) && strpos($_GET['url'], 'Reserva') !== false) ? 'activo' : '' ?>">
          <a href="<?= BASE_URL ?>Reserva">
            <span class="nav-icon">◈</span> Reservas
          </a>
        </li>
        <?php endif; ?>
        <?php if ($tienePermiso('habitaciones.ver')): ?>
        <li class="<?= (isset($_GET['url']) && strpos($_GET['url'], 'Habitacion') !== false) ? 'activo' : '' ?>">
          <a href="<?= BASE_URL ?>Habitacion">
            <span class="nav-icon">⬘</span> Habitaciones
          </a>
        </li>
        <?php endif; ?>
        <?php if ($tienePermiso('clientes.ver')): ?>
        <li class="<?= (isset($_GET['url']) && strpos($_GET['url'], 'Cliente') !== false) ? 'activo' : '' ?>">
          <a href="<?= BASE_URL ?>Cliente">
            <span class="nav-icon">✦</span> Clientes
          </a>
        </li>
        <?php endif; ?>
        <?php if ($tienePermiso('devoluciones.ver')): ?>
        <li class="<?= (isset($_GET['url']) && strpos($_GET['url'], 'Devolucion') !== false) ? 'activo' : '' ?>">
          <a href="<?= BASE_URL ?>Devolucion">
            <span class="nav-icon">↩</span> Devoluciones
          </a>
        </li>
        <?php endif; ?>
        <?php if ($tienePermiso('usuarios.ver')): ?>
        <li class="<?= (isset($_GET['url']) && strpos($_GET['url'], 'Usuario') !== false) ? 'activo' : '' ?>">
          <a href="<?= BASE_URL ?>Usuario">
            <span class="nav-icon">👤</span> Usuarios
          </a>
        </li>
        <?php endif; ?>
        <li class="nav-division"></li>
        <?php if ($tienePermiso('configuracion.ver')): ?>
        <li class="<?= (isset($_GET['url']) && strpos($_GET['url'], 'Configuracion') !== false) ? 'activo' : '' ?>">
          <a href="<?= BASE_URL ?>Configuracion">
            <span class="nav-icon">⚙</span> Configuración
          </a>
        </li>
        <?php endif; ?>
        <?php if ($tienePermiso('perfil.ver')): ?>
        <li class="<?= (isset($_GET['url']) && strpos($_GET['url'], 'Perfil') !== false) ? 'activo' : '' ?>">
          <a href="<?= BASE_URL ?>Perfil">
            <span class="nav-icon">◉</span> Mi Perfil
          </a>
        </li>
        <?php endif; ?>
      </ul>
    </nav>

    <div class="sidebar-footer">
      <a href="<?= BASE_URL ?>Login/salir" class="btn-cs" style="text-decoration: none; display: block; text-align: center;">↩ Cerrar sesión</a>
    </div>
  </aside>
</section>
