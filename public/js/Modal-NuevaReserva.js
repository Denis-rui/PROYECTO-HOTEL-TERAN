let eventosDashboardConfigurados = false;

window.abrirModalReserva = (modo = "nuevo", datos = null) => {
  const contenedor = document.getElementById("contenedor-modal-reserva");
  if (!contenedor) return;

  contenedor.style.display = "block";

  const modal = document.getElementById("modalReserva");
  if (modal) modal.style.display = "flex";

  const form = document.getElementById("formReserva");
  const cerrar = document.getElementById("cerrarModal");
  const btnContinuarPago = document.getElementById("btnContinuarPago");
  const inputBuscarCliente = document.getElementById("buscarCliente");
  const selectorCliente = document.getElementById("selectorClienteReserva");
  const idClienteReserva = document.getElementById("idClienteReserva");
  const campoNombre = document.getElementById("nombre");
  const campoEmail = document.getElementById("email");
  const fechaEntrada = document.getElementById("fechaEntrada");
  const horaEntrada = document.getElementById("horaEntrada");
  const fechaSalida = document.getElementById("fechaSalida");
  const horaSalida = document.getElementById("horaSalida");
  const selectorHabitacion = document.getElementById("seleccioneHabitacion");
  const filtroTipoReserva = document.getElementById("filtroTipoReserva");
  const filtroPisoReserva = document.getElementById("filtroPisoReserva");
  const mensajeHabitaciones = document.getElementById(
    "mensajeHabitacionesDisponibles",
  );
  const mensajeBusquedaCliente = document.getElementById(
    "mensajeBusquedaCliente",
  );

  let clientes = [];
  let habitacionesDisponibles = [];

  // Evitar seleccionar check-in en el pasado y ajustar mínimo de check-out
  if (fechaEntrada) {
    const hoy = new Date().toISOString().split("T")[0];
    fechaEntrada.min = hoy;
    if (fechaSalida) fechaSalida.min = hoy;

    fechaEntrada.addEventListener("change", () => {
      if (fechaEntrada.value && fechaSalida)
        fechaSalida.min = fechaEntrada.value;
    });
  }

  const mostrarClientes = () => {
    selectorCliente.innerHTML = '<option value="">Seleccionar cliente</option>';

    if (clientes.length === 0) {
      selectorCliente.innerHTML +=
        '<option value="" disabled>Sin resultados</option>';
      return;
    }

    clientes.forEach((cliente) => {
      selectorCliente.innerHTML += `<option value="${cliente.id}">${cliente.nombre}</option>`;
    });
  };

  const cargarClientes = (texto = "") => {
    fetch(BASE_URL + `?url=Cliente/buscar&q=${encodeURIComponent(texto)}`)
      .then((res) => res.json())
      .then((respuesta) => {
        if (respuesta.error) {
          alert("No se pudo cargar clientes");
          return;
        }

        clientes = respuesta.clientes || [];
        mostrarClientes();

        if (texto !== "" && clientes.length === 0) {
          mensajeBusquedaCliente.textContent = "No se encontraron clientes.";
        } else {
          mensajeBusquedaCliente.textContent =
            "Selecciona un cliente de la lista.";
        }
      })
      .catch(() => {
        mensajeBusquedaCliente.textContent =
          "No se pudieron cargar los clientes.";
      });
  };

  const seleccionarCliente = () => {
    const idSeleccionado = selectorCliente.value;

    idClienteReserva.value = "";

    if (!idSeleccionado) {
      campoNombre.value = "";
      campoEmail.value = "";
      return;
    }

    for (let i = 0; i < clientes.length; i++) {
      if (String(clientes[i].id) === String(idSeleccionado)) {
        idClienteReserva.value = clientes[i].id;
        campoNombre.value = clientes[i].nombre || "";
        campoEmail.value = clientes[i].correo || "";
        break;
      }
    }

    mensajeBusquedaCliente.textContent = "Cliente seleccionado correctamente.";
  };

  const obtenerFechaHora = (fecha, hora) => {
    if (!fecha || !hora) return "";
    return `${fecha} ${hora}`;
  };

  const calcularTotalReserva = () => {
    const habitacion = habitacionesDisponibles.find(
      (item) => String(item.id) === String(selectorHabitacion.value),
    );
    if (!habitacion) return 0;

    const inicio = new Date(`${fechaEntrada.value}T${horaEntrada.value}`);
    const fin = new Date(`${fechaSalida.value}T${horaSalida.value}`);
    if (
      Number.isNaN(inicio.getTime()) ||
      Number.isNaN(fin.getTime()) ||
      fin <= inicio
    ) {
      return 0;
    }

    const dias = Math.max(1, Math.ceil((fin - inicio) / 86400000));
    return dias * Number(habitacion.precio || 0);
  };

  const mostrarHabitacionesDisponibles = () => {
    selectorHabitacion.innerHTML =
      '<option value="">Seleccionar habitacion</option>';

    if (habitacionesDisponibles.length === 0) {
      selectorHabitacion.innerHTML =
        '<option value="" disabled>No hay habitaciones disponibles para ese rango</option>';
      return;
    }

    habitacionesDisponibles.forEach((habitacion) => {
      selectorHabitacion.innerHTML += `
        <option value="${habitacion.id}" data-precio="${habitacion.precio}">
          Piso ${habitacion.piso} - Hab. ${habitacion.numero_habitacion} - ${habitacion.tipo_nombre} - S/ ${habitacion.precio}
        </option>`;
    });
  };

  const cargarHabitacionesDisponibles = () => {
    const checkIn = obtenerFechaHora(fechaEntrada.value, horaEntrada.value);
    const checkOut = obtenerFechaHora(fechaSalida.value, horaSalida.value);

    habitacionesDisponibles = [];
    mostrarHabitacionesDisponibles();

    if (!checkIn || !checkOut) {
      mensajeHabitaciones.textContent =
        "Primero selecciona check-in y check-out.";
      return;
    }

    if (
      new Date(checkOut.replace(" ", "T")) <=
      new Date(checkIn.replace(" ", "T"))
    ) {
      mensajeHabitaciones.textContent =
        "El check-out debe ser posterior al check-in.";
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
        habitacionesDisponibles = respuesta.habitaciones || [];
        mostrarHabitacionesDisponibles();
        mensajeHabitaciones.textContent = habitacionesDisponibles.length
          ? "Habitaciones disponibles para el rango seleccionado."
          : "No hay habitaciones limpias y disponibles para esas fechas.";
      })
      .catch(() => {
        mensajeHabitaciones.textContent =
          "No se pudieron cargar habitaciones disponibles.";
      });
  };

  const cargarFiltrosHabitacion = () => {
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

  const continuarPago = () => {
    const cliente = selectorCliente.value;
    const nombre = campoNombre.value.trim();
    const email = campoEmail.value.trim();
    const checkIn = fechaEntrada.value;
    const horaEntradaValor = horaEntrada.value;
    const checkOut = fechaSalida.value;
    const horaSalidaValor = horaSalida.value;
    const habitacion = selectorHabitacion.value;

    if (!cliente) {
      alert("Selecciona un cliente");
      return;
    }
    if (!nombre) {
      alert("Nombre y apellido obligatorio");
      return;
    }
    if (!email) {
      alert("Correo electronico obligatorio");
      return;
    }
    if (!checkIn || !horaEntradaValor || !checkOut || !horaSalidaValor) {
      alert("Completa check-in y check-out");
      return;
    }
    if (!habitacion) {
      alert("Selecciona una habitacion");
      return;
    }

    // Validaciones de fecha/hora: check-in no puede ser en el pasado
    const ahora = new Date();
    const fechaHoraCheckIn = new Date(`${checkIn}T${horaEntradaValor}`);
    const fechaHoraCheckOut = new Date(`${checkOut}T${horaSalidaValor}`);

    if (Number.isNaN(fechaHoraCheckIn.getTime())) {
      alert("Fecha/hora de check-in inválida");
      return;
    }
    if (Number.isNaN(fechaHoraCheckOut.getTime())) {
      alert("Fecha/hora de check-out inválida");
      return;
    }

    if (fechaHoraCheckIn < ahora) {
      alert("La fecha y hora de check-in debe ser igual o posterior a ahora");
      return;
    }

    if (fechaHoraCheckOut <= fechaHoraCheckIn) {
      alert("La fecha y hora de check-out debe ser posterior al check-in");
      return;
    }

    const textoClienteSeleccionado =
      selectorCliente.options[selectorCliente.selectedIndex].text;

    abrirModalPagoConDatos({
      cliente: cliente,
      clienteTexto: textoClienteSeleccionado,
      nombre: nombre,
      email: email,
      checkIn: checkIn,
      horaEntrada: horaEntradaValor,
      checkOut: checkOut,
      horaSalida: horaSalidaValor,
      habitacion: habitacion,
      totalReserva: calcularTotalReserva(),
    });

    if (modal) modal.style.display = "none";
    if (contenedor) contenedor.style.display = "none";
  };

  if (modo === "nuevo") {
    if (form) form.reset();
    const tit = document.querySelector(".titulo-modal");
    if (tit) tit.textContent = "Nueva Reserva";
  }

  if (modo === "editar" && datos) {
    const tit = document.querySelector(".titulo-modal");
    if (tit) tit.textContent = "Editar Reserva";
    if (btnContinuarPago) btnContinuarPago.textContent = "Actualizar";
    if (campoNombre) campoNombre.value = datos.cliente || "";
    if (campoEmail) campoEmail.value = datos.email || "";
  }

  cargarClientes();
  cargarFiltrosHabitacion();

  if (!eventosDashboardConfigurados) {
    if (inputBuscarCliente) {
      inputBuscarCliente.addEventListener("input", () => {
        cargarClientes(inputBuscarCliente.value.trim());
      });
    }

    if (selectorCliente) {
      selectorCliente.addEventListener("change", seleccionarCliente);
    }

    [
      fechaEntrada,
      horaEntrada,
      fechaSalida,
      horaSalida,
      filtroTipoReserva,
      filtroPisoReserva,
    ].forEach((campo) => {
      campo?.addEventListener("change", cargarHabitacionesDisponibles);
      campo?.addEventListener("input", cargarHabitacionesDisponibles);
    });

    if (cerrar) {
      cerrar.addEventListener("click", () => {
        if (modal) modal.style.display = "none";
        if (contenedor) contenedor.style.display = "none";
      });
    }

    if (btnContinuarPago) {
      btnContinuarPago.addEventListener("click", (e) => {
        e.preventDefault();
        continuarPago();
      });
    }

    if (form) {
      form.addEventListener("submit", (e) => {
        e.preventDefault();
        continuarPago();
      });
    }

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

const abrirModalPagoConDatos = (datosReserva) => {
  if (typeof window.abrirModalPago !== "function") {
    alert("No se pudo abrir el modulo de pago");
    return;
  }
  window.abrirModalPago(datosReserva);
};
