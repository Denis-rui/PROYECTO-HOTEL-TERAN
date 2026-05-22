document.addEventListener("click", (e) => {
  const contenedor = document.getElementById("contenedorModal");
  if (!contenedor) return;

  // 1. ABRIR MODAL
  if (e.target.id === "btnNuevaHabitacion") {
    const modal = document.getElementById("modalHabitacion");
    if (modal) {
      modal.style.display = "flex";
    }
  }

  // 2. CERRAR MODAL
  if (
    e.target.id === "cerrarModalHabitacion" ||
    e.target.id === "btnCancelarHabitacion"
  ) {
    const modal = document.getElementById("modalHabitacion");
    if (modal) {
      modal.style.display = "none";
      // Opcional: Limpiar el formulario al cerrar
      const form = document.getElementById("formNuevaHabitacion");
      if (form) form.reset();
    }
  }
});

// GUARDAR NUEVA HABITACIÓN
document.addEventListener("submit", async (e) => {
  if (e.target.id === "formNuevaHabitacion") {
    e.preventDefault();

    const form = e.target;
    const formData = new FormData(form);
    const dataObj = Object.fromEntries(formData.entries());

    try {
      const res = await fetch(BASE_URL + "Habitacion/registrar", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(dataObj),
      });

      const resultado = await res.json();

      if (resultado.exito) {
        Notificar(resultado.mensaje, "exito");

        // Cerrar modal automáticamente
        const contenedor = document.getElementById("contenedorModal");
        if (contenedor) {
          contenedor.style.display = "none";
          contenedor.innerHTML = "";
        }

        // Forzar una recarga de la grilla de habitaciones
        // (Como Habitaciones.js escucha 'change' en el document, esto actualiza la tabla)
        document.dispatchEvent(new Event("change"));
      } else {
        Notificar(
          resultado.mensaje || "Error desconocido al guardar.",
          "error",
        );
      }
    } catch (error) {
      console.error("Error al guardar habitación:", error);
      Notificar("Error de conexión con el servidor.", "error");
    }
  }
});
