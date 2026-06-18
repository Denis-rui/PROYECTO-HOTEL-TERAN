window.inicializarReservas = () => {
  if (window.__reservasEventosInicializados) return;
  window.__reservasEventosInicializados = true;

  configurarEventosReservas();
};
const configurarEventosReservas = () => {
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
        panel.style.visibility = "";
        panel.style.maxHeight = "";
      }
    });
  };

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
          const margenPantalla = 12;
          const separacion = 6;
          const left = Math.max(
            margenPantalla,
            Math.min(
              window.innerWidth - anchoMenu - margenPantalla,
              rect.right - anchoMenu,
            ),
          );

          panel.style.left = `${left}px`;
          contenedorMenu.classList.add("menu-abierto");
          panel.style.display = "grid";
          panel.style.visibility = "hidden";
          panel.style.maxHeight = `${window.innerHeight - margenPantalla * 2}px`;

          const altoMenu = panel.getBoundingClientRect().height;
          const espacioAbajo =
            window.innerHeight - rect.bottom - margenPantalla;
          const espacioArriba = rect.top - margenPantalla;
          const abrirHaciaArriba =
            altoMenu > espacioAbajo && espacioArriba > espacioAbajo;
          const top = abrirHaciaArriba
            ? Math.max(margenPantalla, rect.top - altoMenu - separacion)
            : Math.min(
                rect.bottom + separacion,
                window.innerHeight - altoMenu - margenPantalla,
              );

          panel.style.top = `${Math.max(margenPantalla, top)}px`;
          panel.style.visibility = "visible";
        }

        return;
      }

      const accionMarcarAusente = e.target.closest(".accion-marcar-ausente");
      if (accionMarcarAusente) {
        cerrarMenusOpciones();
        const confirmado = await window.Confirmar(
          "¿Marcar esta reserva como ausente?",
        );
        if (!confirmado) return;

        ejecutarAccionReserva("marcarAusente", {
          id_reserva: accionMarcarAusente.dataset.id,
        });
        return;
      }

      const accionMarcarRegreso = e.target.closest(".accion-marcar-regreso");
      if (accionMarcarRegreso) {
        cerrarMenusOpciones();
        const confirmado = await window.Confirmar(
          "¿Marcar regreso y volver la reserva a en estadía?",
        );
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
          window.Alerta("No se pudo abrir el módulo de detalles", "error");
        }

        return;
      }

      const accionEmitirDocumento = e.target.closest(
        ".accion-emitir-documento",
      );
      if (accionEmitirDocumento) {
        const fila = accionEmitirDocumento.closest("tr");
        if (!fila) return;
        cerrarMenusOpciones();

        const estadosConCheckIn = [
          "en_estadia",
          "checkout_pendiente",
          "checkout_realizado",
        ];
        if (!estadosConCheckIn.includes(String(fila.dataset.estado || "").toLowerCase())) {
          window.Alerta(
            "Solo se puede emitir una boleta o factura después de realizar el check-in del cliente.",
            "error",
          );
          return;
        }

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
          documento: fila.dataset.clienteDocumento,
          id_tipo_documento: fila.dataset.clienteTipoDocumento,
          cliente_direccion: fila.dataset.clienteDireccion,
          habitacion: fila.dataset.habitacion,
          habitaciones: parseArray(fila.dataset.habitaciones),
          check_in: fila.dataset.checkin,
          check_out: fila.dataset.checkout,
          email: fila.dataset.email,
          correo_electronico: fila.dataset.email,
          total_pagado: fila.dataset.totalPagado,
          dias_estadia: fila.dataset.diasEstadia,
        };

        if (typeof window.abrirModalDocumentoElectronico === "function") {
          window.abrirModalDocumentoElectronico(datosReserva);
        } else {
          window.Alerta(
            "No se pudo abrir el módulo de emisión de documentos.",
            "error",
          );
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

        let calculoCancelacion;
        try {
          const respuestaCalculo = await fetch(
            BASE_URL + "Reserva/calcularCancelacion",
            {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              body: JSON.stringify({ id_reserva: id }),
            },
          );
          calculoCancelacion = await respuestaCalculo.json();
        } catch (error) {
          console.error(error);
          await Swal.fire({
            icon: "error",
            title: "No se pudo calcular la devolución",
            text: "No se pudo conectar con el servidor.",
          });
          return;
        }

        if (!calculoCancelacion?.exito) {
          await Swal.fire({
            icon: "warning",
            title: "Cancelación no permitida",
            text:
              calculoCancelacion?.mensaje ||
              "No se puede cancelar esta reserva.",
          });
          return;
        }

        const dinero = (valor) => `S/ ${Number(valor || 0).toFixed(2)}`;
        Swal.fire({
          title: `Cancelar reserva ${codigo}`,
          html: `
            <p style="margin:0 0 8px; text-align:left;"><strong>Cliente:</strong> ${cliente}</p>
            <p style="margin:0 0 12px; text-align:left;"><strong>Check-in:</strong> ${checkin}</p>
            <div style="margin:0 0 14px; padding:10px; border:1px solid #ded8c9; text-align:left; font-size:13px;">
              <p style="margin:0 0 5px;"><strong>Monto pagado:</strong> ${dinero(calculoCancelacion.monto_pagado)}</p>
              <p style="margin:0 0 5px;"><strong>Noches hospedadas:</strong> ${dinero(calculoCancelacion.monto_usado)}</p>
              <p style="margin:0 0 5px;"><strong>Noches con boleta/factura:</strong> ${dinero(calculoCancelacion.monto_documentado)}</p>
              <p style="margin:0 0 5px;"><strong>Penalidad (${Number(calculoCancelacion.porcentaje_penalidad || 0)}%):</strong> ${dinero(calculoCancelacion.monto_penalidad)}</p>
              <p style="margin:0;"><strong>Monto a devolver:</strong> ${dinero(calculoCancelacion.monto_devuelto)}</p>
            </div>
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
          window.Alerta("No se pudo abrir el módulo de pago", "error");
        }
      }

      const btnCheckin = e.target.closest(".boton-checkin-reserva");
      if (btnCheckin) {
        const confirmado = await window.Confirmar(
          "¿Confirmar check-in para esta reserva?",
        );

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
        const fechaSalida = await window.SolicitarDato(
          "Extender reserva",
          "Selecciona la nueva fecha de checkout.",
          { tipo: "date" },
        );
        if (!fechaSalida) return;
        const horaSalida = await window.SolicitarDato(
          "Hora de checkout",
          "Selecciona la nueva hora de salida.",
          { tipo: "time", valor: "12:00" },
        );
        if (!horaSalida) return;
        ejecutarAccionReserva("extender", {
          id_reserva: btnExtender.dataset.id,
          nuevo_check_out: `${fechaSalida} ${horaSalida}`,
        });
        return;
      }

      const btnConsumo = e.target.closest(".boton-consumo-reserva");
      if (btnConsumo) {
        const concepto = await window.SolicitarDato(
          "Registrar consumo",
          "Ingresa el concepto del consumo.",
        );
        if (!concepto) return;
        const cantidad = await window.SolicitarDato(
          "Cantidad",
          "Ingresa la cantidad consumida.",
          {
            tipo: "number",
            valor: "1",
            atributos: { min: "1", step: "1" },
          },
        );
        if (!cantidad) return;
        const precioUnitario = await window.SolicitarDato(
          "Precio unitario",
          "Ingresa el precio unitario.",
          {
            tipo: "number",
            valor: "0",
            atributos: { min: "0.01", step: "0.01" },
          },
        );
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
        const idHabitacionNueva = await window.SolicitarDato(
          "Cambiar habitación",
          "ID de la nueva habitación disponible:",
          { tipo: "number", atributos: { min: "1", step: "1" } },
        );
        if (!idHabitacionNueva) return;
        const motivo = await window.SolicitarDato(
          "Motivo del cambio",
          "Describe por qué se cambia la habitación.",
        );
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

  window.addEventListener("resize", cerrarMenusOpciones);
  window.addEventListener("scroll", cerrarMenusOpciones, true);
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
      await Swal.fire({
        toast: true,
        position: "top-end",
        icon: "success",
        title: resultado.mensaje || "Acción procesada",
        showConfirmButton: false,
        timer: 2500,
        timerProgressBar: true,
      });
      window.location.reload();
    } else {
      if (accion === "checkout" && resultado.requiere_pago) {
        const decisionPago = await Swal.fire({
          icon: "warning",
          title: "Pago pendiente",
          text:
            resultado.mensaje ||
            "La reserva tiene saldo pendiente. Registre el pago antes del checkout.",
          showCancelButton: true,
          confirmButtonText: "Registrar pago",
          cancelButtonText: "Cancelar",
        });
        const abrirPago = decisionPago.isConfirmed;

        if (abrirPago && typeof window.abrirModalPago === "function") {
          const reserva = resultado.reserva || {};
          const saldoPendiente = Number(
            resultado.saldo_pendiente ?? reserva.saldo_pendiente ?? 0,
          );
          window.abrirModalPago({
            idReserva: datos.id_reserva,
            ...reserva,
            totalReserva:
              Number(reserva.total || 0) +
              Number(
                resultado.cargo_checkout_tarde ||
                  reserva.cargo_checkout_tarde ||
                  0,
              ),
            saldoPendiente,
            montoAutomatico: saldoPendiente,
            descripcionPago: "Pago de saldo pendiente para checkout",
            confirmarCheckoutDespuesPago: true,
            recargarAlCerrar: true,
          });
        } else {
          window.location.reload();
        }
        return;
      }

      Notificar(resultado.mensaje || "Error al procesar", "error");
    }
  } catch (error) {
    console.error(error);
    window.Alerta("Error de conexión con el servidor.", "error");
  }
};

// La inicialización se ejecuta desde main.js para evitar duplicar listeners.
