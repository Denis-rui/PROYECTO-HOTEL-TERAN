<section class="modal-cliente" id="contenedor-modal-cliente" style="display: none;">
  <div class="contenedor-modal" role="dialog" aria-modal="true">
    <h3 id="titulo-modal-cliente" class="titulo-modal">Nuevo Cliente</h3>

    <form id="form-nuevo-editar-cliente" class="formulario-modal" novalidate>
      <input type="hidden" id="id-cliente" name="id-cliente" />
      <input type="hidden" id="tipo-documento-cliente" value="1" />

      <div class="label-input-modal campo-ancho-completo fila-documento">
        <label for="dni-cliente">Documento <span class="campo-requerido">*</span></label>
        <div class="documento-busqueda">
          <input type="text" id="dni-cliente" class="input-modal" required placeholder="Ingrese el número de documento" />
          <button type="button" id="btn-buscar-datos-cliente" class="btn-buscar-datos">Buscar datos</button>
        </div>
        <span class="error-validation" id="error-dni-cliente"></span>
        <small id="mensaje-busqueda-cliente" class="mensaje-busqueda-cliente">Escribe un documento y pulsa buscar para autocompletar el formulario.</small>
      </div>

      <div class="label-input-modal">
        <label for="nombre-cliente">Nombre <span class="campo-requerido">*</span></label>
        <input type="text" id="nombre-cliente" class="input-modal" required />
        <span class="error-validation" id="error-nombre-cliente"></span>
      </div>

      <div class="label-input-modal">
        <label for="gmail-cliente">Correo Electrónico</label>
        <input type="email" id="gmail-cliente" class="input-modal" />
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

      <!-- Campo Reservaciones oculto: Se calcula automáticamente según check-ins -->
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