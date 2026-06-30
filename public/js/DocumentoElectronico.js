(() => {
  const estado = {
    reserva: null,
    habitaciones: [],
  };

  const toNumber = (valor) => {
    const numero = Number(String(valor ?? 0).replace(/[^0-9.-]/g, ""));
    return Number.isFinite(numero) ? numero : 0;
  };

  const formatMoney = (valor) => `S/ ${toNumber(valor).toFixed(2)}`;

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
      const checkbox = document.getElementById(`doc-hab-${habitacion.id}`);
      return checkbox?.checked;
    });

  const calcularRangoFacturable = (reserva, habitacionesSeleccionadas) => {
    const habitaciones = habitacionesSeleccionadas.length
      ? habitacionesSeleccionadas
      : estado.habitaciones;
    const limitesHabitaciones = habitaciones.map((habitacion) =>
      obtenerLimitesHabitacion(habitacion, reserva),
    );

    const desde = fechaMayor(
      reserva?.checkin_real,
      reserva?.check_in,
      reserva?.check_in_programado,
      ...limitesHabitaciones.map((limite) => limite.desde),
    );
    const hasta = fechaMenor(
      reserva?.checkout_real || reserva?.check_out || reserva?.check_out_programado,
      ...limitesHabitaciones.map((limite) => limite.hasta),
    );

    return { desde, hasta };
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

    const rango = calcularRangoFacturable(
      reserva,
      obtenerHabitacionesSeleccionadas(),
    );

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

  const obtenerHabitacionesActivas = (reserva) =>
    (Array.isArray(reserva?.habitaciones) ? reserva.habitaciones : []).filter(
      (habitacion) =>
        String(habitacion?.estado_asignacion || "activa") === "activa",
    );

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
        '<div class="habitacion-documento-item">No hay habitaciones activas en la reserva.</div>';
      return;
    }

    contenedor.innerHTML = habitaciones
      .map((habitacion) => {
        const id = habitacion.id;
        const numero = habitacion.numero_habitacion || "--";
        const piso = habitacion.piso || "--";
        const tipo = habitacion.tipo_nombre || "--";
        const precio = formatMoney(
          habitacion.precio_aplicado || habitacion.precio || 0,
        );
        return `
          <label class="habitacion-documento-item" for="doc-hab-${id}">
            <div>
              <input type="checkbox" id="doc-hab-${id}" data-id="${id}" checked />
              <strong>Hab. ${escapeHtml(numero)} - Piso ${escapeHtml(piso)}</strong>
            </div>
            <small>${escapeHtml(tipo)} | ${precio} por noche</small>
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

    const totalReserva =
      toNumber(reserva.total) + toNumber(reserva.cargo_checkout_tarde);
    const totalPagado = toNumber(reserva.total_pagado);
    const saldo = Math.max(0, totalReserva - totalPagado);

    let totalDocumento = 0;
    habitacionesSeleccionadas.forEach((habitacion) => {
      const precioBruto = toNumber(
        habitacion.precio_aplicado || habitacion.precio || 0,
      );
      totalDocumento += precioBruto * noches;
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

    setMensaje(errores.join(" "), errores.length ? "error" : "");
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
      if (campo) campo.value = valor;
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
      resumen.totalDocumento - 0.01 > resumen.totalPagado
    ) {
      return;
    }

    if (!resumen.habitacionesSeleccionadas.length) {
      setMensaje("Debe seleccionar al menos una habitación.", "error");
      return;
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
      habitaciones: resumen.habitacionesSeleccionadas.map((habitacion) => ({
        id: habitacion.id,
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
      await Swal.fire({
        icon: "success",
        title: "Documento emitido",
        text:
          resultado.mensaje || "Documento electrónico generado correctamente.",
        confirmButtonColor: "#185025",
      });

      if (documento.enlace_del_pdf) {
        window.open(documento.enlace_del_pdf, "_blank", "noopener,noreferrer");
      } else if (documento.enlace) {
        window.open(documento.enlace, "_blank", "noopener,noreferrer");
      }

      cerrarModalDocumentoElectronico();
      window.location.reload();
    } catch (error) {
      console.error(error);
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

    botonCerrar?.addEventListener("click", cerrarModalDocumentoElectronico);
    botonCancelar?.addEventListener("click", cerrarModalDocumentoElectronico);
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
