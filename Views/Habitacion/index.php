<?php $filtros = $data['filtros'] ?? []; ?>
<section class="contenedor-habitaciones">
  <div class="cabecera-habitaciones">
    <h1>Habitaciones</h1>
    <div class="cabecera-derecha">
      <button class="btn-nueva-habitacion" id="btnNuevaHabitacion">
        + Nueva Habitación
      </button>
    </div>
  </div>

  <form method="GET" class="seccion-filtros">
    <div class="caja-busqueda">
      <span>🔍</span>
      <input type="text" id="inputBuscar" name="numero_habitacion" placeholder="Buscar habitación..."
        value="<?= htmlspecialchars($_GET['numero_habitacion'] ?? '') ?>" />
    </div>

    <!-- TIPOS -->
    <select name="id_tipo_habitacion">
      <option value="">Todos los tipos</option>
      <?php if (!empty($filtros['tipos'])): ?>
        <?php foreach ($filtros['tipos'] as $tipo): ?>
          <option value="<?= $tipo['id'] ?>" <?= (($_GET['id_tipo_habitacion'] ?? '') == $tipo['id']) ? 'selected' : '' ?>>
            <?= $tipo['tipo'] ?>
          </option>
        <?php endforeach; ?>
      <?php endif; ?>
    </select>

    <!-- PISOS -->
    <select name="piso">
      <option value="">Todos los pisos</option>
      <?php foreach ($filtros['pisos'] ?? [] as $pisoItem): ?>
        <option value="<?= htmlspecialchars($pisoItem) ?>" <?= (($_GET['piso'] ?? '') == $pisoItem) ? 'selected' : '' ?>>
          Piso <?= htmlspecialchars($pisoItem) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <!-- ESTADO: opciones fijas -->
    <select name="estado" id="selectEstado">
      <?php
      $estadoSeleccionado = $_GET['estado'] ?? 'Disponible';
      $estadosFijos = ['Disponible', 'Ocupada', 'Mantenimiento', 'Reservada'];
      ?>
      <option value="" <?= ($estadoSeleccionado === '') ? 'selected' : '' ?>>Todos los estados</option>
      <?php foreach ($estadosFijos as $estadoItem): ?>
        <option value="<?= htmlspecialchars($estadoItem) ?>" <?= ($estadoSeleccionado == $estadoItem) ? 'selected' : '' ?>>
          <?= htmlspecialchars($estadoItem) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </form>

  <div class="grid-habitaciones">
    <?php
    $data['is_partial'] = true;
    require_once("Views/Habitacion/grid.php");
    ?>
  </div>

  <!-- INCLUSIÓN DEL MODAL AL FINAL DE LA SECCIÓN -->
  <?php require_once("Views/Template/Modals/Modal-Habitaciones.php"); ?>
</section>