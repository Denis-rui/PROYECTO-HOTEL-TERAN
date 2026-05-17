<section class="modal-cliente" id="contenedor-modal-cliente" style="display: none;">
  <div class="contenedor-modal" role="dialog" aria-modal="true">
    <h3 id="titulo-modal-cliente" class="titulo-modal">Nuevo Cliente</h3>

    <form id="form-nuevo-editar-cliente" class="formulario-modal" novalidate>
      <input type="hidden" id="id-cliente" name="id-cliente" />

      <div class="label-input-modal">
        <label for="nombre-cliente">Nombre</label>
        <input type="text" id="nombre-cliente" class="input-modal" required />
      </div>

      <div class="label-input-modal">
        <label for="dni-cliente">DNI / Pasaporte</label>
        <input type="text" id="dni-cliente" class="input-modal" required />
      </div>

      <div class="label-input-modal">
        <label for="gmail-cliente">Gmail</label>
        <input type="email" id="gmail-cliente" class="input-modal" required />
      </div>

      <div class="label-input-modal">
        <label for="telefono-cliente">Teléfono</label>
        <input type="tel" id="telefono-cliente" class="input-modal" required />
      </div>

      <div class="label-input-modal">
        <label for="nacionalidad-cliente">Nacionalidad</label>
        <input type="text" id="nacionalidad-cliente" class="input-modal" required />
      </div>

      <div class="label-input-modal">
        <label for="reservaciones-cliente">Reservaciones</label>
        <input type="number" id="reservaciones-cliente" class="input-modal" />
      </div>

      <div class="label-input-modal">
        <label for="metodo-pago-cliente">Método de Pago</label>
        <select id="metodo-pago-cliente" class="input-modal" required>
          <option value="">Seleccione</option>
          <option value="efectivo">Efectivo</option>
          <option value="tarjeta">Tarjeta</option>
          <option value="transferencia">Transferencia</option>
        </select>
      </div>

      <div class="label-input-modal">
        <label for="preferencias-cliente">Preferencias</label>
        <textarea id="preferencias-cliente" class="input-modal" rows="2"></textarea>
      </div>

      <div class="label-input-modal">
        <label for="observaciones-cliente">Observaciones</label>
        <textarea id="observaciones-cliente" class="input-modal" rows="2"></textarea>
      </div>

      <div id="error-exito-modal-cliente" class="div-mensaje-exito-error"></div>

      <button type="button" id="btn-cancelar-cliente" class="btn-cancelar btn" onclick="cerrarModalCliente()">
        Cancelar
      </button>

      <button type="submit" class="btn-guardar btn">
        Guardar Cliente
      </button>
    </form>
  </div>
</section>
