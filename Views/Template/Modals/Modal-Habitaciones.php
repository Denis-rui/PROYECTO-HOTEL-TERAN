<section>
  <div id="modalHabitacion" class="modal-habitacion" style="display: none;">
    <div class="modal-contenido-habitacion">
      <button id="cerrarModalHabitacion" class="boton-cerrar-modal">
        &times;
      </button>

      <h2 id="tituloModalHabitacion" class="titulo-modal-habitacion">Nueva Habitación</h2>

      <form id="formNuevaHabitacion">
        <!-- Campo oculto para modo edición -->
        <input type="hidden" id="habitacionId" name="id" value="">

        <div class="fila-formulario">
          <div class="grupo-formulario">
            <label for="numeroHabitacion">NÚMERO</label>
            <input
              type="text"
              id="numeroHabitacion"
              name="numero_habitacion"
              placeholder="Ej: 101"
              required />
          </div>
          <div class="grupo-formulario">
            <label for="tipoHabitacion">TIPO</label>
            <select id="tipoHabitacion" name="id_tipo_habitacion" required>
              <option value="" disabled selected>Seleccione un tipo</option>
              <?php if (!empty($filtros['tipos'])): ?>
                <?php foreach ($filtros['tipos'] as $tipo): ?>
                  <option value="<?= htmlspecialchars($tipo['id']) ?>">
                    <?= htmlspecialchars($tipo['tipo']) ?>
                  </option>
                <?php endforeach; ?>
              <?php endif; ?>
            </select>
          </div>
        </div>

        <div class="fila-formulario">
          <div class="grupo-formulario">
            <label for="pisoHabitacion">PISO</label>
            <input
              type="number"
              id="pisoHabitacion"
              name="piso"
              min="1"
              value="1"
              required />
          </div>
          <div class="grupo-formulario">
            <label for="capacidadHabitacion">CAPACIDAD</label>
            <input
              type="number"
              id="capacidadHabitacion"
              name="capacidad"
              min="1"
              value="1"
              required />
          </div>
        </div>

        <div class="fila-formulario">
          <div class="grupo-formulario">
            <label for="estadoHabitacion">ESTADO</label>
            <select id="estadoHabitacion" name="estado" required>
              <option value="Disponible" selected>Disponible</option>
              <option value="Ocupada">Ocupada</option>
              <option value="Mantenimiento">Mantenimiento</option>
              <option value="Reservada">Reservada</option>
            </select>
          </div>
        </div>

        <div class="grupo-formulario">
          <label for="descripcionHabitacion">DESCRIPCIÓN</label>
          <textarea
            id="descripcionHabitacion"
            name="descripcion_habitacion"
            placeholder="Bungalow con vista al río, hamaca, etc."></textarea>
        </div>

        <div class="acciones-formulario">
          <button type="button" id="btnCancelarHabitacion" class="btn-secundario">
            Cancelar
          </button>
          <button type="submit" id="btnSubmitHabitacion" class="btn-primario">
            Guardar
          </button>
        </div>
      </form>
    </div>
  </div>
</section>