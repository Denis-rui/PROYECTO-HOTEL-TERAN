// ─── COUNTDOWN DE LIMPIEZA ────────────────────────────────────────────────────
// Mapa de intervalos activos: { habitacionId: intervalId }
const _limpiezaIntervalos = {};

/**
 * Inicia (o reinicia) los contadores de limpieza para todas las tarjetas
 * que tengan [data-limpieza-id] en el DOM.
 */
function iniciarCountdownsLimpieza() {
  // Limpiar intervalos anteriores para evitar duplicados
  Object.keys(_limpiezaIntervalos).forEach((id) => {
    clearInterval(_limpiezaIntervalos[id]);
    delete _limpiezaIntervalos[id];
  });

  document.querySelectorAll(".tarjeta-habitacion[data-limpieza-id]").forEach((tarjeta) => {
    const id = tarjeta.dataset.limpiezaId;
    let segundos = parseInt(tarjeta.dataset.segundos, 10) || 0;
    const timerEl = document.getElementById("timer-" + id);

    if (!timerEl) return;

    // Si ya expiro al renderizar, registrar alerta sin liberar la habitacion.
    if (segundos <= 0) {
      _notificarLimpiezaVencida(id, false);
      return;
    }

    const intervalo = setInterval(() => {
      segundos--;
      if (segundos <= 0) {
        clearInterval(intervalo);
        delete _limpiezaIntervalos[id];
        if (timerEl) timerEl.textContent = "00:00";
        _notificarLimpiezaVencida(id, true);
      } else {
        const m = Math.floor(segundos / 60);
        const s = segundos % 60;
        if (timerEl) timerEl.textContent = String(m).padStart(2, "0") + ":" + String(s).padStart(2, "0");
      }
    }, 1000);

    _limpiezaIntervalos[id] = intervalo;
  });
}

/**
 * Registra la alerta de limpieza vencida sin cambiar la habitacion a disponible.
 */
async function _notificarLimpiezaVencida(id, refrescarGrid = true) {
  const claveNotificacion = `limpieza-vencida-${id}`;
  const yaNotificada = sessionStorage.getItem(claveNotificacion) === "1";

  if (yaNotificada && !refrescarGrid) return;

  try {
    const res = await fetch(`${BASE_URL}Habitacion/notificarLimpiezaVencida`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ id: parseInt(id, 10) }),
    });
    const resultado = await res.json();
    if (resultado.exito) sessionStorage.setItem(claveNotificacion, "1");
    if (resultado.exito) {
      Notificar?.("Tiempo de limpieza vencido. La habitacion sigue bloqueada.", "error");
    }
  } catch (e) {
    console.error("Error al registrar limpieza vencida:", e);
  }
  // Recargar solo cuando el contador acaba de vencer en pantalla.
  if (refrescarGrid) {
    setTimeout(() => window.actualizarHabitaciones(), 500);
  }
}

/**
 * Termina la limpieza manualmente (botón "Terminé antes").
 */
window.terminarLimpieza = async (id, numero) => {
  const ok = await Confirmar(`¿Confirmas que la limpieza de la habitación ${numero} ha terminado?`);
  if (!ok) return;

  try {
    const res = await fetch(`${BASE_URL}Habitacion/terminarLimpieza`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ id }),
    });
    const resultado = await res.json();
    if (resultado.exito) {
      sessionStorage.removeItem(`limpieza-vencida-${id}`);
      Notificar?.(resultado.mensaje, "exito");
      setTimeout(() => window.actualizarHabitaciones(), 600);
    } else {
      Notificar?.("Error: " + (resultado.mensaje || "Error desconocido"), "error");
    }
  } catch (e) {
    Notificar?.("Error de conexión con el servidor.", "error");
  }
};

// ─── FILTROS Y GRID ──────────────────────────────────────────────────────────
window.extenderLimpieza = async (id, numero, minutos = 15) => {
  const ok = await Confirmar(`Extender ${minutos} minutos la limpieza de la habitacion ${numero}?`);
  if (!ok) return;

  try {
    const res = await fetch(`${BASE_URL}Habitacion/extenderLimpieza`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ id, minutos }),
    });
    const resultado = await res.json();
    Notificar?.(resultado.mensaje || "Limpieza extendida.", resultado.exito ? "exito" : "error");
    if (resultado.exito) {
      sessionStorage.removeItem(`limpieza-vencida-${id}`);
      setTimeout(() => window.actualizarHabitaciones(), 500);
    }
  } catch (e) {
    Notificar?.("Error de conexion con el servidor.", "error");
  }
};

window.actualizarHabitaciones = (e) => {
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
    .then((html) => {
      grid.innerHTML = html;
      inicializarCarruseles();
      iniciarCountdownsLimpieza();
    })
    .catch(() => (grid.innerHTML = "<p>Error al cargar.</p>"));
};

// Debounce: evita lanzar una peticion AJAX por cada tecla del buscador.
let temporizadorFiltroHabitaciones = null;

document.addEventListener("input", (e) => {
  if (!e.target?.closest?.(".seccion-filtros")) return;

  clearTimeout(temporizadorFiltroHabitaciones);
  temporizadorFiltroHabitaciones = setTimeout(() => {
    window.actualizarHabitaciones(e);
  }, 300);
});
document.addEventListener("change", window.actualizarHabitaciones);

window.cambiarEstado = async (id, nuevoEstado) => {
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

// ─── CARRUSEL ────────────────────────────────────────────────────────────────
window.desplazarCarrusel = (boton, direccion) => {
  const wrapper = boton.closest(".carrusel-piso-wrapper");
  if (!wrapper) return;
  const carrusel = wrapper.querySelector(".carrusel-piso");
  if (!carrusel) return;

  const tarjeta = carrusel.querySelector(".tarjeta-habitacion");
  const anchoTarjeta = tarjeta ? tarjeta.offsetWidth + 20 : 260;

  carrusel.scrollBy({ left: direccion * anchoTarjeta * 2, behavior: "smooth" });
  setTimeout(() => actualizarBotonesCarrusel(wrapper), 350);
};

function actualizarBotonesCarrusel(wrapper) {
  const carrusel = wrapper.querySelector(".carrusel-piso");
  const btnIzq = wrapper.querySelector(".btn-carrusel-izq");
  const btnDer = wrapper.querySelector(".btn-carrusel-der");
  if (!carrusel || !btnIzq || !btnDer) return;

  btnIzq.disabled = carrusel.scrollLeft <= 0;
  btnDer.disabled = carrusel.scrollLeft + carrusel.offsetWidth >= carrusel.scrollWidth - 1;
}

function inicializarCarruseles() {
  document.querySelectorAll(".carrusel-piso-wrapper").forEach((wrapper) => {
    actualizarBotonesCarrusel(wrapper);
    const carrusel = wrapper.querySelector(".carrusel-piso");
    if (carrusel) {
      carrusel.addEventListener("scroll", () => actualizarBotonesCarrusel(wrapper));
    }
  });
}

// Inicializar todo al cargar la página
document.addEventListener("DOMContentLoaded", () => {
  inicializarCarruseles();
  iniciarCountdownsLimpieza();
});
