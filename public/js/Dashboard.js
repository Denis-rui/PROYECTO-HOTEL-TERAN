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
        <strong>Checkout vencido - Hab. ${item.habitacion}</strong>
        <span>${item.cliente} excedio ${formatearMinutosCheckout(item.minutos_excedidos)}.</span>
        <button class="boton-checkout-dashboard" data-id="${item.id_reserva}">Confirmar checkout</button>
      </div>`;
  });

  proximos.forEach((item) => {
    panel.innerHTML += `
      <div class="notificacion-dashboard media">
        <strong>Checkout proximo - Hab. ${item.habitacion}</strong>
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

const ETIQUETAS_ESTADO = {
  pendiente: "Pendiente",
  confirmada: "Confirmada",
  checkin_realizado: "Check-in",
  en_estadia: "En estadia",
  checkout_pendiente: "Checkout pendiente",
  checkout_realizado: "Checkout realizado",
  cancelada: "Cancelada",
};

const COLORES_ESTADOS = [
  "#2563eb",
  "#16a34a",
  "#f59e0b",
  "#6d28d9",
  "#64748b",
  "#dc2626",
  "#94a3b8",
];

const inicializarGraficoHabitaciones = (datos) => {
  const canvas = document.getElementById("graficoHabitaciones");
  if (!canvas || !window.Chart || !datos?.labels?.length) return;

  const totales = (datos.totales || []).map((valor) => Number(valor || 0));
  if (totales.every((valor) => valor === 0)) return;

  if (window.graficoHabitacionesDashboard) {
    window.graficoHabitacionesDashboard.destroy();
  }

  window.graficoHabitacionesDashboard = new Chart(canvas, {
    type: "doughnut",
    data: {
      labels: datos.labels,
      datasets: [
        {
          data: totales,
          backgroundColor: ["#2f855a", "#c53030", "#805ad5", "#d69e2e", "#3182ce"],
          borderColor: "#ffffff",
          borderWidth: 2,
          hoverOffset: 6,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      cutout: "62%",
      plugins: {
        legend: {
          position: "bottom",
          labels: {
            font: { size: 12 },
            padding: 12,
            boxWidth: 14,
          },
        },
        tooltip: {
          callbacks: {
            label: (ctx) => ` ${ctx.label}: ${ctx.parsed} habitaciones`,
          },
        },
      },
    },
  });
};

const inicializarGraficoIngresos = (datos) => {
  const canvas = document.getElementById("graficoIngresos");
  if (!canvas || !window.Chart || !datos?.meses?.length) return;

  if (window.graficoIngresosDashboard) {
    window.graficoIngresosDashboard.destroy();
  }

  window.graficoIngresosDashboard = new Chart(canvas, {
    type: "line",
    data: {
      labels: datos.meses,
      datasets: [
        {
          label: "Ingresos (S/)",
          data: datos.totales,
          borderColor: "#2563eb",
          backgroundColor: "rgba(37, 99, 235, 0.08)",
          borderWidth: 2.5,
          pointBackgroundColor: "#2563eb",
          pointRadius: 4,
          tension: 0.35,
          fill: true,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label: (ctx) =>
              ` S/ ${Number(ctx.parsed.y).toLocaleString("es-PE", {
                minimumFractionDigits: 2,
              })}`,
          },
        },
      },
      scales: {
        x: {
          grid: { display: false },
          ticks: { font: { size: 12 } },
        },
        y: {
          beginAtZero: true,
          grid: { color: "rgba(0,0,0,0.05)" },
          ticks: {
            font: { size: 12 },
            callback: (val) => "S/ " + Number(val).toLocaleString("es-PE"),
          },
        },
      },
    },
  });
};

const inicializarGraficoEstados = (datos) => {
  const canvas = document.getElementById("graficoEstados");
  if (!canvas || !window.Chart || !datos?.labels?.length) return;

  if (window.graficoEstadosDashboard) {
    window.graficoEstadosDashboard.destroy();
  }

  const etiquetas = datos.labels.map((estado) => ETIQUETAS_ESTADO[estado] ?? estado);

  window.graficoEstadosDashboard = new Chart(canvas, {
    type: "doughnut",
    data: {
      labels: etiquetas,
      datasets: [
        {
          data: datos.totales,
          backgroundColor: COLORES_ESTADOS.slice(0, datos.labels.length),
          borderWidth: 2,
          borderColor: "#ffffff",
          hoverOffset: 6,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      cutout: "62%",
      plugins: {
        legend: {
          position: "right",
          labels: {
            font: { size: 12 },
            padding: 14,
            boxWidth: 14,
          },
        },
        tooltip: {
          callbacks: {
            label: (ctx) => ` ${ctx.label}: ${ctx.parsed} reservas`,
          },
        },
      },
    },
  });
};

const inicializarGraficosDashboard = () => {
  if (!window.Chart || !window.DASHBOARD_GRAFICOS) return;

  inicializarGraficoHabitaciones(window.DASHBOARD_GRAFICOS.habitaciones);
  inicializarGraficoIngresos(window.DASHBOARD_GRAFICOS.ingresos);
  inicializarGraficoEstados(window.DASHBOARD_GRAFICOS.estados);
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
  inicializarGraficosDashboard();
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
      Notificar(resultado.mensaje || "Checkout procesado", resultado.exito ? "exito" : "error");
      if (resultado.exito) window.inicializarDashboard?.();
    });
});

document.addEventListener("click", (e) => {
  const accordion = e.target.closest("#accordionMantenimiento");
  if (accordion) {
    accordion.classList.toggle("active");
  }
});

window.inicializarDashboard();
