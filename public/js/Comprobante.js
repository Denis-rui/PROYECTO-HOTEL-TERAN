window.__comprobantePendienteReload = false;

const formatearFechaComprobante = (valor) => {
  if (!valor) return "---";
  const fecha = new Date(String(valor).replace(" ", "T"));
  if (Number.isNaN(fecha.getTime())) return String(valor);

  const yyyy = fecha.getFullYear();
  const mm = String(fecha.getMonth() + 1).padStart(2, "0");
  const dd = String(fecha.getDate()).padStart(2, "0");
  const hh = String(fecha.getHours()).padStart(2, "0");
  const mi = String(fecha.getMinutes()).padStart(2, "0");
  return `${yyyy}-${mm}-${dd} ${hh}:${mi}`;
};

const formatearFormaPagoTexto = (idMetodo) => {
  const mapa = {
    1: "Efectivo",
    2: "Tarjeta",
    3: "Yape / Transferencia",
  };
  return mapa[Number(idMetodo)] || `Método ${idMetodo || "---"}`;
};

const poblarComprobante = (comprobante = {}) => {
  const numeroTicket = document.getElementById("comprobanteNumeroTicket");
  const fechaEmision = document.getElementById("comprobanteFechaEmision");
  const formaPago = document.getElementById("comprobanteFormaPago");
  const cliente = document.getElementById("comprobanteCliente");
  const usuario = document.getElementById("comprobanteUsuario");
  const codigoReserva = document.getElementById("comprobanteCodigoReserva");
  const totalReserva = document.getElementById("comprobanteTotalReserva");
  const total = document.getElementById("comprobanteTotal");
  const descripcion = document.getElementById("comprobanteDescripcion");
  const habitaciones = document.getElementById("comprobanteHabitaciones");

  if (numeroTicket)
    numeroTicket.textContent = comprobante.numero_ticket || "---";
  if (fechaEmision)
    fechaEmision.textContent = formatearFechaComprobante(
      comprobante.fecha_emision,
    );
  if (formaPago)
    formaPago.textContent = formatearFormaPagoTexto(comprobante.id_forma_pago);
  if (cliente) cliente.textContent = comprobante.cliente || "---";
  if (usuario) usuario.textContent = comprobante.usuario || "---";
  if (codigoReserva)
    codigoReserva.textContent = comprobante.reserva?.codigo_reserva || "---";
  if (totalReserva)
    totalReserva.textContent = Number(comprobante.reserva?.total || 0).toFixed(
      2,
    );
  if (total) total.textContent = Number(comprobante.total || 0).toFixed(2);
  if (descripcion) descripcion.textContent = comprobante.descripcion || "---";

  if (habitaciones) {
    const lista = Array.isArray(comprobante.reserva?.habitaciones)
      ? comprobante.reserva.habitaciones
      : [];

    if (lista.length === 0) {
      habitaciones.innerHTML =
        '<div class="comprobante-habitacion-item">Sin habitaciones asociadas</div>';
    } else {
      habitaciones.innerHTML = lista
        .map(
          (hab) => `
          <div class="comprobante-habitacion-item">
            <strong>Hab. ${hab.numero_habitacion || "--"} - Piso ${hab.piso || "--"}</strong>
            <span>Tipo: ${hab.tipo_nombre || "--"}</span><br>
            <span>Precio unitario/día: S/ ${Number(hab.precio || 0).toFixed(2)}</span>
          </div>
        `,
        )
        .join("");
    }
  }
};

window.abrirModalComprobante = (comprobante = {}) => {
  const contenedor = document.getElementById("contenedor-modal-comprobante");
  const modal = document.getElementById("modalComprobante");
  if (!contenedor || !modal) return;

  poblarComprobante(comprobante);
  contenedor.style.display = "flex";
  modal.style.display = "block";
  window.__comprobantePendienteReload = true;
};

const configurarEventosComprobante = () => {
  const cerrarBtn = document.getElementById("cerrarModalComprobante");
  const botonCerrar = document.getElementById("btnCerrarComprobante");
  const botonImprimir = document.getElementById("btnImprimirComprobante");

  if (cerrarBtn) {
    cerrarBtn.addEventListener("click", window.cerrarModalComprobante);
  }

  if (botonCerrar) {
    botonCerrar.addEventListener("click", window.cerrarModalComprobante);
  }

  if (botonImprimir) {
    botonImprimir.addEventListener("click", window.imprimirComprobante);
  }
};

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", configurarEventosComprobante);
} else {
  configurarEventosComprobante();
}

window.cerrarModalComprobante = () => {
  const contenedor = document.getElementById("contenedor-modal-comprobante");
  const modal = document.getElementById("modalComprobante");

  if (contenedor) contenedor.style.display = "none";
  if (modal) modal.style.display = "none";

  if (window.__comprobantePendienteReload) {
    window.__comprobantePendienteReload = false;
    window.location.reload();
  }
};

window.imprimirComprobante = () => {
  window.print();
};

window.addEventListener("click", (e) => {
  const contenedor = document.getElementById("contenedor-modal-comprobante");
  const modal = document.getElementById("modalComprobante");
  if (contenedor && modal && e.target === contenedor) {
    window.cerrarModalComprobante();
  }
});
