// Bandera para evitar que el filtro de habitaciones reaccione mientras el modal está abierto
window._modalAbierto = false;

// Función auxiliar para resetear el modal sin disparar eventos de filtro
function resetearModalHabitacion() {
  const form = document.getElementById("formNuevaHabitacion");
  if (!form) return;
  window._modalAbierto = false;
  form.reset();
  const habitacionId = document.getElementById("habitacionId");
  if (habitacionId) habitacionId.value = "";
}

// Abrir modal nueva habitación / cerrar modal
document.addEventListener("click", (e) => {
  if (e.target.id === "btnNuevaHabitacion") {
    const modal = document.getElementById("modalHabitacion");
    if (modal) {
      resetearModalHabitacion();
      window._modalAbierto = true;
      modal.style.display = "flex";
      requestAnimationFrame(() => {
        document.getElementById("tituloModalHabitacion").textContent = "Nueva Habitación";
        document.getElementById("btnSubmitHabitacion").textContent   = "Guardar";
      });
    }
  }

  if (e.target.id === "cerrarModalHabitacion" || e.target.id === "btnCancelarHabitacion") {
    const modal = document.getElementById("modalHabitacion");
    if (modal) {
      modal.style.display = "none";
      resetearModalHabitacion();
    }
  }
});

// Abrir modal en modo edición
window.editarHabitacion = (id, numero, piso, idTipo, capacidad, estado, descripcion) => {
  const modal = document.getElementById("modalHabitacion");
  if (!modal) return;
  window._modalAbierto = true;

  // Mostrar modal primero 
  modal.style.display = "flex";

  // Rellenar campos en el siguiente frame 
  requestAnimationFrame(() => {
    document.getElementById("tituloModalHabitacion").textContent = "Editar Habitación";
    document.getElementById("btnSubmitHabitacion").textContent   = "Actualizar";
    document.getElementById("habitacionId").value                = id;
    document.getElementById("numeroHabitacion").value            = numero;
    document.getElementById("pisoHabitacion").value              = piso;
    document.getElementById("capacidadHabitacion").value         = capacidad;
    document.getElementById("descripcionHabitacion").value       = descripcion ?? "";

    const selectTipo = document.getElementById("tipoHabitacion");
    if (selectTipo) {
      for (const opt of selectTipo.options) opt.selected = opt.value == idTipo;
    }

    const selectEstado = document.getElementById("estadoHabitacion");
    if (selectEstado) {
      for (const opt of selectEstado.options) opt.selected = opt.value === estado;
    }
  });
};

// Guardar / Actualizar 
document.addEventListener("click", async (e) => {
  if (e.target.id !== "btnSubmitHabitacion") return;

  const form = document.getElementById("formNuevaHabitacion");
  if (!form) return;

  // Validar campos requeridos
  const camposRequeridos = form.querySelectorAll("[required]");
  for (const campo of camposRequeridos) {
    if (!campo.value.trim()) {
      campo.focus();
      Notificar("Por favor completa todos los campos requeridos.", "error");
      return;
    }
  }

  const dataObj   = Object.fromEntries(new FormData(form).entries());
  const esEdicion = dataObj.id && dataObj.id !== "";
  const url       = BASE_URL + (esEdicion ? "Habitacion/editar" : "Habitacion/registrar");

  // Feedback visual en el botón
  const btn = e.target;
  btn.disabled    = true;
  btn.textContent = esEdicion ? "Actualizando..." : "Guardando...";

  try {
    const res  = await fetch(url, {
      method:  "POST",
      headers: { "Content-Type": "application/json" },
      body:    JSON.stringify(dataObj),
    });

    const text = await res.text();
    let resultado;
    try {
      resultado = JSON.parse(text);
    } catch (_) {
      console.error("Respuesta no es JSON:", text);
      Notificar("Error: el servidor no respondió correctamente.", "error");
      btn.disabled    = false;
      btn.textContent = esEdicion ? "Actualizar" : "Guardar";
      return;
    }

    if (resultado.exito) {
      window._modalAbierto = false;
      const modal = document.getElementById("modalHabitacion");
      if (modal) modal.style.display = "none";

      Notificar(
        resultado.mensaje || (esEdicion ? "Habitación actualizada." : "Habitación registrada."),
        "exito"
      );

      setTimeout(() => {
        window.location.href = BASE_URL + "Habitacion/index";
      }, 800);
    } else {
      Notificar(resultado.mensaje || "Error desconocido al guardar.", "error");
      btn.disabled    = false;
      btn.textContent = esEdicion ? "Actualizar" : "Guardar";
    }

  } catch (error) {
    console.error("Error al guardar habitación:", error);
    Notificar("Error de conexión con el servidor.", "error");
    btn.disabled    = false;
    btn.textContent = esEdicion ? "Actualizar" : "Guardar";
  }
});
