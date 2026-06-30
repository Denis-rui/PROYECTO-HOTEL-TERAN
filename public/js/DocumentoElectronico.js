(() => {
  const estado = {
    reserva: null,
    habitaciones: [],
    documentosElectronicos: [],
    documentosElectronicosCargados: false,
  };

  const toNumber = (valor) => {
    const numero = Number(String(valor ?? 0).replace(/[^0-9.-]/g, ""));
    return Number.isFinite(numero) ? numero : 0;
  };

  const formatMoney = (valor) => `S/ ${toNumber(valor).toFixed(2)}`;

  const formatDateTime = (valor) => {
    if (!valor) return "---";
    const fecha = new Date(String(valor).replace(" ", "T"));

    if (Number.isNaN(fecha.getTime())) return String(valor);

    return new Intl.DateTimeFormat("es-PE", {
      year: "numeric",
      month: "2-digit",
      day: "2-digit",
      hour: "2-digit",
      minute: "2-digit",
    }).format(fecha);
  };

  const escapeHtml = (valor) =>
    String(valor ?? "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");

  const parseArray = (valor, fallback = []) => {
    if (Array.isArray(valor)) return valor;
    if (!valor) return fallback;

    try {
      const parsed = JSON.parse(valor);
      return Array.isArray(parsed) ? parsed : fallback;
    } catch (error) {
      return fallback;
    }
  };

  const fechaAInput = (valor) => {
    if (!valor) return "";
    const texto = String(valor).slice(0, 10);
    return /^\d{4}-\d{2}-\d{2}$/.test(texto) ? texto : "";
  };

  const fechaMayor = (...fechas) =>
    fechas.map(fechaAInput).filter(Boolean).sort().pop() || "";

  const fechaMenor = (...fechas) =>
    fechas.map(fechaAInput).filter(Boolean).sort()[0] || "";

  const claveHabitacionDocumento = (habitacion) =>
    String(habitacion?.reserva_habitacion_id || habitacion?.id || "");

  const obtenerLimitesHabitacion = (habitacion, reserva = {}) => ({
    desde: fechaAInput(
      habitacion?.check_in ||
        reserva.check_in_programado ||
        reserva.check_in ||
        reserva.checkin_real ||
        "",
    ),
    hasta: fechaAInput(
      habitacion?.check_out ||
        reserva.check_out_programado ||
        reserva.check_out ||
        reserva.checkout_real ||
        "",
    ),
  });

  const obtenerHabitacionesSeleccionadas = () =>
    estado.habitaciones.filter((habitacion) => {
      const checkbox = document.getElementById(
        `doc-hab-${claveHabitacionDocumento(habitacion)}`,
      );
      return checkbox?.checked;
    });

  const textoHabitacion = (habitacion) =>
    `Hab. ${habitacion?.numero_habitacion || "--"}${habitacion?.piso ? ` - Piso ${habitacion.piso}` : ""}`;

  const calcularRangoFacturable = (reserva) => {
    const desde = fechaMayor(
      reserva?.checkin_real,
      reserva?.check_in,
      reserva?.check_in_programado,
    );
    const hasta = fechaMenor(
      reserva?.checkout_real || reserva?.check_out || reserva?.check_out_programado,
    );

    return { desde, hasta };
  };

  const obtenerRangoCruzadoHabitacion = (
    habitacion,
    reserva,
    fechaDesde,
    fechaHasta,
  ) => {
    const limites = obtenerLimitesHabitacion(habitacion, reserva);
    const desde = fechaMayor(fechaDesde, limites.desde);
    const hasta = fechaMenor(fechaHasta, limites.hasta);

    if (!desde || !hasta || desde >= hasta) return null;

    return {
      desde,
      hasta,
      noches: nochesEntre(desde, hasta),
    };
  };

  const debeIncluirCargoCheckoutTarde = (reserva, fechaHasta) => {
    const cargo = toNumber(reserva?.cargo_checkout_tarde);
    if (cargo <= 0 || !fechaHasta) return false;

    const finHabitaciones =
      estado.habitaciones
        .map((habitacion) => fechaAInput(habitacion?.check_out))
        .filter(Boolean)
        .sort()
        .pop() || "";
    const checkoutReal = fechaAInput(reserva?.checkout_real);
    const limite = [finHabitaciones, checkoutReal].filter(Boolean).sort()[0];

    return Boolean(limite) && fechaHasta >= limite;
  };

  const actualizarRangoFacturable = () => {
    const reserva = estado.reserva;
    if (!reserva) return;

    const fechaDesde = document.getElementById("docElectronicoFechaDesde");
    const fechaHasta = document.getElementById("docElectronicoFechaHasta");
    if (!fechaDesde || !fechaHasta) return;

    const rango = calcularRangoFacturable(reserva);

    if (!rango.desde || !rango.hasta) return;

    fechaDesde.min = rango.desde;
    fechaDesde.max = rango.hasta;
    fechaHasta.min = rango.desde;
    fechaHasta.max = rango.hasta;

    if (!fechaDesde.value || fechaDesde.value < rango.desde || fechaDesde.value > rango.hasta) {
      fechaDesde.value = rango.desde;
    }

    if (!fechaHasta.value || fechaHasta.value < rango.desde || fechaHasta.value > rango.hasta) {
      fechaHasta.value = rango.hasta;
    }
  };

  const tipoDocumentoPorNumero = (numeroDocumento = "") => {
    const limpio = String(numeroDocumento || "").replace(/\D/g, "");
    if (limpio.length === 11) return "6";
    if (limpio.length === 8) return "1";
    return "-";
  };

  const obtenerModal = () => ({
    overlay: document.getElementById("contenedor-modal-documento-electronico"),
    modal: document.getElementById("modalDocumentoElectronico"),
    form: document.getElementById("formDocumentoElectronico"),
  });

  const setMensaje = (mensaje, tipo = "") => {
    const elemento = document.getElementById("mensajeDocumentoElectronico");
    if (!elemento) return;
    elemento.textContent = mensaje || "";
    elemento.classList.remove("error", "exito");
    if (tipo) elemento.classList.add(tipo);
  };

  const resetDocumentosElectronicosEmitidos = () => {
    estado.documentosElectronicos = [];
    estado.documentosElectronicosCargados = false;

    const contenedor = document.getElementById(
      "contenedorDocumentosElectronicosEmitidos",
    );
    const lista = document.getElementById("listaDocumentosElectronicosEmitidos");
    const boton = document.getElementById("btnToggleDocumentosElectronicos");

    if (contenedor) contenedor.hidden = true;
    if (lista) lista.innerHTML = "";
    if (boton) {
      boton.textContent = "Ver documentos emitidos";
      boton.setAttribute("aria-expanded", "false");
    }
  };

  const renderDocumentosElectronicosEmitidos = (documentos = []) => {
    const lista = document.getElementById("listaDocumentosElectronicosEmitidos");
    if (!lista) return;

    if (!documentos.length) {
      lista.innerHTML =
        '<div class="documento-electronico-emitido-vacio">No hay boletas o facturas electrónicas emitidas para esta reserva.</div>';
      return;
    }

    lista.innerHTML = documentos
      .map((documento) => {
        const enlace = documento.enlace_del_pdf || documento.enlace || "";
        const numero = documento.numero || documento.numero_documento || "---";

        return `
          <article class="documento-electronico-emitido-item">
            <div class="documento-electronico-emitido-principal">
              <strong>${escapeHtml(documento.tipo || "Documento electrónico")} ${escapeHtml(numero)}</strong>
              <small>${escapeHtml(formatDateTime(documento.fecha))}</small>
            </div>
            <span class="documento-electronico-emitido-monto">${escapeHtml(formatMoney(documento.monto || documento.total || 0))}</span>
            ${
              enlace
                ? `<a class="documento-electronico-emitido-pdf" href="${escapeHtml(enlace)}" target="_blank" rel="noopener noreferrer">Ver PDF</a>`
                : ""
            }
          </article>
        `;
      })
      .join("");
  };

  const cargarDocumentosElectronicosEmitidos = async (idReserva) => {
    if (!idReserva) return [];

    const respuesta = await fetch(
      `${BASE_URL}Comprobante/emitidosPorReserva/${encodeURIComponent(idReserva)}`,
    );
    const documentos = await respuesta.json().catch(() => []);

    if (!respuesta.ok || !Array.isArray(documentos)) {
      throw new Error("No se pudieron cargar los documentos emitidos.");
    }

    return documentos.filter((documento) =>
      Boolean(documento?.es_documento_electronico),
    );
  };

  const toggleDocumentosElectronicosEmitidos = async () => {
    const reserva = estado.reserva;
    const contenedor = document.getElementById(
      "contenedorDocumentosElectronicosEmitidos",
    );
    const lista = document.getElementById("listaDocumentosElectronicosEmitidos");
    const boton = document.getElementById("btnToggleDocumentosElectronicos");

    if (!reserva?.id || !contenedor || !lista || !boton) return;

    const abrir = contenedor.hidden;
    contenedor.hidden = !abrir;
    boton.setAttribute("aria-expanded", abrir ? "true" : "false");
    boton.textContent = abrir ? "Ocultar documentos emitidos" : "Ver documentos emitidos";

    if (!abrir || estado.documentosElectronicosCargados) return;

    lista.innerHTML =
      '<div class="documento-electronico-emitido-vacio">Cargando documentos emitidos...</div>';

    try {
      estado.documentosElectronicos = await cargarDocumentosElectronicosEmitidos(
        reserva.id,
      );
      estado.documentosElectronicosCargados = true;
      renderDocumentosElectronicosEmitidos(estado.documentosElectronicos);
    } catch (error) {
      console.error("Error cargando documentos electrónicos emitidos:", error);
      lista.innerHTML =
        '<div class="documento-electronico-emitido-vacio">No se pudieron cargar los documentos emitidos.</div>';
    }
  };

  const obtenerHabitacionesActivas = (reserva) => {
    const historial = Array.isArray(reserva?.habitaciones_historial)
      ? reserva.habitaciones_historial
      : [];
    const habitaciones = historial.length
      ? historial
      : Array.isArray(reserva?.habitaciones)
        ? reserva.habitaciones
        : [];

    return habitaciones.filter((habitacion) => Number(habitacion?.id || 0) > 0);
  };

  const nochesEntre = (inicio, fin) => {
    if (!inicio || !fin) return 0;
    const a = new Date(`${inicio}T00:00:00`);
    const b = new Date(`${fin}T00:00:00`);
    const diff = b.getTime() - a.getTime();
    if (Number.isNaN(diff) || diff < 0) return 0;
    if (diff === 0) return 1;
    return Math.round(diff / 86400000);
  };

  const renderHabitaciones = (reserva) => {
    const contenedor = document.getElementById(
      "listaHabitacionesDocumentoElectronico",
    );
    if (!contenedor) return;

    const habitaciones = obtenerHabitacionesActivas(reserva);
    estado.habitaciones = habitaciones;

    if (!habitaciones.length) {
      contenedor.innerHTML =
        '<div class="habitacion-documento-item">No hay habitaciones registradas en la reserva.</div>';
      return;
    }

    contenedor.innerHTML = habitaciones
      .map((habitacion) => {
        const id = claveHabitacionDocumento(habitacion);
        const numero = habitacion.numero_habitacion || "--";
        const piso = habitacion.piso || "--";
        const tipo = habitacion.tipo_nombre || "--";
        const limites = obtenerLimitesHabitacion(habitacion, reserva);
        const estadoAsignacion = String(
          habitacion.estado_asignacion || "activa",
        ).toLowerCase();
        const etiquetaEstado =
          estadoAsignacion === "activa" ? "Actual" : "Anterior";
        const precio = formatMoney(
          habitacion.precio_aplicado || habitacion.precio || 0,
        );
        return `
          <label class="habitacion-documento-item" for="doc-hab-${id}">
            <div>
              <input type="checkbox" id="doc-hab-${id}" data-id="${escapeHtml(habitacion.id)}" data-reserva-habitacion-id="${escapeHtml(habitacion.reserva_habitacion_id || "")}" checked />
              <strong>Hab. ${escapeHtml(numero)} - Piso ${escapeHtml(piso)}</strong>
            </div>
            <small>${escapeHtml(tipo)} | ${precio} por noche | ${escapeHtml(limites.desde || "--")} al ${escapeHtml(limites.hasta || "--")} | ${escapeHtml(etiquetaEstado)}</small>
          </label>
        `;
      })
      .join("");

    contenedor
      .querySelectorAll('input[type="checkbox"]')
      .forEach((checkbox) => {
        checkbox.addEventListener("change", () => {
          actualizarRangoFacturable();
          actualizarResumen();
        });
      });
  };

  const calcularResumen = () => {
    const reserva = estado.reserva;
    if (!reserva) return null;

    const fechaDesde =
      document.getElementById("docElectronicoFechaDesde")?.value || "";
    const fechaHasta =
      document.getElementById("docElectronicoFechaHasta")?.value || "";
    const noches = nochesEntre(fechaDesde, fechaHasta);
    const habitacionesSeleccionadas = obtenerHabitacionesSeleccionadas();
    const habitacionesFacturables = [];
    const habitacionesFueraDeRango = [];

    const totalReserva =
      toNumber(reserva.total) + toNumber(reserva.cargo_checkout_tarde);
    const totalPagado = toNumber(reserva.total_pagado);
    const saldo = Math.max(0, totalReserva - totalPagado);

    let totalDocumento = 0;
    habitacionesSeleccionadas.forEach((habitacion) => {
      const rangoHabitacion = obtenerRangoCruzadoHabitacion(
        habitacion,
        reserva,
        fechaDesde,
        fechaHasta,
      );

      if (!rangoHabitacion || rangoHabitacion.noches <= 0) {
        habitacionesFueraDeRango.push(habitacion);
        return;
      }

      const precioBruto = toNumber(
        habitacion.precio_aplicado || habitacion.precio || 0,
      );
      totalDocumento += precioBruto * rangoHabitacion.noches;
      habitacionesFacturables.push({
        ...habitacion,
        fecha_desde_facturada: rangoHabitacion.desde,
        fecha_hasta_facturada: rangoHabitacion.hasta,
        noches_facturadas: rangoHabitacion.noches,
      });
    });

    if (debeIncluirCargoCheckoutTarde(reserva, fechaHasta)) {
      totalDocumento += toNumber(reserva.cargo_checkout_tarde);
    }

    return {
      fechaDesde,
      fechaHasta,
      noches,
      totalReserva,
      totalPagado,
      saldo,
      totalDocumento,
      habitacionesSeleccionadas,
      habitacionesFacturables,
      habitacionesFueraDeRango,
    };
  };

  const actualizarResumen = () => {
    const reserva = estado.reserva;
    if (!reserva) return null;

    const resumen = calcularResumen();
    if (!resumen) return null;

    const tipoDocumento =
      document.getElementById("docElectronicoTipoDocumento")?.value || "BOLETA";
    const clienteNumero =
      document.getElementById("docElectronicoClienteNumero")?.value || "";
    const tipoDocumentoSUNAT =
      document.getElementById("docElectronicoClienteTipoDocumento")?.value ||
      "-";
    const nochesEl = document.getElementById("docElectronicoNoches");
    const totalEmitir = document.getElementById("docElectronicoTotalEmitir");
    const totalDocumentoResumen = document.getElementById(
      "docElectronicoTotalDocumentoResumen",
    );
    const totalReservaEl = document.getElementById(
      "docElectronicoTotalReserva",
    );
    const totalPagadoEl = document.getElementById("docElectronicoTotalPagado");
    const saldoEl = document.getElementById("docElectronicoSaldoPendiente");
    const estadoEl = document.getElementById("docElectronicoEstado");
    const tipoResumen = document.getElementById("docElectronicoTipoResumen");
    const pagoResumen = document.getElementById("docElectronicoPago");

    if (nochesEl) nochesEl.value = String(resumen.noches);
    if (totalReservaEl)
      totalReservaEl.textContent = formatMoney(resumen.totalReserva);
    if (totalPagadoEl)
      totalPagadoEl.textContent = formatMoney(resumen.totalPagado);
    if (saldoEl) saldoEl.textContent = formatMoney(resumen.saldo);
    if (totalEmitir)
      totalEmitir.textContent = formatMoney(resumen.totalDocumento);
    if (totalDocumentoResumen)
      totalDocumentoResumen.textContent = formatMoney(resumen.totalDocumento);
    if (tipoResumen)
      tipoResumen.textContent =
        tipoDocumento === "FACTURA" ? "Factura" : "Boleta";
    if (pagoResumen)
      pagoResumen.textContent = `${resumen.totalPagado >= resumen.totalReserva ? "Pagada" : "Pendiente"} - ${Math.round((resumen.totalPagado / Math.max(resumen.totalReserva, 1)) * 100)}%`;

    const errores = [];
    if (resumen.noches <= 0)
      errores.push("Seleccione un rango de fechas válido.");
    if (
      resumen.habitacionesSeleccionadas.length > 0 &&
      resumen.habitacionesFacturables.length === 0
    ) {
      errores.push(
        "Ninguna habitación seleccionada está disponible en el rango de fechas elegido.",
      );
    }
    if (resumen.totalDocumento - 0.01 > resumen.totalPagado) {
      errores.push(
        "El importe seleccionado supera el monto pagado hasta el momento.",
      );
    }
    if (
      tipoDocumento === "FACTURA" &&
      String(clienteNumero).replace(/\D/g, "").length !== 11
    ) {
      errores.push("Factura requiere RUC de 11 dígitos.");
    }
    if (
      tipoDocumentoSUNAT === "-" &&
      String(clienteNumero).replace(/\D/g, "").length === 0
    ) {
      errores.push("Ingrese un número de documento válido.");
    }

    if (estadoEl) {
      estadoEl.textContent = errores.length ? "Revisar" : "Listo para emitir";
    }

    const avisoFueraDeRango =
      !errores.length && resumen.habitacionesFueraDeRango.length
        ? "Hay habitaciones seleccionadas que no están disponibles en este rango; se pedirá confirmación antes de emitir."
        : "";

    setMensaje(
      errores.length ? errores.join(" ") : avisoFueraDeRango,
      errores.length ? "error" : "",
    );
    return {
      ...resumen,
      tipoDocumento,
      clienteNumero,
      tipoDocumentoSUNAT,
    };
  };

  const poblarFormulario = (reserva) => {
    estado.reserva = reserva;

    const campos = {
      docElectronicoIdReserva: reserva.id || "",
      docElectronicoCodigoReserva:
        reserva.codigo_reserva || reserva.id || "---",
      docElectronicoClienteNombre: reserva.cliente || "",
      docElectronicoClienteTipoDocumento: tipoDocumentoPorNumero(
        reserva.documento || "",
      ),
      docElectronicoClienteNumero: reserva.documento || "",
      docElectronicoClienteEmail:
        reserva.correo_electronico || reserva.email || "",
      docElectronicoClienteDireccion:
        reserva.cliente_direccion || reserva.procedencia || "",
      docElectronicoFechaDesde: fechaAInput(
        reserva.check_in || reserva.check_in_programado || "",
      ),
      docElectronicoFechaHasta: fechaAInput(
        reserva.check_out || reserva.check_out_programado || "",
      ),
      docElectronicoTipoDocumento: "BOLETA",
    };

    Object.entries(campos).forEach(([id, valor]) => {
      const campo = document.getElementById(id);
      if (!campo) return;

      if ("value" in campo) {
        campo.value = valor;
      } else {
        campo.textContent = valor;
      }
    });

    const rangoFacturable = calcularRangoFacturable(
      reserva,
      obtenerHabitacionesActivas(reserva),
    );
    const checkIn = rangoFacturable.desde;
    const checkOut = rangoFacturable.hasta;
    const fechaDesde = document.getElementById("docElectronicoFechaDesde");
    const fechaHasta = document.getElementById("docElectronicoFechaHasta");
    if (fechaDesde) {
      fechaDesde.min = checkIn;
      fechaDesde.max = checkOut;
      fechaDesde.value = checkIn;
    }
    if (fechaHasta) {
      fechaHasta.min = checkIn;
      fechaHasta.max = checkOut;
      fechaHasta.value = checkOut;
    }

    const totalReserva =
      toNumber(reserva.total) + toNumber(reserva.cargo_checkout_tarde);
    const totalPagado = toNumber(reserva.total_pagado);
    const saldo = Math.max(0, totalReserva - totalPagado);

    if (document.getElementById("docElectronicoTotalReserva")) {
      document.getElementById("docElectronicoTotalReserva").textContent =
        formatMoney(totalReserva);
    }
    if (document.getElementById("docElectronicoTotalPagado")) {
      document.getElementById("docElectronicoTotalPagado").textContent =
        formatMoney(totalPagado);
    }
    if (document.getElementById("docElectronicoSaldoPendiente")) {
      document.getElementById("docElectronicoSaldoPendiente").textContent =
        formatMoney(saldo);
    }
    if (document.getElementById("docElectronicoPago")) {
      document.getElementById("docElectronicoPago").textContent =
        `${totalPagado >= totalReserva ? "Pagada" : "Pendiente"} - ${Math.round((totalPagado / Math.max(totalReserva, 1)) * 100)}%`;
    }

    renderHabitaciones(reserva);
    actualizarRangoFacturable();
    actualizarResumen();
  };

  const cargarReservaCompleta = async (idReserva) => {
    const respuesta = await fetch(
      BASE_URL + `Reserva/obtener/${encodeURIComponent(idReserva)}`,
    );
    const datos = await respuesta.json().catch(() => ({}));
    return respuesta.ok && datos ? datos : null;
  };

  const abrirModalDocumentoElectronico = async (datos = {}) => {
    const { overlay, modal } = obtenerModal();
    if (!overlay || !modal) return;

    let reserva = {
      ...datos,
      habitaciones: parseArray(datos.habitaciones, []),
    };

    if (reserva.id) {
      try {
        const completa = await cargarReservaCompleta(reserva.id);
        if (completa?.id) {
          reserva = {
            ...reserva,
            ...completa,
            habitaciones: parseArray(
              completa.habitaciones,
              reserva.habitaciones || [],
            ),
          };
        }
      } catch (error) {
        console.error(
          "No se pudo cargar la reserva para emitir documento:",
          error,
        );
      }
    }

    estado.reserva = reserva;
    resetDocumentosElectronicosEmitidos();
    poblarFormulario(reserva);
    overlay.style.display = "flex";
    modal.style.display = "block";
    document.body.style.overflow = "hidden";
    setMensaje("Verifique los datos y emita el documento.");
  };

  const cerrarModalDocumentoElectronico = () => {
    const { overlay, modal } = obtenerModal();
    if (overlay) overlay.style.display = "none";
    if (modal) modal.style.display = "none";
    document.body.style.overflow = "";
  };

  const emitirDocumentoElectronico = async (evento) => {
    evento.preventDefault();
    const reserva = estado.reserva;
    if (!reserva) return;

    const resumen = actualizarResumen();
    if (!resumen) return;
    if (
      resumen.noches <= 0 ||
      resumen.totalDocumento - 0.01 > resumen.totalPagado ||
      resumen.habitacionesFacturables.length === 0
    ) {
      return;
    }

    if (!resumen.habitacionesSeleccionadas.length) {
      setMensaje("Debe seleccionar al menos una habitación.", "error");
      return;
    }

    if (resumen.habitacionesFueraDeRango.length) {
      const habitacionesFuera = resumen.habitacionesFueraDeRango
        .map(textoHabitacion)
        .join(", ");
      const confirmarRango = await Swal.fire({
        icon: "warning",
        title: "Habitaciones fuera del rango",
        text:
          `${habitacionesFuera} no está disponible en las fechas seleccionadas. ` +
          `Si continúas, no se incluirá en la ${resumen.tipoDocumento === "FACTURA" ? "factura" : "boleta"}.`,
        showCancelButton: true,
        confirmButtonText: "Continuar",
        cancelButtonText: "Cancelar",
        confirmButtonColor: "#185025",
        cancelButtonColor: "#8f2f2f",
      });

      if (!confirmarRango.isConfirmed) return;
    }

    const payload = {
      id_reserva: reserva.id,
      tipo_documento: resumen.tipoDocumento,
      fecha_desde: resumen.fechaDesde,
      fecha_hasta: resumen.fechaHasta,
      cliente_denominacion:
        document.getElementById("docElectronicoClienteNombre")?.value?.trim() ||
        "",
      cliente_tipo_documento:
        document.getElementById("docElectronicoClienteTipoDocumento")?.value ||
        "-",
      cliente_numero_documento:
        document.getElementById("docElectronicoClienteNumero")?.value?.trim() ||
        "",
      cliente_email:
        document.getElementById("docElectronicoClienteEmail")?.value?.trim() ||
        "",
      cliente_direccion:
        document
          .getElementById("docElectronicoClienteDireccion")
          ?.value?.trim() || "",
      habitaciones: resumen.habitacionesFacturables.map((habitacion) => ({
        id: habitacion.id,
        reserva_habitacion_id: habitacion.reserva_habitacion_id || null,
      })),
    };

    const confirmar = await Swal.fire({
      icon: "question",
      title: `Emitir ${payload.tipo_documento === "FACTURA" ? "factura" : "boleta"}`,
      text: "NubeFact generará el documento electrónico con los datos seleccionados.",
      showCancelButton: true,
      confirmButtonText: "Emitir",
      cancelButtonText: "Cancelar",
      confirmButtonColor: "#185025",
      cancelButtonColor: "#8f2f2f",
    });

    if (!confirmar.isConfirmed) return;

    setMensaje("Enviando a NubeFact...");

    try {
      Swal.fire({
        title: "Enviando a NubeFact",
        text: "Estamos generando el documento electrónico. Por favor espera...",
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: () => {
          Swal.showLoading();
        },
      });

      const respuesta = await fetch(
        BASE_URL + "Reserva/emitirDocumentoElectronico",
        {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(payload),
        },
      );
      const resultado = await respuesta.json().catch(() => ({}));

      if (!respuesta.ok || !resultado.exito) {
        Swal.close();
        setMensaje(
          resultado.mensaje || "No se pudo emitir el documento.",
          "error",
        );
        Swal.fire({
          icon: "error",
          title: "Error",
          text: resultado.mensaje || "No se pudo emitir el documento.",
        });
        return;
      }

      const documento = resultado.documento || {};
      const enlaceDocumento = documento.enlace_del_pdf || documento.enlace || "";
      Swal.close();
      const avisoDocumento = await Swal.fire({
        icon: "success",
        title: resultado.duplicado ? "Documento ya emitido" : "Documento emitido",
        text:
          resultado.mensaje || "Documento electrónico generado correctamente.",
        showCancelButton: Boolean(enlaceDocumento),
        confirmButtonText: enlaceDocumento ? "Ver PDF" : "OK",
        cancelButtonText: "Cancelar",
        confirmButtonColor: "#185025",
        cancelButtonColor: "#8f2f2f",
      });

      if (enlaceDocumento && avisoDocumento.isConfirmed) {
        window.open(enlaceDocumento, "_blank", "noopener,noreferrer");
      }

      setMensaje(
        resultado.duplicado
          ? "Este documento ya estaba emitido. Puedes revisarlo en documentos emitidos."
          : "Documento electrónico emitido correctamente.",
        "success",
      );
      if (estado.reserva?.id) {
        estado.documentosElectronicos = await cargarDocumentosElectronicosEmitidos(
          estado.reserva.id,
        );
        estado.documentosElectronicosCargados = true;
        renderDocumentosElectronicosEmitidos(estado.documentosElectronicos);
      }
    } catch (error) {
      console.error(error);
      Swal.close();
      setMensaje("No se pudo conectar con el servidor.", "error");
      Swal.fire({
        icon: "error",
        title: "Error de conexión",
        text: "No se pudo conectar con el servidor.",
      });
    }
  };

  const configurarEventos = () => {
    const { overlay, form } = obtenerModal();
    const botonCerrar = document.getElementById(
      "cerrarModalDocumentoElectronico",
    );
    const botonCancelar = document.getElementById(
      "btnCancelarDocumentoElectronico",
    );
    const tipoDocumento = document.getElementById(
      "docElectronicoTipoDocumento",
    );
    const fechaDesde = document.getElementById("docElectronicoFechaDesde");
    const fechaHasta = document.getElementById("docElectronicoFechaHasta");
    const clienteNombre = document.getElementById(
      "docElectronicoClienteNombre",
    );
    const clienteTipo = document.getElementById(
      "docElectronicoClienteTipoDocumento",
    );
    const clienteNumero = document.getElementById(
      "docElectronicoClienteNumero",
    );
    const clienteEmail = document.getElementById("docElectronicoClienteEmail");
    const clienteDireccion = document.getElementById(
      "docElectronicoClienteDireccion",
    );
    const botonDocumentosEmitidos = document.getElementById(
      "btnToggleDocumentosElectronicos",
    );

    botonCerrar?.addEventListener("click", cerrarModalDocumentoElectronico);
    botonCancelar?.addEventListener("click", cerrarModalDocumentoElectronico);
    botonDocumentosEmitidos?.addEventListener(
      "click",
      toggleDocumentosElectronicosEmitidos,
    );
    form?.addEventListener("submit", emitirDocumentoElectronico);

    overlay?.addEventListener("click", (evento) => {
      if (evento.target === overlay) {
        cerrarModalDocumentoElectronico();
      }
    });

    document.addEventListener("keydown", (evento) => {
      if (evento.key === "Escape" && overlay?.style.display === "flex") {
        cerrarModalDocumentoElectronico();
      }
    });

    [
      tipoDocumento,
      fechaDesde,
      fechaHasta,
      clienteNombre,
      clienteTipo,
      clienteNumero,
      clienteEmail,
      clienteDireccion,
    ]
      .filter(Boolean)
      .forEach((campo) => {
        campo.addEventListener("change", actualizarResumen);
        campo.addEventListener("input", actualizarResumen);
      });
  };

  window.abrirModalDocumentoElectronico = abrirModalDocumentoElectronico;
  window.cerrarModalDocumentoElectronico = cerrarModalDocumentoElectronico;

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", configurarEventos, {
      once: true,
    });
  } else {
    configurarEventos();
  }
})();

(() => {
  let temporizadorBusquedaCliente = null;
  let controladorBusquedaCliente = null;
  let clientesEncontrados = [];

  const escapeClienteHtml = (valor) =>
    String(valor ?? "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");

  const obtenerTipoSunatCliente = (cliente = {}) => {
    const tipo = String(cliente.tipo_documento_nombre || "").toLowerCase();
    const numero = String(cliente.documento || "").replace(/\D/g, "");

    if (tipo.includes("ruc") || numero.length === 11) return "6";
    if (tipo.includes("dni") || numero.length === 8) return "1";
    if (tipo.includes("extranjer")) return "4";
    if (tipo.includes("pasaporte")) return "7";
    if (tipo.includes("no domiciliado")) return "0";
    return "-";
  };

  const actualizarCampoCliente = (id, valor) => {
    const campo = document.getElementById(id);
    if (!campo) return;
    campo.value = valor ?? "";
    campo.dispatchEvent(new Event("input", { bubbles: true }));
    campo.dispatchEvent(new Event("change", { bubbles: true }));
  };

  const ocultarResultadosCliente = () => {
    const resultados = document.getElementById(
      "docElectronicoResultadosCliente",
    );
    resultados?.classList.remove("visible");
  };

  const seleccionarClienteDocumento = (cliente) => {
    actualizarCampoCliente("docElectronicoClienteNombre", cliente.nombre || "");
    actualizarCampoCliente(
      "docElectronicoClienteTipoDocumento",
      obtenerTipoSunatCliente(cliente),
    );
    actualizarCampoCliente(
      "docElectronicoClienteNumero",
      cliente.documento || "",
    );
    actualizarCampoCliente("docElectronicoClienteEmail", cliente.correo || "");
    actualizarCampoCliente(
      "docElectronicoClienteDireccion",
      cliente.procedencia || "",
    );

    const buscador = document.getElementById("docElectronicoBuscarCliente");
    const mensaje = document.getElementById("docElectronicoMensajeCliente");
    if (buscador) {
      buscador.value = cliente.nombre || cliente.documento || "";
    }
    if (mensaje) {
      mensaje.textContent = `Cliente seleccionado: ${cliente.nombre || cliente.documento}.`;
    }

    ocultarResultadosCliente();
  };

  const renderizarResultadosCliente = (clientes, textoBusqueda) => {
    const resultados = document.getElementById(
      "docElectronicoResultadosCliente",
    );
    const mensaje = document.getElementById("docElectronicoMensajeCliente");
    if (!resultados) return;

    clientesEncontrados = Array.isArray(clientes) ? clientes : [];
    if (!clientesEncontrados.length) {
      resultados.innerHTML =
        '<div class="resultado-cliente-vacio">No se encontraron clientes activos.</div>';
      resultados.classList.add("visible");
      if (mensaje) {
        mensaje.textContent = `Sin resultados para "${textoBusqueda}".`;
      }
      return;
    }

    resultados.innerHTML = clientesEncontrados
      .map(
        (cliente, indice) => `
          <button
            type="button"
            class="resultado-cliente-documento"
            data-indice-cliente="${indice}"
            role="option"
          >
            <strong>${escapeClienteHtml(cliente.nombre || "Sin nombre")}</strong>
            <span>${escapeClienteHtml(cliente.tipo_documento_nombre || "Documento")}: ${escapeClienteHtml(cliente.documento || "---")}</span>
            <small>${escapeClienteHtml(cliente.correo || "Sin correo")} · ${escapeClienteHtml(cliente.procedencia || "Sin procedencia")}</small>
          </button>
        `,
      )
      .join("");
    resultados.classList.add("visible");
    if (mensaje) {
      mensaje.textContent = `${clientesEncontrados.length} cliente${clientesEncontrados.length === 1 ? "" : "s"} encontrado${clientesEncontrados.length === 1 ? "" : "s"}.`;
    }
  };

  const buscarClientesDocumento = async (textoBusqueda) => {
    const mensaje = document.getElementById("docElectronicoMensajeCliente");
    controladorBusquedaCliente?.abort();
    controladorBusquedaCliente = new AbortController();

    if (mensaje) mensaje.textContent = "Buscando clientes...";

    try {
      const respuesta = await fetch(
        BASE_URL + `Cliente/buscar&q=${encodeURIComponent(textoBusqueda)}`,
        { signal: controladorBusquedaCliente.signal },
      );
      const datos = await respuesta.json();
      if (!respuesta.ok || datos.error) {
        throw new Error(datos.error || "No se pudo buscar clientes.");
      }

      renderizarResultadosCliente(datos.clientes || [], textoBusqueda);
    } catch (error) {
      if (error.name === "AbortError") return;
      console.error("Error buscando clientes para documento:", error);
      ocultarResultadosCliente();
      if (mensaje)
        mensaje.textContent = "No se pudo consultar la base de datos.";
      Swal.fire({
        icon: "error",
        title: "Error al buscar clientes",
        text: "No se pudo consultar la base de datos.",
        confirmButtonColor: "#185025",
      });
    }
  };

  const configurarBuscadorClienteDocumento = () => {
    const buscador = document.getElementById("docElectronicoBuscarCliente");
    const limpiar = document.getElementById("docElectronicoLimpiarCliente");
    const resultados = document.getElementById(
      "docElectronicoResultadosCliente",
    );
    const mensaje = document.getElementById("docElectronicoMensajeCliente");
    if (!buscador || !resultados || buscador.dataset.configurado === "true") {
      return;
    }

    buscador.dataset.configurado = "true";
    buscador.addEventListener("input", () => {
      clearTimeout(temporizadorBusquedaCliente);
      const texto = buscador.value.trim();

      if (texto.length < 2) {
        controladorBusquedaCliente?.abort();
        clientesEncontrados = [];
        resultados.innerHTML = "";
        ocultarResultadosCliente();
        if (mensaje) {
          mensaje.textContent =
            "Escribe al menos 2 caracteres para buscar por nombre, DNI o RUC.";
        }
        return;
      }

      temporizadorBusquedaCliente = setTimeout(
        () => buscarClientesDocumento(texto),
        300,
      );
    });

    buscador.addEventListener("focus", () => {
      if (clientesEncontrados.length) {
        resultados.classList.add("visible");
      }
    });

    limpiar?.addEventListener("click", () => {
      buscador.value = "";
      buscador.focus();
      clientesEncontrados = [];
      resultados.innerHTML = "";
      ocultarResultadosCliente();
      if (mensaje) {
        mensaje.textContent =
          "Busca un cliente de la base de datos para reemplazar los datos actuales.";
      }
    });

    resultados.addEventListener("click", (evento) => {
      const opcion = evento.target.closest("[data-indice-cliente]");
      if (!opcion) return;
      const cliente = clientesEncontrados[Number(opcion.dataset.indiceCliente)];
      if (cliente) seleccionarClienteDocumento(cliente);
    });

    document.addEventListener("click", (evento) => {
      if (!evento.target.closest(".buscador-cliente-documento")) {
        ocultarResultadosCliente();
      }
    });
  };

  if (document.readyState === "loading") {
    document.addEventListener(
      "DOMContentLoaded",
      configurarBuscadorClienteDocumento,
      { once: true },
    );
  } else {
    configurarBuscadorClienteDocumento();
  }
})();
