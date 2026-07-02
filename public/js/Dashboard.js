// Escapa caracteres HTML para prevenir ataques XSS al construir clases o textos.
const escaparHtmlDashboard = (valor) =>
  String(valor ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");

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
    const div = document.createElement("div");
    div.className = "notificacion-dashboard critica";

    const strong = document.createElement("strong");
    strong.textContent = `Checkout vencido - Hab. ${item.habitacion ?? "---"}`;

    const span = document.createElement("span");
    span.textContent = `${item.cliente ?? "Cliente"} excedio ${formatearMinutosCheckout(item.minutos_excedidos)}.`;

    const btn = document.createElement("button");
    btn.className = "boton-checkout-dashboard";
    btn.dataset.id = item.id_reserva;
    btn.textContent = "Confirmar checkout";

    div.appendChild(strong);
    div.appendChild(span);
    div.appendChild(btn);
    panel.appendChild(div);
  });

  proximos.forEach((item) => {
    const div = document.createElement("div");
    div.className = "notificacion-dashboard media";

    const strong = document.createElement("strong");
    strong.textContent = `Checkout proximo - Hab. ${item.habitacion ?? "---"}`;

    const span = document.createElement("span");
    span.textContent = `${item.cliente ?? "Cliente"}: faltan ${formatearMinutosCheckout(item.minutos_faltantes)}.`;

    div.appendChild(strong);
    div.appendChild(span);
    panel.appendChild(div);
  });

  notificaciones.slice(0, 5).forEach((item) => {
    const div = document.createElement("div");
    div.className = `notificacion-dashboard ${escaparHtmlDashboard(item.prioridad || "baja")}`;

    const strong = document.createElement("strong");
    strong.textContent = item.titulo || "";

    const span = document.createElement("span");
    span.textContent = item.mensaje || "";

    div.appendChild(strong);
    div.appendChild(span);
    panel.appendChild(div);
  });

  if (!panel.children.length) {
    const div = document.createElement("div");
    div.className = "notificacion-dashboard baja";

    const span = document.createElement("span");
    span.textContent = "Sin alertas operativas pendientes.";

    div.appendChild(span);
    panel.appendChild(div);
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
    method: "PATCH",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ id_reserva: btn.dataset.id }),
  })
    .then(async (res) => ({ ok: res.ok, resultado: await res.json() }))
    .then(({ ok, resultado }) => {
      Notificar(resultado.mensaje || "Checkout procesado", ok && resultado.exito ? "exito" : "error");
      if (ok && resultado.exito) window.inicializarDashboard?.();
    });
});

document.addEventListener("click", (e) => {
  const accordion = e.target.closest("#accordionMantenimiento");
  if (accordion) {
    accordion.classList.toggle("active");
  }
});

window.inicializarDashboard();
