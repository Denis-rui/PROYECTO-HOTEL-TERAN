window.inicializarReservas = () => {
  configurarEventosReservas();
  const buscarReserva = document.getElementById("inputBuscarReserva");
  const estadoSeleccionadoFiltro = document.getElementById("filtroEstado");

  if (!buscarReserva || !estadoSeleccionadoFiltro) return;

  const aplicarFiltros = () => {
    const nombreBuscar = buscarReserva.value.toLowerCase();
    const estadoSeleccionado = estadoSeleccionadoFiltro.value.toLowerCase();
    const filas = document.querySelectorAll("#contenido-reservas tr");
    filas.forEach((fila) => {
      const nombre = fila.children[0].textContent.toLowerCase();
      const estadoSelect = fila.children[5]?.querySelector(
        "select.estado-reserva",
      );
      const estado = (
        estadoSelect?.value ||
        fila.dataset.estado ||
        ""
      ).toLowerCase();
      if (
        nombre.includes(nombreBuscar) &&
        (estadoSeleccionado === "" || estado === estadoSeleccionado)
      ) {
        fila.style.display = "";
      } else {
        fila.style.display = "none";
      }
    });
  };

  buscarReserva.addEventListener("input", () => {
    aplicarFiltros();
  });

  estadoSeleccionadoFiltro.addEventListener("change", () => {
    aplicarFiltros();
  });
};
const configurarEventosReservas = () => {
  const btnNuevaReserva = document.getElementById("btnNuevaReserva");
  const cuerpoTabla = document.getElementById("contenido-reservas");

  const pedirConfirmacionCambioEstado = async (estadoAnterior, estadoNuevo) => {
    const etiquetas = {
      confirmada: "Confirmada",
      en_estadia: "En estadía",
      checkout_realizado: "Checkout realizado",
    };

    const mensaje = `¿Deseas cambiar el estado de la reserva de ${etiquetas[estadoAnterior] || estadoAnterior} a ${etiquetas[estadoNuevo] || estadoNuevo}?`;

    if (typeof window.Confirmar === "function") {
      return window.Confirmar(mensaje);
    }

    return Promise.resolve(confirm(mensaje));
  };

  if (btnNuevaReserva) {
    btnNuevaReserva.addEventListener("click", () => {
      window.abrirModalReserva("nuevo");
    });
  }

  if (cuerpoTabla) {
    cuerpoTabla.addEventListener("change", (e) => {
      const selectEstado = e.target.closest(".estado-reserva");
      if (!selectEstado || selectEstado.tagName !== "SELECT") return;

      const idReserva = selectEstado.dataset.id;
      const estadoActual = (selectEstado.dataset.estado || "").toLowerCase();
      const nuevoEstado = (selectEstado.value || "").toLowerCase();

      if (!nuevoEstado || nuevoEstado === estadoActual) {
        return;
      }

      if (typeof window.SolicitarDato === "function") {
        // Solo aprovechamos el modal bonito si existe; la confirmación sí usa Confirmar.
      }

      pedirConfirmacionCambioEstado(estadoActual, nuevoEstado).then(
        (confirmado) => {
          if (!confirmado) {
            selectEstado.value = estadoActual;
            return;
          }

          ejecutarAccionReserva("actualizarEstado", {
            id_reserva: idReserva,
            nuevo_estado: nuevoEstado,
          });
        },
      );
    });

    cuerpoTabla.addEventListener("click", (e) => {
      const btnEditar = e.target.closest(".boton-editar-reserva");
      if (btnEditar) {
        const fila = btnEditar.closest("tr");
        const id = Number(fila.dataset.id);
        window.abrirModalReserva("editar", { id });
        return;
      }

      // Evento para botón de pago
      const btnPago = e.target.closest(".boton-pago-tabla");
      if (btnPago) {
        const idReserva = btnPago.dataset.id;

        // Guardar el ID en el modal de pago
        const formPago = document.getElementById("formPago");
        if (formPago) {
          formPago.dataset.idReserva = idReserva;
          formPago.dataset.modoNuevo = "false"; // Indica que no es reserva nueva
        }

        // Abrir modal de pago
        if (typeof window.abrirModalPago === "function") {
          window.abrirModalPago({ idReserva });
        } else {
          alert("No se pudo abrir el modulo de pago");
        }
      }

      const btnCheckin = e.target.closest(".boton-checkin-reserva");
      if (btnCheckin) {
        ejecutarAccionReserva("checkin", { id_reserva: btnCheckin.dataset.id });
        return;
      }

      const btnCheckout = e.target.closest(".boton-checkout-reserva");
      if (btnCheckout) {
        ejecutarAccionReserva("checkout", {
          id_reserva: btnCheckout.dataset.id,
        });
        return;
      }

      const btnExtender = e.target.closest(".boton-extender-reserva");
      if (btnExtender) {
        const fechaSalida = prompt("Nueva fecha de checkout (YYYY-MM-DD):");
        if (!fechaSalida) return;
        const horaSalida = prompt("Nueva hora de checkout (HH:MM):", "12:00");
        if (!horaSalida) return;
        ejecutarAccionReserva("extender", {
          id_reserva: btnExtender.dataset.id,
          nuevo_check_out: `${fechaSalida} ${horaSalida}`,
        });
        return;
      }

      const btnConsumo = e.target.closest(".boton-consumo-reserva");
      if (btnConsumo) {
        const concepto = prompt("Concepto del consumo:");
        if (!concepto) return;
        const cantidad = prompt("Cantidad:", "1");
        if (!cantidad) return;
        const precioUnitario = prompt("Precio unitario:", "0");
        if (!precioUnitario) return;
        ejecutarAccionReserva("consumo", {
          id_reserva: btnConsumo.dataset.id,
          concepto,
          cantidad,
          precio_unitario: precioUnitario,
        });
        return;
      }

      const btnCancelar = e.target.closest(".boton-cancelar-reserva");
      if (btnCancelar) {
        const confirmado = confirm(
          "La cancelación debe pasar por devoluciones. Por ahora solo se dejará marcado como cancelada. ¿Continuar?",
        );
        if (!confirmado) return;

        ejecutarAccionReserva("actualizarEstado", {
          id_reserva: btnCancelar.dataset.id,
          nuevo_estado: "cancelada",
        });
        return;
      }

      const btnCambioHabitacion = e.target.closest(".boton-cambio-habitacion");
      if (btnCambioHabitacion) {
        const idHabitacionNueva = prompt(
          "ID de la nueva habitación disponible:",
        );
        if (!idHabitacionNueva) return;
        const motivo = prompt("Motivo del cambio:");
        if (!motivo) return;
        ejecutarAccionReserva("cambiarHabitacion", {
          id_reserva: btnCambioHabitacion.dataset.id,
          id_habitacion_nueva: idHabitacionNueva,
          motivo,
        });
      }
    });
  }
};

const ejecutarAccionReserva = async (accion, datos) => {
  try {
    const res = await fetch(BASE_URL + "?url=Reserva/" + accion, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(datos),
    });
    const resultado = await res.json();

    if (typeof Notificar === "function") {
      Notificar(
        resultado.mensaje || "Acción procesada",
        resultado.exito ? "exito" : "error",
      );
    } else {
      alert(resultado.mensaje || "Acción procesada");
    }

    if (resultado.exito) {
      window.location.reload();
    }
  } catch (error) {
    console.error(error);
    alert("Error de conexión con el servidor.");
  }
};

// Inicializar automáticamente al cargar el script
window.inicializarReservas();
