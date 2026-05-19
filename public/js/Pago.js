let eventosPagoConfigurados = false;

const asegurarModalPago = async () => {
  const modalContenedor = document.getElementById("contenedor-modal-pago");
  if (modalContenedor) {
    modalContenedor.style.display = "block";
  }
  return document.getElementById("modalPago");
};

const poblarCamposOcultosReserva = (datos = {}) => {
  const formPago = document.getElementById("formPago");
  if (formPago) {
    if (datos.idReserva) {
      formPago.dataset.idReserva = datos.idReserva;
      formPago.dataset.modoNuevo = "false";
    } else {
      delete formPago.dataset.idReserva;
      formPago.dataset.modoNuevo = "true";
    }
  }

  const mapa = {
    pagoCliente: datos.cliente || "",
    pagoNombre: datos.nombre || "",
    pagoEmail: datos.email || "",
    pagoCheckIn: datos.checkIn || "",
    pagoHoraEntrada: datos.horaEntrada || "",
    pagoCheckOut: datos.checkOut || "",
    pagoHoraSalida: datos.horaSalida || "",
    pagoHabitacion: datos.habitacion || "",
    pagoHabitaciones: JSON.stringify(datos.habitaciones || []),
    pagoTotalReserva: datos.totalReserva || "",
  };

  Object.entries(mapa).forEach(([id, valor]) => {
    const campo = document.getElementById(id);
    if (campo) campo.value = valor;
  });

  const infoCliente = document.getElementById("infoPagoCliente");
  const infoHabitacion = document.getElementById("infoPagoHabitacion");
  const infoCheckin = document.getElementById("infoPagoCheckin");
  const infoCheckout = document.getElementById("infoPagoCheckout");
  const infoMonto = document.getElementById("infoPagoMonto");
  const infoPagado = document.getElementById("infoPagoPagado");

  if (infoCliente)
    infoCliente.textContent = datos.clienteTexto || datos.nombre || "---";
  if (infoHabitacion) {
    infoHabitacion.textContent = datos.habitacion || "---";
  }
  if (infoCheckin) {
    infoCheckin.textContent = datos.checkIn
      ? `${datos.checkIn} ${datos.horaEntrada || ""}`.trim()
      : "---";
  }
  if (infoCheckout) {
    infoCheckout.textContent = datos.checkOut
      ? `${datos.checkOut} ${datos.horaSalida || ""}`.trim()
      : "---";
  }
  if (infoMonto) {
    infoMonto.textContent = datos.totalReserva
      ? `S/ ${Number(datos.totalReserva).toFixed(2)}`
      : "S/ ---";
  }
  if (infoPagado) infoPagado.textContent = `S/ ${Number(datos.totalPagado || 0).toFixed(2)}`;

  // Cálculos de política
  const infoSugerido = document.getElementById("infoPagoSugerido");
  const etiquetaPolitica = document.getElementById("etiquetaPolitica");
  const inputMonto = document.getElementById("montoPago");

  if (infoSugerido && datos.totalReserva) {
    const hoy = new Date().toISOString().split("T")[0];
    const esHoy = datos.checkIn === hoy;
    const total = parseFloat(datos.totalReserva);
    const pagado = parseFloat(datos.totalPagado || 0);
    const saldo = total - pagado;

    let sugerido = 0;
    if (esHoy) {
      sugerido = saldo;
      if (etiquetaPolitica) etiquetaPolitica.textContent = "(100% por ingreso directo/hoy)";
    } else {
      sugerido = Math.max(0, (total * 0.5) - pagado);
      if (etiquetaPolitica) etiquetaPolitica.textContent = "(50% por reserva anticipada)";
    }

    infoSugerido.textContent = `S/ ${sugerido.toFixed(2)}`;
    if (inputMonto && !inputMonto.value) {
        inputMonto.value = sugerido > 0 ? sugerido.toFixed(2) : "";
    }
  }
};

const configurarEventosPago = () => {
  if (eventosPagoConfigurados) return;

  const modalPago = document.getElementById("modalPago");
  const cerrarBtn = document.getElementById("cerrarModalPago");
  const cancelarBtn = document.getElementById("btnCancelarPago");
  const formPago = document.getElementById("formPago");

  if (cerrarBtn) {
    cerrarBtn.addEventListener("click", window.cerrarModalPago);
  }

  if (cancelarBtn) {
    cancelarBtn.addEventListener("click", window.cerrarModalPago);
  }

  if (modalPago) {
    modalPago.addEventListener("click", (e) => {
      if (e.target === modalPago) {
        window.cerrarModalPago();
      }
    });
  }

  if (formPago) {
    formPago.addEventListener("submit", async (e) => {
      e.preventDefault();

      const montoPago =
        document.getElementById("montoPago")?.value.trim() || "";
      const metodoPago =
        document.getElementById("metodoPago")?.value.trim() || "";
      const fechaPago =
        document.getElementById("fechaPago")?.value.trim() || "";

      if (!montoPago) {
        alert("El monto es requerido");
        return;
      }

      if (!metodoPago) {
        alert("Debe seleccionar un metodo de pago");
        return;
      }

      if (!fechaPago) {
        alert("Debe ingresar la fecha del pago");
        return;
      }

      if (parseFloat(montoPago) <= 0) {
        alert("El monto debe ser mayor a 0");
        return;
      }

      const esReservaNueva = formPago.dataset.modoNuevo === "true";
      if (esReservaNueva) {
        const cliente =
          document.getElementById("pagoCliente")?.value.trim() || "";
        const habitacion =
          document.getElementById("pagoHabitacion")?.value.trim() || "";
        const checkIn =
          document.getElementById("pagoCheckIn")?.value.trim() || "";
        const checkOut =
          document.getElementById("pagoCheckOut")?.value.trim() || "";

        if (!cliente || !habitacion || !checkIn || !checkOut) {
          alert(
            "Faltan datos de la reserva. Vuelve a abrir la reserva y selecciona un cliente valido.",
          );
          return;
        }
      }

      const descripcionPago =
        document.getElementById("descripcionPago")?.value.trim() || "";

      try {
        let url = BASE_URL + (esReservaNueva ? "?url=Reserva/registrar" : "?url=Reserva/pago");
        
        let fetchPayload = esReservaNueva 
          ? {
              cliente: document.getElementById("pagoCliente")?.value.trim() || "",
              nombre: document.getElementById("pagoNombre")?.value.trim() || "",
              email: document.getElementById("pagoEmail")?.value.trim() || "",
              checkIn: document.getElementById("pagoCheckIn")?.value.trim() || "",
              horaEntrada: document.getElementById("pagoHoraEntrada")?.value.trim() || "",
              checkOut: document.getElementById("pagoCheckOut")?.value.trim() || "",
              horaSalida: document.getElementById("pagoHoraSalida")?.value.trim() || "",
              habitacion: document.getElementById("pagoHabitacion")?.value.trim() || "",
              habitaciones: JSON.parse(document.getElementById("pagoHabitaciones")?.value || "[]"),
              totalReserva: document.getElementById("pagoTotalReserva")?.value.trim() || "",
              pago: {
                monto: montoPago,
                id_metodo_pago: metodoPago,
                fecha_pago: fechaPago,
                descripcion: descripcionPago || "Pago inicial",
              },
            }
          : {
              id_reserva: formPago.dataset.idReserva,
              monto: montoPago,
              id_metodo_pago: metodoPago,
              fecha_pago: fechaPago,
              descripcion: descripcionPago || "Pago de reserva",
            };

        const res = await fetch(url, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(fetchPayload),
        });
        const resultado = await res.json();

        if (!resultado.exito) {
          alert(resultado.mensaje || "No se pudo registrar.");
          return;
        }

        if (typeof Notificar === "function") {
          Notificar(resultado.mensaje || "Operación registrada correctamente", "exito");
        } else {
          alert(resultado.mensaje || "Operación registrada correctamente");
        }

        window.cerrarModalPago();
        window.location.reload(); // Recargar para ver cambios
      } catch (error) {
        console.error(error);
        alert("Error de conexión con el servidor.");
      }
    });
  }

  eventosPagoConfigurados = true;
};

window.abrirModalPago = async (datosReserva = {}) => {
  const modalPago = await asegurarModalPago();
  if (!modalPago) return;

  if (datosReserva.idReserva && !datosReserva.totalReserva) {
    try {
      const res = await fetch(
        BASE_URL + `?url=Reserva/obtener/${encodeURIComponent(datosReserva.idReserva)}`,
      );
      const respuesta = await res.json();
      if (respuesta.reserva) {
        const habitacionesResumen = Array.isArray(respuesta.reserva.habitaciones)
          ? respuesta.reserva.habitaciones
              .map((habitacion) => {
                if (!habitacion) return "";
                return `Hab. ${habitacion.numero_habitacion} - Piso ${habitacion.piso}`;
              })
              .filter(Boolean)
              .join(" | ")
          : respuesta.reserva.habitacion;

        datosReserva = {
          ...datosReserva,
          clienteTexto: respuesta.reserva.cliente,
          habitacion: habitacionesResumen,
          habitaciones: respuesta.reserva.habitaciones || [],
          checkIn: respuesta.reserva.check_in,
          checkOut: respuesta.reserva.check_out,
          totalReserva: respuesta.reserva.total,
          totalPagado: respuesta.reserva.total_pagado,
        };
      }
    } catch (error) {
      console.error(error);
    }
  }

  poblarCamposOcultosReserva(datosReserva);

  const fechaPago = document.getElementById("fechaPago");
  if (fechaPago && !fechaPago.value) {
    fechaPago.valueAsDate = new Date();
  }

  configurarEventosPago();
  modalPago.classList.add("activo");
  const modalContenedor = document.getElementById("contenedor-modal-pago");
  if (modalContenedor) {
    modalContenedor.style.display = "block";
  }
};

window.cerrarModalPago = () => {
  const modalPago = document.getElementById("modalPago");
  const formPago = document.getElementById("formPago");
  const modalContenedor = document.getElementById("contenedor-modal-pago");

  if (modalContenedor) {
    modalContenedor.style.display = "none";
  }

  if (modalPago) {
    modalPago.classList.remove("activo");
  }

  if (formPago) {
    formPago.reset();
  }
};
