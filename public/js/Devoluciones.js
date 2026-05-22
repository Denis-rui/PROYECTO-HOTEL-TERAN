// Devoluciones.js

const configurarEventosDevoluciones = () => {
  const btnNuevo = document.getElementById("btnNuevaDevolucion");
  const cuerpoTabla = document.getElementById("tabla-devoluciones-body");

  if (btnNuevo) {
    btnNuevo.addEventListener("click", () => abrirModalDevolucion("nuevo"));
  }

  if (cuerpoTabla) {
    cuerpoTabla.addEventListener("click", async (e) => {
      const btnEditar = e.target.closest(".btnEditarDevolucion");
      if (btnEditar) {
        abrirModalDevolucion("editar", {
          id:          btnEditar.dataset.id,
          reserva:     btnEditar.dataset.reserva,
          fecha:       btnEditar.dataset.fecha,
          diasUsados:  btnEditar.dataset.diasUsados,
          diasNoUsados:btnEditar.dataset.diasNoUsados,
          total:       btnEditar.dataset.total,
          porcentaje:  btnEditar.dataset.porcentaje,
          penalidad:   btnEditar.dataset.penalidad,
          devuelto:    btnEditar.dataset.devuelto,
        });
        return;
      }

      const btnEliminar = e.target.closest(".btnEliminarDevolucion");
      if (btnEliminar) {
        if (confirm("¿Está seguro de eliminar esta devolución?")) {
          try {
            const res = await fetch(BASE_URL + "Devolucion/eliminar", {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              body: JSON.stringify({ id: btnEliminar.dataset.id }),
            });
            const resultado = await res.json();
            if (resultado.exito) {
              window.location.reload();
            } else {
              alert(resultado.mensaje || "Error al eliminar");
            }
          } catch (err) {
            console.error(err);
          }
        }
      }
    });
  }
};

window.abrirModalDevolucion = (modo, datos = {}) => {
  const modal  = document.getElementById("contenedor-modal-devolucion");
  const titulo = document.getElementById("titulo-modal-devolucion");
  const msg    = document.getElementById("error-exito-modal-devolucion");

  // Formatear fecha para datetime-local (quitar los segundos si vienen)
  let fecha = datos.fecha ?? "";
  if (fecha && fecha.length === 19) fecha = fecha.slice(0, 16); // "YYYY-MM-DD HH:MM"
  if (fecha) fecha = fecha.replace(" ", "T");

  document.getElementById("id-devolucion").value                   = datos.id ?? "";
  document.getElementById("reserva-devolucion").value              = datos.reserva ?? "";
  document.getElementById("fecha-cancelacion-devolucion").value    = fecha;
  document.getElementById("dias-usados-devolucion").value          = datos.diasUsados ?? 0;
  document.getElementById("dias-no-usados-devolucion").value       = datos.diasNoUsados ?? 0;
  document.getElementById("total-no-ocupado-devolucion").value     = datos.total ?? 0;
  document.getElementById("porcentaje-penalidad-devolucion").value = datos.porcentaje ?? 0;
  document.getElementById("monto-penalidad-devolucion").value      = datos.penalidad ?? 0;
  document.getElementById("monto-devuelto-devolucion").value       = datos.devuelto ?? 0;

  titulo.textContent = modo === "editar" ? "Editar Devolución" : "Nueva Devolución";
  msg.textContent = "";
  msg.className = "div-mensaje-exito-error";
  modal.style.display = "flex";
};

window.cerrarModalDevolucion = () => {
  document.getElementById("contenedor-modal-devolucion").style.display = "none";
};

document.addEventListener("click", (e) => {
  const modal = document.getElementById("contenedor-modal-devolucion");
  if (modal && e.target === modal) cerrarModalDevolucion();
});

document.addEventListener("DOMContentLoaded", () => {
  configurarEventosDevoluciones();

  const form = document.getElementById("form-devolucion");
  if (!form) return;

  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    const msg = document.getElementById("error-exito-modal-devolucion");
    const id  = document.getElementById("id-devolucion").value;

    const datos = {
      id:                   id || undefined,
      id_reserva:           document.getElementById("reserva-devolucion").value,
      fecha_cancelacion:    document.getElementById("fecha-cancelacion-devolucion").value.replace("T", " "),
      dias_usados:          document.getElementById("dias-usados-devolucion").value,
      dias_no_usados:       document.getElementById("dias-no-usados-devolucion").value,
      total_no_ocupado:     document.getElementById("total-no-ocupado-devolucion").value,
      porcentaje_penalidad: document.getElementById("porcentaje-penalidad-devolucion").value,
      monto_penalidad:      document.getElementById("monto-penalidad-devolucion").value,
      monto_devuelto:       document.getElementById("monto-devuelto-devolucion").value,
    };

    const url = id
      ? BASE_URL + "Devolucion/actualizar"
      : BASE_URL + "Devolucion/registrar";

    try {
      const res = await fetch(url, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(datos),
      });
      const resultado = await res.json();
      if (resultado.exito) {
        window.location.reload();
      } else {
        msg.textContent = resultado.mensaje || "Error al guardar";
        msg.className = "div-mensaje-exito-error error";
      }
    } catch (err) {
      msg.textContent = "Error de conexión";
      msg.className = "div-mensaje-exito-error error";
    }
  });
});
