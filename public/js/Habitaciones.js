// Exponer funciones en window para que sean accesibles desde cualquier lugar
window.actualizarHabitaciones = (e) => {
  const targetId = e?.target?.id || "";
  const targetName = e?.target?.name || "";
  const isManual = !e || (!targetId && !targetName);

  if (
    isManual ||
    targetId === "inputBuscar" ||
    targetName === "id_tipo_habitacion" ||
    targetName === "estado" ||
    targetName === "piso"
  ) {
    const form = document.querySelector(".seccion-filtros");
    const grid = document.querySelector(".grid-habitaciones");

    if (!form || !grid) return;

    const params = new URLSearchParams(new FormData(form)).toString();

    fetch(`${BASE_URL}?url=Habitacion/buscar&html=1&${params}`, {
      headers: { "X-Requested-With": "XMLHttpRequest" },
    })
      .then((res) => res.text())
      .then((html) => (grid.innerHTML = html))
      .catch(() => (grid.innerHTML = "<p>Error al cargar.</p>"));
  }
};

// Delegamos los eventos al 'document'
document.addEventListener("input", window.actualizarHabitaciones);
document.addEventListener("change", window.actualizarHabitaciones);

// Función global para cambiar el estado
window.cambiarEstado = async (id, nuevoEstado) => {
  if (typeof Confirmar !== "function") {
    console.error(
      "La función Confirmar no está cargada. Asegúrate de que Notificaciones.js esté en index.php",
    );
    return;
  }

  const confirmacion = await Confirmar(
    `¿Desea cambiar el estado de la habitación a "${nuevoEstado}"?`,
  );

  if (!confirmacion) {
    window.actualizarHabitaciones(); // Resetear vista
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

    const payload = {
      id: id,
      estado: nuevoEstado,
      motivo: motivo
    };

    const res = await fetch(`${BASE_URL}?url=Habitacion/actualizarEstado`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });

    const resultado = await res.json();

    if (resultado.exito) {
      if (typeof Notificar === "function") {
        Notificar(resultado.mensaje, "exito");
      }
      // Recargar la página para actualizar los filtros inteligentes y la grilla
      setTimeout(() => {
        window.location.reload();
      }, 1000);
    } else {
      const msg = resultado.mensaje || "Error desconocido";
      if (typeof Notificar === "function") {
        Notificar("Error: " + msg, "error");
      }
      window.actualizarHabitaciones();
    }
  } catch (error) {
    console.error("Error:", error);
    if (typeof Notificar === "function") {
      Notificar("Error de conexión con el servidor.", "error");
    }
  }
};
