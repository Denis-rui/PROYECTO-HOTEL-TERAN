window.inicializarReservas = () => {
  if (window.__reservasEventosInicializados) return;
  window.__reservasEventosInicializados = true;

  inicializarTablaReservas();
  configurarEventosReservas();
};

const inicializarTablaReservas = () => {
  const tabla = document.getElementById("tablaReservas");
  if (!tabla || typeof DataTable === "undefined") return;

  const inputBusqueda = document.getElementById("inputBuscarReserva");
  const filtroEstado = document.getElementById("filtroEstado");
  const filtroHoy = document.getElementById("filtroHoyReserva");
  const formularioFiltros = inputBusqueda?.closest("form");

  // Esta instancia reemplaza la carga MVC de filas por una carga Ajax server-side.
  // DataTables mandará paginación, orden y búsqueda al endpoint Reserva/datatable.
  const tablaReservas = new DataTable("#tablaReservas", {
    processing: true,
    serverSide: true,
    pageLength: 30,
    lengthMenu: [10, 30, 50, 100],
    searching: false,
    info: false,
    ajax: {
      url: BASE_URL + "Reserva/datatable",
      type: "POST",
      headers: {
        "X-CSRF-Token": typeof CSRF_TOKEN !== "undefined" ? CSRF_TOKEN : "",
      },
      data: (datos) => {
        // Además de los datos propios de DataTables, enviamos nuestros filtros externos.
        const filtroHoyActivo = filtroHoy?.value || "";
        datos.csrf_token = typeof CSRF_TOKEN !== "undefined" ? CSRF_TOKEN : "";
        datos.filtro_hoy = filtroHoyActivo;
        datos.busqueda = filtroHoyActivo ? "" : inputBusqueda?.value?.trim() || "";
        datos.estado = filtroHoyActivo ? "" : filtroEstado?.value || "";
        return datos;
      },
    },
    order: [],
    columns: [
      { data: "cliente", render: renderTextoSeguro },
      {
        data: null,
        orderable: false,
        render: (_, __, reserva) => renderHabitaciones(reserva),
      },
      { data: "check_in", render: renderFechaReserva },
      {
        data: "check_out",
        render: (_, __, reserva) => renderCheckOut(reserva),
      },
      { data: "estado", render: renderEstadoReserva },
      {
        data: "porcentaje_pago",
        orderable: false,
        searchable: false,
        render: renderPagoReserva,
      },
      {
        data: null,
        orderable: false,
        searchable: false,
        render: (_, __, reserva) => renderAccionesReserva(reserva),
      },
    ],
    language: {
      processing: "Cargando reservas...",
      emptyTable: "No hay reservas para mostrar.",
      zeroRecords: "No se encontraron reservas con esos filtros.",
      lengthMenu: "Mostrar _MENU_ reservas",
      info: "Mostrando _START_ a _END_ de _TOTAL_ reservas",
      infoEmpty: "Mostrando 0 reservas",
      infoFiltered: "(filtrado de _MAX_ reservas)",
      paginate: {
        first: "Primero",
        last: "Último",
        next: "Siguiente",
        previous: "Anterior",
      },
    },
  });

  // Guardamos una función global para refrescar la tabla después de check-in, pago, cancelación, etc.
  window.recargarTablaReservas = () => {
    tablaReservas.ajax.reload(null, false);
    return true;
  };

  // Permite a los eventos delegados recuperar el objeto completo de la fila.
  // Así evitamos guardar toda la reserva en atributos data-* del HTML.
  window.obtenerReservaDesdeFila = (fila) =>
    tablaReservas.row(fila).data() || null;

  let temporizadorBusqueda;
  inputBusqueda?.addEventListener("input", () => {
    if (filtroHoy) filtroHoy.value = "";
    clearTimeout(temporizadorBusqueda);
    temporizadorBusqueda = setTimeout(() => tablaReservas.ajax.reload(), 350);
  });

  filtroEstado?.addEventListener("change", () => {
    if (filtroHoy) filtroHoy.value = "";
    tablaReservas.ajax.reload();
  });

  filtroHoy?.addEventListener("change", () => {
    if (filtroHoy.value) {
      if (inputBusqueda) inputBusqueda.value = "";
      if (filtroEstado) filtroEstado.value = "";
    }
    tablaReservas.ajax.reload();
  });

  formularioFiltros?.addEventListener("submit", (e) => {
    e.preventDefault();
    tablaReservas.ajax.reload();
  });

  formularioFiltros
    ?.querySelector(".btn-limpiar-filtros")
    ?.addEventListener("click", (e) => {
      e.preventDefault();
      if (inputBusqueda) inputBusqueda.value = "";
      if (filtroEstado) filtroEstado.value = "";
      if (filtroHoy) filtroHoy.value = "";
      tablaReservas.ajax.reload();
    });
};

const recargarReservasDespuesDeAccion = () => {
  if (typeof window.recargarTablaReservas === "function") {
    window.recargarTablaReservas();
    return;
  }

  window.location.reload();
};

const escaparHtml = (valor) =>
  String(valor ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");

const renderTextoSeguro = (valor) => escaparHtml(valor);

const formatearFechaReserva = (fecha) => {
  if (!fecha) return "Sin fecha";
  const texto = String(fecha).trim();
  const coincidencia = texto.match(
    /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/,
  );

  if (!coincidencia) return texto;

  const [, anio, mes, dia, hora, minuto] = coincidencia;
  return `${hora}:${minuto} ${dia}/${mes}/${anio}`;
};

const renderFechaReserva = (fecha) => escaparHtml(formatearFechaReserva(fecha));

const renderHabitaciones = (reserva) => {
  const habitaciones = Array.isArray(reserva?.habitaciones)
    ? reserva.habitaciones
    : [];

  if (!habitaciones.length) {
    return escaparHtml(reserva?.habitacion || "Sin habitación");
  }

  return habitaciones
    .map((habitacion) => {
      const numero = habitacion?.numero_habitacion || "";
      const piso = habitacion?.piso || "";
      const tipo = habitacion?.tipo_nombre || "";
      const texto =
        `Hab. ${numero}${piso !== "" ? ` - Piso ${piso}` : ""}${tipo !== "" ? ` - ${tipo}` : ""}`.trim();
      return escaparHtml(texto);
    })
    .filter(Boolean)
    .join("<br>");
};

const renderCheckOut = (reserva) => {
  const fecha = renderFechaReserva(reserva?.check_out);
  const badges = [];

  if (Number(reserva?.minutos_checkout_vencido || 0) > 0) {
    badges.push('<span class="badge-vencido">Checkout vencido</span>');
  } else if (reserva?.checkout_hoy) {
    badges.push('<span class="badge-checkout-hoy">Checkout hoy</span>');
  }

  return `${fecha}${badges.length ? `<br>${badges.join("")}` : ""}`;
};

const textoEstadoReserva = (estado) => {
  const mapa = {
    confirmada: "Confirmada",
    en_estadia: "En estadía",
    ausente: "Ausente",
    checkout_pendiente: "Checkout pendiente",
    checkout_realizado: "Checkout realizado",
    cancelada: "Cancelada",
  };

  const clave = String(estado || "")
    .trim()
    .toLowerCase();
  return mapa[clave] || clave.charAt(0).toUpperCase() + clave.slice(1);
};

const claseEstadoReserva = (estado) => {
  const mapa = {
    confirmada: "estado-confirmada",
    en_estadia: "estado-en-estadia",
    ausente: "estado-ausente",
    checkout_pendiente: "estado-checkout-pendiente",
    checkout_realizado: "estado-checkout-realizado",
    cancelada: "estado-cancelada",
  };

  return (
    mapa[
      String(estado || "")
        .trim()
        .toLowerCase()
    ] || "estado-reserva-desconocido"
  );
};

const renderEstadoReserva = (estado) =>
  `<span class="estado-reserva ${claseEstadoReserva(estado)}">${escaparHtml(textoEstadoReserva(estado))}</span>`;

const renderPagoReserva = (porcentaje) => {
  const valor = Math.max(0, Math.min(100, Number(porcentaje || 0)));
  return `
    <div class="barra-pago-reserva" aria-label="Pago ${valor}%">
      <span class="barra-pago-reserva-fill" style="width:${valor}%"></span>
      <span class="barra-pago-reserva-texto">${valor}%</span>
    </div>
  `;
};

const tieneAccionReserva = (reserva, accion) =>
  Array.isArray(reserva?.acciones_disponibles) &&
  reserva.acciones_disponibles.includes(accion);

const renderAccionesReserva = (reserva) => {
  const estado = String(reserva?.estado || "").toLowerCase();
  const editarDisabled =
    estado === "checkout_realizado"
      ? ' disabled title="No se puede editar una reserva con checkout realizado"'
      : "";
  const partes = [
    `<button type="button" class="boton-editar-reserva"${editarDisabled}>✏️</button>`,
  ];

  if (tieneAccionReserva(reserva, "checkin")) {
    partes.push(
      '<button type="button" class="boton-checkin-reserva" title="Confirmar check-in">Check-in</button>',
    );
  }

  if (tieneAccionReserva(reserva, "checkout")) {
    partes.push(
      '<button type="button" class="boton-checkout-reserva" title="Confirmar checkout">Checkout</button>',
    );
  }

  if (Number(reserva?.porcentaje_pago || 0) < 100) {
    partes.push(
      '<button type="button" class="boton-pago-tabla" title="Registrar pago">💳</button>',
    );
  }

  partes.push(renderMenuAccionesReserva(reserva));

  return `<div class="acciones-reserva-wrap">${partes.join("")}</div>`;
};

const renderMenuAccionesReserva = (reserva) => {
  const opciones = [];

  if (tieneAccionReserva(reserva, "marcar_ausente")) {
    opciones.push(
      '<button type="button" class="item-menu-opcion accion-marcar-ausente">Marcar ausente</button>',
    );
  }

  if (tieneAccionReserva(reserva, "marcar_regreso")) {
    opciones.push(
      '<button type="button" class="item-menu-opcion accion-marcar-regreso">Marcar regreso</button>',
    );
  }

  if (tieneAccionReserva(reserva, "emitir_documento")) {
    opciones.push(
      '<button type="button" class="item-menu-opcion accion-emitir-documento">Emitir boleta / factura</button>',
    );
  }

  if (tieneAccionReserva(reserva, "ver_detalles")) {
    opciones.push(
      '<button type="button" class="item-menu-opcion accion-ver-detalles">Ver detalles</button>',
    );
  }

  if (tieneAccionReserva(reserva, "cancelar")) {
    opciones.push(
      '<button type="button" class="item-menu-opcion accion-cancelar-reserva">Cancelar reserva</button>',
    );
  }

  return `
    <div class="menu-mas-opciones-wrap">
      <button type="button" class="boton-mas-opciones" aria-label="Más opciones">⋮</button>
      <div class="menu-mas-opciones-panel">${opciones.join("")}</div>
    </div>
  `;
};

const obtenerReservaDesdeEvento = (elemento) => {
  const fila = elemento?.closest("tr");
  if (!fila || typeof window.obtenerReservaDesdeFila !== "function")
    return null;
  return window.obtenerReservaDesdeFila(fila);
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
        const reserva = obtenerReservaDesdeEvento(accionMarcarAusente);
        if (!reserva) return;
        const confirmado = await window.Confirmar(
          "¿Marcar esta reserva como ausente?",
        );
        if (!confirmado) return;

        ejecutarAccionReserva("marcarAusente", {
          id_reserva: reserva.id,
        });
        return;
      }

      const accionMarcarRegreso = e.target.closest(".accion-marcar-regreso");
      if (accionMarcarRegreso) {
        cerrarMenusOpciones();
        const reserva = obtenerReservaDesdeEvento(accionMarcarRegreso);
        if (!reserva) return;
        const confirmado = await window.Confirmar(
          "¿Marcar regreso y volver la reserva a en estadía?",
        );
        if (!confirmado) return;

        ejecutarAccionReserva("marcarRegreso", {
          id_reserva: reserva.id,
        });
        return;
      }

      const accionVerDetalles = e.target.closest(".accion-ver-detalles");
      if (accionVerDetalles) {
        cerrarMenusOpciones();
        const reserva = obtenerReservaDesdeEvento(accionVerDetalles);
        if (!reserva) return;

        if (typeof window.abrirModalVerDetalles === "function") {
          window.abrirModalVerDetalles(reserva);
        } else {
          window.Alerta("No se pudo abrir el módulo de detalles", "error");
        }

        return;
      }

      const accionEmitirDocumento = e.target.closest(
        ".accion-emitir-documento",
      );
      if (accionEmitirDocumento) {
        cerrarMenusOpciones();
        const reserva = obtenerReservaDesdeEvento(accionEmitirDocumento);
        if (!reserva) return;

        const estadosConCheckIn = [
          "en_estadia",
          "checkout_pendiente",
          "checkout_realizado",
        ];
        if (
          !estadosConCheckIn.includes(
            String(reserva.estado || "").toLowerCase(),
          )
        ) {
          window.Alerta(
            "Solo se puede emitir una boleta o factura después de realizar el check-in del cliente.",
            "error",
          );
          return;
        }

        if (typeof window.abrirModalDocumentoElectronico === "function") {
          window.abrirModalDocumentoElectronico(reserva);
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
        const reserva = obtenerReservaDesdeEvento(accionCancelar);
        if (!reserva) return;

        const id = reserva.id;
        const codigo = reserva.codigo_reserva || `#${id}`;
        const cliente = reserva.cliente || "";
        const checkin = formatearFechaReserva(reserva.check_in);

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
              recargarReservasDespuesDeAccion();
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
        const reserva = obtenerReservaDesdeEvento(btnEditar);
        if (!reserva) return;
        window.abrirModalReserva("editar", { id: Number(reserva.id) });
        return;
      }

      // Evento para botón de pago
      const btnPago = e.target.closest(".boton-pago-tabla");
      if (btnPago) {
        const reserva = obtenerReservaDesdeEvento(btnPago);
        if (!reserva) return;
        const idReserva = reserva.id;

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
        const reserva = obtenerReservaDesdeEvento(btnCheckin);
        if (!reserva) return;
        const confirmado = await window.Confirmar(
          "¿Confirmar check-in para esta reserva?",
        );

        if (!confirmado) return;

        ejecutarAccionReserva("checkin", { id_reserva: reserva.id });
        return;
      }

      const btnCheckout = e.target.closest(".boton-checkout-reserva");
      if (btnCheckout) {
        const reserva = obtenerReservaDesdeEvento(btnCheckout);
        if (!reserva) return;
        ejecutarAccionReserva("checkout", {
          id_reserva: reserva.id,
        });
        return;
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
      recargarReservasDespuesDeAccion();
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
          recargarReservasDespuesDeAccion();
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
