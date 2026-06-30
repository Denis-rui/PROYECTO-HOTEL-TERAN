<?php
?>

<section id="contenedor-modal-documento-electronico" class="modal-documento-electronico-overlay" style="display:none;">
  <div id="modalDocumentoElectronico" class="modal-documento-electronico" role="dialog" aria-modal="true" aria-labelledby="tituloModalDocumentoElectronico">
    <header class="modal-documento-electronico-cabecera">
      <div>
        <span class="modal-documento-electronico-badge">Documento electrónico</span>
        <h2 id="tituloModalDocumentoElectronico">Emitir boleta / factura</h2>
        <p id="subtituloModalDocumentoElectronico">Selecciona el rango, cliente y habitaciones que deseas facturar o emitir en boleta.</p>
      </div>

      <button type="button" id="cerrarModalDocumentoElectronico" class="cerrar-modal-documento-electronico" aria-label="Cerrar">&times;</button>
    </header>

    <div class="modal-documento-electronico-resumen">
      <article class="resumen-documento-chip">
        <span class="resumen-documento-label">Reserva</span>
        <strong id="docElectronicoCodigoReserva">---</strong>
      </article>
      <article class="resumen-documento-chip">
        <span class="resumen-documento-label">Pago</span>
        <strong id="docElectronicoPago">---</strong>
      </article>
      <article class="resumen-documento-chip">
        <span class="resumen-documento-label">Importe a emitir</span>
        <strong id="docElectronicoTotalEmitir">S/ 0.00</strong>
      </article>
      <article class="resumen-documento-chip">
        <span class="resumen-documento-label">Estado</span>
        <strong id="docElectronicoEstado">Pendiente</strong>
      </article>
    </div>

    <form id="formDocumentoElectronico" class="formulario-documento-electronico" novalidate>
      <input type="hidden" id="docElectronicoIdReserva" name="id_reserva" />

      <section class="panel-documento panel-documento-cliente">
        <div class="panel-documento-cabecera">
          <h3>Cliente</h3>
          <p>El nombre, documento y correo pueden ajustarse antes de emitir.</p>
        </div>

        <div class="grid-documento-2">
          <div class="campo-documento campo-ancho-completo buscador-cliente-documento">
            <span>Buscar cliente registrado</span>
            <div class="buscador-cliente-documento-control">
              <input
                type="search"
                id="docElectronicoBuscarCliente"
                class="input-documento"
                placeholder="Escribe nombre, DNI o RUC"
                autocomplete="off" />
              <button
                type="button"
                id="docElectronicoLimpiarCliente"
                class="boton-limpiar-busqueda-cliente"
                aria-label="Limpiar búsqueda de cliente"
                title="Limpiar búsqueda">&times;</button>
            </div>
            <div
              id="docElectronicoResultadosCliente"
              class="resultados-cliente-documento"
              role="listbox"
              aria-label="Clientes encontrados"></div>
            <small id="docElectronicoMensajeCliente" class="mensaje-busqueda-cliente">
              Busca un cliente de la base de datos para reemplazar los datos actuales.
            </small>
          </div>

          <label class="campo-documento campo-ancho-completo">
            <span>Nombre o razón social</span>
            <input type="text" id="docElectronicoClienteNombre" name="cliente_denominacion" class="input-documento" required />
          </label>

          <label class="campo-documento">
            <span>Tipo de documento SUNAT</span>
            <select id="docElectronicoClienteTipoDocumento" name="cliente_tipo_documento" class="input-documento">
              <option value="-">Varios</option>
              <option value="1">DNI</option>
              <option value="6">RUC</option>
              <option value="4">Carnet de extranjería</option>
              <option value="7">Pasaporte</option>
              <option value="0">No domiciliado</option>
            </select>
          </label>

          <label class="campo-documento">
            <span>Número de documento</span>
            <input type="text" id="docElectronicoClienteNumero" name="cliente_numero_documento" class="input-documento" required />
          </label>

          <label class="campo-documento">
            <span>Correo electrónico</span>
            <input type="email" id="docElectronicoClienteEmail" name="cliente_email" class="input-documento" />
          </label>

          <label class="campo-documento campo-ancho-completo">
            <span>Dirección / procedencia</span>
            <input type="text" id="docElectronicoClienteDireccion" name="cliente_direccion" class="input-documento" />
          </label>
        </div>
      </section>

      <section class="panel-documento panel-documento-fechas">
        <div class="panel-documento-cabecera">
          <h3>Tipo y fechas</h3>
          <p>El rango debe estar dentro de las fechas de la reserva.</p>
        </div>

        <div class="grid-documento-2">
          <label class="campo-documento">
            <span>Tipo de documento</span>
            <select id="docElectronicoTipoDocumento" name="tipo_documento" class="input-documento">
              <option value="BOLETA">Boleta</option>
              <option value="FACTURA">Factura</option>
            </select>
          </label>

          <label class="campo-documento">
            <span>Noche(s) seleccionadas</span>
            <input type="text" id="docElectronicoNoches" class="input-documento" readonly value="0" />
          </label>

          <label class="campo-documento">
            <span>Desde</span>
            <input type="date" id="docElectronicoFechaDesde" name="fecha_desde" class="input-documento" required />
          </label>

          <label class="campo-documento">
            <span>Hasta</span>
            <input type="date" id="docElectronicoFechaHasta" name="fecha_hasta" class="input-documento" required />
          </label>
        </div>
      </section>

      <section class="panel-documento panel-documento-habitaciones">
        <div class="panel-documento-cabecera">
          <h3>Habitaciones</h3>
          <p>Puede seleccionar o deseleccionar habitaciones.</p>
        </div>

        <div id="listaHabitacionesDocumentoElectronico" class="lista-habitaciones-documento"></div>
      </section>

      <section class="panel-documento panel-documento-resumen">
        <div class="panel-documento-cabecera">
          <h3>Validación</h3>
        </div>

        <div class="resumen-documento-grid">
          <article>
            <span class="resumen-documento-label">Total reserva</span>
            <strong id="docElectronicoTotalReserva">S/ 0.00</strong>
          </article>
          <article>
            <span class="resumen-documento-label">Total pagado</span>
            <strong id="docElectronicoTotalPagado">S/ 0.00</strong>
          </article>
          <article class="resumen-documento-destacado">
            <span class="resumen-documento-label">Total boleta/factura</span>
            <strong id="docElectronicoTotalDocumentoResumen">S/ 0.00</strong>
          </article>
          <article>
            <span class="resumen-documento-label">Saldo pendiente</span>
            <strong id="docElectronicoSaldoPendiente">S/ 0.00</strong>
          </article>
          <article>
            <span class="resumen-documento-label">Documento</span>
            <strong id="docElectronicoTipoResumen">Boleta</strong>
          </article>
        </div>

        <div id="mensajeDocumentoElectronico" class="mensaje-documento-electronico" role="status"></div>
      </section>

      <section class="panel-documento panel-documento-emitidos">
        <div class="panel-documento-cabecera">
          <h3>Documentos emitidos</h3>
          <button
            type="button"
            id="btnToggleDocumentosElectronicos"
            class="btn-documentos-emitidos-toggle"
            aria-expanded="false"
            aria-controls="contenedorDocumentosElectronicosEmitidos">Ver documentos emitidos</button>
        </div>

        <div id="contenedorDocumentosElectronicosEmitidos" class="documentos-electronicos-emitidos" hidden>
          <div id="listaDocumentosElectronicosEmitidos" class="lista-documentos-electronicos-emitidos"></div>
        </div>
      </section>

      <footer class="acciones-documento-electronico">
        <button type="button" id="btnCancelarDocumentoElectronico" class="btn-documento secundario">Cancelar</button>
        <button type="submit" id="btnEmitirDocumentoElectronico" class="btn-documento principal">Emitir documento</button>
      </footer>
    </form>
  </div>
</section>