const formatearMinutosCheckout = (minutos) => {
  const valor = Math.abs(Number(minutos || 0));
  const horas = Math.floor(valor / 60);
  const mins = valor % 60;
  return `${horas}h ${mins}m`;
};

const renderizarNotificacionesCheckout = (datos) => {
  const panel = document.getElementById("panelNotificacionesCheckout");
  if (!panel) return;

  const vencidos = datos.vencidos || [];
  const proximos = datos.proximos || [];
  const notificaciones = datos.notificaciones || [];

  panel.innerHTML = "";

  vencidos.forEach((item) => {
    panel.innerHTML += `
      <div class="notificacion-dashboard critica">
        <strong>Checkout vencido · Hab. ${item.habitacion}</strong>
        <span>${item.cliente} excedió ${formatearMinutosCheckout(item.minutos_excedidos)}.</span>
        <button class="boton-checkout-dashboard" data-id="${item.id_reserva}">Confirmar checkout</button>
      </div>`;
  });

  proximos.forEach((item) => {
    panel.innerHTML += `
      <div class="notificacion-dashboard media">
        <strong>Checkout próximo · Hab. ${item.habitacion}</strong>
        <span>${item.cliente}: faltan ${formatearMinutosCheckout(item.minutos_faltantes)}.</span>
      </div>`;
  });

  notificaciones.slice(0, 5).forEach((item) => {
    panel.innerHTML += `
      <div class="notificacion-dashboard ${item.prioridad}">
        <strong>${item.titulo}</strong>
        <span>${item.mensaje}</span>
      </div>`;
  });

  if (!panel.innerHTML) {
    panel.innerHTML = '<div class="notificacion-dashboard baja"><span>Sin alertas operativas pendientes.</span></div>';
  }
};

window.inicializarDashboard = () => {
  const cargar = async () => {
    try {
      const res = await fetch(BASE_URL + "Reserva/notificaciones");
      const datos = await res.json();
      renderizarNotificacionesCheckout(datos);
    } catch (error) {
      console.error(error);
    }
  };

  cargar();
  window.configurarBtnNuevaReserva?.();
  clearInterval(window.intervaloDashboardCheckout);
  window.intervaloDashboardCheckout = setInterval(cargar, 60000);
};

document.addEventListener("click", (e) => {
  const btn = e.target.closest(".boton-checkout-dashboard");
  if (!btn) return;

  fetch(BASE_URL + "Reserva/checkout", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ id_reserva: btn.dataset.id }),
  })
    .then((res) => res.json())
    .then((resultado) => {
      if (typeof Notificar === "function") {
        Notificar(resultado.mensaje || "Checkout procesado", resultado.exito ? "exito" : "error");
      }
      if (resultado.exito) window.inicializarDashboard?.();
    });
});

// Toggle para el acordeón de mantenimiento (despliegue debajo)
document.addEventListener("click", (e) => {
  const accordion = e.target.closest("#accordionMantenimiento");
  if (accordion) {
    accordion.classList.toggle("active");
  }
});

// Inicializar automáticamente al cargar el script
window.inicializarDashboard();
