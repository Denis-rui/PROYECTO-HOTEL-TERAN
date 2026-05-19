let eventosDashboardConfigurados = false;

window.__modalReservaState = window.__modalReservaState || {};

const obtenerEstadoModalReserva = () => window.__modalReservaState;

const obtenerFechaActualISO = () => new Date().toISOString().split("T")[0];

const obtenerHoraActualISO = () => {
  const ahora = new Date();
  const horas = String(ahora.getHours()).padStart(2, "0");
  const minutos = String(ahora.getMinutes()).padStart(2, "0");
  return `${horas}:${minutos}`;
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

  const inicio = new Date(checkIn);
  const fin = new Date(checkOut);
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

  selectorCliente.innerHTML = '<option value="">Seleccionar cliente</option>';

  if (!estado.clientes || estado.clientes.length === 0) {
    selectorCliente.innerHTML +=
      '<option value="" disabled>Sin resultados</option>';
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
  const estado = obtenerEstadoModalReserva();
  const fechaEntrada = estado.elementos?.fechaEntrada;
  const horaEntrada = estado.elementos?.horaEntrada;
  if (!fechaEntrada || !horaEntrada) return;

  const hoy = obtenerFechaActualISO();
  if (fechaEntrada.value === hoy) {
    const horaMinima = obtenerHoraActualISO();
    horaEntrada.min = horaMinima;
    if (horaEntrada.value && horaEntrada.value < horaMinima) {
      horaEntrada.value = horaMinima;
    }
  } else {
    horaEntrada.min = "";
  }
};

const actualizarMinimosFecha = () => {
  const estado = obtenerEstadoModalReserva();
  const fechaEntrada = estado.elementos?.fechaEntrada;
  const fechaSalida = estado.elementos?.fechaSalida;
  if (!fechaEntrada || !fechaSalida) return;

  const hoy = obtenerFechaActualISO();
  fechaEntrada.min = hoy;
  fechaSalida.min = fechaEntrada.value || hoy;
  actualizarHoraMinimaEntrada();
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

  fetch(BASE_URL + `?url=Cliente/buscar&q=${encodeURIComponent(texto)}`)
    .then((res) => res.json())
    .then((respuesta) => {
      if (respuesta.error) {
        alert("No se pudo cargar clientes");
        return;
      }

      estado.clientes = respuesta.clientes || [];
      renderizarClientes();

      if (mensajeBusquedaCliente) {
        mensajeBusquedaCliente.textContent =
          texto !== "" && estado.clientes.length === 0
            ? "No se encontraron clientes."
            : "Selecciona un cliente de la lista.";
      }
    })
    .catch(() => {
      if (mensajeBusquedaCliente) {
        mensajeBusquedaCliente.textContent =
          "No se pudieron cargar los clientes.";
      }
    });
};

const seleccionarCliente = () => {
  const estado = obtenerEstadoModalReserva();
  const selectorCliente = estado.elementos?.selectorCliente;
  const idClienteReserva = estado.elementos?.idClienteReserva;
  const campoNombre = estado.elementos?.campoNombre;
  const campoEmail = estado.elementos?.campoEmail;
  const mensajeBusquedaCliente = estado.elementos?.mensajeBusquedaCliente;

  if (!selectorCliente) return;

  const idSeleccionado = selectorCliente.value;
  if (idClienteReserva) idClienteReserva.value = "";

  if (!idSeleccionado) {
    if (campoNombre) campoNombre.value = "";
    if (campoEmail) campoEmail.value = "";
    return;
  }

  const cliente = (estado.clientes || []).find(
    (item) => String(item.id) === String(idSeleccionado),
  );

  if (!cliente) return;

  if (idClienteReserva) idClienteReserva.value = cliente.id;
  if (campoNombre) campoNombre.value = cliente.nombre || "";
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

  const checkIn = new Date(`${fechaEntrada}T${horaEntrada}`);
  const checkOut = new Date(`${fechaSalida}T${horaSalida}`);
  const ahora = new Date();

  if (Number.isNaN(checkIn.getTime()) || Number.isNaN(checkOut.getTime())) {
    alert("Fecha/hora inválida");
    return false;
  }

  if (checkIn < ahora) {
    alert("La fecha y hora de check-in debe ser igual o posterior a ahora");
    return false;
  }

  if (checkOut <= checkIn) {
    alert("La fecha y hora de check-out debe ser posterior al check-in");
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

  const checkIn =
    fechaEntrada && horaEntrada ? `${fechaEntrada} ${horaEntrada}` : "";
  const checkOut =
    fechaSalida && horaSalida ? `${fechaSalida} ${horaSalida}` : "";

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
    new Date(checkOut.replace(" ", "T")) <= new Date(checkIn.replace(" ", "T"))
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

  fetch(BASE_URL + `?url=Habitacion/disponiblesPorRango&${params.toString()}`)
    .then((res) => res.json())
    .then((respuesta) => {
      const habitaciones = Array.isArray(respuesta)
        ? respuesta
        : (respuesta.habitaciones || []);

      estado.habitacionesDisponibles = habitaciones.map(
        normalizarHabitacion,
      );
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

  fetch(BASE_URL + "?url=Habitacion/obtenerFiltros")
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
  const campoNombre = estado.elementos?.campoNombre;
  const campoEmail = estado.elementos?.campoEmail;
  const fechaEntrada = estado.elementos?.fechaEntrada;
  const horaEntrada = estado.elementos?.horaEntrada;
  const fechaSalida = estado.elementos?.fechaSalida;
  const horaSalida = estado.elementos?.horaSalida;
  const modal = estado.elementos?.modal;
  const contenedor = estado.elementos?.contenedor;

  const cliente = selectorCliente?.value || "";
  const nombre = campoNombre?.value.trim() || "";
  const email = campoEmail?.value.trim() || "";
  const habitaciones = estado.habitacionesSeleccionadas || [];

  if (!cliente) return alert("Selecciona un cliente");
  if (!nombre) return alert("Nombre y apellido obligatorio");
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
    clienteTexto: textoClienteSeleccionado,
    nombre,
    email,
    checkIn: fechaEntrada.value,
    horaEntrada: horaEntrada.value,
    checkOut: fechaSalida.value,
    horaSalida: horaSalida.value,
    habitacion: resumen.habitacionTexto,
    habitaciones: resumen.habitaciones,
    habitacionPrincipal: resumen.habitacionPrincipal,
    totalReserva: resumen.totalReserva,
  });

  if (modal) modal.style.display = "none";
  if (contenedor) contenedor.style.display = "none";
};

const abrirModalPagoConDatos = (datosReserva) => {
  if (typeof window.abrirModalPago !== "function") {
    alert("No se pudo abrir el modulo de pago");
    return;
  }

  window.abrirModalPago(datosReserva);
};

window.abrirModalReserva = (modo = "nuevo", datos = null) => {
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

  actualizarMinimosFecha();

  if (modo === "nuevo") {
    estado.elementos.form?.reset();
    limpiarSeleccionHabitaciones();
    const titulo = document.querySelector(".titulo-modal");
    if (titulo) titulo.textContent = "Nueva Reserva";
  }

  if (modo === "editar" && datos) {
    const titulo = document.querySelector(".titulo-modal");
    if (titulo) titulo.textContent = "Editar Reserva";
    if (estado.elementos.btnContinuarPago) {
      estado.elementos.btnContinuarPago.textContent = "Actualizar";
    }
    if (estado.elementos.campoNombre)
      estado.elementos.campoNombre.value = datos.cliente || "";
    if (estado.elementos.campoEmail)
      estado.elementos.campoEmail.value = datos.email || "";
  }

  cargarClientes();
  cargarFiltrosHabitacion();
  cargarHabitacionesDisponibles();

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
      limpiarSeleccionHabitaciones();
      cargarHabitacionesDisponibles();
    });

    estado.elementos.horaEntrada?.addEventListener("change", () => {
      actualizarHoraMinimaEntrada();
      cargarHabitacionesDisponibles();
    });

    estado.elementos.fechaSalida?.addEventListener("change", () => {
      actualizarMinimosFecha();
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
