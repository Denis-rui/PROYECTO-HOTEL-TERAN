<section class="modal-cliente" id="contenedor-modal-cliente" style="display: none;">
  <div class="contenedor-modal" role="dialog" aria-modal="true">
    <h3 id="titulo-modal-cliente" class="titulo-modal">Nuevo Cliente</h3>

    <form id="form-nuevo-editar-cliente" class="formulario-modal" novalidate>
      <input type="hidden" id="id-cliente" name="id-cliente" />

      <div class="label-input-modal campo-ancho-completo fila-documento">
        <label for="dni-cliente">Buscar por Documento</label>
        <div class="documento-busqueda">
          <input type="text" id="dni-cliente" class="input-modal" placeholder="Escriba DNI o CE para buscar..." />
          <button type="button" id="btn-buscar-datos-cliente" class="btn-buscar-datos">Buscar datos</button>
        </div>
        <span class="error-validation" id="error-dni-cliente"></span>
        <small id="mensaje-busqueda-cliente" class="mensaje-busqueda-cliente">Escribe un documento y pulsa buscar para autocompletar el formulario.</small>
      </div>

      <div class="label-input-modal">
        <label for="tipo-documento-cliente">Tipo de Documento <span class="campo-requerido">*</span></label>
        <select id="tipo-documento-cliente" class="input-modal" required>
          <option value="1">DNI</option>
          <option value="4">Carnet de Extranjería</option>
        </select>
        <span class="error-validation" id="error-tipo-documento-cliente"></span>
      </div>

      <div class="label-input-modal">
        <label for="documento-cliente">Número de Documento <span class="campo-requerido">*</span></label>
        <input type="text" id="documento-cliente" class="input-modal" required placeholder="Número de documento final" />
        <span class="error-validation" id="error-documento-cliente"></span>
      </div>

      <div class="label-input-modal">
        <label for="ruc-cliente">RUC</label>
        <input type="text" id="ruc-cliente" class="input-modal" maxlength="11" placeholder="Ingrese el RUC si aplica" />
        <span class="error-validation" id="error-ruc-cliente"></span>
      </div>

      <div class="label-input-modal">
        <label for="nombres-cliente">Nombres <span class="campo-requerido">*</span></label>
        <input type="text" id="nombres-cliente" class="input-modal" required />
        <span class="error-validation" id="error-nombres-cliente"></span>
      </div>

      <div class="label-input-modal">
        <label for="apellido-paterno-cliente">Apellido Paterno <span class="campo-requerido">*</span></label>
        <input type="text" id="apellido-paterno-cliente" class="input-modal" required />
        <span class="error-validation" id="error-apellido-paterno-cliente"></span>
      </div>

      <div class="label-input-modal">
        <label for="apellido-materno-cliente">Apellido Materno <span class="campo-requerido">*</span></label>
        <input type="text" id="apellido-materno-cliente" class="input-modal" required />
        <span class="error-validation" id="error-apellido-materno-cliente"></span>
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

      <div class="label-input-modal campo-ancho-completo">
        <label for="observaciones-cliente">Observaciones</label>
        <textarea id="observaciones-cliente" class="input-modal" rows="3" style="resize: vertical;"></textarea>
      </div>

      <input type="hidden" id="reservaciones-cliente" name="reservaciones-cliente" />

      <div id="error-exito-modal-cliente" class="div-mensaje-exito-error campo-ancho-completo"></div>

      <div class="acciones-modal-cliente campo-ancho-completo">
        <button type="button" id="btn-cancelar-cliente" class="btn-cancelar btn" onclick="cerrarModalCliente()">
          Cancelar
        </button>

        <button type="submit" class="btn-guardar btn">
          Guardar Cliente
        </button>
      </div>
    </form>
  </div>
</section>