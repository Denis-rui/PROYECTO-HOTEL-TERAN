<?php


?>

<section id="contenedor-modal-reserva" style="display: none;">
  <div id="modalReserva" class="modal">
    <div class="modal-contenido">
      <!-- CERRAR -->
      <span id="cerrarModal" class="cerrarModal">&times;</span>

      <h2 class="titulo-modal">Nueva Reserva</h2>

      <!-- FORM -->
      <form id="formReserva">
        <!-- BUSCAR CLIENTE -->
        <div class="form-group">
          <label for="buscarCliente">BUSCAR / SELECCIONAR CLIENTE</label>
          <input
            type="text"
            id="buscarCliente"
            name="buscarCliente"
            placeholder="🔍 Escribe un nombre para buscar..." />
        </div>

        <div class="form-group">
          <select id="selectorClienteReserva" name="cliente" required>
            <option value="">Seleccionar cliente</option>
          </select>
          <small id="mensajeBusquedaCliente">Selecciona un cliente de la lista.</small>
        </div>

        <input type="hidden" id="idClienteReserva" name="idClienteReserva" />

        <!-- NOMBRE -->
        <div class="form-row">
          <div class="form-group">
            <div class="formRE">
              <label for="nombre">NOMBRES Y APELLIDOS:</label>
              <button
                type="button"
                class="btn-registrar"
                id="btn-registrar-cliente-manual">
                + Registrar
              </button>
            </div>
            <input
              type="text"
              id="nombre"
              name="nombre"
              placeholder="Ingrese el nombre del cliente"
              required />
          </div>

          <!-- EMAIL -->
          <div class="form-group">
            <div class="formCORREO">
              <label for="email">CORREO ELECTRÓNICO:</label>
            </div>
            <input
              type="email"
              id="email"
              name="email"
              placeholder="abejita@gmail.com"
              required />
          </div>
        </div>


        <!-- FECHAS -->
        <div class="form-row">
          <div class="form-group">
            <label for="fechaEntrada">CHECK-IN (FECHA):</label>
            <input type="date" id="fechaEntrada" name="fechaEntrada" required />
          </div>

          <div class="form-group">
            <label for="horaEntrada">HORA DE ENTRADA:</label>
            <input type="time" id="horaEntrada" name="horaEntrada" required />
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="fechaSalida">CHECK-OUT (FECHA):</label>
            <input type="date" id="fechaSalida" name="fechaSalida" required />
          </div>

          <div class="form-group">
            <label for="horaSalida">HORA DE SALIDA:</label>
            <input type="time" id="horaSalida" name="horaSalida" required />
          </div>
        </div>

        <!-- HABITACIÓN -->
        <div class="form-row">
          <div class="form-group">
            <label for="filtroTipoReserva">TIPO DE HABITACIÓN:</label>
            <select id="filtroTipoReserva" name="filtroTipoReserva">
              <option value="">Todos los tipos</option>
            </select>
          </div>
          <div class="form-group">
            <label for="filtroPisoReserva">PISO:</label>
            <select id="filtroPisoReserva" name="filtroPisoReserva">
              <option value="">Todos los pisos</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label for="seleccioneHabitacion">SELECCIONE HABITACION:</label>
          <select
            id="seleccioneHabitacion"
            name="seleccioneHabitacion"
            required>
            <option value="">Primero selecciona fechas y horas</option>
          </select>
          <small id="mensajeHabitacionesDisponibles">Solo se listan habitaciones disponibles, limpias y sin cruces de fechas.</small>
        </div>



        <!-- BOTÓN -->
        <div class="form-actions">
          <button type="button" id="btnContinuarPago" class="boton-continuar-pago">Continuar con Pago</button>
        </div>
      </form>
    </div>
  </div>
</section>
