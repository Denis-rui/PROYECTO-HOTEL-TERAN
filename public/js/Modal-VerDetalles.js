(() => {
  const estadoModal = {
    ultimaReserva: null,
  };

  const safeParseArray = (valor, fallback = []) => {
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

    const fecha = new Date(valor);
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
      checkout_realizado: "Checkout realizado",
      cancelada: "Cancelada",
      emitido: "Emitido",
      pendiente: "Pendiente",
    };

    return mapa[normalizarEstado(estado)] || "Pendiente";
  };

  const descripcionTipo = (tipo) => {
    const mapa = {
      CREACION: "Reserva realizada",
      EDICION: "Reserva actualizada",
      CHECK_IN: "Entrada confirmada",
      CHECK_OUT: "Salida confirmada",
      CANCELACION: "Reserva cancelada",
      NO_SHOW: "No show registrado",
      PAGO: "Pago registrado",
      COMPROBANTE: "Documento emitido",
      PENALIDAD: "Penalidad aplicada",
    };

    return mapa[String(tipo || "").toUpperCase()] || String(tipo || "Evento");
  };

  const iconoTipo = (tipo) => {
    const normalizado = String(tipo || "").toUpperCase();
    if (normalizado.includes("CHECK_IN")) return "Entrada";
    if (normalizado.includes("CHECK_OUT")) return "Salida";
    if (normalizado.includes("PAGO")) return "Pago";
    if (normalizado.includes("COMPROBANTE")) return "Documento";
    if (normalizado.includes("CANCEL")) return "Cancelación";
    return "Reserva";
  };

  const generarHistorialBase = (reserva) => {
    const historial = safeParseArray(reserva.historial, []);
    if (historial.length > 0) return historial;

    const eventos = [];
    const fechaInicial =
      reserva.fecha_reserva || reserva.created_at || reserva.check_in || "";

    eventos.push({
      tipo: "CREACION",
      descripcion: "Reserva realizada",
      fecha: fechaInicial,
      estado_nuevo: reserva.estado || "",
      monto: reserva.total || null,
      id_usuario: reserva.usuario || "",
    });

    if (reserva.check_in) {
      eventos.push({
        tipo: "CHECK_IN",
        descripcion: "Check-in confirmado",
        fecha: reserva.check_in,
        estado_nuevo: "en_estadia",
      });
    }

    if (reserva.porcentaje_pago && toNumber(reserva.porcentaje_pago) > 0) {
      eventos.push({
        tipo: "PAGO",
        descripcion: "Pago registrado",
        fecha: reserva.fecha_pago || reserva.check_in || "",
        monto:
          (toNumber(reserva.total) * toNumber(reserva.porcentaje_pago)) / 100,
      });
    }

    if (normalizarEstado(reserva.estado) === "cancelada") {
      eventos.push({
        tipo: "CANCELACION",
        descripcion: "Reserva cancelada",
        fecha:
          reserva.fecha_cancelacion ||
          reserva.check_out ||
          reserva.check_in ||
          "",
        estado_nuevo: "cancelada",
      });
    }

    if (reserva.check_out) {
      eventos.push({
        tipo: "CHECK_OUT",
        descripcion: "Checkout confirmado",
        fecha: reserva.check_out,
        estado_nuevo: "checkout_realizado",
      });
    }

    return eventos;
  };

  const generarDocumentosBase = (reserva) => {
    const documentos = safeParseArray(reserva.documentos, []);
    if (documentos.length > 0) return documentos;

    const porcentajePago = toNumber(reserva.porcentaje_pago);
    const total = toNumber(reserva.total);
    const saldo = toNumber(reserva.saldo_pendiente);
    const email = reserva.email || reserva.correo_electronico || "";

    const docs = [
      {
        tipo: "Voucher",
        numero: `V-${reserva.id || "0000"}`,
        fecha:
          reserva.fecha_pago || reserva.check_in || reserva.fecha_reserva || "",
        estado: porcentajePago > 0 ? "emitido" : "pendiente",
        monto: total > 0 ? (total * porcentajePago) / 100 : 0,
        correo: email,
      },
    ];

    if (normalizarEstado(reserva.estado) !== "cancelada") {
      docs.push({
        tipo: "Comprobante",
        numero: `C-${reserva.id || "0000"}`,
        fecha:
          reserva.check_out || reserva.check_in || reserva.fecha_reserva || "",
        estado: "pendiente",
        monto: total,
        correo: email,
      });
    }

    if (saldo > 0) {
      docs.push({
        tipo: "Saldo",
        numero: `S-${reserva.id || "0000"}`,
        fecha:
          reserva.check_out || reserva.check_in || reserva.fecha_reserva || "",
        estado: "pendiente",
        monto: saldo,
        correo: email,
      });
    }

    return docs;
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
				<td colspan="5">No hay documentos cargados aún.</td>
			</tr>
		`;
  };

  const renderizarHistorial = (reserva) => {
    const contenedor = document.getElementById("timelineReservaHistorial");
    if (!contenedor) return;

    const historial = generarHistorialBase(reserva);

    if (!historial.length) {
      contenedor.innerHTML = `
				<article class="timeline-item">
					<div class="timeline-marker"></div>
					<div class="timeline-card">
						<div class="timeline-card-top">
							<strong>Sin historial registrado</strong>
							<span class="timeline-card-fecha">---</span>
						</div>
						<p>Cuando el backend envíe los eventos, se mostrarán aquí ordenados de forma cronológica.</p>
					</div>
				</article>
			`;
      return;
    }

    contenedor.innerHTML = historial
      .map((item) => {
        const fecha =
          item.fecha || item.fecha_evento || item.fecha_registro || "";
        return `
					<article class="timeline-item">
						<div class="timeline-marker"></div>
						<div class="timeline-card">
							<div class="timeline-card-top">
								<strong>${descripcionTipo(item.tipo)}</strong>
								<span class="timeline-card-fecha">${formatDateTime(fecha)}</span>
							</div>
							<p>${item.descripcion || "Sin descripción disponible."}</p>
							<div class="timeline-card-meta">
								<span class="timeline-meta-chip">${iconoTipo(item.tipo)}</span>
								${item.estado_nuevo ? `<span class="timeline-meta-chip">${etiquetaEstado(item.estado_nuevo)}</span>` : ""}
								${item.monto !== null && item.monto !== undefined ? `<span class="timeline-meta-chip">${formatMoney(item.monto)}</span>` : ""}
							</div>
						</div>
					</article>
				`;
      })
      .join("");
  };

  const renderizarDocumentos = (reserva) => {
    const contenedor = document.getElementById("listaDocumentosReserva");
    const contador = document.getElementById("contadorDocumentosReserva");
    const resumenPago = document.getElementById("resumenPagoReserva");
    if (!contenedor) return;

    const documentos = generarDocumentosBase(reserva);
    const porcentajePago = toNumber(reserva.porcentaje_pago);

    if (contador) {
      contador.textContent = `${documentos.length} documento${documentos.length === 1 ? "" : "s"}`;
    }

    if (resumenPago) {
      resumenPago.textContent =
        porcentajePago > 0
          ? `Pago registrado ${porcentajePago}%`
          : "Pago no registrado";
    }

    if (!documentos.length) {
      limpiarTablaDocumentos();
      return;
    }

    contenedor.innerHTML = documentos
      .map(
        (documento, indice) => `
				<tr>
					<td>
						<span class="documento-badge">${documento.tipo || "Documento"} ${documento.numero ? `#${documento.numero}` : ""}</span>
					</td>
					<td>${formatDateTime(documento.fecha)}</td>
					<td>${formatMoney(documento.monto)}</td>
					<td>
						<span class="documento-estado ${normalizarEstado(documento.estado) || "pendiente"}">${etiquetaEstado(documento.estado)}</span>
					</td>
					<td>
						<div class="documento-acciones">
							<button type="button" class="boton-documento enviar" data-accion="enviar" data-indice="${indice}">Enviar</button>
							<button type="button" class="boton-documento imprimir" data-accion="imprimir" data-indice="${indice}">Imprimir</button>
						</div>
					</td>
				</tr>
			`,
      )
      .join("");
  };

  const cargarResumen = (reserva) => {
    const habitaciones =
      Array.isArray(reserva.habitaciones) && reserva.habitaciones.length > 0
        ? reserva.habitaciones
        : [];

    const habitacionesTexto = habitaciones.length
      ? habitaciones
          .map((habitacion) => {
            if (typeof habitacion !== "object" || habitacion === null)
              return "";
            const numero =
              habitacion.numero_habitacion || habitacion.numero || "";
            const piso = habitacion.piso ? `Piso ${habitacion.piso}` : "";
            const tipo = habitacion.tipo_nombre || habitacion.tipo || "";
            return [numero ? `Hab. ${numero}` : "", piso, tipo]
              .filter(Boolean)
              .join(" - ");
          })
          .filter(Boolean)
          .join(" | ")
      : reserva.habitacion || "---";

    setText("#detalleReservaCodigo", reserva.id || "---");
    setText("#detalleReservaCliente", reserva.cliente || "---");
    setText("#detalleReservaEstado", etiquetaEstado(reserva.estado));
    setText("#detalleReservaPago", `${toNumber(reserva.porcentaje_pago)}%`);
    setText("#detalleReservaSaldo", formatMoney(reserva.saldo_pendiente));
    setText("#detalleReservaClienteNombre", reserva.cliente || "---");
    setText(
      "#detalleReservaClienteEmail",
      reserva.email || reserva.correo_electronico || "---",
    );
    const listaHabitaciones = document.getElementById(
      "detalleReservaHabitacionesLista",
    );
    if (listaHabitaciones) {
      if (habitaciones.length) {
        listaHabitaciones.innerHTML = habitaciones
          .map((habitacion) => {
            if (typeof habitacion !== "object" || habitacion === null)
              return "";
            const numero =
              habitacion.numero_habitacion || habitacion.numero || "";
            const piso = habitacion.piso ? `Piso ${habitacion.piso}` : "";
            const tipo = habitacion.tipo_nombre || habitacion.tipo || "";
            const texto = [numero ? `Hab. ${numero}` : "", piso, tipo]
              .filter(Boolean)
              .join(" - ");
            return texto ? `<li>${texto}</li>` : "";
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

    if (typeof window.Swal === "undefined") {
      const correo = prompt("Correo electrónico del cliente:", correoBase);
      if (!correo) return;
      alert(
        `Se preparó el envío de ${documento.tipo || "documento"} a ${correo}.`,
      );
      return;
    }

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

    if (typeof window.Swal !== "undefined") {
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
    } else if (!confirm(`¿Imprimir ${documento.tipo || "documento"}?`)) {
      return;
    }

    window.print();
  };

  const abrirModalVerDetalles = (datos = {}) => {
    estadoModal.ultimaReserva = {
      ...datos,
      historial: safeParseArray(datos.historial, datos.historial || []),
      documentos: safeParseArray(datos.documentos, datos.documentos || []),
      habitaciones: Array.isArray(datos.habitaciones) ? datos.habitaciones : [],
    };

    cargarResumen(estadoModal.ultimaReserva);
    renderizarHistorial(estadoModal.ultimaReserva);
    renderizarDocumentos(estadoModal.ultimaReserva);
    mostrarModal();
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
