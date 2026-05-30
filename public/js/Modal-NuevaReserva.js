let eventosDashboardConfigurados = false;

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

const esDocumentoCompletoOchoDigitos = (texto = "") => /^\d{8}$/.test(String(texto || "").trim());

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
      horaEntrada.value = "12:00";
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

  if (estado.elementos?.campoDni) {
    estado.elementos.campoDni.value = reserva.documento || "";
  }

  // mostrar tipo de documento en label si viene
  const etiquetaDni = document.getElementById("label-dni");
  if (etiquetaDni) {
    etiquetaDni.textContent = reserva.documento_tipo_nombre || "DNI";
  }

  if (estado.elementos?.procedencia) {
    estado.elementos.procedencia.value = reserva.procedencia || "";
  }

  if (estado.elementos?.campoEmail) {
    estado.elementos.campoEmail.value = reserva.correo_electronico || "";
  }

  const checkIn = separarFechaHora(reserva.check_in);
  const checkOut = separarFechaHora(reserva.check_out);

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

  renderizarHabitacionesSeleccionadas();
};

const normalizarHabitacion = (habitacion) => ({
  id: String(habitacion.id),
  numero_habitacion: habitacion.numero_habitacion,
  piso: habitacion.piso,
  tipo_nombre: habitacion.tipo_nombre,
  precio: Number(habitacion.precio || 0),
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

const obtenerHabitacionSeleccionadaPorId = (id) => {
  const estado = obtenerEstadoModalReserva();
  return (estado.habitacionesSeleccionadas || []).find(
    (habitacion) => String(habitacion.id) === String(id),
  );
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

  const sumaPrecio = habitaciones.reduce(
    (acumulado, habitacion) => acumulado + Number(habitacion.precio || 0),
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

  if (estado.elementos?.totalHabitacionesReserva) {
    estado.elementos.totalHabitacionesReserva.textContent = `S/ ${calcularTotalReserva().toFixed(2)}`;
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
    const card = document.createElement("article");
    card.className = `habitacion-card${seleccionada ? " seleccionada" : ""}`;
    card.innerHTML = `
      <div class="habitacion-card-info">
        <strong>${formatearHabitacionTexto(habitacion)}</strong>
      </div>
      <div class="habitacion-card-acciones">
        <button type="button" class="boton-habitacion agregar" ${seleccionada ? "disabled" : ""} data-id="${habitacion.id}">
          ${seleccionada ? "Seleccionada" : "Agregar"}
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
    const card = document.createElement("article");
    card.className = "habitacion-card seleccionada";
    card.innerHTML = `
      <div class="habitacion-card-info">
        <strong>${formatearHabitacionTexto(habitacion)}</strong>
      </div>
      <div class="habitacion-card-acciones">
        <button type="button" class="boton-habitacion quitar" data-id="${habitacion.id}">Quitar</button>
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

  const yaExiste = (estado.habitacionesSeleccionadas || []).some(
    (item) => String(item.id) === String(habitacion.id),
  );

  if (yaExiste) return;

  estado.habitacionesSeleccionadas.push(normalizarHabitacion(habitacion));
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

const cargarClientes = (texto = "") => {
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
    return Promise.resolve();
  }

  return fetch(
    BASE_URL + `Cliente/buscar&q=${encodeURIComponent(textoBusqueda)}`,
  )
    .then((res) => res.json())
    .then((respuesta) => {
      if (respuesta.error) {
        alert("No se pudo cargar clientes");
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
    })
    .catch(() => {
      if (mensajeBusquedaCliente) {
        mensajeBusquedaCliente.textContent =
          "No se pudieron cargar los clientes.";
      }
      if (!esDniCompleto && textoBusqueda !== "") {
        resaltarBotonRegistrarCliente();
      }
    });
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
    return;
  }

  const cliente = (estado.clientes || []).find(
    (item) => String(item.id) === String(idSeleccionado),
  );

  if (!cliente) return;

  if (idClienteReserva) idClienteReserva.value = cliente.id;
  if (campoNombre) campoNombre.value = cliente.nombre || "";
  if (campoDni) campoDni.value = cliente.documento || "";
  if (estado.elementos?.procedencia)
    estado.elementos.procedencia.value = cliente.procedencia || "";
  const etiquetaDni2 = document.getElementById("label-dni");
  if (etiquetaDni2)
    etiquetaDni2.textContent = cliente.tipo_documento_nombre || "DNI";
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
    alert("Completa check-in y check-out");
    return false;
  }

  const checkIn = new Date(`${fechaEntrada}T00:00:00`);
  const checkOut = new Date(`${fechaSalida}T00:00:00`);

  if (Number.isNaN(checkIn.getTime()) || Number.isNaN(checkOut.getTime())) {
    alert("Fecha/hora inválida");
    return false;
  }

  if (checkOut <= checkIn) {
    alert("La fecha de check-out debe ser posterior a la fecha de check-in");
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

  const checkIn = fechaEntrada && horaEntrada ? `${fechaEntrada} 12:00:00` : "";
  const checkOut = fechaSalida && horaSalida ? `${fechaSalida} 12:00:00` : "";

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
    new Date(`${checkIn.slice(0, 10)}T00:00:00`)
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

const validarYContinuarPago = () => {
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
  const modal = estado.elementos?.modal;
  const contenedor = estado.elementos?.contenedor;

  const cliente = idClienteReserva?.value || selectorCliente?.value || "";
  const nombre = campoNombre?.value.trim() || "";
  const dni = campoDni?.value.trim() || "";
  const email = campoEmail?.value.trim() || "";
  const procedencia = procedenciaCampo?.value.trim() || "";
  const habitaciones = estado.habitacionesSeleccionadas || [];

  if (!cliente) return alert("Selecciona un cliente");
  if (!nombre) return alert("Nombre y apellido obligatorio");
  if (!dni) return alert("DNI obligatorio");
  if (!email) return alert("Correo electronico obligatorio");
  if (habitaciones.length === 0)
    return alert("Selecciona al menos una habitacion");

  if (!validarFechasReserva()) return;
  if (
    !fechaEntrada?.value ||
    !horaEntrada?.value ||
    !fechaSalida?.value ||
    !horaSalida?.value
  )
    return;

  const textoClienteSeleccionado =
    selectorCliente?.options?.[selectorCliente.selectedIndex]?.text || "";
  const resumen = prepararResumenReserva();

  abrirModalPagoConDatos({
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
    guardarCambiosReserva: Boolean(estado.reservaEditandoId),
  });

  if (modal) modal.style.display = "none";
  if (contenedor) contenedor.style.display = "none";
};

const abrirModalPagoConDatos = (datosReserva) => {
  if (typeof window.abrirModalPago !== "function") {
    Swal.fire({
      icon: "error",
      title: "Error",
      text: "No se pudo abrir el módulo de pago",
    });
    return;
  }
  Swal.fire({
    title: "Confirmar pago",
    text: "¿Desea continuar con el proceso de pago de la reserva?",
    icon: "question",
    showCancelButton: true,
    confirmButtonText: "Sí, continuar",
    cancelButtonText: "Cancelar",
  }).then((resultado) => {
    if (resultado.isConfirmed) {
      window.abrirModalPago(datosReserva);
    }
  });
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
    inputBuscarCliente: document.getElementById("buscarCliente"),
    selectorCliente: document.getElementById("selectorClienteReserva"),
    idClienteReserva: document.getElementById("idClienteReserva"),
    campoNombre: document.getElementById("nombre"),
    campoDni: document.getElementById("dni"),
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
    btnRegistrarCliente: document.getElementById("btn-registrar-cliente-manual"),
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
    const etiquetaDni = document.getElementById("label-dni");
    if (etiquetaDni) etiquetaDni.textContent = "DNI";
    if (estado.elementos?.procedencia) estado.elementos.procedencia.value = "";
  }

  if (modo === "editar" && datos) {
    const titulo = document.querySelector(".titulo-modal");
    if (titulo) titulo.textContent = "Editar Reserva";
    if (estado.elementos.btnContinuarPago) {
      estado.elementos.btnContinuarPago.textContent = "Actualizar";
    }
  }

  if (modo === "editar" && datos?.id) {
    try {
      const respuesta = await fetch(
        BASE_URL + `Reserva/obtener/${encodeURIComponent(datos.id)}`,
      );
      const reserva = await respuesta.json();
      const datosReserva = reserva?.id ? reserva : datos;

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
      cargarClientes(estado.elementos.inputBuscarCliente.value.trim());
    });

    estado.elementos.selectorCliente?.addEventListener(
      "change",
      seleccionarCliente,
    );

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
        const boton = evento.target.closest(".boton-habitacion.quitar");
        if (!boton) return;
        quitarHabitacionSeleccionada(boton.dataset.id);
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

  btn.addEventListener("click", () => {
    window.abrirModalReserva("nuevo");
  });
};
