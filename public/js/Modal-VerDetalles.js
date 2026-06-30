(() => {
  const estadoModal = {
    ultimaReserva: null,
  };

  const safeParseArray = (valor, fallback = []) => {
    if (Array.isArray(valor)) return valor;
    if (!valor) return fallback;

    try {
      const parsed = JSON.parse(valor);
      return Array.isArray(parsed) ? parsed : fallback;
    } catch (error) {
      return fallback;
    }
  };

  const toNumber = (valor) => {
    const numero = Number(String(valor ?? 0).replace(/[^0-9.-]/g, ""));
    return Number.isFinite(numero) ? numero : 0;
  };

  const formatMoney = (valor) => {
    return `S/ ${toNumber(valor).toFixed(2)}`;
  };

  const escapeHtml = (valor) =>
    String(valor ?? "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");

  const formatDateTime = (valor) => {
    if (!valor) return "---";

    const fecha = new Date(valor);
    if (Number.isNaN(fecha.getTime())) return String(valor);

    return new Intl.DateTimeFormat("es-PE", {
      day: "2-digit",
      month: "short",
      year: "numeric",
      hour: "2-digit",
      minute: "2-digit",
    }).format(fecha);
  };

  const formatDateOnly = (valor) => {
    if (!valor) return "---";

    const texto = String(valor).slice(0, 10);
    const partes = texto.split("-").map(Number);
    const fecha =
      partes.length === 3 && partes.every(Number.isFinite)
        ? new Date(partes[0], partes[1] - 1, partes[2])
        : new Date(valor);
    if (Number.isNaN(fecha.getTime())) return String(valor);

    return new Intl.DateTimeFormat("es-PE", {
      day: "2-digit",
      month: "short",
      year: "numeric",
    }).format(fecha);
  };

  const normalizarEstado = (estado) =>
    String(estado || "")
      .trim()
      .toLowerCase();

  const etiquetaEstado = (estado) => {
    const mapa = {
      confirmada: "Confirmada",
      en_estadia: "En estadía",
      ausente: "Ausente",
      checkout_realizado: "Checkout realizado",
      cancelada: "Cancelada",
      emitido: "Emitido",
      aceptado: "Aceptado por SUNAT",
      aceptada: "Aceptado por SUNAT",
      rechazado: "Rechazado por SUNAT",
      pendiente: "Pendiente",
    };

    return mapa[normalizarEstado(estado)] || "Pendiente";
  };

  const formatearMotivoCambio = (motivo) => {
    const texto = String(motivo || "").trim();
    if (!texto) return "Cambio de habitación";

    const partes = texto.split(":");
    const tipo = partes.shift()?.trim().toLowerCase() || "";
    const descripcion = partes.join(":").trim();
    const etiquetas = {
      falla_hotel: "Falla Hotel",
      solicitud_cliente: "Cambio elegido por el cliente",
    };

    return etiquetas[tipo]
      ? `${etiquetas[tipo]}${descripcion ? `: ${descripcion}` : ""}`
      : texto;
  };

  const generarDocumentosBase = (reserva) => {
    const documentos = safeParseArray(reserva.documentos, []);
    return documentos.filter(
      (documento) =>
        documento &&
        (documento.es_documento_electronico ||
          normalizarEstado(documento.estado) === "emitido"),
    );
  };

  const cargarComprobantesEmitidos = async (idReserva) => {
    if (!idReserva) return [];

    try {
      const respuesta = await fetch(
        `${BASE_URL}Comprobante/emitidosPorReserva/${encodeURIComponent(idReserva)}`,
      );

      if (!respuesta.ok) {
        return [];
      }

      const datos = await respuesta.json();
      return Array.isArray(datos) ? datos : [];
    } catch (error) {
      console.error("Error cargando comprobantes emitidos:", error);
      return [];
    }
  };

  const setText = (selector, texto) => {
    const elemento = document.querySelector(selector);
    if (elemento) {
      elemento.textContent = texto;
    }
  };

  const limpiarTablaDocumentos = () => {
    const contenedor = document.getElementById("listaDocumentosReserva");
    if (!contenedor) return;

    contenedor.innerHTML = `
			<tr class="fila-vacia-documentos">
				<td colspan="5">No hay comprobantes emitidos aún.</td>
			</tr>
		`;
  };

  const renderizarDocumentos = (reserva) => {
    const contenedor = document.getElementById("listaDocumentosReserva");
    const contador = document.getElementById("contadorDocumentosReserva");
    const resumenPago = document.getElementById("resumenPagoReserva");
    if (!contenedor) return;

    const documentos = generarDocumentosBase(reserva);
    const porcentajePago = toNumber(reserva.porcentaje_pago);

    if (contador) {
      contador.textContent = `${documentos.length} emitido${documentos.length === 1 ? "" : "s"}`;
    }

    if (resumenPago) {
      resumenPago.textContent =
        porcentajePago > 0 ? `Pago registrado ${porcentajePago}%` : "";
    }

    if (!documentos.length) {
      limpiarTablaDocumentos();
      return;
    }

    contenedor.innerHTML = documentos
      .map(
        (documento, indice) => {
          const esElectronico = Boolean(documento.es_documento_electronico);
          const rango =
            documento.fecha_desde && documento.fecha_hasta
              ? `${formatDateOnly(documento.fecha_desde)} al ${formatDateOnly(documento.fecha_hasta)}`
              : "";
          const detalle = [documento.descripcion, rango].filter(Boolean).join(" | ");

          return `
				<tr>
					<td>
						<span class="documento-badge">${documento.tipo || "Documento"} ${documento.numero ? `#${documento.numero}` : ""}</span>
            ${detalle ? `<small class="documento-detalle">${escapeHtml(detalle)}</small>` : ""}
					</td>
					<td>${formatDateTime(documento.fecha)}</td>
					<td>${formatMoney(documento.monto)}</td>
					<td>
						<span class="documento-estado ${normalizarEstado(documento.estado) || "pendiente"}">${etiquetaEstado(documento.estado)}</span>
					</td>
					<td>
						<div class="documento-acciones">
							${esElectronico ? "" : `<button type="button" class="boton-documento enviar" data-accion="enviar" data-indice="${indice}">Enviar</button>`}
							<button type="button" class="boton-documento imprimir" data-accion="imprimir" data-indice="${indice}">${documento.enlace_del_pdf || documento.enlace ? "Ver PDF" : "Imprimir"}</button>
						</div>
					</td>
				</tr>
			`;
        },
      )
      .join("");
  };

  const cargarResumen = (reserva) => {
    const habitaciones =
      Array.isArray(reserva.habitaciones) && reserva.habitaciones.length > 0
        ? reserva.habitaciones
        : [];
    const habitacionesHistorial = Array.isArray(reserva.habitaciones_historial)
      ? reserva.habitaciones_historial
      : habitaciones;

    const formatearHabitacion = (habitacion) => {
      if (typeof habitacion !== "object" || habitacion === null) return "";
      const numero = habitacion.numero_habitacion || habitacion.numero || "";
      const piso = habitacion.piso ? `Piso ${habitacion.piso}` : "";
      const tipo = habitacion.tipo_nombre || habitacion.tipo || "";
      return [numero ? `Hab. ${numero}` : "", piso, tipo]
        .filter(Boolean)
        .join(" - ");
    };

    const habitacionesTexto = habitaciones.length
      ? habitaciones
          .map(formatearHabitacion)
          .filter(Boolean)
          .join(" | ")
      : reserva.habitacion || "---";

    setText("#detalleReservaCodigo", reserva.codigo_reserva || reserva.id || "---");
    setText("#detalleReservaCliente", reserva.cliente || "---");
    setText("#detalleReservaEstado", etiquetaEstado(reserva.estado));
    setText("#detalleReservaPago", `${toNumber(reserva.porcentaje_pago)}%`);
    setText("#detalleReservaSaldo", formatMoney(reserva.saldo_pendiente));
    setText("#detalleReservaClienteNombre", reserva.cliente || "---");
    setText(
      "#detalleReservaClienteDocumento",
      [
        reserva.documento_tipo_nombre || "Documento",
        reserva.documento || "",
      ]
        .filter(Boolean)
        .join(": ") || "---",
    );
    setText("#detalleReservaClienteTelefono", reserva.telefono || "---");
    setText(
      "#detalleReservaClienteEmail",
      reserva.email || reserva.correo_electronico || "---",
    );
    setText("#detalleReservaClienteProcedencia", reserva.procedencia || "---");
    const listaHabitaciones = document.getElementById(
      "detalleReservaHabitacionesLista",
    );
    if (listaHabitaciones) {
      if (habitacionesHistorial.length) {
        const cambiosActivos = habitacionesHistorial.filter(
          (habitacion) => habitacion.tipo_asignacion === "cambio",
        );
        const habitacionesOrdenadas = [
          ...habitacionesHistorial.filter((habitacion) => {
            const estadoAsignacion = normalizarEstado(
              habitacion.estado_asignacion || habitacion.estado,
            );
            return estadoAsignacion === "activa";
          }),
          ...habitacionesHistorial.filter((habitacion) => {
            const estadoAsignacion = normalizarEstado(
              habitacion.estado_asignacion || habitacion.estado,
            );
            return estadoAsignacion !== "activa";
          }),
        ];

        listaHabitaciones.innerHTML = habitacionesOrdenadas
          .map((habitacion) => {
            const texto = formatearHabitacion(habitacion);
            if (!texto) return "";

            const estadoAsignacion = normalizarEstado(
              habitacion.estado_asignacion || habitacion.estado,
            );
            if (estadoAsignacion === "cambiada") {
              const reemplazo = cambiosActivos.find(
                (item) =>
                  item.motivo_cambio === habitacion.motivo_cambio &&
                  item.fecha_movimiento === habitacion.fecha_movimiento,
              );
              return `
                <li class="detalle-habitacion-cambiada">
                  <span class="detalle-habitacion-badge">Cambiada</span>
                  <div><small>Habitación anterior</small><strong>${escapeHtml(texto)}</strong></div>
                  ${
                    reemplazo
                      ? `<div><small>Actualizada por</small><strong>${escapeHtml(formatearHabitacion(reemplazo))}</strong></div>`
                      : ""
                  }
                  <p>${escapeHtml(formatearMotivoCambio(habitacion.motivo_cambio))}</p>
                </li>`;
            }

            if (habitacion.tipo_asignacion === "cambio") {
              return `
                <li class="detalle-habitacion-nueva">
                  <span class="detalle-habitacion-badge">Actual</span>
                  ${escapeHtml(texto)}
                </li>`;
            }

            return `<li>${escapeHtml(texto)}</li>`;
          })
          .filter(Boolean)
          .join("");
      } else {
        listaHabitaciones.innerHTML = "<li>---</li>";
      }
    }
    setText("#detalleReservaHabitaciones", habitacionesTexto);
    setText("#detalleReservaCheckIn", formatDateOnly(reserva.check_in));
    setText(
      "#detalleReservaHoraEntrada",
      reserva.hora_entrada || reserva.horaEntrada || "---",
    );
    setText("#detalleReservaCheckOut", formatDateOnly(reserva.check_out));
    setText(
      "#detalleReservaHoraSalida",
      reserva.hora_salida || reserva.horaSalida || "---",
    );
    setText("#detalleReservaTotal", formatMoney(reserva.total));
    setText(
      "#detalleReservaSaldoDetalle",
      `Saldo pendiente: ${formatMoney(reserva.saldo_pendiente)}`,
    );
    setText(
      "#detalleReservaUsuario",
      reserva.usuario || reserva.usuario_nombre || "Sistema",
    );
  };

  const mostrarModal = () => {
    const contenedor = document.getElementById("contenedor-modal-ver-detalles");
    const modal = document.getElementById("modalVerDetalles");
    const body = document.body;

    if (contenedor) contenedor.style.display = "block";
    if (modal) modal.style.display = "block";
    if (body) body.style.overflow = "hidden";
  };

  const ocultarModal = () => {
    const contenedor = document.getElementById("contenedor-modal-ver-detalles");
    const modal = document.getElementById("modalVerDetalles");
    const body = document.body;

    if (modal) modal.style.display = "none";
    if (contenedor) contenedor.style.display = "none";
    if (body) body.style.overflow = "";
  };

  const accionEnviarDocumento = async (indice) => {
    const documentos = generarDocumentosBase(estadoModal.ultimaReserva || {});
    const documento = documentos[indice];
    if (!documento) return;

    const correoBase =
      estadoModal.ultimaReserva?.email ||
      estadoModal.ultimaReserva?.correo_electronico ||
      documento.correo ||
      "";

    const resultado = await Swal.fire({
      title: `Enviar ${documento.tipo || "documento"}`,
      text: "Confirma o modifica el correo antes de enviar.",
      input: "email",
      inputValue: correoBase,
      inputLabel: "Correo electrónico",
      inputPlaceholder: "cliente@correo.com",
      showCancelButton: true,
      confirmButtonText: "Enviar",
      cancelButtonText: "Cancelar",
      confirmButtonColor: "#185025",
      cancelButtonColor: "#8f2f2f",
      inputValidator: (value) => {
        if (!value) return "Debes ingresar un correo válido.";
        return null;
      },
    });

    if (!resultado.isConfirmed) return;

    await Swal.fire({
      icon: "success",
      title: "Correo preparado",
      text: `El documento se enviará a ${resultado.value}.`,
      confirmButtonColor: "#185025",
    });
  };

  const accionImprimirDocumento = async (indice) => {
    const documentos = generarDocumentosBase(estadoModal.ultimaReserva || {});
    const documento = documentos[indice];
    if (!documento) return;

    if (documento.enlace_del_pdf || documento.enlace) {
      const url = documento.enlace_del_pdf || documento.enlace;
      window.open(url, "_blank", "noopener,noreferrer");
      return;
    }

    const resultado = await Swal.fire({
      title: `Imprimir ${documento.tipo || "documento"}`,
      text: "Se abrirá la impresión de la ficha de detalles.",
      icon: "question",
      showCancelButton: true,
      confirmButtonText: "Imprimir",
      cancelButtonText: "Cancelar",
      confirmButtonColor: "#185025",
      cancelButtonColor: "#8f2f2f",
    });

    if (!resultado.isConfirmed) return;

    if (
      documento.id_pago &&
      typeof window.abrirModalComprobante === "function"
    ) {
      try {
        const respuesta = await fetch(
          BASE_URL +
            `Comprobante/obtenerPorPago/${encodeURIComponent(documento.id_pago)}`,
        );
        const comprobante = await respuesta.json();
        if (!respuesta.ok || !comprobante) {
          throw new Error("No se pudo recuperar el ticket.");
        }

        window.abrirModalComprobante(comprobante, {
          recargarAlCerrar: false,
        });
        return;
      } catch (error) {
        console.error(error);
        await Swal.fire({
          icon: "error",
          title: "No se pudo abrir el ticket",
          text: "No fue posible cargar los datos para reimprimirlo.",
          confirmButtonColor: "#185025",
        });
        return;
      }
    }

    if (typeof window.abrirModalComprobante === "function") {
      window.abrirModalComprobante({
        numero_ticket: documento.numero || "---",
        fecha_emision: documento.fecha || "",
        total: documento.monto || 0,
        descripcion: documento.descripcion || "",
        id_forma_pago: documento.id_forma_pago,
        cliente: estadoModal.ultimaReserva?.cliente || "---",
        reserva: {
          codigo_reserva:
            estadoModal.ultimaReserva?.codigo_reserva ||
            estadoModal.ultimaReserva?.id ||
            "---",
          total: estadoModal.ultimaReserva?.total || 0,
          habitaciones: estadoModal.ultimaReserva?.habitaciones || [],
        },
      }, {
        recargarAlCerrar: false,
      });
      return;
    }

    await Swal.fire({
      icon: "error",
      title: "Impresión no disponible",
      text: "No se pudo abrir la vista imprimible del ticket.",
    });
  };

  const abrirModalVerDetalles = async (datos = {}) => {
    estadoModal.ultimaReserva = {
      ...datos,
      documentos: [],
      habitaciones: Array.isArray(datos.habitaciones) ? datos.habitaciones : [],
    };

    if (estadoModal.ultimaReserva.id) {
      try {
        const respuesta = await fetch(
          BASE_URL + `Reserva/obtener/${encodeURIComponent(estadoModal.ultimaReserva.id)}`,
        );
        const reservaCompleta = await respuesta.json();
        if (reservaCompleta?.id) {
          estadoModal.ultimaReserva = {
            ...estadoModal.ultimaReserva,
            ...reservaCompleta,
            documentos: [],
          };
        }
      } catch (error) {
        console.error("No se pudo cargar el detalle completo de la reserva:", error);
      }
    }

    cargarResumen(estadoModal.ultimaReserva);
    limpiarTablaDocumentos();
    mostrarModal();

    const documentos = await cargarComprobantesEmitidos(
      estadoModal.ultimaReserva.id,
    );
    estadoModal.ultimaReserva.documentos = documentos;
    renderizarDocumentos(estadoModal.ultimaReserva);
  };

  const configurarEventos = () => {
    const botonCerrar = document.getElementById("cerrarModalVerDetalles");
    const botonCerrarFooter = document.getElementById("btnCerrarVerDetalles");
    const overlay = document.getElementById("contenedor-modal-ver-detalles");
    const tablaDocumentos = document.getElementById("listaDocumentosReserva");

    botonCerrar?.addEventListener("click", ocultarModal);
    botonCerrarFooter?.addEventListener("click", ocultarModal);

    overlay?.addEventListener("click", (evento) => {
      if (evento.target === overlay) {
        ocultarModal();
      }
    });

    tablaDocumentos?.addEventListener("click", (evento) => {
      const botonEnviar = evento.target.closest('[data-accion="enviar"]');
      if (botonEnviar) {
        accionEnviarDocumento(Number(botonEnviar.dataset.indice || 0));
        return;
      }

      const botonImprimir = evento.target.closest('[data-accion="imprimir"]');
      if (botonImprimir) {
        accionImprimirDocumento(Number(botonImprimir.dataset.indice || 0));
      }
    });

    document.addEventListener("keydown", (evento) => {
      if (evento.key === "Escape") {
        const contenedorVisible = document.getElementById(
          "contenedor-modal-ver-detalles",
        );
        if (contenedorVisible?.style.display === "block") {
          ocultarModal();
        }
      }
    });
  };

  window.abrirModalVerDetalles = abrirModalVerDetalles;
  window.cerrarModalVerDetalles = ocultarModal;

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", configurarEventos, {
      once: true,
    });
  } else {
    configurarEventos();
  }
})();
