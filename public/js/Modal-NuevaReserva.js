let eventosDashboardConfigurados = false;
let _temporizadorBusquedaClienteModal = null;
let _controladorBusquedaClienteModal = null;
window.__modalReservaState = window.__modalReservaState || {};

const obtenerEstadoModalReserva = () => window.__modalReservaState;

const limpiarResaltadoRegistrarCliente = () => {
  const estado = obtenerEstadoModalReserva();
  const botonRegistrar = estado.elementos?.btnRegistrarCliente;
  if (!botonRegistrar) return;

  botonRegistrar.classList.remove("resaltar-boton");
  if (estado.temporizadorResaltadoRegistrarCliente) {
    clearTimeout(estado.temporizadorResaltadoRegistrarCliente);
    estado.temporizadorResaltadoRegistrarCliente = null;
  }
};

const resaltarBotonRegistrarCliente = () => {
  const estado = obtenerEstadoModalReserva();
  const botonRegistrar = estado.elementos?.btnRegistrarCliente;
  if (!botonRegistrar) return;

  botonRegistrar.classList.add("resaltar-boton");
  if (estado.temporizadorResaltadoRegistrarCliente) {
    clearTimeout(estado.temporizadorResaltadoRegistrarCliente);
  }

  estado.temporizadorResaltadoRegistrarCliente = window.setTimeout(() => {
    botonRegistrar.classList.remove("resaltar-boton");
    estado.temporizadorResaltadoRegistrarCliente = null;
  }, 5000);
};

const abrirModalNuevoClienteConDocumento = (documento = "") => {
  if (typeof window.abrirModalCliente !== "function") return;

  window.abrirModalCliente("nuevo", {
    documento,
  });
};

const esDocumentoCompletoOchoDigitos = (texto = "") =>
  /^\d{8}$/.test(String(texto || "").trim());

const obtenerFechaActualISO = () => {
  const hoy = new Date();
  const anio = hoy.getFullYear();
  const mes = String(hoy.getMonth() + 1).padStart(2, "0");
  const dia = String(hoy.getDate()).padStart(2, "0");
  return `${anio}-${mes}-${dia}`;
};

const obtenerHoraActualISO = () => {
  const ahora = new Date();
  const horas = String(ahora.getHours()).padStart(2, "0");
  const minutos = String(ahora.getMinutes()).padStart(2, "0");
  return `${horas}:${minutos}`;
};

const sumarDiasISO = (fechaISO, dias = 1) => {
  const partes = String(fechaISO || "")
    .split("-")
    .map((valor) => Number(valor));
  if (partes.length !== 3 || partes.some((valor) => Number.isNaN(valor))) {
    return "";
  }

  const fecha = new Date(partes[0], partes[1] - 1, partes[2]);
  fecha.setDate(fecha.getDate() + dias);

  const anio = fecha.getFullYear();
  const mes = String(fecha.getMonth() + 1).padStart(2, "0");
  const dia = String(fecha.getDate()).padStart(2, "0");
  return `${anio}-${mes}-${dia}`;
};

const establecerHorasPorDefectoEstadia = (forzar = false) => {
  const estado = obtenerEstadoModalReserva();
  const horaEntrada = estado.elementos?.horaEntrada;
  const horaSalida = estado.elementos?.horaSalida;

  if (horaEntrada) {
    if (forzar || !horaEntrada.value) {
      horaEntrada.value = "14:00";
    }
    horaEntrada.min = "";
  }

  if (horaSalida) {
    if (forzar || !horaSalida.value) {
      horaSalida.value = "12:00";
    }
    horaSalida.min = "";
  }
};

const ajustarCheckoutPorDefecto = () => {
  const estado = obtenerEstadoModalReserva();
  const fechaEntrada = estado.elementos?.fechaEntrada;
  const fechaSalida = estado.elementos?.fechaSalida;

  if (!fechaEntrada || !fechaSalida || !fechaEntrada.value) return;

  const minimoCheckout = sumarDiasISO(fechaEntrada.value, 1);
  fechaSalida.min = minimoCheckout;

  if (!fechaSalida.value || fechaSalida.value < minimoCheckout) {
    fechaSalida.value = minimoCheckout;
  }
};

const separarFechaHora = (valor) => {
  const texto = String(valor || "").trim();
  if (!texto) {
    return { fecha: "", hora: "" };
  }

  const normalizado = texto.replace("T", " ");
  const [fecha = "", horaCompleta = ""] = normalizado.split(" ");
  const hora = horaCompleta ? horaCompleta.slice(0, 5) : "";

  return { fecha, hora };
};

const asegurarOpcionCliente = (cliente) => {
  const estado = obtenerEstadoModalReserva();
  const selectorCliente = estado.elementos?.selectorCliente;
  if (!selectorCliente || !cliente?.id) return;

  const existe = Array.from(selectorCliente.options || []).some(
    (opcion) => String(opcion.value) === String(cliente.id),
  );

  if (!existe) {
    const option = document.createElement("option");
    option.value = String(cliente.id);
    option.textContent = cliente.nombre || `Cliente ${cliente.id}`;
    selectorCliente.appendChild(option);
  }
};

const resolverTipoDocumentoCliente = (cliente = {}) => {
  const nombreTipo = String(cliente.tipo_documento_nombre || "").toLowerCase();

  if (nombreTipo.includes("ruc")) return "ruc";
  if (!cliente.documento && cliente.ruc) return "ruc";

  return "dni";
};

const obtenerNumeroDocumentoCliente = (cliente = {}, tipo = "dni") =>
  tipo === "ruc" ? cliente.ruc || "" : cliente.documento || "";

const actualizarDocumentoClienteReserva = (cliente = null) => {
  const estado = obtenerEstadoModalReserva();
  const clienteActual = cliente || estado.clienteSeleccionado || {};
  const selectorTipo = estado.elementos?.tipoDocumentoCliente;
  const campoDni = estado.elementos?.campoDni;
  const tipo = selectorTipo?.value || "dni";

  if (campoDni) {
    campoDni.value = obtenerNumeroDocumentoCliente(clienteActual, tipo);
    campoDni.placeholder =
      tipo === "ruc" ? "RUC no registrado" : "DNI no registrado";
  }
};

const aplicarReservaEdicion = (reserva) => {
  const estado = obtenerEstadoModalReserva();
  if (!reserva) return;

  asegurarOpcionCliente({
    id: reserva.id_cliente,
    nombre: reserva.cliente,
  });

  if (estado.elementos?.inputBuscarCliente) {
    estado.elementos.inputBuscarCliente.value = "";
  }

  if (estado.elementos?.selectorCliente) {
    estado.elementos.selectorCliente.value = reserva.id_cliente
      ? String(reserva.id_cliente)
      : "";
  }

  if (estado.elementos?.idClienteReserva) {
    estado.elementos.idClienteReserva.value = reserva.id_cliente || "";
  }

  if (estado.elementos?.campoNombre) {
    estado.elementos.campoNombre.value = reserva.cliente || "";
  }

  estado.clienteSeleccionado = {
    documento: reserva.documento || "",
    ruc: reserva.ruc || "",
    tipo_documento_nombre: reserva.documento_tipo_nombre || "",
  };

  if (estado.elementos?.tipoDocumentoCliente) {
    estado.elementos.tipoDocumentoCliente.value = resolverTipoDocumentoCliente(
      estado.clienteSeleccionado,
    );
  }

  actualizarDocumentoClienteReserva(estado.clienteSeleccionado);

  if (estado.elementos?.procedencia) {
    estado.elementos.procedencia.value = reserva.procedencia || "";
  }

  if (estado.elementos?.campoEmail) {
    estado.elementos.campoEmail.value = reserva.correo_electronico || "";
  }

  estado.reservaTotalOriginal = Number(reserva.total || 0);
  estado.reservaTotalPagado = Number(reserva.total_pagado || 0);
  estado.reservaTotalPagadoNeto = Number(
    reserva.total_pagado_neto ?? reserva.total_pagado ?? 0,
  );
  estado.reservaCheckOutOriginal =
    reserva.check_out_programado || reserva.check_out || "";
  estado.reservaCheckInOriginal =
    reserva.check_in_programado || reserva.check_in || "";
  estado.reservaHabitacionesOriginalesIds = (reserva.habitaciones || []).map(
    (h) => String(h.id)
  );

  const checkIn = separarFechaHora(
    reserva.check_in_programado || reserva.check_in,
  );
  const checkOut = separarFechaHora(
    reserva.check_out_programado || reserva.check_out,
  );

  if (estado.elementos?.fechaEntrada) {
    estado.elementos.fechaEntrada.value = checkIn.fecha;
  }
  if (estado.elementos?.horaEntrada) {
    estado.elementos.horaEntrada.value = checkIn.hora;
  }
  if (estado.elementos?.fechaSalida) {
    estado.elementos.fechaSalida.value = checkOut.fecha;
  }
  if (estado.elementos?.horaSalida) {
    estado.elementos.horaSalida.value = checkOut.hora;
  }

  estado.habitacionesSeleccionadas = Array.isArray(reserva.habitaciones)
    ? reserva.habitaciones.map(normalizarHabitacion)
    : [];
  estado.habitacionesHistorial = Array.isArray(reserva.habitaciones_historial)
    ? reserva.habitaciones_historial.map(normalizarHabitacion)
    : estado.habitacionesSeleccionadas;
  estado.reservaEstado = String(reserva.estado || "").toLowerCase();

  bloquearCamposPorEstadoReserva();
  renderizarHabitacionesSeleccionadas();
};

const normalizarHabitacion = (habitacion) => ({
  id: String(habitacion.id),
  reserva_habitacion_id: habitacion.reserva_habitacion_id || null,
  numero_habitacion: habitacion.numero_habitacion,
  piso: habitacion.piso,
  tipo_nombre: habitacion.tipo_nombre,
  precio: Number(habitacion.precio || 0),
  precio_aplicado: Number(habitacion.precio_aplicado || habitacion.precio || 0),
  subtotal: Number(habitacion.subtotal || 0),
  id_tipo_habitacion:
    habitacion.id_tipo_habitacion || habitacion.tipo_id || null,
  tipo_asignacion: habitacion.tipo_asignacion || "original",
  estado_asignacion: habitacion.estado_asignacion || "activa",
  check_in: habitacion.check_in || "",
  check_out: habitacion.check_out || "",
});

const formatearHabitacionTexto = (habitacion) =>
  `Hab. ${habitacion.numero_habitacion} - Piso ${habitacion.piso} - ${habitacion.tipo_nombre} - S/ ${Number(habitacion.precio || 0).toFixed(2)}`;

const obtenerDiasEstadia = (checkIn, checkOut) => {
  if (!checkIn || !checkOut) return 0;

  const fechaInicio = String(checkIn).slice(0, 10);
  const fechaFin = String(checkOut).slice(0, 10);

  const inicio = new Date(`${fechaInicio}T00:00:00`);
  const fin = new Date(`${fechaFin}T00:00:00`);

  if (
    Number.isNaN(inicio.getTime()) ||
    Number.isNaN(fin.getTime()) ||
    fin <= inicio
  ) {
    return 0;
  }

  return Math.max(1, Math.ceil((fin - inicio) / 86400000));
};

const obtenerDiasEstadiaActiva = (checkIn, checkOut) => {
  const dias = obtenerDiasEstadia(checkIn, checkOut);
  const fechaCheckOut = String(checkOut || "").slice(0, 10);

  return fechaCheckOut === obtenerFechaActualISO() ? Math.max(1, dias) : dias;
};

const obtenerFechaEfectivaCobroCambio = (tipoMotivo = "") => {
  const ahora = new Date();
  const fechaBase = new Date(
    ahora.getFullYear(),
    ahora.getMonth(),
    ahora.getDate(),
  );

  if (tipoMotivo === "solicitud_cliente" && ahora.getHours() >= 12) {
    fechaBase.setDate(fechaBase.getDate() + 1);
  }

  const anio = fechaBase.getFullYear();
  const mes = String(fechaBase.getMonth() + 1).padStart(2, "0");
  const dia = String(fechaBase.getDate()).padStart(2, "0");
  return `${anio}-${mes}-${dia}`;
};

const obtenerHabitacionSeleccionadaPorId = (id) => {
  const estado = obtenerEstadoModalReserva();
  return (estado.habitacionesSeleccionadas || []).find(
    (habitacion) => String(habitacion.id) === String(id),
  );
};

const esEdicionEnEstadia = () => {
  const estado = obtenerEstadoModalReserva();
  return (
    estado.modo === "editar" &&
    ["en_estadia", "checkout_pendiente"].includes(
      String(estado.reservaEstado || ""),
    )
  );
};

const bloquearCamposPorEstadoReserva = () => {
  const estado = obtenerEstadoModalReserva();
  const bloquearEstadia = esEdicionEnEstadia();

  [
    estado.elementos?.inputBuscarCliente,
    estado.elementos?.selectorCliente,
    estado.elementos?.campoNombre,
    estado.elementos?.campoDni,
    estado.elementos?.campoEmail,
    estado.elementos?.procedencia,
    estado.elementos?.fechaEntrada,
    estado.elementos?.horaEntrada,
  ].forEach((elemento) => {
    if (elemento) elemento.disabled = bloquearEstadia;
  });
};

const calcularTotalReserva = () => {
  const estado = obtenerEstadoModalReserva();
  const fechaEntrada = estado.elementos?.fechaEntrada?.value || "";
  const horaEntrada = estado.elementos?.horaEntrada?.value || "";
  const fechaSalida = estado.elementos?.fechaSalida?.value || "";
  const horaSalida = estado.elementos?.horaSalida?.value || "";
  const habitaciones = estado.habitacionesSeleccionadas || [];

  const checkIn =
    fechaEntrada && horaEntrada ? `${fechaEntrada}T${horaEntrada}` : "";
  const checkOut =
    fechaSalida && horaSalida ? `${fechaSalida}T${horaSalida}` : "";
  const dias = obtenerDiasEstadia(checkIn, checkOut);
  if (dias === 0 || habitaciones.length === 0) return 0;

  if (esEdicionEnEstadia()) {
    const historial = Array.isArray(estado.habitacionesHistorial)
      ? estado.habitacionesHistorial
      : [];
    const totalHistorico = historial
      .filter((habitacion) => {
        const estadoAsignacion = String(
          habitacion.estado_asignacion || habitacion.estado || "activa",
        ).toLowerCase();
        return estadoAsignacion !== "activa";
      })
      .reduce(
        (acumulado, habitacion) =>
          acumulado + Number(habitacion.subtotal || 0),
        0,
      );

    const totalActivo = habitaciones.reduce((acumulado, habitacion) => {
      const inicioHabitacion = habitacion.check_in || checkIn;
      const diasHabitacion = obtenerDiasEstadiaActiva(
        inicioHabitacion,
        checkOut,
      );
      const precio = Number(
        habitacion.precio_aplicado || habitacion.precio || 0,
      );
      return acumulado + precio * diasHabitacion;
    }, 0);

    return totalHistorico + totalActivo;
  }

  const sumaPrecio = habitaciones.reduce(
    (acumulado, habitacion) =>
      acumulado + Number(habitacion.precio_aplicado || habitacion.precio || 0),
    0,
  );

  return dias * sumaPrecio;
};

const sincronizarHabitaciones = () => {
  const estado = obtenerEstadoModalReserva();
  const habitaciones = estado.habitacionesSeleccionadas || [];

  if (estado.elementos?.inputHabitacionesReserva) {
    estado.elementos.inputHabitacionesReserva.value =
      JSON.stringify(habitaciones);
  }

  if (estado.elementos?.contadorHabitacionesSeleccionadas) {
    estado.elementos.contadorHabitacionesSeleccionadas.textContent = `${habitaciones.length} ${habitaciones.length === 1 ? "habitación" : "habitaciones"}`;
  }

  const totalNuevo = calcularTotalReserva();
  if (estado.elementos?.totalHabitacionesReserva) {
    estado.elementos.totalHabitacionesReserva.textContent = `S/ ${totalNuevo.toFixed(2)}`;
  }

  // Actualizar penalidad y devolución en caliente en el modal de edición
  const contenedorPenalidad = document.getElementById("contenedor-penalidad-edicion");
  const txtPenalidad = document.getElementById("montoPenalidadEdicion");
  const txtDevolver = document.getElementById("montoDevolverEdicion");

  if (contenedorPenalidad && txtPenalidad && txtDevolver) {
    const esEstadia = estado.modo === "editar" && ["en_estadia", "checkout_pendiente"].includes(String(estado.reservaEstado || ""));
    const esConfirmada = estado.modo === "editar" && ["confirmada", "pre_checkin"].includes(String(estado.reservaEstado || ""));

    const totalAnterior = Number(estado.reservaTotalOriginal || 0);
    const totalPagado = Number(estado.reservaTotalPagadoNeto || estado.reservaTotalPagado || 0);

    if ((esEstadia || esConfirmada) && haCambiadoReserva() && totalPagado > totalNuevo) {
      const montoCancelado = Math.max(0, totalAnterior - totalNuevo);
      const montoPenalidad = redondearMonedaPeru(montoCancelado * 0.25);
      const excesoDevolvible = Math.max(0, totalPagado - totalNuevo);
      const montoDevolver = redondearMonedaPeru(Math.min(montoCancelado - montoPenalidad, excesoDevolvible));
      const montoTotal = redondearMonedaPeru(montoPenalidad + montoDevolver);

      txtPenalidad.textContent = `S/ ${montoPenalidad.toFixed(2)}`;
      txtDevolver.textContent = `S/ ${montoDevolver.toFixed(2)}`;

      const txtTotal = document.getElementById("montoTotalDevolucionEdicion");
      if (txtTotal) {
        txtTotal.textContent = `S/ ${montoTotal.toFixed(2)}`;
      }

      contenedorPenalidad.style.display = "block";
    } else {
      contenedorPenalidad.style.display = "none";
    }
  }
};

const renderizarClientes = () => {
  const estado = obtenerEstadoModalReserva();
  const selectorCliente = estado.elementos?.selectorCliente;
  if (!selectorCliente) return;
  // Siempre dejar la opción por defecto
  selectorCliente.innerHTML = '<option value="">Seleccionar cliente</option>';

  // Si no hay clientes cargados, no agregamos más opciones (evita mostrar "Sin resultados")
  if (!estado.clientes || estado.clientes.length === 0) {
    return;
  }

  estado.clientes.forEach((cliente) => {
    selectorCliente.innerHTML += `<option value="${cliente.id}">${cliente.nombre}</option>`;
  });
};

const renderizarHabitacionesDisponibles = () => {
  const estado = obtenerEstadoModalReserva();
  const lista = estado.elementos?.listaHabitacionesDisponibles;
  if (!lista) return;

  lista.innerHTML = "";

  const habitaciones = estado.habitacionesDisponibles || [];
  if (habitaciones.length === 0) {
    lista.innerHTML =
      '<div class="vacío-habitaciones">No hay habitaciones para mostrar con los filtros actuales.</div>';
    return;
  }

  habitaciones.forEach((habitacion) => {
    const seleccionada = obtenerHabitacionSeleccionadaPorId(habitacion.id);
    const cambiando = Boolean(estado.habitacionCambioActual);
    const textoBoton = cambiando ? "Cambiar por esta" : "Agregar";
    const card = document.createElement("article");
    card.className = `habitacion-card${seleccionada ? " seleccionada" : ""}`;
    card.innerHTML = `
      <div class="habitacion-card-info">
        <strong>${formatearHabitacionTexto(habitacion)}</strong>
      </div>
      <div class="habitacion-card-acciones">
        <button type="button" class="boton-habitacion agregar" ${seleccionada ? "disabled" : ""} data-id="${habitacion.id}">
          ${seleccionada ? "Seleccionada" : textoBoton}
        </button>
      </div>
    `;
    lista.appendChild(card);
  });
};

const renderizarHabitacionesSeleccionadas = () => {
  const estado = obtenerEstadoModalReserva();
  const lista = estado.elementos?.listaHabitacionesSeleccionadas;
  if (!lista) return;

  lista.innerHTML = "";

  const habitaciones = estado.habitacionesSeleccionadas || [];
  if (habitaciones.length === 0) {
    lista.innerHTML =
      '<div class="vacío-habitaciones">Aún no has agregado habitaciones.</div>';
    sincronizarHabitaciones();
    return;
  }

  habitaciones.forEach((habitacion) => {
    const cambioPendiente =
      estado.habitacionCambioPendiente &&
      String(estado.habitacionCambioPendiente.actual?.id) ===
        String(habitacion.id)
        ? estado.habitacionCambioPendiente
        : null;
    const botonAccion = cambioPendiente
      ? `<button type="button" class="boton-habitacion cancelar-cambio-pendiente" data-id="${habitacion.id}">Cancelar cambio</button>`
      : esEdicionEnEstadia()
        ? `<button type="button" class="boton-habitacion cambiar" data-id="${habitacion.id}">Cambiar habitación</button>`
        : `<button type="button" class="boton-habitacion quitar" data-id="${habitacion.id}">Quitar</button>`;
    const cambioActual =
      estado.habitacionCambioActual &&
      String(estado.habitacionCambioActual.id) === String(habitacion.id)
        ? `<div class="habitacion-cambio-panel">
            <label>Motivo del cambio</label>
            <select id="motivoTipoCambioHabitacion" class="input-modal">
              <option value="solicitud_cliente">Solicitud del cliente</option>
              <option value="falla_hotel">Falla de habitación</option>
            </select>
            <input id="motivoCambioHabitacion" class="input-modal" type="text" placeholder="Detalle del motivo" />
            <div class="habitacion-cambio-acciones">
              <button type="button" class="boton-habitacion confirmar-cambio" ${estado.habitacionCambioNueva ? "" : "disabled"}>Confirmar cambio</button>
              <button type="button" class="boton-habitacion cancelar-cambio">Cancelar cambio</button>
            </div>
          </div>`
        : "";
    const reemplazo = cambioPendiente
      ? `<div class="habitacion-cambio-pendiente">
            <span class="habitacion-cambio-etiqueta">Cambio pendiente</span>
            <div class="habitacion-cambio-par">
              <div>
                <small>Habitación actual</small>
                <strong>${formatearHabitacionTexto(cambioPendiente.actual)}</strong>
              </div>
              <div>
                <small>Se cambiará por</small>
                <strong>${formatearHabitacionTexto(cambioPendiente.nueva)}</strong>
              </div>
            </div>
            <p>${cambioPendiente.tipo_motivo === "falla_hotel" ? "Falla de habitación" : "Solicitud del cliente"} - ${cambioPendiente.motivo}</p>
          </div>`
      : estado.habitacionCambioActual &&
          String(estado.habitacionCambioActual.id) === String(habitacion.id) &&
          estado.habitacionCambioNueva
        ? `<div class="habitacion-reemplazo">Se cambiará por: <strong>${formatearHabitacionTexto(estado.habitacionCambioNueva)}</strong></div>`
        : "";
    const card = document.createElement("article");
    card.className = "habitacion-card seleccionada";
    card.innerHTML = `
      <div class="habitacion-card-info">
        <strong>${formatearHabitacionTexto(habitacion)}</strong>
        ${reemplazo}
        ${cambioActual}
      </div>
      <div class="habitacion-card-acciones">
        ${botonAccion}
      </div>
    `;
    lista.appendChild(card);
  });

  sincronizarHabitaciones();
};

const actualizarHoraMinimaEntrada = () => {
  establecerHorasPorDefectoEstadia(false);
};

const actualizarMinimosFecha = () => {
  const estado = obtenerEstadoModalReserva();
  const fechaEntrada = estado.elementos?.fechaEntrada;
  const fechaSalida = estado.elementos?.fechaSalida;
  if (!fechaEntrada || !fechaSalida) return;

  const hoy = obtenerFechaActualISO();
  fechaEntrada.min = hoy;
  const minimoCheckout = fechaEntrada.value
    ? sumarDiasISO(fechaEntrada.value, 1)
    : sumarDiasISO(hoy, 1);
  fechaSalida.min = minimoCheckout || hoy;

  if (fechaEntrada.value) {
    if (!fechaSalida.value || fechaSalida.value < fechaSalida.min) {
      fechaSalida.value = fechaSalida.min;
    }
  }

  establecerHorasPorDefectoEstadia(false);
};

const limpiarSeleccionHabitaciones = () => {
  const estado = obtenerEstadoModalReserva();
  estado.habitacionesSeleccionadas = [];
  renderizarHabitacionesSeleccionadas();
  renderizarHabitacionesDisponibles();
};

const agregarHabitacionSeleccionada = (idHabitacion) => {
  const estado = obtenerEstadoModalReserva();
  const habitacion = (estado.habitacionesDisponibles || []).find(
    (item) => String(item.id) === String(idHabitacion),
  );

  if (!habitacion) return;

  if (estado.habitacionCambioActual) {
    estado.habitacionCambioNueva = normalizarHabitacion(habitacion);
    renderizarHabitacionesDisponibles();
    renderizarHabitacionesSeleccionadas();
    return;
  }

  const yaExiste = (estado.habitacionesSeleccionadas || []).some(
    (item) => String(item.id) === String(habitacion.id),
  );

  if (yaExiste) return;

  estado.habitacionesSeleccionadas.push(normalizarHabitacion(habitacion));
  renderizarHabitacionesDisponibles();
  renderizarHabitacionesSeleccionadas();
};

const iniciarCambioHabitacion = (idHabitacion) => {
  const estado = obtenerEstadoModalReserva();
  if (estado.habitacionCambioPendiente) {
    Swal.fire(
      "Cambio pendiente",
      "Primero guarda o cancela el cambio de habitación pendiente.",
      "info",
    );
    return;
  }

  const habitacion = obtenerHabitacionSeleccionadaPorId(idHabitacion);
  if (!habitacion) return;

  estado.habitacionCambioActual = habitacion;
  estado.habitacionCambioNueva = null;
  cargarHabitacionesDisponibles();
  renderizarHabitacionesSeleccionadas();
};

const cancelarCambioHabitacion = () => {
  const estado = obtenerEstadoModalReserva();
  estado.habitacionCambioActual = null;
  estado.habitacionCambioNueva = null;
  cargarHabitacionesDisponibles();
  renderizarHabitacionesSeleccionadas();
};

const cancelarCambioHabitacionPendiente = () => {
  const estado = obtenerEstadoModalReserva();
  estado.habitacionCambioPendiente = null;
  estado.habitacionCambioActual = null;
  estado.habitacionCambioNueva = null;
  cargarHabitacionesDisponibles();
  renderizarHabitacionesSeleccionadas();
};

const confirmarCambioHabitacion = async () => {
  const estado = obtenerEstadoModalReserva();
  const actual = estado.habitacionCambioActual;
  const nueva = estado.habitacionCambioNueva;
  if (!actual || !nueva) return;

  const tipoMotivo =
    document.getElementById("motivoTipoCambioHabitacion")?.value || "";
  const motivo =
    document.getElementById("motivoCambioHabitacion")?.value?.trim() || "";
  if (!motivo) {
    Swal.fire(
      "Motivo requerido",
      "Indica el detalle del cambio de habitación.",
      "warning",
    );
    return;
  }

  const fechaSalida = estado.elementos?.fechaSalida?.value || "";
  const hoy = obtenerFechaActualISO();
  if (tipoMotivo === "solicitud_cliente" && fechaSalida === hoy) {
    Swal.fire(
      "No se puede cambiar",
      "Si el cambio es por solicitud del cliente y la salida es hoy, primero actualiza la fecha de checkout.",
      "warning",
    );
    return;
  }

  const totalActual = Number(estado.reservaTotalOriginal || 0);
  const precioActual = Number(actual.precio_aplicado || actual.precio || 0);
  const precioNuevoReal = Number(nueva.precio || 0);
  const precioNuevoAplicado =
    tipoMotivo === "falla_hotel"
      ? Math.min(precioActual, precioNuevoReal)
      : precioNuevoReal;
  const fechaEfectivaCobro = obtenerFechaEfectivaCobroCambio(tipoMotivo);
  const diasRestantes = obtenerDiasEstadia(
    fechaEfectivaCobro,
    estado.elementos?.fechaSalida?.value || "",
  );
  const diferencia = (precioNuevoAplicado - precioActual) * diasRestantes;
  const nuevoTotal = totalActual + diferencia;

  let nota = "";
  if (tipoMotivo === "falla_hotel" && precioNuevoReal > precioActual) {
    nota =
      "<p>No se cobrará diferencia porque el cambio es responsabilidad del hotel.</p>";
  } else if (diferencia > 0) {
    nota = `<p>El cliente debe pagar S/ ${diferencia.toFixed(2)} adicionales.</p>`;
  } else if (diferencia < 0) {
    nota = `<p>Se genera un ajuste a favor del cliente de S/ ${Math.abs(diferencia).toFixed(2)}.</p>`;
  } else {
    nota = "<p>El cambio no modifica el total de la reserva.</p>";
  }

  const resultado = await Swal.fire({
    title: "Cambio de habitación",
    html: `
      <div style="text-align:left; display:grid; gap:8px;">
        <p><strong>Habitación actual:</strong><br>${formatearHabitacionTexto(actual)}</p>
        <p><strong>Nueva habitación:</strong><br>${formatearHabitacionTexto(nueva)}</p>
        <p><strong>Motivo:</strong><br>${tipoMotivo === "falla_hotel" ? "Falla de habitación" : "Solicitud del cliente"} - ${motivo}</p>
        <p><strong>Fecha efectiva de cobro:</strong><br>${fechaEfectivaCobro}</p>
        <p><strong>Resumen económico:</strong><br>Total actual: S/ ${totalActual.toFixed(2)}<br>Nuevo total estimado: S/ ${nuevoTotal.toFixed(2)}<br>Ajuste: S/ ${diferencia.toFixed(2)}</p>
        ${nota}
      </div>
    `,
    icon: "question",
    showCancelButton: true,
    confirmButtonText: "Confirmar cambio",
    cancelButtonText: "Cancelar",
  });

  if (!resultado.isConfirmed) return;

  estado.habitacionCambioPendiente = {
    actual,
    nueva,
    tipo_motivo: tipoMotivo,
    motivo,
  };
  estado.habitacionCambioActual = null;
  estado.habitacionCambioNueva = null;
  await Swal.fire(
    "Cambio preparado",
    "El cambio se aplicará cuando guardes la actualización.",
    "success",
  );
  renderizarHabitacionesDisponibles();
  renderizarHabitacionesSeleccionadas();
};

const quitarHabitacionSeleccionada = (idHabitacion) => {
  const estado = obtenerEstadoModalReserva();
  estado.habitacionesSeleccionadas = (
    estado.habitacionesSeleccionadas || []
  ).filter((item) => String(item.id) !== String(idHabitacion));
  renderizarHabitacionesDisponibles();
  renderizarHabitacionesSeleccionadas();
};

const cargarClientes = async (texto = "") => {
  const estado = obtenerEstadoModalReserva();
  const mensajeBusquedaCliente = estado.elementos?.mensajeBusquedaCliente;
  const textoBusqueda = String(texto || "").trim();
  const btnRegistrar = estado.elementos?.btnRegistrarCliente;
  const esDniCompleto = esDocumentoCompletoOchoDigitos(textoBusqueda);
  limpiarResaltadoRegistrarCliente();
  // No cargar la lista completa si el usuario no ha escrito nada
  if (textoBusqueda === "") {
    estado.clientes = [];
    renderizarClientes();
    if (mensajeBusquedaCliente) {
      mensajeBusquedaCliente.textContent =
        "Escribe un nombre o DNI para buscar clientes.";
    }
    return;
  }
  // AbortController: cancela la petición anterior si todavía está en vuelo
  _controladorBusquedaClienteModal?.abort();
  _controladorBusquedaClienteModal = new AbortController();
  if (mensajeBusquedaCliente) {
    mensajeBusquedaCliente.textContent = "Buscando clientes...";
  }
  try {
    const res = await fetch(
      BASE_URL + `Cliente/buscar?q=${encodeURIComponent(textoBusqueda)}`,
      {
        signal: _controladorBusquedaClienteModal.signal,
        headers: {
          Accept: "application/json",
        },
      },
    );

    if (!res.ok) {
      throw new Error(`Error HTTP ${res.status}`);
    }

    const contentType = res.headers.get("content-type") || "";
    if (!contentType.includes("application/json")) {
      throw new Error("La respuesta del servidor no es JSON.");
    }

    const respuesta = await res.json();
    if (respuesta.error) {
      if (mensajeBusquedaCliente)
        mensajeBusquedaCliente.textContent = "No se pudo cargar clientes.";
      return;
    }
    estado.clientes = respuesta.clientes || [];
    renderizarClientes();
    if (mensajeBusquedaCliente) {
      const clienteInhabilitado = respuesta.cliente_inhabilitado || null;
      if (textoBusqueda !== "" && clienteInhabilitado) {
        mensajeBusquedaCliente.textContent =
          "Este cliente ya existe en la base de datos pero esta inhabilitado. Si desea hacer una reserva, habilitelo desde el modulo de clientes.";
        return;
      }
      if (estado.clientes.length === 0) {
        mensajeBusquedaCliente.textContent = esDniCompleto
          ? "No se encontró un cliente con ese documento."
          : "No se encontraron clientes.";
        if (esDniCompleto) {
          abrirModalNuevoClienteConDocumento(textoBusqueda);
        } else if (btnRegistrar) {
          resaltarBotonRegistrarCliente();
        }
        return;
      }
      mensajeBusquedaCliente.textContent = "Selecciona un cliente de la lista.";
    }
  } catch (error) {
    if (error.name === "AbortError") return; // petición cancelada intencionalmente, ignorar
    console.error("Error buscando clientes en modal reserva:", error);
    if (mensajeBusquedaCliente) {
      mensajeBusquedaCliente.textContent =
        "No se pudieron cargar los clientes.";
    }
    if (!esDniCompleto && textoBusqueda !== "") {
      resaltarBotonRegistrarCliente();
    }
  }
};

const seleccionarCliente = () => {
  const estado = obtenerEstadoModalReserva();
  const selectorCliente = estado.elementos?.selectorCliente;
  const idClienteReserva = estado.elementos?.idClienteReserva;
  const campoNombre = estado.elementos?.campoNombre;
  const campoDni = estado.elementos?.campoDni;
  const campoEmail = estado.elementos?.campoEmail;
  const mensajeBusquedaCliente = estado.elementos?.mensajeBusquedaCliente;

  if (!selectorCliente) return;

  const idSeleccionado = selectorCliente.value;
  if (idClienteReserva) idClienteReserva.value = "";

  if (!idSeleccionado) {
    if (campoNombre) campoNombre.value = "";
    if (campoDni) campoDni.value = "";
    if (campoEmail) campoEmail.value = "";
    estado.clienteSeleccionado = null;
    return;
  }

  const cliente = (estado.clientes || []).find(
    (item) => String(item.id) === String(idSeleccionado),
  );

  if (!cliente) return;

  estado.clienteSeleccionado = cliente;

  if (idClienteReserva) idClienteReserva.value = cliente.id;
  if (campoNombre) campoNombre.value = cliente.nombre || "";
  if (estado.elementos?.tipoDocumentoCliente) {
    estado.elementos.tipoDocumentoCliente.value = resolverTipoDocumentoCliente(cliente);
  }
  actualizarDocumentoClienteReserva(cliente);
  if (estado.elementos?.procedencia)
    estado.elementos.procedencia.value = cliente.procedencia || "";
  if (campoEmail) campoEmail.value = cliente.correo || "";
  if (mensajeBusquedaCliente)
    mensajeBusquedaCliente.textContent = "Cliente seleccionado correctamente.";
};

const validarFechasReserva = () => {
  const estado = obtenerEstadoModalReserva();
  const fechaEntrada = estado.elementos?.fechaEntrada?.value || "";
  const horaEntrada = estado.elementos?.horaEntrada?.value || "";
  const fechaSalida = estado.elementos?.fechaSalida?.value || "";
  const horaSalida = estado.elementos?.horaSalida?.value || "";

  if (!fechaEntrada || !horaEntrada || !fechaSalida || !horaSalida) {
    window.Alerta("Completa check-in y check-out", "advertencia");
    return false;
  }

  const checkIn = new Date(`${fechaEntrada}T00:00:00`);
  const checkOut = new Date(`${fechaSalida}T00:00:00`);

  if (Number.isNaN(checkIn.getTime()) || Number.isNaN(checkOut.getTime())) {
    window.Alerta("Fecha u hora inválida", "advertencia");
    return false;
  }

  if (checkOut <= checkIn) {
    window.Alerta(
      "La fecha de check-out debe ser posterior a la fecha de check-in",
      "advertencia",
    );
    return false;
  }

  return true;
};

const cargarHabitacionesDisponibles = () => {
  const estado = obtenerEstadoModalReserva();
  const fechaEntrada = estado.elementos?.fechaEntrada?.value || "";
  const horaEntrada = estado.elementos?.horaEntrada?.value || "";
  const fechaSalida = estado.elementos?.fechaSalida?.value || "";
  const horaSalida = estado.elementos?.horaSalida?.value || "";
  const filtroTipoReserva = estado.elementos?.filtroTipoReserva;
  const filtroPisoReserva = estado.elementos?.filtroPisoReserva;
  const mensajeHabitaciones = estado.elementos?.mensajeHabitaciones;
  const listaDisponibles = estado.elementos?.listaHabitacionesDisponibles;

  const fechaCambio = new Date();
  const fechaCambioISO = `${fechaCambio.getFullYear()}-${String(fechaCambio.getMonth() + 1).padStart(2, "0")}-${String(fechaCambio.getDate()).padStart(2, "0")}`;
  const horaCambioISO = `${String(fechaCambio.getHours()).padStart(2, "0")}:${String(fechaCambio.getMinutes()).padStart(2, "0")}:00`;
  const checkIn = estado.habitacionCambioActual
    ? `${fechaCambioISO} ${horaCambioISO}`
    : fechaEntrada && horaEntrada
      ? `${fechaEntrada} ${horaEntrada}:00`
      : "";
  const checkOut = fechaSalida && horaSalida ? `${fechaSalida} ${horaSalida}:00` : "";

  estado.habitacionesDisponibles = [];
  renderizarHabitacionesDisponibles();

  if (!checkIn || !checkOut) {
    if (mensajeHabitaciones)
      mensajeHabitaciones.textContent =
        "Primero selecciona check-in y check-out.";
    if (listaDisponibles) {
      listaDisponibles.innerHTML =
        '<div class="vacío-habitaciones">Selecciona fechas para cargar habitaciones.</div>';
    }
    return;
  }

  if (
    new Date(`${checkOut.slice(0, 10)}T00:00:00`) <=
      new Date(`${checkIn.slice(0, 10)}T00:00:00`) &&
    !estado.habitacionCambioActual
  ) {
    if (mensajeHabitaciones)
      mensajeHabitaciones.textContent =
        "El check-out debe ser posterior al check-in.";
    if (listaDisponibles) {
      listaDisponibles.innerHTML =
        '<div class="vacío-habitaciones">Corrige las fechas para ver habitaciones disponibles.</div>';
    }
    return;
  }

  const params = new URLSearchParams({
    check_in: checkIn,
    check_out: checkOut,
  });

  if (filtroTipoReserva && filtroTipoReserva.value)
    params.append("tipo", filtroTipoReserva.value);
  if (filtroPisoReserva && filtroPisoReserva.value)
    params.append("piso", filtroPisoReserva.value);
  if (estado.habitacionCambioActual) {
    params.append(
      "precio_referencia",
      estado.habitacionCambioActual.precio_aplicado ||
        estado.habitacionCambioActual.precio ||
        0,
    );
    if (estado.habitacionCambioActual.id_tipo_habitacion) {
      params.append(
        "tipo_referencia",
        estado.habitacionCambioActual.id_tipo_habitacion,
      );
    }
    if (estado.habitacionCambioActual.piso) {
      params.append("piso_referencia", estado.habitacionCambioActual.piso);
    }
  }

  return fetch(BASE_URL + `Habitacion/disponiblesPorRango&${params.toString()}`)
    .then((res) => res.json())
    .then((respuesta) => {
      const habitaciones = Array.isArray(respuesta)
        ? respuesta
        : respuesta.habitaciones || [];

      estado.habitacionesDisponibles = habitaciones.map(normalizarHabitacion);
      renderizarHabitacionesDisponibles();
      renderizarHabitacionesSeleccionadas();

      if (mensajeHabitaciones) {
        mensajeHabitaciones.textContent = estado.habitacionesDisponibles.length
          ? "Habitaciones disponibles para el rango seleccionado."
          : "No hay habitaciones limpias y disponibles para esas fechas.";
      }
    })
    .catch(() => {
      if (mensajeHabitaciones) {
        mensajeHabitaciones.textContent =
          "No se pudieron cargar habitaciones disponibles.";
      }
    });
};

const cargarFiltrosHabitacion = () => {
  const estado = obtenerEstadoModalReserva();
  const filtroTipoReserva = estado.elementos?.filtroTipoReserva;
  const filtroPisoReserva = estado.elementos?.filtroPisoReserva;

  return fetch(BASE_URL + "Habitacion/obtenerFiltros")
    .then((res) => res.json())
    .then((data) => {
      if (filtroTipoReserva && data.tipos) {
        filtroTipoReserva.innerHTML =
          '<option value="">Todos los tipos</option>';
        data.tipos.forEach((tipo) => {
          filtroTipoReserva.innerHTML += `<option value="${tipo.id}">${tipo.tipo}</option>`;
        });
      }

      if (filtroPisoReserva && data.pisos) {
        filtroPisoReserva.innerHTML =
          '<option value="">Todos los pisos</option>';
        data.pisos.forEach((piso) => {
          filtroPisoReserva.innerHTML += `<option value="${piso}">Piso ${piso}</option>`;
        });
      }
    })
    .catch((err) => console.error("Error cargando filtros:", err));
};

const prepararResumenReserva = () => {
  const estado = obtenerEstadoModalReserva();
  const habitaciones = estado.habitacionesSeleccionadas || [];

  return {
    habitaciones,
    habitacionTexto: habitaciones.map(formatearHabitacionTexto).join(" | "),
    habitacionPrincipal: habitaciones[0]?.id || "",
    totalReserva: calcularTotalReserva(),
  };
};

const obtenerDatosReservaDesdeFormulario = () => {
  const estado = obtenerEstadoModalReserva();
  const selectorCliente = estado.elementos?.selectorCliente;
  const idClienteReserva = estado.elementos?.idClienteReserva;
  const campoNombre = estado.elementos?.campoNombre;
  const campoDni = estado.elementos?.campoDni;
  const campoEmail = estado.elementos?.campoEmail;
  const procedenciaCampo = estado.elementos?.procedencia;
  const fechaEntrada = estado.elementos?.fechaEntrada;
  const horaEntrada = estado.elementos?.horaEntrada;
  const fechaSalida = estado.elementos?.fechaSalida;
  const horaSalida = estado.elementos?.horaSalida;

  const cliente = idClienteReserva?.value || selectorCliente?.value || "";
  const nombre = campoNombre?.value.trim() || "";
  const dni = campoDni?.value.trim() || "";
  const email = campoEmail?.value.trim() || "";
  const procedencia = procedenciaCampo?.value.trim() || "";
  const habitaciones = estado.habitacionesSeleccionadas || [];

  if (!cliente) {
    window.Alerta("Selecciona un cliente", "advertencia");
    return null;
  }
  if (!nombre) {
    window.Alerta("Nombre y apellido obligatorio", "advertencia");
    return null;
  }
  if (!dni) {
    window.Alerta("Documento obligatorio", "advertencia");
    return null;
  }
  if (!email) {
    window.Alerta("Correo electrónico obligatorio", "advertencia");
    return null;
  }
  if (habitaciones.length === 0) {
    window.Alerta("Selecciona al menos una habitación", "advertencia");
    return null;
  }

  if (!validarFechasReserva()) return null;
  if (
    !fechaEntrada?.value ||
    !horaEntrada?.value ||
    !fechaSalida?.value ||
    !horaSalida?.value
  )
    return null;

  const textoClienteSeleccionado =
    selectorCliente?.options?.[selectorCliente.selectedIndex]?.text || "";
  const resumen = prepararResumenReserva();

  return {
    cliente,
    idCliente: cliente,
    clienteTexto: textoClienteSeleccionado,
    nombre,
    dni,
    procedencia,
    email,
    checkIn: fechaEntrada.value,
    horaEntrada: horaEntrada.value,
    checkOut: fechaSalida.value,
    horaSalida: horaSalida.value,
    habitacion: resumen.habitacionTexto,
    habitaciones: resumen.habitaciones,
    habitacionPrincipal: resumen.habitacionPrincipal,
    totalReserva: resumen.totalReserva,
    idReserva: estado.reservaEditandoId || "",
  };
};

const validarYContinuarPago = async () => {
  const estado = obtenerEstadoModalReserva();
  const modal = estado.elementos?.modal;
  const contenedor = estado.elementos?.contenedor;
  const datosReserva = obtenerDatosReservaDesdeFormulario();

  if (!datosReserva) return;

  if (estado.reservaEditandoId) {
    await confirmarGuardarEdicionReserva(datosReserva);
    return;
  }

  const pagoAbierto = await abrirModalPagoConDatos(datosReserva);
  if (!pagoAbierto) return;

  if (modal) modal.style.display = "none";
  if (contenedor) contenedor.style.display = "none";
};

const registrarReservaPendiente = async () => {
  const estado = obtenerEstadoModalReserva();
  const datosReserva = obtenerDatosReservaDesdeFormulario();
  if (!datosReserva || estado.reservaEditandoId) return;

  const confirmacion = await Swal.fire({
    title: "Dejar pago pendiente",
    text: "La reserva quedará pendiente y deberá pagarse en un plazo máximo de 6 horas.",
    icon: "question",
    showCancelButton: true,
    confirmButtonText: "Guardar pendiente",
    cancelButtonText: "Cancelar",
  });

  if (!confirmacion.isConfirmed) return;

  try {
    const respuesta = await fetch(BASE_URL + "Reserva/registrar", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        ...datosReserva,
        estado: "pendiente",
        dejar_pago_pendiente: true,
      }),
    });
    const resultado = await respuesta.json();

    if (!respuesta.ok || !resultado.exito) {
      window.Alerta(
        resultado.mensaje || "No se pudo registrar la reserva pendiente.",
        "error",
      );
      return;
    }

    await Swal.fire({
      toast: true,
      position: "top-end",
      icon: "success",
      title: resultado.mensaje || "Reserva pendiente registrada",
      showConfirmButton: false,
      timer: 2200,
      timerProgressBar: true,
    });

    if (estado.elementos?.modal) estado.elementos.modal.style.display = "none";
    if (estado.elementos?.contenedor)
      estado.elementos.contenedor.style.display = "none";
    window.location.reload();
  } catch (error) {
    console.error(error);
    window.Alerta(
      "Error de conexión al registrar la reserva pendiente.",
      "error",
    );
  }
};

const guardarEdicionReserva = async (datosReserva) => {
  const respuesta = await fetch(BASE_URL + "Reserva/actualizar", {
    method: "PUT",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
      ...datosReserva,
      id_reserva: datosReserva.idReserva,
    }),
  });

  const resultado = await respuesta.json();
  return respuesta.ok ? resultado : { ...resultado, exito: false };
};

const registrarPagoDevolucionReserva = async (idReserva, devolucion) => {
  const respuesta = await fetch(BASE_URL + "Reserva/registrarDevolucionPago", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "X-CSRF-Token": typeof CSRF_TOKEN !== "undefined" ? CSRF_TOKEN : "",
    },
    body: JSON.stringify({
      id_reserva: idReserva,
      monto: Number(devolucion?.monto_devuelto || 0),
      fecha_desde_devuelta: devolucion?.fecha_desde_devuelta || "",
      fecha_hasta_devuelta: devolucion?.fecha_hasta_devuelta || "",
    }),
  });

  const resultado = await respuesta.json();
  return respuesta.ok ? resultado : { ...resultado, exito: false };
};

const validarPagoDevolucionReserva = async (idReserva, devolucion) => {
  const respuesta = await fetch(BASE_URL + "Reserva/validarDevolucionPago", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "X-CSRF-Token": typeof CSRF_TOKEN !== "undefined" ? CSRF_TOKEN : "",
    },
    body: JSON.stringify({
      id_reserva: idReserva,
      monto: Number(devolucion?.monto_devuelto || 0),
      fecha_desde_devuelta: devolucion?.fecha_desde_devuelta || "",
      fecha_hasta_devuelta: devolucion?.fecha_hasta_devuelta || "",
    }),
  });

  const resultado = await respuesta.json();
  return respuesta.ok ? resultado : { ...resultado, exito: false };
};

const haCambiadoReserva = () => {
  const estado = obtenerEstadoModalReserva();
  if (estado.modo !== "editar") return false;

  const fechaEntrada = estado.elementos?.fechaEntrada?.value || "";
  const horaEntrada = estado.elementos?.horaEntrada?.value || "";
  const fechaSalida = estado.elementos?.fechaSalida?.value || "";
  const horaSalida = estado.elementos?.horaSalida?.value || "";

  const checkInActual = `${fechaEntrada} ${horaEntrada}`.replace("T", " ").slice(0, 16);
  const checkOutActual = `${fechaSalida} ${horaSalida}`.replace("T", " ").slice(0, 16);

  const checkInOrig = String(estado.reservaCheckInOriginal || "").replace("T", " ").slice(0, 16);
  const checkOutOrig = String(estado.reservaCheckOutOriginal || "").replace("T", " ").slice(0, 16);

  if (checkInActual !== checkInOrig) return true;
  if (checkOutActual !== checkOutOrig) return true;

  const origIds = estado.reservaHabitacionesOriginalesIds || [];
  const actualIds = (estado.habitacionesSeleccionadas || []).map(h => String(h.id));

  if (origIds.length !== actualIds.length) return true;

  for (const id of actualIds) {
    if (!origIds.includes(id)) return true;
  }

  if (estado.habitacionCambioPendiente) return true;

  return false;
};

const redondearMonedaPeru = (valor) => Math.round(Number(valor || 0) * 10) / 10;

const calcularDevolucionPreviaEdicion = (datosReserva) => {
  const estado = obtenerEstadoModalReserva();
  const esEstadia = esEdicionEnEstadia();
  const esConfirmada = estado.modo === "editar" && ["confirmada", "pre_checkin"].includes(String(estado.reservaEstado || ""));

  if (!esEstadia && !esConfirmada) return null;
  if (!haCambiadoReserva()) return null;

  const totalAnterior = Number(estado.reservaTotalOriginal || 0);
  const totalNuevo = Number(datosReserva?.totalReserva || 0);
  const totalPagado = Number(
    estado.reservaTotalPagadoNeto || estado.reservaTotalPagado || 0,
  );

  const montoCancelado = Math.max(0, totalAnterior - totalNuevo);
  const montoPenalidad = redondearMonedaPeru(montoCancelado * 0.25);
  const excesoDevolvible = Math.max(0, totalPagado - totalNuevo);
  const montoDevuelto = redondearMonedaPeru(Math.min(montoCancelado - montoPenalidad, excesoDevolvible));

  if (montoDevuelto <= 0) return null;

  const fechaDesde = esConfirmada ? (datosReserva?.checkIn || "") : (datosReserva?.checkOut || "");
  const fechaHasta = esConfirmada ? (datosReserva?.checkOut || "") : (String(estado.reservaCheckOutOriginal || "").slice(0, 10));
  const descripcion = esConfirmada
    ? `Devolución por reducción de reserva. Total anterior: S/ ${totalAnterior.toFixed(2)}; nuevo total: S/ ${totalNuevo.toFixed(2)}; pagado: S/ ${totalPagado.toFixed(2)}; penalidad (25%): S/ ${montoPenalidad.toFixed(2)}.`
    : `Devolución por disminución de días de estadía del ${fechaDesde} al ${fechaHasta || fechaDesde}. Total anterior: S/ ${totalAnterior.toFixed(2)}; nuevo total: S/ ${totalNuevo.toFixed(2)}; pagado: S/ ${totalPagado.toFixed(2)}; penalidad (25%): S/ ${montoPenalidad.toFixed(2)}.`;

  return {
    monto_devuelto: montoDevuelto,
    monto_penalidad: montoPenalidad,
    porcentaje_penalidad: 25,
    descripcion,
    total_anterior: totalAnterior,
    total_nuevo: totalNuevo,
    total_pagado: totalPagado,
    fecha_desde_devuelta: fechaDesde,
    fecha_hasta_devuelta: fechaHasta || fechaDesde,
  };
};

const confirmarDevolucionAntesDeGuardar = async (devolucion) =>
  Swal.fire({
    icon: "info",
    title: "Devolución por reducción de estadía",
    html: `
      <p>Se debe devolver al cliente:</p>
      <h2 style="margin: 10px 0;">S/ ${Number(devolucion.monto_devuelto).toFixed(2)}</h2>
      <p style="font-size: 0.95rem;">${devolucion.descripcion}</p>
    `,
    confirmButtonText: "Devolver",
    showCancelButton: true,
    cancelButtonText: "Cancelar",
  });

const aplicarCambioHabitacionPendiente = async () => {
  const estado = obtenerEstadoModalReserva();
  const cambio = estado.habitacionCambioPendiente;
  if (!cambio) {
    return { exito: true };
  }

  const respuesta = await fetch(BASE_URL + "Reserva/cambiarHabitacion", {
    method: "PATCH",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
      id_reserva: estado.reservaEditandoId,
      id_habitacion_actual: cambio.actual.id,
      id_habitacion_nueva: cambio.nueva.id,
      tipo_motivo: cambio.tipo_motivo,
      motivo: cambio.motivo,
    }),
  });

  const resultado = await respuesta.json();
  return respuesta.ok ? resultado : { ...resultado, exito: false };
};

const confirmarGuardarEdicionReserva = async (datosReserva) => {
  const estado = obtenerEstadoModalReserva();
  const modal = estado.elementos?.modal;
  const contenedor = estado.elementos?.contenedor;
  const devolucionPrevia = calcularDevolucionPreviaEdicion(datosReserva);

  let devolucionConfirmada = null;

  if (devolucionPrevia) {
    const respuestaDevolucion = await confirmarDevolucionAntesDeGuardar(
      devolucionPrevia,
    );

    if (!respuestaDevolucion.isConfirmed) return;

    const validacionDevolucion = await validarPagoDevolucionReserva(
      datosReserva.idReserva,
      devolucionPrevia,
    );

    if (!validacionDevolucion.exito) {
      await Swal.fire({
        icon: "error",
        title: "No se puede devolver",
        text:
          validacionDevolucion.mensaje ||
          "No se puede registrar la devolución para estas fechas.",
      });
      return;
    }

    devolucionConfirmada = devolucionPrevia;
  } else {
    const resultadoConfirmacion = await Swal.fire({
      title: "Guardar cambios",
      text: "¿Desea guardar los cambios de la reserva?",
      icon: "question",
      showCancelButton: true,
      confirmButtonText: "Sí, guardar",
      cancelButtonText: "Cancelar",
    });

    if (!resultadoConfirmacion.isConfirmed) return;
  }

  try {
    let comprobanteDevolucionAbierto = false;
    const resultado = await guardarEdicionReserva(datosReserva);
    if (!resultado.exito) {
      Swal.fire({
        icon: "error",
        title: "No se pudo guardar",
        text: resultado.mensaje || "No se pudieron guardar los cambios.",
      });
      return;
    }

    const resultadoCambio = await aplicarCambioHabitacionPendiente();
    if (!resultadoCambio.exito) {
      Swal.fire({
        icon: "error",
        title: "No se pudo cambiar la habitación",
        text:
          resultadoCambio.mensaje ||
          "La reserva se guardó, pero no se pudo aplicar el cambio de habitación.",
      });
      return;
    }

    if (resultado.devolucion?.monto_devuelto > 0) {
        const registroDevolucion = await registrarPagoDevolucionReserva(
          resultado.id_reserva || datosReserva.idReserva,
          resultado.devolucion || devolucionConfirmada,
        );

        if (!registroDevolucion.exito) {
          await Swal.fire({
            icon: "error",
            title: "No se pudo registrar la devolución",
            text:
              registroDevolucion.mensaje ||
              "No se pudo generar el movimiento negativo de devolución.",
          });
          return;
        }

        if (
          registroDevolucion.comprobante &&
          typeof window.abrirModalComprobante === "function"
        ) {
          comprobanteDevolucionAbierto = true;
          window.abrirModalComprobante(registroDevolucion.comprobante, {
            recargarAlCerrar: true,
          });
        } else {
          await Swal.fire({
            icon: "success",
            title: "Devolución registrada",
            text: registroDevolucion.mensaje || "Se registró el pago negativo.",
          });
        }
    } else {
      await Swal.fire({
        toast: true,
        position: "top-end",
        icon: "success",
        title: resultado.mensaje || "Reserva actualizada correctamente",
        showConfirmButton: false,
        timer: 2200,
        timerProgressBar: true,
      });
    }

    estado.habitacionCambioPendiente = null;
    if (modal) modal.style.display = "none";
    if (contenedor) contenedor.style.display = "none";
    if (comprobanteDevolucionAbierto) return;
    window.location.reload();
  } catch (error) {
    console.error(error);
    Swal.fire({
      icon: "error",
      title: "Error",
      text: "Error de conexión al guardar la reserva.",
    });
  }
};

const abrirModalPagoConDatos = async (datosReserva) => {
  if (typeof window.abrirModalPago !== "function") {
    await Swal.fire({
      icon: "error",
      title: "Error",
      text: "No se pudo abrir el módulo de pago",
    });
    return false;
  }

  await window.abrirModalPago(datosReserva);
  return true;
};

window.abrirModalReserva = async (modo = "nuevo", datos = null) => {
  const contenedor = document.getElementById("contenedor-modal-reserva");
  if (!contenedor) return;

  contenedor.style.display = "block";

  const modal = document.getElementById("modalReserva");
  if (modal) modal.style.display = "flex";

  const estado = obtenerEstadoModalReserva();
  estado.elementos = {
    contenedor,
    modal,
    form: document.getElementById("formReserva"),
    cerrar: document.getElementById("cerrarModal"),
    btnContinuarPago: document.getElementById("btnContinuarPago"),
    btnDejarPagoPendiente: document.getElementById("btnDejarPagoPendiente"),
    inputBuscarCliente: document.getElementById("buscarCliente"),
    selectorCliente: document.getElementById("selectorClienteReserva"),
    idClienteReserva: document.getElementById("idClienteReserva"),
    campoNombre: document.getElementById("nombre"),
    campoDni: document.getElementById("dni"),
    tipoDocumentoCliente: document.getElementById("tipoDocumentoClienteReserva"),
    procedencia: document.getElementById("procedencia"),
    campoEmail: document.getElementById("email"),
    fechaEntrada: document.getElementById("fechaEntrada"),
    horaEntrada: document.getElementById("horaEntrada"),
    fechaSalida: document.getElementById("fechaSalida"),
    horaSalida: document.getElementById("horaSalida"),
    filtroTipoReserva: document.getElementById("filtroTipoReserva"),
    filtroPisoReserva: document.getElementById("filtroPisoReserva"),
    mensajeHabitaciones: document.getElementById(
      "mensajeHabitacionesDisponibles",
    ),
    mensajeBusquedaCliente: document.getElementById("mensajeBusquedaCliente"),
    btnRegistrarCliente: document.getElementById(
      "btn-registrar-cliente-manual",
    ),
    listaHabitacionesDisponibles: document.getElementById(
      "listaHabitacionesDisponibles",
    ),
    listaHabitacionesSeleccionadas: document.getElementById(
      "listaHabitacionesSeleccionadas",
    ),
    inputHabitacionesReserva: document.getElementById("habitacionesReserva"),
    totalHabitacionesReserva: document.getElementById(
      "totalHabitacionesReserva",
    ),
    contadorHabitacionesSeleccionadas: document.getElementById(
      "contadorHabitacionesSeleccionadas",
    ),
  };

  estado.clientes = [];
  estado.habitacionesDisponibles = [];
  estado.habitacionesSeleccionadas = [];
  estado.habitacionesHistorial = [];
  estado.habitacionCambioActual = null;
  estado.habitacionCambioNueva = null;
  estado.habitacionCambioPendiente = null;
  estado.reservaEstado = "";
  estado.clienteSeleccionado = null;
  estado.reservaTotalOriginal = Number(datos?.total || 0);
  estado.modo = modo;
  estado.reservaEditandoId = datos?.id || null;

  actualizarMinimosFecha();

  estado.elementos.form?.reset();

  if (modo === "nuevo") {
    limpiarSeleccionHabitaciones();
    establecerHorasPorDefectoEstadia(true);
    const titulo = document.querySelector(".titulo-modal");
    if (titulo) titulo.textContent = "Nueva Reserva";
    if (estado.elementos.btnContinuarPago) {
      estado.elementos.btnContinuarPago.textContent = "Continuar con pago";
    }
    if (estado.elementos.btnDejarPagoPendiente) {
      estado.elementos.btnDejarPagoPendiente.style.display = "";
    }
    if (estado.elementos?.tipoDocumentoCliente) {
      estado.elementos.tipoDocumentoCliente.value = "dni";
    }
    if (estado.elementos?.procedencia) estado.elementos.procedencia.value = "";
  }

  if (modo === "editar" && datos) {
    const titulo = document.querySelector(".titulo-modal");
    if (titulo) titulo.textContent = "Editar Reserva";
    if (estado.elementos.btnContinuarPago) {
      estado.elementos.btnContinuarPago.textContent = "Actualizar";
    }
    if (estado.elementos.btnDejarPagoPendiente) {
      estado.elementos.btnDejarPagoPendiente.style.display = "none";
    }
  }

  if (modo === "editar" && datos?.id) {
    try {
      const respuesta = await fetch(
        BASE_URL + `Reserva/obtener/${encodeURIComponent(datos.id)}`,
      );
      const reserva = await respuesta.json();
      const datosReserva = reserva?.id ? reserva : datos;
      estado.reservaTotalOriginal = Number(datosReserva.total || 0);

      await cargarClientes(datosReserva.cliente || "");
      aplicarReservaEdicion(datosReserva);
      actualizarMinimosFecha();
      establecerHorasPorDefectoEstadia(false);
      await cargarFiltrosHabitacion();
      await cargarHabitacionesDisponibles();
      renderizarHabitacionesSeleccionadas();
    } catch (error) {
      console.error("Error cargando reserva para edición:", error);
      await cargarClientes(datos.cliente || "");
      await cargarFiltrosHabitacion();
      await cargarHabitacionesDisponibles();
      aplicarReservaEdicion(datos);
      establecerHorasPorDefectoEstadia(false);
    }
  } else {
    await cargarClientes();
    await cargarFiltrosHabitacion();
    await cargarHabitacionesDisponibles();
  }

  if (!eventosDashboardConfigurados) {
    estado.elementos.inputBuscarCliente?.addEventListener("input", () => {
      // Debounce: espera 300ms después de la última tecla antes de lanzar la petición
      clearTimeout(_temporizadorBusquedaClienteModal);
      _temporizadorBusquedaClienteModal = setTimeout(() => {
        cargarClientes(estado.elementos.inputBuscarCliente.value.trim());
      }, 300);
    });

    estado.elementos.selectorCliente?.addEventListener(
      "change",
      seleccionarCliente,
    );

    estado.elementos.tipoDocumentoCliente?.addEventListener("change", () => {
      actualizarDocumentoClienteReserva();
    });

    estado.elementos.fechaEntrada?.addEventListener("change", () => {
      actualizarMinimosFecha();
      ajustarCheckoutPorDefecto();
      limpiarSeleccionHabitaciones();
      cargarHabitacionesDisponibles();
    });

    estado.elementos.horaEntrada?.addEventListener("change", () => {
      establecerHorasPorDefectoEstadia(false);
      cargarHabitacionesDisponibles();
    });

    estado.elementos.fechaSalida?.addEventListener("change", () => {
      actualizarMinimosFecha();
      establecerHorasPorDefectoEstadia(false);
      cargarHabitacionesDisponibles();
    });

    estado.elementos.horaSalida?.addEventListener(
      "change",
      cargarHabitacionesDisponibles,
    );
    estado.elementos.filtroTipoReserva?.addEventListener(
      "change",
      cargarHabitacionesDisponibles,
    );
    estado.elementos.filtroPisoReserva?.addEventListener(
      "change",
      cargarHabitacionesDisponibles,
    );

    estado.elementos.listaHabitacionesDisponibles?.addEventListener(
      "click",
      (evento) => {
        const boton = evento.target.closest(".boton-habitacion.agregar");
        if (!boton) return;
        agregarHabitacionSeleccionada(boton.dataset.id);
      },
    );

    estado.elementos.listaHabitacionesSeleccionadas?.addEventListener(
      "click",
      (evento) => {
        const botonCambiar = evento.target.closest(".boton-habitacion.cambiar");
        if (botonCambiar) {
          iniciarCambioHabitacion(botonCambiar.dataset.id);
          return;
        }

        const botonConfirmarCambio = evento.target.closest(
          ".boton-habitacion.confirmar-cambio",
        );
        if (botonConfirmarCambio) {
          confirmarCambioHabitacion();
          return;
        }

        const botonCancelarCambio = evento.target.closest(
          ".boton-habitacion.cancelar-cambio",
        );
        if (botonCancelarCambio) {
          cancelarCambioHabitacion();
          return;
        }

        const botonCancelarCambioPendiente = evento.target.closest(
          ".boton-habitacion.cancelar-cambio-pendiente",
        );
        if (botonCancelarCambioPendiente) {
          cancelarCambioHabitacionPendiente();
          return;
        }

        const boton = evento.target.closest(".boton-habitacion.quitar");
        if (boton) quitarHabitacionSeleccionada(boton.dataset.id);
      },
    );

    estado.elementos.cerrar?.addEventListener("click", () => {
      if (modal) modal.style.display = "none";
      if (contenedor) contenedor.style.display = "none";

      // Si se estaba editando una reserva, recargar para actualizar la tabla
      if (estado.reservaEditandoId) {
        window.location.reload();
      }
    });

    estado.elementos.btnContinuarPago?.addEventListener("click", (e) => {
      e.preventDefault();
      validarYContinuarPago();
    });

    estado.elementos.btnDejarPagoPendiente?.addEventListener("click", (e) => {
      e.preventDefault();
      registrarReservaPendiente();
    });

    estado.elementos.form?.addEventListener("submit", (e) => {
      e.preventDefault();
      validarYContinuarPago();
    });

    const btnNuevoCliente = document.getElementById(
      "btn-registrar-cliente-manual",
    );
    if (btnNuevoCliente) {
      btnNuevoCliente.addEventListener("click", () => {
        if (typeof window.abrirModalCliente === "function") {
          window.abrirModalCliente("nuevo");
        }
      });
    }

    eventosDashboardConfigurados = true;
  }
};

window.configurarBtnNuevaReserva = () => {
  const btn = document.getElementById("btnNuevaReserva");
  if (!btn) return;
  if (btn.dataset.reservaConfigurada === "true") return;

  btn.addEventListener("click", () => {
    window.abrirModalReserva("nuevo");
  });
  btn.dataset.reservaConfigurada = "true";
};
