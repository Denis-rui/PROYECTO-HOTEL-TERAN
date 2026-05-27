<section class="modal-devolucion" id="contenedor-modal-devolucion" style="display: none;">
  <div class="contenedor-modal" role="dialog" aria-modal="true">
    <h3 id="titulo-modal-devolucion" class="titulo-modal">Nueva Devolución</h3>

    <form id="form-devolucion" class="formulario-modal" novalidate>
      <input type="hidden" id="id-devolucion" name="id-devolucion" />

      <div class="label-input-modal">
        <label for="reserva-devolucion">N° de Reserva</label>
        <input type="number" id="reserva-devolucion" class="input-modal" placeholder="Ej: 6" required />
      </div>

      <div class="label-input-modal">
        <label for="fecha-inicio-devolucion">Fecha de Inicio (Check-in)</label>
        <input type="datetime-local" id="fecha-inicio-devolucion" class="input-modal" readonly />
      </div>

      <div class="label-input-modal">
        <label for="fecha-prevista-devolucion">Fecha Prevista de Checkout</label>
        <input type="datetime-local" id="fecha-prevista-devolucion" class="input-modal" readonly />
      </div>

      <div class="label-input-modal">
        <label for="fecha-cancelacion-devolucion">Fecha de Cancelación</label>
        <input type="datetime-local" id="fecha-cancelacion-devolucion" class="input-modal" required />
      </div>

      <div class="label-input-modal">
        <label for="dias-usados-devolucion">Días Usados</label>
        <input type="number" id="dias-usados-devolucion" class="input-modal" min="0" value="0" />
      </div>

      <div class="label-input-modal">
        <label for="dias-no-usados-devolucion">Días No Usados</label>
        <input type="number" id="dias-no-usados-devolucion" class="input-modal" min="0" value="0" />
      </div>

      <div class="label-input-modal">
        <label for="total-no-ocupado-devolucion">Total No Ocupado (S/)</label>
        <input type="number" step="0.01" id="total-no-ocupado-devolucion" class="input-modal" value="0.00" />
      </div>

      <div class="label-input-modal">
        <label for="porcentaje-penalidad-devolucion">% Penalidad</label>
        <input type="number" step="0.01" id="porcentaje-penalidad-devolucion" class="input-modal" value="0" />
      </div>

      <div class="label-input-modal">
        <label for="monto-penalidad-devolucion">Monto Penalidad (S/)</label>
        <input type="number" step="0.01" id="monto-penalidad-devolucion" class="input-modal" value="0.00" />
      </div>

      <div class="label-input-modal">
        <label for="monto-devuelto-devolucion">Monto Devuelto (S/)</label>
        <input type="number" step="0.01" id="monto-devuelto-devolucion" class="input-modal" value="0.00" required />
      </div>

      <div id="error-exito-modal-devolucion" class="div-mensaje-exito-error"></div>

      <button type="button" class="btn-cancelar btn" onclick="cerrarModalDevolucion()">
        Cancelar
      </button>
      <button type="submit" class="btn-guardar btn">
        Guardar Devolución
      </button>
    </form>
  </div>
</section>