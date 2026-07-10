// Devoluciones.js

const inicializarTablaDevoluciones = () => {
  const tabla = document.getElementById("tablaDevoluciones");
  if (!tabla || typeof DataTable === "undefined") return;

  const inputBusqueda = document.getElementById("inputBuscarDevolucion");
  const fechaDesde = document.getElementById("fechaDesdeDevolucion");
  const fechaHasta = document.getElementById("fechaHastaDevolucion");
  const btnHoy = document.getElementById("btnDevolucionesHoy");
  const btnLimpiar = document.getElementById("btnLimpiarFiltrosDevolucion");
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
        datos.fecha_desde = fechaDesde?.value || "";
        datos.fecha_hasta = fechaHasta?.value || "";
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

  btnHoy?.addEventListener("click", () => {
    const hoy = obtenerFechaLocalISO();
    if (fechaDesde) fechaDesde.value = hoy;
    if (fechaHasta) fechaHasta.value = hoy;
    tablaDevoluciones.ajax.reload();
  });

  btnLimpiar?.addEventListener("click", () => {
    if (inputBusqueda) inputBusqueda.value = "";
    if (fechaDesde) fechaDesde.value = "";
    if (fechaHasta) fechaHasta.value = "";
    tablaDevoluciones.ajax.reload();
  });

  window.recargarTablaDevoluciones = () => {
    tablaDevoluciones.ajax.reload(null, false);
    return true;
  };
};

const obtenerFechaLocalISO = () => {
  const ahora = new Date();
  const zonaLocal = new Date(
    ahora.getTime() - ahora.getTimezoneOffset() * 60000,
  );
  return zonaLocal.toISOString().slice(0, 10);
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

document.addEventListener("DOMContentLoaded", () => {
  inicializarTablaDevoluciones();
});
