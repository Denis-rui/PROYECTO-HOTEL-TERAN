<section class="modal-pago-section" id="contenedor-modal-pago" style="display: none;">
    <div id="modalPago" class="modal-pago">
        <div class="modal-pago-contenido">
            <!-- CERRAR -->
            <span id="cerrarModalPago" class="cerrar-modal-pago">&times;</span>

            <h2 class="titulo-modal-pago">Registrar Pago</h2>
            <div class="seccion-info-reserva">
                <h3 class="subtitulo-pago">Información de la Reserva</h3>
                <div class="info-reserva-grid">
                    <div class="info-item">
                        <label class="etiqueta-info">Cliente:</label>
                        <span id="infoPagoCliente" class="valor-info">---</span>
                    </div>
                    <div class="info-item">
                        <label class="etiqueta-info">Habitaciones:</label>
                        <span id="infoPagoHabitacion" class="valor-info">---</span>
                    </div>
                    <div class="info-item">
                        <label class="etiqueta-info">Check-in:</label>
                        <span id="infoPagoCheckin" class="valor-info">---</span>
                    </div>
                    <div class="info-item">
                        <label class="etiqueta-info">Check-out:</label>
                        <span id="infoPagoCheckout" class="valor-info">---</span>
                    </div>
                    <div class="info-item">
                        <label class="etiqueta-info">Monto Total:</label>
                        <span id="infoPagoMonto" class="valor-info monto-total">S/ ---</span>
                    </div>
                    <div class="info-item">
                        <label class="etiqueta-info">Pagado:</label>
                        <span id="infoPagoPagado" class="valor-info pagado">S/ ---</span>
                    </div>
                    <div class="info-item" id="itemPagoSugerido">
                        <label class="etiqueta-info">Monto sugerido:</label>
                        <span id="infoPagoSugerido" class="valor-info sugerido">S/ ---</span>
                        <small id="etiquetaPolitica" style="display:block; color:#666; font-size:10px;">(Cálculo según política)</small>
                    </div>
                </div>
            </div>

            <form action="#" id="formPago" method="post">
                <h3 class="subtitulo-pago">Detalles del Pago</h3>

                <!-- MONTO A PAGAR -->
                <div class="form-group">
                    <label for="montoPago">MONTO A PAGAR:</label>
                    <div class="contenedor-monto">
                        <span class="moneda">S/</span>
                        <input
                            type="number"
                            id="montoPago"
                            name="montoPago"
                            placeholder="0.00"
                            min="0.01"
                            step="0.01"
                            required />
                    </div>
                </div>

                <!-- MÉTODO DE PAGO -->
                <div class="form-group">
                    <label for="metodoPago">MÉTODO DE PAGO:</label>
                    <select id="metodoPago" name="metodoPago" required>
                        <option value="">Seleccionar método</option>
                        <option value="1">Efectivo</option>
                        <option value="2">Tarjeta</option>
                        <option value="3">Yape / Transferencia</option>
                    </select>
                </div>

                <!-- DESCRIPCIÓN/REFERENCIA -->
                <div class="form-group">
                    <label for="descripcionPago">DESCRIPCIÓN / REFERENCIA:</label>
                    <input
                        type="text"
                        id="descripcionPago"
                        name="descripcionPago"
                        placeholder="Ej: Referencia de transferencia, número de cheque, etc."
                        maxlength="255" />
                </div>

                <!-- FECHA DE PAGO -->
                <div class="form-group">
                    <label for="fechaPago">FECHA DEL PAGO:</label>
                    <input type="date" id="fechaPago" name="fechaPago" required />
                </div>

                <!-- OBSERVACIONES -->
                <div class="form-group">
                    <label for="observacionesPago">OBSERVACIONES:</label>
                    <textarea
                        id="observacionesPago"
                        name="observacionesPago"
                        placeholder="Notas adicionales sobre el pago..."
                        maxlength="500"
                        rows="3"></textarea>
                </div>


                <input type="hidden" id="pagoCliente" name="cliente" />
                <input type="hidden" id="pagoEmail" name="email" />
                <input type="hidden" id="pagoNombre" name="nombre" />
                <input type="hidden" id="pagoCheckIn" name="checkIn" />
                <input type="hidden" id="pagoHoraEntrada" name="horaEntrada" />
                <input type="hidden" id="pagoCheckOut" name="checkOut" />
                <input type="hidden" id="pagoHoraSalida" name="horaSalida" />
                <input type="hidden" id="pagoHabitacion" name="habitacion" />
                <input type="hidden" id="pagoHabitaciones" name="habitaciones" />
                <input type="hidden" id="pagoTotalReserva" name="totalReserva" />

                <!-- BOTONES -->
                <div class="form-acciones-pago">
                    <button type="button" id="btnCancelarPago" class="boton-cancelar-pago">
                        Cancelar
                    </button>
                    <button type="submit" class="boton-confirmar-pago">
                        Confirmar Pago
                    </button>
                </div>
            </form>

            <div class="seccion-historial-pagos">
                <h3 class="subtitulo-pago">Historial de Pagos</h3>
                <div id="historialPagos" class="tabla-historial">
                    <table class="tabla-pagos">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Monto</th>
                                <th>Método</th>
                                <th>Descripción</th>
                            </tr>
                        </thead>
                        <tbody id="contenidoHistorialPagos">
                            <!-- Se llena dinámicamente -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>
