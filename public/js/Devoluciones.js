// Devoluciones.js

const inicializarTablaDevoluciones = () => {
  const tabla = document.getElementById("tablaDevoluciones");
  if (!tabla || typeof DataTable === "undefined") return;

  const inputBusqueda = document.getElementById("inputBuscarDevolucion");
  const tablaDevoluciones = new DataTable("#tablaDevoluciones", {
    processing: true,
    serverSide: true,
    pageLength: 30,
    lengthMenu: [10, 30, 50, 100],
    info: false,
    searching: false,
    ajax: {
      url: BASE_URL + "Devolucion/datatable",
      type: "POST",
      headers: {
        "X-CSRF-Token": typeof CSRF_TOKEN !== "undefined" ? CSRF_TOKEN : "",
      },
      data: (datos) => {
        datos.csrf_token = typeof CSRF_TOKEN !== "undefined" ? CSRF_TOKEN : "";
        datos.busqueda = inputBusqueda?.value?.trim() || "";
        return datos;
      },
    },
    order: [[0, "desc"]],
    columns: [
      { data: "id" },
      { data: "id_reserva", render: (valor) => `#${escaparHtml(valor)}` },
      { data: "cliente", render: renderTextoSeguro },
      { data: "fecha_inicio", render: renderFechaDevolucion },
      { data: "fecha_prevista", render: renderFechaDevolucion },
      { data: "fecha_cancelacion", render: renderFechaDevolucion },
      { data: "dias_usados" },
      { data: "dias_no_usados" },
      { data: "total_no_ocupado", render: renderMonedaDevolucion },
      { data: "porcentaje_penalidad", render: renderPorcentajeDevolucion },
      { data: "monto_penalidad", render: renderMonedaDevolucion },
      { data: "monto_devuelto", render: renderMonedaDevolucion },
    ],
    layout: {
      topStart: "pageLength",
      topEnd: null,
    },
    language: {
      emptyTable: "No hay devoluciones para mostrar.",
      zeroRecords: "No se encontraron devoluciones con esa búsqueda.",
      lengthMenu: "Mostrar _MENU_ devoluciones",
      info: "Mostrando _START_ a _END_ de _TOTAL_ devoluciones",
      infoEmpty: "Mostrando 0 devoluciones",
      infoFiltered: "(filtrado de _MAX_ devoluciones)",
      paginate: {
        first: "Primero",
        last: "Último",
        next: "Siguiente",
        previous: "Anterior",
      },
    },
  });

  let temporizadorBusqueda;
  inputBusqueda?.addEventListener("input", () => {
    clearTimeout(temporizadorBusqueda);
    temporizadorBusqueda = setTimeout(() => {
      tablaDevoluciones.ajax.reload();
    }, 250);
  });

  inputBusqueda?.closest("form")?.addEventListener("submit", (e) => {
    e.preventDefault();
    tablaDevoluciones.ajax.reload();
  });

  window.recargarTablaDevoluciones = () => {
    tablaDevoluciones.ajax.reload(null, false);
    return true;
  };
};

const escaparHtml = (valor) =>
  String(valor ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");

const renderTextoSeguro = (valor) => escaparHtml(valor || "--");

const renderFechaDevolucion = (valor) => {
  if (!valor) return "--";

  const partes = String(valor).match(
    /^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}))?/,
  );

  if (!partes) return escaparHtml(valor);

  const [, anio, mes, dia, hora = "00", minuto = "00"] = partes;
  return `${hora}:${minuto} ${dia}/${mes}/${anio}`;
};

const renderMonedaDevolucion = (valor) => {
  const numero = Number(valor || 0);
  return `S/ ${numero.toFixed(2)}`;
};

const renderPorcentajeDevolucion = (valor) => {
  const numero = Number(valor || 0);
  return `${numero.toFixed(2)}%`;
};

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
          id: btnEditar.dataset.id,
          reserva: btnEditar.dataset.reserva,
          fechaInicio: btnEditar.dataset.fechaInicio,
          fechaPrevista: btnEditar.dataset.fechaPrevista,
          fecha: btnEditar.dataset.fecha,
          diasUsados: btnEditar.dataset.diasUsados,
          diasNoUsados: btnEditar.dataset.diasNoUsados,
          total: btnEditar.dataset.total,
          porcentaje: btnEditar.dataset.porcentaje,
          penalidad: btnEditar.dataset.penalidad,
          devuelto: btnEditar.dataset.devuelto,
        });
        return;
      }

      const btnEliminar = e.target.closest(".btnEliminarDevolucion");
      if (btnEliminar) {
        const confirmado = await window.Confirmar(
          "¿Está seguro de eliminar esta devolución?",
          {
            titulo: "Eliminar devolución",
            icono: "warning",
            confirmar: "Eliminar",
          },
        );
        if (confirmado) {
          try {
            const res = await fetch(BASE_URL + "Devolucion/eliminar", {
              method: "DELETE",
              headers: { "Content-Type": "application/json" },
              body: JSON.stringify({ id: btnEliminar.dataset.id }),
            });
            const resultado = await res.json();
            if (resultado.exito) {
              if (typeof window.recargarTablaDevoluciones === "function") {
                window.recargarTablaDevoluciones();
              } else {
                window.location.reload();
              }
            } else {
              await window.Alerta(
                resultado.mensaje || "Error al eliminar",
                "error",
              );
            }
          } catch (err) {
            console.error(err);
            await window.Alerta(
              "No se pudo conectar con el servidor.",
              "error",
            );
          }
        }
      }
    });
  }
};

window.abrirModalDevolucion = (modo, datos = {}) => {
  const modal = document.getElementById("contenedor-modal-devolucion");
  const titulo = document.getElementById("titulo-modal-devolucion");
  const msg = document.getElementById("error-exito-modal-devolucion");

  // Formatear fecha para datetime-local (quitar los segundos si vienen)
  let fecha = datos.fecha ?? "";
  if (fecha && fecha.length === 19) fecha = fecha.slice(0, 16); // "YYYY-MM-DD HH:MM"
  if (fecha) fecha = fecha.replace(" ", "T");

  // Helper para formatear fecha a datetime-local
  const fmtFecha = (f) => {
    if (!f) return "";
    if (f.length === 19) f = f.slice(0, 16);
    return f.replace(" ", "T");
  };

  document.getElementById("id-devolucion").value = datos.id ?? "";
  document.getElementById("reserva-devolucion").value = datos.reserva ?? "";
  document.getElementById("fecha-inicio-devolucion").value = fmtFecha(datos.fechaInicio ?? "");
  document.getElementById("fecha-prevista-devolucion").value = fmtFecha(datos.fechaPrevista ?? "");
  document.getElementById("fecha-cancelacion-devolucion").value = fecha;
  document.getElementById("dias-usados-devolucion").value = datos.diasUsados ?? 0;
  document.getElementById("dias-no-usados-devolucion").value = datos.diasNoUsados ?? 0;
  document.getElementById("total-no-ocupado-devolucion").value = datos.total ?? 0;
  document.getElementById("porcentaje-penalidad-devolucion").value = datos.porcentaje ?? 0;
  document.getElementById("monto-penalidad-devolucion").value = datos.penalidad ?? 0;
  document.getElementById("monto-devuelto-devolucion").value = datos.devuelto ?? 0;

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
  inicializarTablaDevoluciones();
  configurarEventosDevoluciones();

  const form = document.getElementById("form-devolucion");
  if (!form) return;

  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    const msg = document.getElementById("error-exito-modal-devolucion");
    const id = document.getElementById("id-devolucion").value;

    const datos = {
      id: id || undefined,
      id_reserva: document.getElementById("reserva-devolucion").value,
      fecha_inicio: document.getElementById("fecha-inicio-devolucion").value.replace("T", " ") || null,
      fecha_prevista: document.getElementById("fecha-prevista-devolucion").value.replace("T", " ") || null,
      fecha_cancelacion: document.getElementById("fecha-cancelacion-devolucion").value.replace("T", " "),
      dias_usados: document.getElementById("dias-usados-devolucion").value,
      dias_no_usados: document.getElementById("dias-no-usados-devolucion").value,
      total_no_ocupado: document.getElementById("total-no-ocupado-devolucion").value,
      porcentaje_penalidad: document.getElementById("porcentaje-penalidad-devolucion").value,
      monto_penalidad: document.getElementById("monto-penalidad-devolucion").value,
      monto_devuelto: document.getElementById("monto-devuelto-devolucion").value,
    };

    const url = id
      ? BASE_URL + "Devolucion/actualizar"
      : BASE_URL + "Devolucion/registrar";

    try {
      const res = await fetch(url, {
        method: id ? "PUT" : "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(datos),
      });
      const resultado = await res.json();
      if (resultado.exito) {
        cerrarModalDevolucion();
        if (typeof window.recargarTablaDevoluciones === "function") {
          window.recargarTablaDevoluciones();
        } else {
          window.location.reload();
        }
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
