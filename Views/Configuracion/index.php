<?php 
$hotel = $data['hotel'] ?? []; 
$tipos = $data['tipos_habitacion'] ?? [];
?>
<section class="configuracion">
  <h2>Configuración del Sistema</h2>

  <form action="<?= BASE_URL ?>?url=Configuracion/actualizar" method="post" class="form" id="formulario">
    <p>🏨 Datos del Hotel</p>
    <hr />

    <div class="form-grid">
      <div class="form-campo">
        <label for="nombre" class="form-label">NOMBRE DEL HOTEL</label>
        <input id="nombre" type="text" name="nombre" class="form-input" value="<?= htmlspecialchars($hotel['nombre'] ?? '') ?>" />
      </div>

      <div class="form-campo">
        <label for="ruc" class="form-label">RUC</label>
        <input id="ruc" type="text" name="ruc" class="form-input" value="<?= htmlspecialchars($hotel['ruc'] ?? '') ?>" />
      </div>

      <div class="form-campo">
        <label for="telefono" class="form-label">TELÉFONO</label>
        <input id="telefono" type="tel" name="telefono" class="form-input" value="<?= htmlspecialchars($hotel['telefono'] ?? '') ?>" />
      </div>

      <div class="form-campo">
        <label for="email" class="form-label">EMAIL</label>
        <input id="email" type="email" name="email" class="form-input" value="<?= htmlspecialchars($hotel['email'] ?? '') ?>" />
      </div>

      <div class="form-campo">
        <label for="monedas" class="form-label">MONEDA</label>
        <select id="monedas" name="monedas" class="form-select">
          <option value="sol" <?= ($hotel['moneda'] ?? '') == 'sol' ? 'selected' : '' ?>>S/ - Sol Peruano</option>
          <option value="dolar" <?= ($hotel['moneda'] ?? '') == 'dolar' ? 'selected' : '' ?>>$ - Dólar</option>
        </select>
      </div>

      <div class="form-campo">
        <label for="web-redes" class="form-label">WEB / REDES</label>
        <input id="web-redes" type="text" name="web-redes" class="form-input" value="<?= htmlspecialchars($hotel['web'] ?? '') ?>" />
      </div>
    </div>

    <br>
    <p>📜 Políticas de Negocio</p>
    <hr />
    <div class="form-grid">
      <div class="form-campo">
        <label for="porcentaje_adelanto" class="form-label">ADELANTO PARA RESERVA (%)</label>
        <input id="porcentaje_adelanto" type="number" name="porcentaje_adelanto" class="form-input" value="<?= $hotel['porcentaje_adelanto'] ?? 50 ?>" min="0" max="100" />
        <small>(Por defecto 50% según política)</small>
      </div>
      <div class="form-campo">
        <label for="porcentaje_penalidad" class="form-label">PENALIDAD POR CANCELACIÓN (%)</label>
        <input id="porcentaje_penalidad" type="number" name="porcentaje_penalidad" class="form-input" value="<?= $hotel['porcentaje_penalidad_cancelacion'] ?? 25 ?>" min="0" max="100" />
        <small>(Por defecto 25% según política)</small>
      </div>
    </div>

    <button type="submit" class="form-button">Guardar Cambios Generales</button>
  </form>

  <br><br>
  <p>🛏️ Tipos de Habitación y Precios Base</p>
  <hr />
  <div class="tabla">
    <table class="tbl-usuarios">
      <thead>
        <tr>
          <th>ID</th>
          <th>Tipo</th>
          <th>Precio Base (S/)</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($tipos as $tipo): ?>
          <tr>
            <td><?= $tipo['id'] ?></td>
            <td><strong><?= htmlspecialchars($tipo['tipo']) ?></strong></td>
            <td>S/ <?= number_format($tipo['precio_base'], 2) ?></td>
            <td>
               <button class="btn-editar-tipo" data-id="<?= $tipo['id'] ?>" data-tipo="<?= htmlspecialchars($tipo['tipo']) ?>" data-precio="<?= $tipo['precio_base'] ?>">✏️</button>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <button id="btnNuevoTipoHabitacion" class="form-button" style="width: auto; margin-top: 10px;">+ Agregar Nuevo Tipo</button>
</section>
