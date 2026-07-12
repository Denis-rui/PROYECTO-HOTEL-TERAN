
(function () {
  const metodosPorAccion = {
    checkin: "PATCH",
    preCheckin: "PATCH",
    checkout: "PATCH",
    marcarAusente: "PATCH",
    marcarRegreso: "PATCH",
    eliminarPendiente: "PATCH",
  };

  window.ejecutarAccionReservaApi = async (accion, datos = {}, opciones = {}) => {
    const respuesta = await fetch(BASE_URL + "Reserva/" + accion, {
      method: opciones.method || metodosPorAccion[accion] || "POST",
      headers: {
        "Content-Type": "application/json",
        ...(opciones.headers || {}),
      },
      body: JSON.stringify(datos),
    });

    let resultado = {};
    try {
      resultado = await respuesta.json();
    } catch (error) {
      resultado = {
        exito: false,
        mensaje: "La respuesta del servidor no es valida.",
      };
    }

    return {
      ok: respuesta.ok,
      exito: respuesta.ok && Boolean(resultado.exito),
      resultado,
      status: respuesta.status,
    };
  };

  window.recargarVistaReservas = () => {
    if (typeof window.recargarTablaReservas === "function") {
      window.recargarTablaReservas();
      return;
    }

    if (typeof window.inicializarDashboard === "function") {
      window.inicializarDashboard();
      return;
    }

    window.location.reload();
  };
})();
