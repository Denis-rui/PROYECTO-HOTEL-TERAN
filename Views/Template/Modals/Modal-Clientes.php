<section class="modal-cliente" id="contenedor-modal-cliente" style="display: none;">
  <div class="contenedor-modal" role="dialog" aria-modal="true">
    <h3 id="titulo-modal-cliente" class="titulo-modal">Nuevo Cliente</h3>

    <form id="form-nuevo-editar-cliente" class="formulario-modal" novalidate>
      <input type="hidden" id="id-cliente" name="id-cliente" />

      <div class="label-input-modal">
        <label for="nombre-cliente">Nombre <span class="campo-requerido">*</span></label>
        <input type="text" id="nombre-cliente" class="input-modal" required />
        <span class="error-validation" id="error-nombre-cliente"></span>
      </div>

      <div class="label-input-modal">
        <label for="tipo-documento-cliente">Tipo de Documento <span class="campo-requerido">*</span></label>
        <select id="tipo-documento-cliente" class="input-modal" required>
          <option value="">Seleccione</option>
          <option value="1">DNI</option>
          <option value="2">RUC</option>
          <option value="3">PASAPORTE</option>
        </select>
        <span class="error-validation" id="error-tipo-documento-cliente"></span>
      </div>

      <div class="label-input-modal">
        <label for="dni-cliente">Documento <span class="campo-requerido">*</span></label>
        <input type="text" id="dni-cliente" class="input-modal" required />
        <span class="error-validation" id="error-dni-cliente"></span>
      </div>

      <div class="label-input-modal">
        <label for="gmail-cliente">Correo Electrónico <span class="campo-requerido">*</span></label>
        <input type="email" id="gmail-cliente" class="input-modal" required />
        <span class="error-validation" id="error-gmail-cliente"></span>
      </div>

      <div class="label-input-modal">
        <label for="telefono-cliente">Teléfono <span class="campo-requerido">*</span></label>
        <input type="tel" id="telefono-cliente" class="input-modal" required />
        <span class="error-validation" id="error-telefono-cliente"></span>
      </div>

      <div class="label-input-modal">
        <label for="procedencia-cliente">Procedencia <span class="campo-requerido">*</span></label>
        <input type="text" id="procedencia-cliente" class="input-modal" required />
        <span class="error-validation" id="error-procedencia-cliente"></span>
      </div>

      <div class="label-input-modal">
        <label for="observaciones-cliente">Observaciones</label>
        <textarea id="observaciones-cliente" class="input-modal" rows="3" style="resize: vertical;"></textarea>
      </div>

      <!-- Campo Reservaciones oculto: Se calcula automáticamente según check-ins -->
      <input type="hidden" id="reservaciones-cliente" name="reservaciones-cliente" />

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

