window.actualizarHabitaciones = (e) => {
  // Solo reaccionar a controles que estén DENTRO del panel de filtros
  // Ignorar eventos del modal de habitación aunque burbujeen al documento
  if (e && !e.target?.closest?.(".seccion-filtros")) return;
  if (window._modalAbierto) return;

  const form = document.querySelector(".seccion-filtros");
  const grid = document.querySelector(".grid-habitaciones");
  if (!form || !grid) return;

  const params = new URLSearchParams(new FormData(form)).toString();

  fetch(`${BASE_URL}Habitacion/buscar?html=1&${params}`, {
    headers: { "X-Requested-With": "XMLHttpRequest" },
  })
    .then((res) => res.text())
    .then((html) => (grid.innerHTML = html))
    .catch(() => (grid.innerHTML = "<p>Error al cargar.</p>"));
};

document.addEventListener("input",  window.actualizarHabitaciones);
document.addEventListener("change", window.actualizarHabitaciones);

window.cambiarEstado = async (id, nuevoEstado) => {
  if (typeof Confirmar !== "function") {
    console.error("La función Confirmar no está cargada.");
    return;
  }

  const confirmacion = await Confirmar(
    `¿Desea cambiar el estado de la habitación a "${nuevoEstado}"?`
  );

  if (!confirmacion) {
    window.actualizarHabitaciones();
    return;
  }

  try {
    let motivo = "";
    if (nuevoEstado.toLowerCase().includes("mantenim")) {
      motivo = await SolicitarDato("Mantenimiento", "Por favor, indique el motivo:");
      if (!motivo) {
        Notificar?.("Debe indicar un motivo para mantenimiento.", "error");
        window.actualizarHabitaciones();
        return;
      }
    }

    const res = await fetch(`${BASE_URL}Habitacion/actualizarEstado`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ id, estado: nuevoEstado, motivo }),
    });

    const resultado = await res.json();

    if (resultado.exito) {
      Notificar?.(resultado.mensaje, "exito");
      setTimeout(() => window.location.reload(), 1000);
    } else {
      Notificar?.("Error: " + (resultado.mensaje || "Error desconocido"), "error");
      window.actualizarHabitaciones();
    }
  } catch (error) {
    console.error("Error:", error);
    Notificar?.("Error de conexión con el servidor.", "error");
  }
};