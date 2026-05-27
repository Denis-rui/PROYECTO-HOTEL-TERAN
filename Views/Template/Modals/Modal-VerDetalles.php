<?php
?>

<section id="contenedor-modal-ver-detalles" class="modal-ver-detalles-overlay" style="display:none;">
    <div id="modalVerDetalles" class="modal-ver-detalles" role="dialog" aria-modal="true" aria-labelledby="tituloModalVerDetalles">
        <header class="modal-ver-detalles-cabecera">
            <div class="modal-ver-detalles-encabezado-texto">
                <span class="modal-ver-detalles-badge">Detalle de reserva</span>
                <h2 id="tituloModalVerDetalles">Reserva #<span id="detalleReservaCodigo">---</span></h2>
                <p id="subtituloModalVerDetalles">Información general, historial y documentos asociados.</p>
            </div>

            <button type="button" id="cerrarModalVerDetalles" class="cerrar-modal-ver-detalles" aria-label="Cerrar detalles de reserva">&times;</button>
        </header>

        <div class="modal-ver-detalles-resumen">
            <article class="resumen-chip">
                <span class="resumen-chip-etiqueta">Cliente</span>
                <strong id="detalleReservaCliente">---</strong>
            </article>
            <article class="resumen-chip">
                <span class="resumen-chip-etiqueta">Estado</span>
                <strong id="detalleReservaEstado">---</strong>
            </article>
            <article class="resumen-chip">
                <span class="resumen-chip-etiqueta">Pago</span>
                <strong id="detalleReservaPago">---</strong>
            </article>
            <article class="resumen-chip">
                <span class="resumen-chip-etiqueta">Saldo</span>
                <strong id="detalleReservaSaldo">---</strong>
            </article>
        </div>

        <div class="modal-ver-detalles-grid">
            <section class="panel-ver-detalles panel-historial">
                <div class="panel-ver-detalles-cabecera">
                    <div>
                        <h3>Historial de la reserva</h3>
                    </div>
                </div>

                <div id="timelineReservaHistorial" class="timeline-reserva"></div>
            </section>

            <section class="panel-ver-detalles panel-info">
                <div class="panel-ver-detalles-cabecera">
                    <div>
                        <h3>Información</h3>
                    </div>
                </div>

                <div class="info-reserva-grid">
                    <article class="info-reserva-card">
                        <span class="info-reserva-label">Cliente</span>
                        <strong id="detalleReservaClienteNombre">---</strong>
                        <small id="detalleReservaClienteEmail">---</small>
                    </article>

                    <article class="info-reserva-card">
                        <span class="info-reserva-label">Habitación / habitaciones</span>
                        <ul id="detalleReservaHabitacionesLista" class="habitaciones-lista-detalle">
                            <li>---</li>
                        </ul>
                    </article>

                    <article class="info-reserva-card">
                        <span class="info-reserva-label">Check-in</span>
                        <strong id="detalleReservaCheckIn">---</strong>
                        <small id="detalleReservaHoraEntrada">---</small>
                    </article>

                    <article class="info-reserva-card">
                        <span class="info-reserva-label">Check-out</span>
                        <strong id="detalleReservaCheckOut">---</strong>
                        <small id="detalleReservaHoraSalida">---</small>
                    </article>

                    <article class="info-reserva-card">
                        <span class="info-reserva-label">Total</span>
                        <strong id="detalleReservaTotal">---</strong>
                        <small id="detalleReservaSaldoDetalle">---</small>
                    </article>

                    <article class="info-reserva-card">
                        <span class="info-reserva-label">Usuario que gestionó</span>
                        <strong id="detalleReservaUsuario">---</strong>
                    </article>
                </div>
            </section>
        </div>

        <section class="panel-ver-detalles panel-documentos">
            <div class="panel-ver-detalles-cabecera panel-documentos-cabecera">
                <div>
                    <h3>Detalle de pagos y comprobantes</h3>
                </div>

                <div class="resumen-documentos">
                    <span id="contadorDocumentosReserva">0 documentos</span>
                    <span id="resumenPagoReserva">Pago no registrado</span>
                </div>
            </div>

            <div class="tabla-documentos-wrap">
                <table class="tabla-documentos-reserva">
                    <thead>
                        <tr>
                            <th>Documento</th>
                            <th>Fecha</th>
                            <th>Monto</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="listaDocumentosReserva">
                        <tr class="fila-vacia-documentos">
                            <td colspan="5">No hay documentos cargados aún.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <footer class="modal-ver-detalles-acciones">
            <button type="button" id="btnCerrarVerDetalles" class="boton-cerrar-ver-detalles">Cerrar</button>
        </footer>
    </div>
</section>