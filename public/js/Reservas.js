window.inicializarReservas = () => {
  if (window.__reservasEventosInicializados) return;
  window.__reservasEventosInicializados = true;

  configurarEventosReservas();
};
const configurarEventosReservas = () => {
  const btnNuevaReserva = document.getElementById("btnNuevaReserva");
  const cuerpoTabla = document.getElementById("contenido-reservas");
  const anchoMenu = 220;
  const cerrarMenusOpciones = () => {
    document.querySelectorAll(".menu-mas-opciones-wrap").forEach((menu) => {
      menu.classList.remove("menu-abierto");
      const panel = menu.querySelector(".menu-mas-opciones-panel");
      if (panel) {
        panel.style.display = "none";
        panel.style.top = "";
        panel.style.left = "";
      }
    });
  };

  if (btnNuevaReserva) {
    btnNuevaReserva.addEventListener("click", () => {
      window.abrirModalReserva("nuevo");
    });
  }

  if (cuerpoTabla) {
    cuerpoTabla.addEventListener("click", async (e) => {
      const btnDetalles = e.target.closest(".boton-mas-opciones");
      if (btnDetalles) {
        e.preventDefault();
        e.stopPropagation();

        const contenedorMenu = btnDetalles.closest(".menu-mas-opciones-wrap");
        if (!contenedorMenu) return;

        const panel = contenedorMenu.querySelector(".menu-mas-opciones-panel");
        if (!panel) return;

        const estabaAbierto = contenedorMenu.classList.contains("menu-abierto");
        cerrarMenusOpciones();
        if (!estabaAbierto) {
          const rect = btnDetalles.getBoundingClientRect();
          const left = Math.max(
            12,
            Math.min(
              window.innerWidth - anchoMenu - 12,
              rect.right - anchoMenu,
            ),
          );
          panel.style.top = `${rect.bottom + 6}px`;
          panel.style.left = `${left}px`;
          contenedorMenu.classList.add("menu-abierto");
          panel.style.display = "grid";
        }

        return;
      }

      const accionMarcarAusente = e.target.closest(".accion-marcar-ausente");
      if (accionMarcarAusente) {
        cerrarMenusOpciones();
        let confirmado = true;
        if (typeof window.Confirmar === "function") {
          confirmado = await window.Confirmar(
            "¿Marcar esta reserva como ausente?",
          );
        } else {
          confirmado = confirm("¿Marcar esta reserva como ausente?");
        }
        if (!confirmado) return;

        ejecutarAccionReserva("marcarAusente", {
          id_reserva: accionMarcarAusente.dataset.id,
        });
        return;
      }

      const accionMarcarRegreso = e.target.closest(".accion-marcar-regreso");
      if (accionMarcarRegreso) {
        cerrarMenusOpciones();
        let confirmado = true;
        if (typeof window.Confirmar === "function") {
          confirmado = await window.Confirmar(
            "¿Marcar regreso y volver la reserva a en estadía?",
          );
        } else {
          confirmado = confirm(
            "¿Marcar regreso y volver la reserva a en estadía?",
          );
        }
        if (!confirmado) return;

        ejecutarAccionReserva("marcarRegreso", {
          id_reserva: accionMarcarRegreso.dataset.id,
        });
        return;
      }

      const accionVerDetalles = e.target.closest(".accion-ver-detalles");
      if (accionVerDetalles) {
        const fila = accionVerDetalles.closest("tr");
        if (!fila) return;
        cerrarMenusOpciones();

        const parseArray = (valor) => {
          try {
            return JSON.parse(valor || "[]");
          } catch (error) {
            return [];
          }
        };

        const datosReserva = {
          id: fila.dataset.id,
          estado: fila.dataset.estado,
          porcentaje_pago: fila.dataset.porcentajepago,
          total: fila.dataset.total,
          saldo_pendiente: fila.dataset.saldoPendiente,
          cliente: fila.dataset.cliente,
          habitacion: fila.dataset.habitacion,
          habitaciones: parseArray(fila.dataset.habitaciones),
          check_in: fila.dataset.checkin,
          check_out: fila.dataset.checkout,
          email: fila.dataset.email,
          correo_electronico: fila.dataset.email,
        };

        if (typeof window.abrirModalVerDetalles === "function") {
          window.abrirModalVerDetalles(datosReserva);
        } else {
          alert("No se pudo abrir el módulo de detalles");
        }

        return;
      }

      const accionCancelar = e.target.closest(".accion-cancelar-reserva");
      if (accionCancelar) {
        cerrarMenusOpciones();

        const id = accionCancelar.dataset.id;
        const codigo = accionCancelar.dataset.codigo;
        const cliente = accionCancelar.dataset.cliente;
        const checkin = accionCancelar.dataset.checkin;

        if (typeof window.Swal === "undefined") {
          alert(`Frontend: cancelar reserva ${codigo} (${cliente})`);
          return;
        }

        Swal.fire({
          title: `Cancelar reserva ${codigo}`,
          html: `
            <p style="margin:0 0 8px; text-align:left;"><strong>Cliente:</strong> ${cliente}</p>
            <p style="margin:0 0 12px; text-align:left;"><strong>Check-in:</strong> ${checkin}</p>
            <select id="motivoCancelacionReserva" class="swal2-input" style="margin:0; width:100%;">
              <option value="">Motivo de cancelación</option>
              <option value="cliente">Cancelación del cliente</option>
              <option value="no_show">No show</option>
              <option value="error">Error de reserva</option>
              <option value="fuerza_mayor">Fuerza mayor</option>
            </select>
          `,
          showCancelButton: true,
          confirmButtonText: "Confirmar cancelación",
          cancelButtonText: "Volver",
          confirmButtonColor: "#8f2f2f",
          cancelButtonColor: "#2f3e1f",
          preConfirm: () => {
            const motivo =
              document.getElementById("motivoCancelacionReserva")?.value || "";
            if (!motivo) {
              Swal.showValidationMessage("Seleccione un motivo");
              return null;
            }
            return { id_reserva: id, motivo };
          },
        }).then(async (result) => {
          if (!result.isConfirmed) return;

          try {
            const res = await fetch(BASE_URL + "Reserva/cancelar", {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              body: JSON.stringify({
                id_reserva: result.value.id_reserva,
                motivo: result.value.motivo,
              }),
            });
            const resultado = await res.json();

            if (resultado.exito) {
              await Swal.fire({
                toast: true,
                position: "top-end",
                icon: "success",
                title: resultado.mensaje || "Reserva cancelada correctamente",
                showConfirmButton: false,
                timer: 2500,
                timerProgressBar: true,
              });
              window.location.reload();
            } else {
              Swal.fire({
                icon: "error",
                title: "Error",
                text: resultado.mensaje || "No se pudo cancelar la reserva.",
                confirmButtonColor: "#8f2f2f",
              });
            }
          } catch (err) {
            console.error(err);
            Swal.fire({
              icon: "error",
              title: "Error de conexión",
              text: "No se pudo conectar con el servidor.",
              confirmButtonColor: "#8f2f2f",
            });
          }
        });

        return;
      }

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
        // Confirmación antes de ejecutar check-in
        let confirmado = false;
        if (typeof window.Confirmar === "function") {
          confirmado = await window.Confirmar(
            "¿Confirmar check-in para esta reserva?",
          );
        } else {
          confirmado = confirm("¿Confirmar check-in para esta reserva?");
        }

        if (!confirmado) return;

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

  document.addEventListener("click", (e) => {
    if (!e.target.closest(".menu-mas-opciones-wrap")) {
      cerrarMenusOpciones();
    }
  });
};

const ejecutarAccionReserva = async (accion, datos) => {
  try {
    const res = await fetch(BASE_URL + "Reserva/" + accion, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(datos),
    });
    const resultado = await res.json();

    if (resultado.exito) {
      if (typeof Swal !== "undefined") {
        await Swal.fire({
          toast: true,
          position: "top-end",
          icon: "success",
          title: resultado.mensaje || "Acción procesada",
          showConfirmButton: false,
          timer: 2500,
          timerProgressBar: true,
        });
      } else {
        alert(resultado.mensaje || "Acción procesada");
      }
      window.location.reload();
    } else {
      if (typeof Notificar === "function") {
        Notificar(resultado.mensaje || "Error al procesar", "error");
      } else {
        alert(resultado.mensaje || "Error al procesar");
      }
    }
  } catch (error) {
    console.error(error);
    alert("Error de conexión con el servidor.");
  }
};

// La inicialización se ejecuta desde main.js para evitar duplicar listeners.
