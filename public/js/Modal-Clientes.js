let modoFormularioCliente = "nuevo";

const mostrarMensajeModalCliente = (mensaje, tipo = "error") => {
  const elemento = document.getElementById("error-exito-modal-cliente");
  if (!elemento) return;

  elemento.textContent = mensaje;
  elemento.classList.remove("error", "exito");

  if (tipo) elemento.classList.add(tipo);
};

const limpiarMensajeModalCliente = () => {
  mostrarMensajeModalCliente("", "");
};

const limpiarErroresValidacion = () => {
  const erroresElementos = document.querySelectorAll(".error-validation");
  erroresElementos.forEach((elemento) => {
    elemento.textContent = "";
    elemento.style.display = "none";
  });

  const camposConError = document.querySelectorAll(".input-modal.error");
  camposConError.forEach((campo) => {
    campo.classList.remove("error");
  });
};

const mostrarErrorValidacion = (idCampo, mensaje) => {
  const campo = document.getElementById(idCampo);
  const elementoError = document.getElementById(`error-${idCampo}`);

  if (campo) {
    campo.classList.add("error");
  }

  if (elementoError) {
    elementoError.textContent = mensaje;
    elementoError.style.display = "block";
  }
};

const validarCampoEnTiempoReal = (idCampo, validador) => {
  const campo = document.getElementById(idCampo);
  if (!campo) return;

  const validar = () => {
    const error = validador(campo.value.trim());
    const elementoError = document.getElementById(`error-${idCampo}`);

    if (error) {
      campo.classList.add("error");
      if (elementoError) {
        elementoError.textContent = error;
        elementoError.style.display = "block";
      }
    } else {
      campo.classList.remove("error");
      if (elementoError) {
        elementoError.textContent = "";
        elementoError.style.display = "none";
      }
    }
  };

  campo.addEventListener("blur", validar);
  campo.addEventListener("change", validar);
};

const obtenerDatosFormularioCliente = () => ({
  id: document.getElementById("id-cliente").value.trim(),
  id_tipo_documento: parseInt(document.getElementById("tipo-documento-cliente").value, 10) || "",
  nombre: document.getElementById("nombre-cliente").value.trim(),
  documento: document.getElementById("dni-cliente").value.trim(),
  gmail: document.getElementById("gmail-cliente").value.trim(),
  telefono: document.getElementById("telefono-cliente").value.trim(),
  procedencia: document.getElementById("procedencia-cliente").value.trim(),
  observaciones: document.getElementById("observaciones-cliente").value.trim(),
  reservaciones: 0
});

const validarFormularioCliente = (datos) => {
  limpiarErroresValidacion();
  let tieneErrores = false;

  // Validar nombre
  if (!datos.nombre || datos.nombre.length < 3) {
    mostrarErrorValidacion("nombre-cliente", "El nombre es obligatorio y debe tener al menos 3 caracteres");
    tieneErrores = true;
  } else if (/\d/.test(datos.nombre)) {
    mostrarErrorValidacion("nombre-cliente", "El nombre no puede contener números");
    tieneErrores = true;
  }

  // Validar tipo de documento
  if (!datos.id_tipo_documento) {
    mostrarErrorValidacion("tipo-documento-cliente", "Seleccione un tipo de documento válido");
    tieneErrores = true;
  }

  // Validar documento
  if (!datos.documento || datos.documento.length === 0) {
    mostrarErrorValidacion("dni-cliente", "El documento es obligatorio");
    tieneErrores = true;
  } else if (!/^\d+$/.test(datos.documento)) {
    mostrarErrorValidacion("dni-cliente", "El documento solo puede contener números");
    tieneErrores = true;
  }

  // Validar correo electrónico
  if (!datos.gmail || datos.gmail.length === 0) {
    mostrarErrorValidacion("gmail-cliente", "El correo electrónico es obligatorio");
    tieneErrores = true;
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(datos.gmail)) {
    mostrarErrorValidacion("gmail-cliente", "Correo electrónico no válido");
    tieneErrores = true;
  }

  // Validar teléfono
  if (!datos.telefono || datos.telefono.length === 0) {
    mostrarErrorValidacion("telefono-cliente", "El teléfono es obligatorio");
    tieneErrores = true;
  } else if (!/^\d+$/.test(datos.telefono)) {
    mostrarErrorValidacion("telefono-cliente", "El teléfono solo puede contener números");
    tieneErrores = true;
  } else if (datos.telefono.length < 7 || datos.telefono.length > 15) {
    mostrarErrorValidacion("telefono-cliente", "El teléfono debe tener entre 7 y 15 dígitos");
    tieneErrores = true;
  }

  // Validar procedencia
  if (!datos.procedencia || datos.procedencia.length === 0) {
    mostrarErrorValidacion("procedencia-cliente", "La procedencia es obligatoria");
    tieneErrores = true;
  }

  return tieneErrores ? "Por favor, corrija los errores en el formulario" : "";
};

const completarFormularioCliente = (datos = null) => {
  const titulo = document.getElementById("titulo-modal-cliente");
  const formElement = document.getElementById("form-nuevo-editar-cliente");
  if (!titulo) return;

  limpiarErroresValidacion();

  if (modoFormularioCliente === "editar" && datos) {
    titulo.textContent = "Editar Cliente";

    document.getElementById("id-cliente").value = datos.id;
    document.getElementById("tipo-documento-cliente").value = datos.id_tipo_documento || "";
    document.getElementById("nombre-cliente").value = datos.nombre || "";
    document.getElementById("dni-cliente").value = datos.documento || "";
    document.getElementById("gmail-cliente").value = datos.gmail || "";
    document.getElementById("telefono-cliente").value = datos.telefono || "";
    document.getElementById("procedencia-cliente").value = datos.procedencia || "";
    document.getElementById("observaciones-cliente").value = datos.observaciones || "";
    
    configurarValidacionesTiempoReal();
    return;
  }

  titulo.textContent = "Nuevo Cliente";
  if (formElement) {
    formElement.reset();
    document.getElementById("id-cliente").value = "";
  }

  configurarValidacionesTiempoReal();
};

const manejarEnvioFormularioCliente = async (e) => {
  e.preventDefault();
  limpiarMensajeModalCliente();

  const datos = obtenerDatosFormularioCliente();
  const error = validarFormularioCliente(datos);

  if (error) {
    mostrarMensajeModalCliente(error, "error");
    return;
  }

  try {
    if (modoFormularioCliente === "editar") {
      if (typeof window.actualizarClienteExistente !== "function") {
        throw new Error("No se encontro la funcion para actualizar clientes");
      }
      await window.actualizarClienteExistente(datos);
    } else {
      if (typeof window.registrarClienteNuevo !== "function") {
        throw new Error("No se encontro la funcion para registrar clientes");
      }
      await window.registrarClienteNuevo(datos);
    }

    cerrarModalCliente();
  } catch (error) {
    mostrarMensajeModalCliente(error.message, "error");
  }
};

const configurarValidacionesTiempoReal = () => {
  validarCampoEnTiempoReal("nombre-cliente", (valor) => {
    if (!valor || valor.length < 3) {
      return "El nombre es obligatorio y debe tener al menos 3 caracteres";
    }
    if (/\d/.test(valor)) {
      return "El nombre no puede contener números";
    }
    return "";
  });

  validarCampoEnTiempoReal("tipo-documento-cliente", (valor) => {
    if (!valor) {
      return "Seleccione un tipo de documento válido";
    }
    return "";
  });

  validarCampoEnTiempoReal("dni-cliente", (valor) => {
    if (!valor) {
      return "El documento es obligatorio";
    }
    if (!/^\d+$/.test(valor)) {
      return "El documento solo puede contener números";
    }
    return "";
  });

  validarCampoEnTiempoReal("gmail-cliente", (valor) => {
    if (!valor) {
      return "El correo electrónico es obligatorio";
    }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(valor)) {
      return "Correo electrónico no válido";
    }
    return "";
  });

  validarCampoEnTiempoReal("telefono-cliente", (valor) => {
    if (!valor) {
      return "El teléfono es obligatorio";
    }
    if (!/^\d+$/.test(valor)) {
      return "El teléfono solo puede contener números";
    }
    if (valor.length < 7 || valor.length > 15) {
      return "El teléfono debe tener entre 7 y 15 dígitos";
    }
    return "";
  });

  validarCampoEnTiempoReal("procedencia-cliente", (valor) => {
    if (!valor) {
      return "La procedencia es obligatoria";
    }
    return "";
  });
};

const configurarEventosModalCliente = () => {
  const form = document.getElementById("form-nuevo-editar-cliente");
  const btnCancelar = document.getElementById("btn-cancelar-cliente");
  if (!form || !btnCancelar) return;

  form.removeEventListener("submit", manejarEnvioFormularioCliente);
  btnCancelar.removeEventListener("click", cerrarModalCliente);

  form.addEventListener("submit", manejarEnvioFormularioCliente);
  btnCancelar.addEventListener("click", cerrarModalCliente);
};

const abrirModalCliente = (modo, datos = null) => {
  modoFormularioCliente = modo;
  const contenedor = document.getElementById("contenedor-modal-cliente");
  if (!contenedor) return;

  contenedor.style.display = "flex";
  completarFormularioCliente(datos);
  configurarEventosModalCliente();
};

const cerrarModalCliente = () => {
  const contenedor = document.getElementById("contenedor-modal-cliente");
  if (!contenedor) return;

  contenedor.style.display = "none";
};

window.abrirModalCliente = abrirModalCliente;
window.cerrarModalCliente = cerrarModalCliente;
