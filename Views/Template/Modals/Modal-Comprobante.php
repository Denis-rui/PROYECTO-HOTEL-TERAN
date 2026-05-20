<section id="contenedor-modal-comprobante" class="modal-comprobante-overlay" style="display:none;">
  <div id="modalComprobante" class="modal-comprobante">
    <div class="modal-comprobante-cabecera">
      <div>
        <span class="etiqueta-ticket">Comprobante</span>
        <h2 class="titulo-comprobante">Ticket de pago</h2>
      </div>
      <span id="cerrarModalComprobante" class="cerrar-modal-comprobante">&times;</span>
    </div>

    <div class="comprobante-resumen">
      <div class="comprobante-chip" id="comprobanteNumeroTicket">---</div>
      <div class="comprobante-chip" id="comprobanteFechaEmision">---</div>
      <div class="comprobante-chip" id="comprobanteFormaPago">---</div>
    </div>

    <div class="comprobante-bloque comprobante-bloque-denso">
      <h3>Datos del cliente</h3>
      <p><strong>Cliente:</strong> <span id="comprobanteCliente">---</span></p>
      <p><strong>Usuario:</strong> <span id="comprobanteUsuario">---</span></p>
      <p><strong>Reserva:</strong> <span id="comprobanteCodigoReserva">---</span></p>
    </div>

    <div class="comprobante-bloque comprobante-bloque-denso">
      <h3>Detalle del cobro</h3>
      <p><strong>Total de reserva:</strong> S/ <span id="comprobanteTotalReserva">0.00</span></p>
      <p><strong>Total pagado:</strong> S/ <span id="comprobanteTotal">0.00</span></p>
      <p><strong>Descripción:</strong></p>
      <div id="comprobanteDescripcion" class="comprobante-descripcion">---</div>
    </div>

    <div class="comprobante-bloque">
      <h3>Habitaciones</h3>
      <div id="comprobanteHabitaciones" class="comprobante-habitaciones"></div>
    </div>

    <div class="comprobante-acciones">
      <button type="button" id="btnImprimirComprobante" class="boton-imprimir-comprobante">Imprimir</button>
      <button type="button" id="btnCerrarComprobante" class="boton-cerrar-comprobante">Cerrar</button>
    </div>
  </div>
</section>
