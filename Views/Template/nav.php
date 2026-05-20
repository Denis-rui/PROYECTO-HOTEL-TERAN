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
    <nav class="nav">
      <ul>
        <li class="<?= (isset($_GET['url']) && strpos($_GET['url'], 'Dashboard') !== false) ? 'activo' : '' ?>">
          <a href="<?= BASE_URL ?>?url=Dashboard/index">
            <span class="nav-icon">⬡</span> Dashboard
          </a>
        </li>
        <li class="<?= (isset($_GET['url']) && strpos($_GET['url'], 'Reserva') !== false) ? 'activo' : '' ?>">
          <a href="<?= BASE_URL ?>?url=Reserva/index">
            <span class="nav-icon">◈</span> Reservas
          </a>
        </li>
        <li class="<?= (isset($_GET['url']) && strpos($_GET['url'], 'Habitacion') !== false) ? 'activo' : '' ?>">
          <a href="<?= BASE_URL ?>?url=Habitacion/index">
            <span class="nav-icon">⬘</span> Habitaciones
          </a>
        </li>
        <li class="<?= (isset($_GET['url']) && strpos($_GET['url'], 'Cliente') !== false) ? 'activo' : '' ?>">
          <a href="<?= BASE_URL ?>?url=Cliente/index">
            <span class="nav-icon">✦</span> Clientes
          </a>
        </li>
        <li class="<?= (isset($_GET['url']) && strpos($_GET['url'], 'Devolucion') !== false) ? 'activo' : '' ?>">
          <a href="<?= BASE_URL ?>?url=Devolucion/index">
            <span class="nav-icon">↩</span> Devoluciones
          </a>
        </li>
        <?php if ($_SESSION['rol'] == 'administrador'): ?>
        <li class="<?= (isset($_GET['url']) && strpos($_GET['url'], 'Usuario') !== false) ? 'activo' : '' ?>">
          <a href="<?= BASE_URL ?>?url=Usuario/index">
            <span class="nav-icon">👤</span> Usuarios
          </a>
        </li>
        <?php endif; ?>
        <li class="nav-division"></li>
        <li class="<?= (isset($_GET['url']) && strpos($_GET['url'], 'Configuracion') !== false) ? 'activo' : '' ?>">
          <a href="<?= BASE_URL ?>?url=Configuracion/index">
            <span class="nav-icon">⚙</span> Configuración
          </a>
        </li>
        <li class="<?= (isset($_GET['url']) && strpos($_GET['url'], 'Perfil') !== false) ? 'activo' : '' ?>">
          <a href="<?= BASE_URL ?>?url=Perfil/index">
            <span class="nav-icon">◉</span> Mi Perfil
          </a>
        </li>
      </ul>
    </nav>

    <div class="sidebar-footer">
      <a href="<?= BASE_URL ?>?url=Login/salir" class="btn-cs" style="text-decoration: none; display: block; text-align: center;">↩ Cerrar sesión</a>
    </div>
  </aside>
</section>
