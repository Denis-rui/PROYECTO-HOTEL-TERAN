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
          <div style="display:flex; gap:12px; align-items:center;">
            <div style="flex:1; min-width:0;">
              <select id="selectorClienteReserva" name="cliente" required style="width:100%;">
                <option value="">Seleccionar cliente</option>
              </select>
            </div>
            <div style="width:110px; display:flex; align-items:center; justify-content:flex-end;">
              <button
                type="button"
                class="btn-registrar btn-registrar-al-lado"
                id="btn-registrar-cliente-manual"
                title="Registrar cliente"
                style="white-space:nowrap; height:34px; padding:6px 10px; font-size:14px;">
                + Registrar
              </button>
            </div>
          </div>
          <small id="mensajeBusquedaCliente" style="display:block; margin-top:8px;">Selecciona un cliente de la lista.</small>
        </div>

        <input type="hidden" id="idClienteReserva" name="idClienteReserva" />

        <!-- NOMBRE, DNI, EMAIL, PROCEDENCIA (dos columnas) -->
        <div class="form-row form-row-cols-2" style="display:flex; gap:16px;">
          <div class="form-col" style="flex:1;">
            <div class="form-group">
              <label for="nombre">NOMBRES Y APELLIDOS:</label>
              <input
                type="text"
                id="nombre"
                name="nombre"
                placeholder="Ingrese el nombre del cliente"
                readonly />
            </div>

            <div class="form-group">
              <label for="dni" id="label-dni">DNI:</label>
              <input
                type="text"
                id="dni"
                name="dni"
                placeholder="Documento"
                readonly />
            </div>
          </div>

          <div class="form-col" style="flex:1;">
            <div class="form-group">
              <label for="email">CORREO ELECTRÓNICO:</label>
              <input
                type="email"
                id="email"
                name="email"
                placeholder="abejita@gmail.com"
                readonly />
            </div>

            <div class="form-group">
              <label for="procedencia">LUGAR DE PROCEDENCIA:</label>
              <input
                type="text"
                id="procedencia"
                name="procedencia"
                placeholder="Ciudad / Procedencia"
                readonly />
            </div>
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
            <input type="time" id="horaEntrada" name="horaEntrada" required readonly />
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="fechaSalida">CHECK-OUT (FECHA):</label>
            <input type="date" id="fechaSalida" name="fechaSalida" required />
          </div>

          <div class="form-group">
            <label for="horaSalida">HORA DE SALIDA:</label>
            <input type="time" id="horaSalida" name="horaSalida" required readonly />
          </div>
        </div>

        <!-- HABITACIONES -->
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

        <div class="form-group habitaciones-reserva-bloque">
          <div class="habitaciones-reserva-encabezado">
            <label>SELECCIONA HABITACIONES:</label>
            <small id="mensajeHabitacionesDisponibles">Solo se listan habitaciones disponibles, limpias y sin cruces de fechas.</small>
          </div>

          <div class="habitaciones-reserva-layout">
            <div class="habitaciones-panel">
              <div class="panel-titulo">
                <h3>Disponibles</h3>
                <span class="panel-subtitulo">Haz clic en agregar</span>
              </div>
              <div id="listaHabitacionesDisponibles" class="lista-habitaciones"></div>
            </div>

            <div class="habitaciones-panel panel-seleccionadas">
              <div class="panel-titulo">
                <h3>Seleccionadas</h3>
                <span id="contadorHabitacionesSeleccionadas" class="panel-subtitulo">0 habitaciones</span>
              </div>
              <div id="listaHabitacionesSeleccionadas" class="lista-habitaciones lista-habitaciones-seleccionadas"></div>
              <div class="resumen-total-habitaciones">
                <strong>Total estimado:</strong>
                <span id="totalHabitacionesReserva">S/ 0.00</span>
              </div>
            </div>
          </div>

          <input type="hidden" id="habitacionesReserva" name="habitacionesReserva" />
        </div>



        <!-- BOTÓN -->
        <div class="form-actions">
          <button type="button" id="btnContinuarPago" class="boton-continuar-pago">Continuar con Pago</button>
        </div>
      </form>
    </div>
  </div>
</section>