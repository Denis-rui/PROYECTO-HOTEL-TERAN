let modoFormularioCliente = "nuevo";

const mostrarAlertaCliente = (titulo, texto, icono = "info") => {
  if (typeof Swal !== "undefined" && typeof Swal.fire === "function") {
    return Swal.fire({
      title: titulo,
      text: texto,
      icon: icono,
      confirmButtonText: "Aceptar",
    });
  }

  alert(`${titulo}: ${texto}`);
  return Promise.resolve();
};

const normalizarDocumentoCliente = (valor = "") =>
  String(valor || "").replace(/\D/g, "");

const esDniCliente = (documento = "") =>
  normalizarDocumentoCliente(documento).length === 8;

const esRucCliente = (documento = "") =>
  normalizarDocumentoCliente(documento).length === 11;

const obtenerTipoDocumentoPorLongitud = (documento = "") => {
  if (esDniCliente(documento)) return "1";
  if (esRucCliente(documento)) return "2";
  return "1";
};

const formatearNombreDni = (datos = {}) =>
  [datos.nombres, datos.apellidoPaterno, datos.apellidoMaterno]
    .map((valor) => String(valor || "").trim())
    .filter(Boolean)
    .join(" ");

const formatearProcedenciaRuc = (datos = {}) =>
  [datos.direccion, datos.distrito, datos.provincia, datos.departamento]
    .map((valor) => String(valor || "").trim())
    .filter(Boolean)
    .join(" - ");

const formatearObservacionesApi = (datos = {}, tipoDocumento = "") => {
  const partes = [];

  if (tipoDocumento === "DNI") {
    if (datos.codVerifica)
      partes.push(`Código de verificación: ${datos.codVerifica}`);
    partes.push("Datos consultados desde Apis Peru");
    return partes.join(" | ");
  }

  if (tipoDocumento === "RUC") {
    if (datos.nombreComercial)
      partes.push(`Nombre comercial: ${datos.nombreComercial}`);
    if (datos.estado) partes.push(`Estado: ${datos.estado}`);
    if (datos.condicion) partes.push(`Condición: ${datos.condicion}`);
    if (datos.ubigeo) partes.push(`Ubigeo: ${datos.ubigeo}`);
    if (datos.capital) partes.push(`Capital: ${datos.capital}`);
    return partes.join(" | ");
  }

  return "Datos consultados desde Apis Peru";
};

const consultarApisPeru = async (documento) => {
  const valor = normalizarDocumentoCliente(documento);
  const tipo = esRucCliente(valor) ? "ruc" : "dni";

  const respuesta = await fetch(
    BASE_URL +
      `?url=Cliente/consultarApiPeru&tipo=${encodeURIComponent(tipo)}&documento=${encodeURIComponent(valor)}`,
  );
  const datos = await respuesta.json().catch(() => ({}));

  return {
    ok: respuesta.ok,
    status: respuesta.status,
    data: datos,
  };
};

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

const establecerMensajeBusquedaCliente = (mensaje, tipo = "") => {
  const elemento = document.getElementById("mensaje-busqueda-cliente");
  if (!elemento) return;

  elemento.textContent = mensaje;
  elemento.classList.remove("error", "exito");

  if (tipo) {
    elemento.classList.add(tipo);
  }
};

const limpiarFormularioCliente = () => {
  const campos = [
    "id-cliente",
    "nombre-cliente",
    "tipo-documento-cliente",
    "dni-cliente",
    "gmail-cliente",
    "telefono-cliente",
    "procedencia-cliente",
    "observaciones-cliente",
  ];

  campos.forEach((idCampo) => {
    const campo = document.getElementById(idCampo);
    if (!campo) return;

    if (idCampo === "tipo-documento-cliente") {
      campo.value = "1";
      return;
    }

    campo.value = "";
  });
};

const aplicarDatosClienteFormulario = (datos = {}) => {
  const mapeo = {
    "id-cliente": datos.id || "",
    "nombre-cliente": datos.nombre || "",
    "tipo-documento-cliente": datos.id_tipo_documento || "1",
    "dni-cliente": datos.documento || "",
    "gmail-cliente": datos.gmail || "",
    "telefono-cliente": datos.telefono || "",
    "procedencia-cliente": datos.procedencia || "",
    "observaciones-cliente": datos.observaciones || "",
  };

  Object.entries(mapeo).forEach(([idCampo, valor]) => {
    const campo = document.getElementById(idCampo);
    if (campo) {
      campo.value = valor;
    }
  });
};

const manejarEnterBusquedaCliente = (evento) => {
  if (evento.key === "Enter") {
    evento.preventDefault();
    buscarDatosClientePorDocumento();
  }
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
  id_tipo_documento:
    parseInt(document.getElementById("tipo-documento-cliente").value, 10) || "",
  nombre: document.getElementById("nombre-cliente").value.trim(),
  documento: document.getElementById("dni-cliente").value.trim(),
  gmail: document.getElementById("gmail-cliente").value.trim(),
  telefono: document.getElementById("telefono-cliente").value.trim(),
  procedencia: document.getElementById("procedencia-cliente").value.trim(),
  observaciones: document.getElementById("observaciones-cliente").value.trim(),
  reservaciones: 0,
});

const validarFormularioCliente = (datos) => {
  limpiarErroresValidacion();
  let tieneErrores = false;

  // Validar nombre
  if (!datos.nombre || datos.nombre.length < 3) {
    mostrarErrorValidacion(
      "nombre-cliente",
      "El nombre es obligatorio y debe tener al menos 3 caracteres",
    );
    tieneErrores = true;
  } else if (/\d/.test(datos.nombre)) {
    mostrarErrorValidacion(
      "nombre-cliente",
      "El nombre no puede contener números",
    );
    tieneErrores = true;
  }

  // Validar tipo de documento
  if (!datos.id_tipo_documento) {
    mostrarErrorValidacion(
      "tipo-documento-cliente",
      "Seleccione un tipo de documento válido",
    );
    tieneErrores = true;
  }

  // Validar documento
  if (!datos.documento || datos.documento.length === 0) {
    mostrarErrorValidacion("dni-cliente", "El documento es obligatorio");
    tieneErrores = true;
  } else if (!/^\d+$/.test(datos.documento)) {
    mostrarErrorValidacion(
      "dni-cliente",
      "El documento solo puede contener números",
    );
    tieneErrores = true;
  }

  // Validar correo electrónico
  if (!datos.gmail || datos.gmail.length === 0) {
    mostrarErrorValidacion(
      "gmail-cliente",
      "El correo electrónico es obligatorio",
    );
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
    mostrarErrorValidacion(
      "telefono-cliente",
      "El teléfono solo puede contener números",
    );
    tieneErrores = true;
  } else if (datos.telefono.length < 7 || datos.telefono.length > 15) {
    mostrarErrorValidacion(
      "telefono-cliente",
      "El teléfono debe tener entre 7 y 15 dígitos",
    );
    tieneErrores = true;
  }

  // Validar procedencia
  if (!datos.procedencia || datos.procedencia.length === 0) {
    mostrarErrorValidacion(
      "procedencia-cliente",
      "La procedencia es obligatoria",
    );
    tieneErrores = true;
  }

  return tieneErrores ? "Por favor, corrija los errores en el formulario" : "";
};

const completarFormularioCliente = (datos = null) => {
  const titulo = document.getElementById("titulo-modal-cliente");
  const formElement = document.getElementById("form-nuevo-editar-cliente");
  if (!titulo) return;

  limpiarErroresValidacion();
  establecerMensajeBusquedaCliente(
    "Escribe un documento y pulsa buscar para autocompletar el formulario.",
  );

  if (modoFormularioCliente === "editar" && datos) {
    titulo.textContent = "Editar Cliente";
    aplicarDatosClienteFormulario(datos);

    configurarValidacionesTiempoReal();
    return;
  }

  titulo.textContent = "Nuevo Cliente";
  if (formElement) {
    formElement.reset();
    limpiarFormularioCliente();
  }

  if (datos?.documento) {
    const campoDocumento = document.getElementById("dni-cliente");
    if (campoDocumento) {
      campoDocumento.value = datos.documento;
    }
  }

  const campoTipoDocumento = document.getElementById("tipo-documento-cliente");
  if (campoTipoDocumento) {
    campoTipoDocumento.value = "1";
  }

  configurarValidacionesTiempoReal();
};

const buscarDatosClientePorDocumento = async () => {
  const documento = normalizarDocumentoCliente(
    document.getElementById("dni-cliente")?.value.trim() || "",
  );

  if (!documento) {
    establecerMensajeBusquedaCliente(
      "Ingresa un documento para buscar.",
      "error",
    );
    return;
  }

  if (!esDniCliente(documento) && !esRucCliente(documento)) {
    establecerMensajeBusquedaCliente(
      "El documento debe tener 8 dígitos para DNI o 11 dígitos para RUC.",
      "error",
    );
    return;
  }

  establecerMensajeBusquedaCliente("Buscando datos del cliente...");

  try {
    const respuesta = await fetch(
      BASE_URL + `Cliente/buscar&q=${encodeURIComponent(documento)}`,
    );
    const data = await respuesta.json();
    const clientes = Array.isArray(data.clientes) ? data.clientes : [];
    const cliente =
      clientes.find((item) => String(item.documento || "") === documento) ||
      null;

    if (cliente) {
      await mostrarAlertaCliente(
        "Cliente existente",
        "El cliente ya existe en la base de datos.",
        "info",
      );
      establecerMensajeBusquedaCliente(
        "El cliente ya existe en la base de datos.",
        "error",
      );
      return;
    }

    const respuestaApi = await consultarApisPeru(documento);
    const datosApi = respuestaApi.data || {};

    if (!respuestaApi.ok || datosApi.success === false) {
      const mensajeApi = String(
        datosApi.message || datosApi.mensaje || "",
      ).trim();
      const esNoEncontrado =
        respuestaApi.status === 404 ||
        (respuestaApi.status === 200 && datosApi.success === false) ||
        /no se encontr|sin resultados|no existe/i.test(mensajeApi);

      if (esNoEncontrado) {
        await mostrarAlertaCliente(
          "Sin resultados",
          "No existe en la API. Puedes registrar el cliente manualmente.",
          "warning",
        );
        establecerMensajeBusquedaCliente(
          "No existe en la API. Puedes registrar el cliente manualmente.",
          "error",
        );
      } else {
        await mostrarAlertaCliente(
          "Error",
          "No se pudo consultar la API en este momento.",
          "error",
        );
        establecerMensajeBusquedaCliente(
          "No se pudo consultar la API en este momento.",
          "error",
        );
      }

      return;
    }

    const tieneDatosDni = Boolean(datosApi?.dni && datosApi?.nombres);
    const tieneDatosRuc = Boolean(datosApi?.ruc && datosApi?.razonSocial);

    if (!tieneDatosDni && !tieneDatosRuc) {
      const mensajeSinDatos =
        "No existe en la API. Puedes registrar el cliente manualmente.";
      await mostrarAlertaCliente("Sin resultados", mensajeSinDatos, "warning");
      establecerMensajeBusquedaCliente(mensajeSinDatos, "error");
      return;
    }

    const esDni = tieneDatosDni || esDniCliente(documento);
    const tipoDocumentoApi = esDni ? "DNI" : "RUC";
    const nombreCompleto = esDni
      ? formatearNombreDni(datosApi)
      : String(datosApi.razonSocial || "").trim();
    const procedencia = esDni ? "" : formatearProcedenciaRuc(datosApi);
    const telefono = Array.isArray(datosApi.telefonos)
      ? String(datosApi.telefonos[0] || "").trim()
      : "";
    const observaciones = formatearObservacionesApi(datosApi, tipoDocumentoApi);

    modoFormularioCliente = "nuevo";
    const titulo = document.getElementById("titulo-modal-cliente");
    if (titulo) {
      titulo.textContent = "Nuevo Cliente";
    }

    limpiarFormularioCliente();
    aplicarDatosClienteFormulario({
      id: "",
      id_tipo_documento: obtenerTipoDocumentoPorLongitud(documento),
      nombre: nombreCompleto,
      documento,
      gmail: "",
      telefono,
      procedencia,
      observaciones,
    });

    const campoDocumento = document.getElementById("dni-cliente");
    if (campoDocumento) {
      campoDocumento.value = documento;
    }

    limpiarErroresValidacion();
    establecerMensajeBusquedaCliente(
      "Datos cargados desde Apis Peru. Completa los campos faltantes.",
      "exito",
    );
    await mostrarAlertaCliente(
      "Datos encontrados",
      "Se cargaron los datos desde Apis Peru. Revisa los campos faltantes antes de guardar.",
      "success",
    );
  } catch (error) {
    establecerMensajeBusquedaCliente(
      "No se pudieron cargar los datos del cliente.",
      "error",
    );
    await mostrarAlertaCliente(
      "Error",
      "No se pudo validar el documento ni consultar Apis Peru.",
      "error",
    );
  }
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
  const btnBuscarDatos = document.getElementById("btn-buscar-datos-cliente");
  const inputDocumento = document.getElementById("dni-cliente");
  if (!form || !btnCancelar) return;

  form.removeEventListener("submit", manejarEnvioFormularioCliente);
  btnCancelar.removeEventListener("click", cerrarModalCliente);
  if (btnBuscarDatos) {
    btnBuscarDatos.removeEventListener("click", buscarDatosClientePorDocumento);
  }

  form.addEventListener("submit", manejarEnvioFormularioCliente);
  btnCancelar.addEventListener("click", cerrarModalCliente);
  if (btnBuscarDatos) {
    btnBuscarDatos.addEventListener("click", buscarDatosClientePorDocumento);
  }

  if (inputDocumento) {
    inputDocumento.removeEventListener("keydown", manejarEnterBusquedaCliente);
    inputDocumento.addEventListener("keydown", manejarEnterBusquedaCliente);
  }
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
