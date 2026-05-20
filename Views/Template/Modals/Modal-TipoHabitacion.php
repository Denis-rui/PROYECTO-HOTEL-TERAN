<section class="modal-tipo-habitacion" id="contenedor-modal-tipo-habitacion" style="display: none;">
  <div class="contenedor-modal" role="dialog" aria-modal="true">
    <h3 id="titulo-modal-tipo" class="titulo-modal">Nuevo Tipo de Habitación</h3>

    <form id="form-tipo-habitacion" class="formulario-modal" novalidate>
      <input type="hidden" id="id-tipo" name="id" />

      <div class="label-input-modal">
        <label for="tipo-habitacion" class="tipo-habitacion">Tipo</label>
        <input
          type="text"
          id="tipo-habitacion"
          name="tipo"
          placeholder="Ej: Simple, Doble, Suite..."
          class="input-modal"
          required />
      </div>

      <div class="label-input-modal">
        <label for="precio-base" class="precio-base">Precio Base (S/)</label>
        <input
          type="number"
          id="precio-base"
          name="precio_base"
          placeholder="0.00"
          class="input-modal"
          min="0"
          step="0.01"
          required />
      </div>

      <div class="div-mensaje-exito-error" id="error-exito-modal-tipo"></div>

      <button type="button" id="btn-cancelar-tipo" class="btn-cancelar btn">
        Cancelar
      </button>

      <button class="btn-guardar btn" type="submit">Guardar Tipo</button>
    </form>
  </div>
</section>