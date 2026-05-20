let eventosPagoConfigurados = false;

const asegurarModalPago = async () => {
  const modalContenedor = document.getElementById("contenedor-modal-pago");
  if (modalContenedor) {
    modalContenedor.style.display = "block";
  }
  return document.getElementById("modalPago");
};

const obtenerEsEdicionReserva = () => {
  const formPago = document.getElementById("formPago");
  return formPago?.dataset.guardarCambiosReserva === "true";
};

const obtenerDatosReservaDesdeFormularioPago = () => ({
  id_reserva: document.getElementById("formPago")?.dataset.idReserva || "",
  cliente:
    document.getElementById("pagoIdCliente")?.value.trim() ||
    document.getElementById("pagoCliente")?.value.trim() ||
    "",
  nombre: document.getElementById("pagoNombre")?.value.trim() || "",
  email: document.getElementById("pagoEmail")?.value.trim() || "",
  checkIn: document.getElementById("pagoCheckIn")?.value.trim() || "",
  horaEntrada: document.getElementById("pagoHoraEntrada")?.value.trim() || "",
  checkOut: document.getElementById("pagoCheckOut")?.value.trim() || "",
  horaSalida: document.getElementById("pagoHoraSalida")?.value.trim() || "",
  habitacion: document.getElementById("pagoHabitacion")?.value.trim() || "",
  habitaciones: JSON.parse(
    document.getElementById("pagoHabitaciones")?.value || "[]",
  ),
  totalReserva: document.getElementById("pagoTotalReserva")?.value.trim() || "",
  observaciones:
    document.getElementById("observacionesPago")?.value.trim() || "",
});

const sincronizarModalPagoConReserva = (reserva = {}) => {
  if (!reserva) return;

  poblarCamposOcultosReserva({
    idReserva:
      reserva.id ||
      reserva.id_reserva ||
      document.getElementById("formPago")?.dataset.idReserva,
    guardarCambiosReserva:
      document.getElementById("formPago")?.dataset.guardarCambiosReserva ===
      "true",
    cliente: reserva.cliente || "",
    clienteTexto: reserva.cliente || "",
    nombre: reserva.cliente || "",
    email: reserva.correo_electronico || "",
    checkIn: reserva.check_in || "",
    horaEntrada: "",
    checkOut: reserva.check_out || "",
    horaSalida: "",
    habitacion: reserva.habitacion || "",
    habitaciones: reserva.habitaciones || [],
    totalReserva: reserva.total || 0,
    totalPagado: reserva.total_pagado || 0,
    saldoPendiente: reserva.saldo_pendiente || 0,
    pagos: reserva.pagos || [],
  });
};

const guardarCambiosReserva = async () => {
  const formPago = document.getElementById("formPago");
  if (!formPago?.dataset.idReserva) {
    return {
      exito: false,
      mensaje: "No hay una reserva activa para actualizar.",
    };
  }

  const datosReserva = obtenerDatosReservaDesdeFormularioPago();

  try {
    const res = await fetch(BASE_URL + "?url=Reserva/actualizar", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(datosReserva),
    });
    const resultado = await res.json();

    if (!resultado.exito) {
      return resultado;
    }

    if (resultado.reserva) {
      sincronizarModalPagoConReserva(resultado.reserva);
    }

    // Indica que, tras guardar cambios de reserva, es necesario recargar la tabla
    try {
      const formPagoEl = document.getElementById("formPago");
      if (formPagoEl) formPagoEl.dataset.needsReload = "true";
    } catch (e) {
      // ignore
    }

    return resultado;
  } catch (error) {
    console.error(error);
    return {
      exito: false,
      mensaje: "Error al guardar los cambios de la reserva.",
    };
  }
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

    if (datos.guardarCambiosReserva) {
      formPago.dataset.guardarCambiosReserva = "true";
    } else {
      delete formPago.dataset.guardarCambiosReserva;
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
    pagoIdCliente: datos.idCliente || datos.cliente || "",
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
  if (infoPagado)
    infoPagado.textContent = `S/ ${Number(datos.totalPagado || 0).toFixed(2)}`;

  const saldoDisponible = Math.max(
    0,
    Number(datos.totalReserva || 0) - Number(datos.totalPagado || 0),
  );

  const esReservaNueva = formPago?.dataset.modoNuevo === "true";
  const esEdicionReserva = formPago?.dataset.guardarCambiosReserva === "true";

  // Cálculos de política
  const infoSugerido = document.getElementById("infoPagoSugerido");
  const etiquetaPolitica = document.getElementById("etiquetaPolitica");
  const inputMonto = document.getElementById("montoPago");

  if (infoSugerido && datos.totalReserva) {
    const total = parseFloat(datos.totalReserva);
    const pagado = parseFloat(datos.totalPagado || 0);
    const saldo = total - pagado;

    let sugerido = 0;
    if (!esReservaNueva && !esEdicionReserva) {
      sugerido = saldo;
      if (etiquetaPolitica) {
        etiquetaPolitica.textContent = "(Saldo pendiente)";
      }
    } else {
      const hoy = new Date().toISOString().split("T")[0];
      const esHoy = datos.checkIn === hoy;
      sugerido = esHoy ? saldo : Math.max(0, total * 0.5 - pagado);
      if (etiquetaPolitica) {
        etiquetaPolitica.textContent = "(50% por reserva anticipada)";
      }
    }

    infoSugerido.textContent = `S/ ${sugerido.toFixed(2)}`;
    if (inputMonto && !inputMonto.value) {
      inputMonto.value = sugerido > 0 ? sugerido.toFixed(2) : "";
    }
  }

  if (inputMonto) {
    inputMonto.max = saldoDisponible > 0 ? saldoDisponible.toFixed(2) : "";
    inputMonto.min = datos.idReserva
      ? "0.01"
      : Math.max(
          0.01,
          Number((Number(datos.totalReserva || 0) * 0.5).toFixed(2)),
        );
  }

  const historial = document.getElementById("contenidoHistorialPagos");
  if (historial) {
    const pagos = Array.isArray(datos.pagos) ? datos.pagos : [];
    const formatearFechaPago = (valor) => {
      if (!valor) return "--";
      const texto = String(valor).trim().replace(" ", "T");
      const fecha = new Date(texto);
      if (Number.isNaN(fecha.getTime())) {
        return String(valor);
      }

      const yyyy = fecha.getFullYear();
      const mm = String(fecha.getMonth() + 1).padStart(2, "0");
      const dd = String(fecha.getDate()).padStart(2, "0");
      const hh = String(fecha.getHours()).padStart(2, "0");
      const mi = String(fecha.getMinutes()).padStart(2, "0");
      const ss = String(fecha.getSeconds()).padStart(2, "0");
      return `${yyyy}-${mm}-${dd} ${hh}:${mi}:${ss}`;
    };
    const metodoPagoTexto = (idMetodo) => {
      const mapa = {
        1: "Efectivo",
        2: "Tarjeta",
        3: "Yape / Transferencia",
      };
      return mapa[Number(idMetodo)] || `Método ${idMetodo || "--"}`;
    };

    if (pagos.length === 0) {
      historial.innerHTML = `
        <tr>
          <td colspan="4" style="text-align:center; color:#777; padding: 12px;">Sin pagos registrados</td>
        </tr>`;
    } else {
      historial.innerHTML = pagos
        .map(
          (pago) => `
            <tr>
              <td>${formatearFechaPago(pago.fecha_pago)}</td>
              <td>S/ ${Number(pago.monto || 0).toFixed(2)}</td>
              <td>${metodoPagoTexto(pago.id_metodo_pago)}</td>
              <td>${pago.descripcion || "--"}</td>
            </tr>`,
        )
        .join("");
    }
  }

  const botonCancelar = document.getElementById("btnCancelarPago");
  if (botonCancelar) {
    botonCancelar.textContent = esEdicionReserva
      ? "Salir guardando"
      : "Cancelar";
  }
};

const configurarEventosPago = () => {
  if (eventosPagoConfigurados) return;

  const modalPago = document.getElementById("modalPago");
  const cerrarBtn = document.getElementById("cerrarModalPago");
  const cancelarBtn = document.getElementById("btnCancelarPago");
  const formPago = document.getElementById("formPago");

  if (cerrarBtn) {
    cerrarBtn.addEventListener("click", async () => {
      if (obtenerEsEdicionReserva()) {
        const resultado = await guardarCambiosReserva();
        if (!resultado.exito) {
          alert(resultado.mensaje || "No se pudieron guardar los cambios.");
          return;
        }
      }
      window.cerrarModalPago();
    });
  }

  if (cancelarBtn) {
    cancelarBtn.addEventListener("click", async () => {
      if (obtenerEsEdicionReserva()) {
        const resultado = await guardarCambiosReserva();
        if (!resultado.exito) {
          alert(resultado.mensaje || "No se pudieron guardar los cambios.");
          return;
        }
      }
      window.cerrarModalPago();
    });
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

      const montoNumerico = parseFloat(montoPago);

      const esReservaNueva = formPago.dataset.modoNuevo === "true";
      const esEdicionReserva =
        formPago.dataset.guardarCambiosReserva === "true";
      if (esReservaNueva) {
        const cliente =
          document.getElementById("pagoIdCliente")?.value.trim() ||
          document.getElementById("pagoCliente")?.value.trim() ||
          "";
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

        const totalReserva = parseFloat(
          document.getElementById("pagoTotalReserva")?.value || "0",
        );
        const minimoInicial = totalReserva * 0.5;
        if (montoNumerico < minimoInicial) {
          alert(
            `El pago inicial debe ser al menos el 50% del total. Monto mínimo: S/ ${minimoInicial.toFixed(2)}`,
          );
          return;
        }

        if (montoNumerico > totalReserva) {
          alert(
            `El pago inicial no puede ser mayor al total de la reserva. Total permitido: S/ ${totalReserva.toFixed(2)}`,
          );
          return;
        }
      }

      if (!esReservaNueva && !esEdicionReserva) {
        const saldoDisponible = parseFloat(
          (document.getElementById("montoPago")?.max || "0").toString(),
        );
        if (saldoDisponible > 0 && montoNumerico > saldoDisponible) {
          alert(
            `El monto no puede ser mayor al saldo pendiente. Saldo disponible: S/ ${saldoDisponible.toFixed(2)}`,
          );
          return;
        }
      }

      const descripcionPago =
        document.getElementById("descripcionPago")?.value.trim() || "";

      try {
        if (esEdicionReserva && formPago.dataset.idReserva) {
          const guardado = await guardarCambiosReserva();
          if (!guardado.exito) {
            alert(guardado.mensaje || "No se pudieron guardar los cambios.");
            return;
          }

          const saldoActualizado = parseFloat(
            (document.getElementById("montoPago")?.max || "0").toString(),
          );
          if (saldoActualizado > 0 && montoNumerico > saldoActualizado) {
            alert(
              `El monto no puede ser mayor al saldo pendiente actualizado. Saldo disponible: S/ ${saldoActualizado.toFixed(2)}`,
            );
            return;
          }
        }

        const url =
          BASE_URL +
          (esReservaNueva ? "?url=Reserva/registrar" : "?url=Reserva/pago");

        const fetchPayload = esReservaNueva
          ? {
              cliente:
                document.getElementById("pagoCliente")?.value.trim() || "",
              nombre: document.getElementById("pagoNombre")?.value.trim() || "",
              email: document.getElementById("pagoEmail")?.value.trim() || "",
              checkIn:
                document.getElementById("pagoCheckIn")?.value.trim() || "",
              horaEntrada:
                document.getElementById("pagoHoraEntrada")?.value.trim() || "",
              checkOut:
                document.getElementById("pagoCheckOut")?.value.trim() || "",
              horaSalida:
                document.getElementById("pagoHoraSalida")?.value.trim() || "",
              habitacion:
                document.getElementById("pagoHabitacion")?.value.trim() || "",
              habitaciones: JSON.parse(
                document.getElementById("pagoHabitaciones")?.value || "[]",
              ),
              totalReserva:
                document.getElementById("pagoTotalReserva")?.value.trim() || "",
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
          Notificar(
            resultado.mensaje || "Operación registrada correctamente",
            "exito",
          );
        } else {
          alert(resultado.mensaje || "Operación registrada correctamente");
        }

        window.cerrarModalPago();

        if (
          resultado.comprobante &&
          typeof window.abrirModalComprobante === "function"
        ) {
          window.abrirModalComprobante(resultado.comprobante);
          return;
        }

        if (
          resultado.pago_id &&
          typeof window.abrirModalComprobante === "function"
        ) {
          try {
            const comprobanteRes = await fetch(
              BASE_URL +
                `?url=Comprobante/obtenerPorPago/${encodeURIComponent(resultado.pago_id)}`,
            );
            const comprobante = await comprobanteRes.json();
            if (comprobante) {
              window.abrirModalComprobante(comprobante);
              return;
            }
          } catch (error) {
            console.error(error);
          }
        }

        window.location.reload(); // Recargar si no se pudo mostrar el comprobante
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

  if (datosReserva.idReserva) {
    try {
      const res = await fetch(
        BASE_URL +
          `?url=Reserva/obtener/${encodeURIComponent(datosReserva.idReserva)}`,
      );
      const respuesta = await res.json();
      const reserva = respuesta?.reserva || respuesta;

      if (reserva) {
        const habitacionesResumen = Array.isArray(reserva.habitaciones)
          ? reserva.habitaciones
              .map((habitacion) => {
                if (!habitacion) return "";
                return `Hab. ${habitacion.numero_habitacion} - Piso ${habitacion.piso}`;
              })
              .filter(Boolean)
              .join(" | ")
          : reserva.habitacion;

        datosReserva = {
          ...reserva,
          ...datosReserva,
          clienteTexto: datosReserva.clienteTexto || reserva.cliente,
          habitacion: datosReserva.habitacion || habitacionesResumen,
          habitaciones: datosReserva.habitaciones || reserva.habitaciones || [],
          checkIn: datosReserva.checkIn || reserva.check_in,
          checkOut: datosReserva.checkOut || reserva.check_out,
          totalReserva: datosReserva.totalReserva || reserva.total,
          totalPagado: datosReserva.totalPagado ?? reserva.total_pagado,
          saldoPendiente:
            datosReserva.saldoPendiente ?? reserva.saldo_pendiente,
          pagos: reserva.pagos || datosReserva.pagos || [],
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
    // Si se guardaron cambios a la reserva, recargar para actualizar la tabla
    if (formPago.dataset.needsReload === "true") {
      window.location.reload();
    }
  }
};
