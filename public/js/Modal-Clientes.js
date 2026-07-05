let modoFormularioCliente = "nuevo";

const mostrarAlertaCliente = (titulo, texto, icono = "info") => {
  return Swal.fire({
    title: titulo,
    text: texto,
    icon: icono,
    confirmButtonText: "Aceptar",
  });
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
  return "";
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
    "tipo-documento-cliente",
    "dni-cliente", // El input del buscador
    "documento-cliente", // El input real de persistencia
    "ruc-cliente",
    "nombres-cliente",
    "apellido-paterno-cliente",
    "apellido-materno-cliente",
    "gmail-cliente",
    "telefono-cliente",
    "procedencia-cliente",
    "observaciones-cliente",
    "reservaciones-cliente", // Input oculto de control
  ];

  campos.forEach((idCampo) => {
    const campo = document.getElementById(idCampo);
    if (!campo) return;

    if (idCampo === "tipo-documento-cliente") {
      campo.value = "1";
      campo.dispatchEvent(new Event("change"));
      return;
    }

    // Para el input oculto de reservaciones, lo reseteamos a string "0"
    if (idCampo === "reservaciones-cliente") {
      campo.value = "0";
      return;
    }

    // Limpia el resto de inputs convencionales y textareas
    campo.value = "";
  });
};

const aplicarDatosClienteFormulario = (datos = {}) => {
  const mapeo = {
    "id-cliente": datos.id || "",
    "tipo-documento-cliente": datos.id_tipo_documento || "1",
    "dni-cliente": datos.documento_busqueda || datos.documento || "",
    "documento-cliente": datos.documento || "",
    "ruc-cliente": datos.ruc || "",
    "nombres-cliente": datos.nombres || "",
    "apellido-paterno-cliente": datos.apellido_paterno || "",
    "apellido-materno-cliente": datos.apellido_materno || "",
    "gmail-cliente": datos.correo_electronico || datos.gmail || "",
    "telefono-cliente": datos.telefono || "",
    "procedencia-cliente": datos.procedencia || "",
    "observaciones-cliente": datos.observaciones || "",
    "reservaciones-cliente": datos.reservaciones || "0",
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
    parseInt(document.getElementById("tipo-documento-cliente").value, 10) || 1,

  documento: document.getElementById("documento-cliente").value.trim(),
  ruc: document.getElementById("ruc-cliente").value.trim() || null,

  nombres: document.getElementById("nombres-cliente").value.trim(),
  apellido_paterno: document
    .getElementById("apellido-paterno-cliente")
    .value.trim(),
  apellido_materno: document
    .getElementById("apellido-materno-cliente")
    .value.trim(),

  correo_electronico: document.getElementById("gmail-cliente").value.trim(),

  telefono: document.getElementById("telefono-cliente").value.trim(),
  procedencia: document.getElementById("procedencia-cliente").value.trim(),
  observaciones: document.getElementById("observaciones-cliente").value.trim(),

  reservaciones: 0,
});
const validarFormularioCliente = (datos) => {
  limpiarErroresValidacion();
  let tieneErrores = false;

  const tipoDoc = parseInt(datos.id_tipo_documento, 10);
  const esEmpresaRuc = tipoDoc === 6;

  // 1. Validar Nombres (Persona Natural Estricto)
  if (!datos.nombres || datos.nombres.trim().length < 3) {
    mostrarErrorValidacion(
      "nombres-cliente",
      "El nombre es obligatorio y debe tener al menos 3 caracteres",
    );
    tieneErrores = true;
  } else if (/\d/.test(datos.nombres)) {
    mostrarErrorValidacion(
      "nombres-cliente",
      "El nombre no puede contener números",
    );
    tieneErrores = true;
  }

  // 2. Validar Apellidos (Solo si NO es RUC/Empresa)
  if (!esEmpresaRuc) {
    if (!datos.apellido_paterno || datos.apellido_paterno.trim().length === 0) {
      mostrarErrorValidacion(
        "apellido-paterno-cliente",
        "El apellido paterno es obligatorio",
      );
      tieneErrores = true;
    } else if (/\d/.test(datos.apellido_paterno)) {
      mostrarErrorValidacion(
        "apellido-paterno-cliente",
        "El apellido paterno no puede contener números",
      );
      tieneErrores = true;
    }

    if (!datos.apellido_materno || datos.apellido_materno.trim().length === 0) {
      mostrarErrorValidacion(
        "apellido-materno-cliente",
        "El apellido materno es obligatorio",
      );
      tieneErrores = true;
    } else if (/\d/.test(datos.apellido_materno)) {
      mostrarErrorValidacion(
        "apellido-materno-cliente",
        "El apellido materno no puede contener números",
      );
      tieneErrores = true;
    }
  }

  // 3. Validar Tipo de Documento
  if (!datos.id_tipo_documento) {
    mostrarErrorValidacion(
      "tipo-documento-cliente",
      "Seleccione un tipo de documento válido",
    );
    tieneErrores = true;
  }

  // 4. Validar Número de Documento Principal (Obligatorio siempre, excepto si es netamente RUC y se prefiere dejar vacío, pero para consistencia lo validamos según el tipo)
  if (!esEmpresaRuc) {
    if (!datos.documento || datos.documento.trim().length === 0) {
      mostrarErrorValidacion(
        "documento-cliente",
        "El número de documento es obligatorio",
      );
      tieneErrores = true;
    } else if (!/^\d+$/.test(datos.documento)) {
      mostrarErrorValidacion(
        "documento-cliente",
        "El documento solo puede contener números",
      );
      tieneErrores = true;
    } else if (tipoDoc === 1 && datos.documento.trim().length !== 8) {
      mostrarErrorValidacion(
        "documento-cliente",
        "El DNI debe tener exactamente 8 dígitos",
      );
      tieneErrores = true;
    }
  }

  // 5. Validar Campo RUC (Obligatorio si tipo de documento es RUC, o validación de formato si se añade como opcional)
  if (esEmpresaRuc || (datos.ruc && datos.ruc.trim().length > 0)) {
    const rucValor = datos.ruc ? datos.ruc.trim() : "";
    if (rucValor.length === 0) {
      mostrarErrorValidacion(
        "ruc-cliente",
        "El número de RUC es obligatorio para este tipo de documento",
      );
      tieneErrores = true;
    } else if (!/^\d{11}$/.test(rucValor)) {
      mostrarErrorValidacion(
        "ruc-cliente",
        "El RUC debe ser un número válido de exactamente 11 dígitos",
      );
      tieneErrores = true;
    }
  }

  // 6. Validar Correo Electrónico
  if (!datos.correo_electronico) {
    mostrarErrorValidacion(
      "gmail-cliente",
      "El correo electrónico es obligatorio",
    );
    tieneErrores = true;
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(datos.correo_electronico)) {
    mostrarErrorValidacion("gmail-cliente", "Correo electrónico no válido");
    tieneErrores = true;
  }

  // 7. Validar Teléfono
  if (!datos.telefono || datos.telefono.trim().length === 0) {
    mostrarErrorValidacion("telefono-cliente", "El teléfono es obligatorio");
    tieneErrores = true;
  } else if (!/^\d+$/.test(datos.telefono)) {
    mostrarErrorValidacion(
      "telefono-cliente",
      "El teléfono solo puede contener números",
    );
    tieneErrores = true;
  } else if (
    datos.telefono.trim().length < 7 ||
    datos.telefono.trim().length > 15
  ) {
    mostrarErrorValidacion(
      "telefono-cliente",
      "El teléfono debe tener entre 7 y 15 dígitos",
    );
    tieneErrores = true;
  }

  // 8. Validar Procedencia
  if (!datos.procedencia || datos.procedencia.trim().length === 0) {
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
    console.log(data);
    const clientes = Array.isArray(data.clientes) ? data.clientes : [];
    const cliente =
      clientes.find((item) => {
        const docLocal = String(item.documento || "").trim();
        const rucLocal = String(item.ruc || "").trim();

        // El cliente existe si coincide con su Documento principal O con su RUC
        return docLocal === documento || rucLocal === documento;
      }) || null;

    if (cliente) {
      // Personalizamos el mensaje si se encontró específicamente por RUC o por DNI
      const esRucEncontrado =
        String(cliente.ruc || "").trim() === documento;
      const tipoMensaje = esRucEncontrado ? "con el RUC" : "con el documento";

      await mostrarAlertaCliente(
        "Cliente existente",
        `El cliente ya existe en la base de datos ${tipoMensaje} proporcionado.`,
        "info",
      );

      establecerMensajeBusquedaCliente(
        `El cliente ya existe en la base de datos (${esRucEncontrado ? "RUC" : "Doc"}: ${documento}).`,
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
          "Sin coincidencias",
          "No se encontró un cliente con ese documento. Puedes registrarlo manualmente.",
          "warning",
        );
        establecerMensajeBusquedaCliente(
          "No se encontró un cliente con ese documento. Puedes registrarlo manualmente.",
          "error",
        );
      } else {
        await mostrarAlertaCliente(
          "Error",
          "No se pudo completar la búsqueda en este momento.",
          "error",
        );
        establecerMensajeBusquedaCliente(
          "No se pudo completar la búsqueda en este momento.",
          "error",
        );
      }
      return;
    }

    const tieneDatosDni = Boolean(datosApi?.dni && datosApi?.nombres);
    const tieneDatosRuc = Boolean(datosApi?.ruc && datosApi?.razonSocial);

    if (!tieneDatosDni && !tieneDatosRuc) {
      const mensajeSinDatos =
        "No se encontró un cliente con ese documento. Puedes registrarlo manualmente.";
      await mostrarAlertaCliente(
        "Sin coincidencias",
        mensajeSinDatos,
        "warning",
      );
      establecerMensajeBusquedaCliente(mensajeSinDatos, "error");
      return;
    }

    // Variables preparadas para la hidratación del formulario
    let datosParaFormulario = {
      id: "",
      id_tipo_documento: "1", // Por defecto DNI
      documento_busqueda: documento, // Para dejar rastro en el input buscador
      documento: "",
      ruc: "",
      nombres: "",
      apellido_paterno: "",
      apellido_materno: "",
      procedencia: "",
      telefono: "",
      observaciones: "",
    };

    if (tieneDatosDni) {
      datosParaFormulario.id_tipo_documento = "1";
      datosParaFormulario.documento = String(datosApi.dni).trim();
      datosParaFormulario.nombres = String(datosApi.nombres || "").trim();
      datosParaFormulario.apellido_paterno = String(
        datosApi.apellidoPaterno || "",
      ).trim();
      datosParaFormulario.apellido_materno = String(
        datosApi.apellidoMaterno || "",
      ).trim();
      datosParaFormulario.observaciones = "Cliente verificado por DNI.";
    } else if (tieneDatosRuc) {
      datosParaFormulario.id_tipo_documento = "6"; // Carnet de extranjería o el id que manejes para empresas/RUC si aplica
      datosParaFormulario.documento = ""; // Queda vacío para obligar a poner un DNI/CE si es persona, o rellenar manualmente
      datosParaFormulario.ruc = String(datosApi.ruc).trim();
      datosParaFormulario.nombres = ""; // La razón social va al campo principal
      datosParaFormulario.apellido_paterno = ""; // No aplica para RUC
      datosParaFormulario.apellido_materno = ""; // No aplica para RUC

      // Extrae departamento, provincia, distrito si existen
      datosParaFormulario.procedencia = datosApi.direccion;

      datosParaFormulario.observaciones = `RUC: ${datosApi.condicion || "HABIDO"} - ${datosApi.estado || "ACTIVO"}.`;
    }

    // Procesamiento común de teléfonos si vienen en la respuesta del RUC
    if (
      datosApi.telefonos &&
      Array.isArray(datosApi.telefonos) &&
      datosApi.telefonos.length > 0
    ) {
      datosParaFormulario.telefono = String(datosApi.telefonos[0]).trim();
    }

    // Cambiar comportamiento visual de la UI
    modoFormularioCliente = "nuevo";
    const titulo = document.getElementById("titulo-modal-cliente");
    if (titulo) {
      titulo.textContent = "Nuevo Cliente";
    }

    limpiarFormularioCliente();

    // Inyectamos el objeto estructurado directamente a tu helper actualizado
    aplicarDatosClienteFormulario(datosParaFormulario);

    limpiarErroresValidacion();
    establecerMensajeBusquedaCliente(
      "Datos cargados correctamente. Completa los campos faltantes.",
      "exito",
    );
    await mostrarAlertaCliente(
      "Datos encontrados",
      "Se cargaron los datos. Revisa los campos faltantes antes de guardar.",
      "success",
    );
  } catch (error) {
    console.error(error);
    establecerMensajeBusquedaCliente(
      "No se pudieron cargar los datos del cliente.",
      "error",
    );
    await mostrarAlertaCliente(
      "Error",
      "No se pudo validar el documento ni completar la búsqueda.",
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
  // 1. Validar Nombres / Razón Social
  validarCampoEnTiempoReal("nombres-cliente", (valor) => {
    const tipoDoc = document.getElementById("tipo-documento-cliente")?.value;
    const esRuc = tipoDoc === "6";

    if (!valor || valor.trim().length < 3) {
      return esRuc
        ? "La razón social es obligatoria y debe tener al menos 3 caracteres"
        : "El nombre es obligatorio y debe tener al menos 3 caracteres";
    }

    // Si NO es RUC, bloqueamos números. Si SÍ es RUC, permitimos números (ej: Alimentos 247 S.A.)
    if (!esRuc && /\d/.test(valor)) {
      return "El nombre no puede contener números";
    }

    return "";
  });

  // 2. Validar Apellido Paterno
  validarCampoEnTiempoReal("apellido-paterno-cliente", (valor) => {
    const tipoDoc = document.getElementById("tipo-documento-cliente")?.value;
    if (tipoDoc === "6") return ""; // Omitir si es RUC

    if (!valor || valor.trim().length === 0) {
      return "El apellido p_aterno es obligatorio";
    }
    if (/\d/.test(valor)) {
      return "El apellido paterno no puede contener números";
    }
    return "";
  });

  // 3. Validar Apellido Materno
  validarCampoEnTiempoReal("apellido-materno-cliente", (valor) => {
    const tipoDoc = document.getElementById("tipo-documento-cliente")?.value;
    if (tipoDoc === "6") return ""; // Omitir si es RUC

    if (!valor || valor.trim().length === 0) {
      return "El apellido materno es obligatorio";
    }
    if (/\d/.test(valor)) {
      return "El apellido materno no puede contener números";
    }
    return "";
  });

  // 4. Validar Tipo de Documento
  validarCampoEnTiempoReal("tipo-documento-cliente", (valor) => {
    if (!valor) {
      return "Seleccione un tipo de documento válido";
    }

    // Comportamiento extra: Forzar re-validación de los campos que dependen del tipo de documento
    const inputsAEvaluar = [
      "nombres-cliente",
      "apellido-paterno-cliente",
      "apellido-materno-cliente",
      "documento-cliente",
      "ruc-cliente",
    ];
    inputsAEvaluar.forEach((id) => {
      const el = document.getElementById(id);
      if (el) el.dispatchEvent(new Event("input"));
    });

    return "";
  });

  // 5. Validar Número de Documento Final
  validarCampoEnTiempoReal("documento-cliente", (valor) => {
    const tipoDoc = document.getElementById("tipo-documento-cliente")?.value;
    if (tipoDoc === "6") return ""; // Si es netamente RUC, este campo no es obligatorio obligatoriamente

    if (!valor || valor.trim().length === 0) {
      return "El número de documento es obligatorio";
    }
    if (!/^\d+$/.test(valor)) {
      return "El documento solo puede contener números";
    }
    if (tipoDoc === "1" && valor.trim().length !== 8) {
      return "El DNI debe tener exactamente 8 dígitos";
    }
    return "";
  });

  // 6. Validar RUC
  validarCampoEnTiempoReal("ruc-cliente", (valor) => {
    const tipoDoc = document.getElementById("tipo-documento-cliente")?.value;
    const esRucObligatorio = tipoDoc === "6";

    if (esRucObligatorio && (!valor || valor.trim().length === 0)) {
      return "El número de RUC es obligatorio";
    }
    if (valor && valor.trim().length > 0 && !/^\d{11}$/.test(valor)) {
      return "El RUC debe tener exactamente 11 dígitos numéricos";
    }
    return "";
  });

  // 7. Validar Correo Electrónico
  validarCampoEnTiempoReal("gmail-cliente", (valor) => {
    if (!valor) {
      return "El correo electrónico es obligatorio";
    }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(valor)) {
      return "Correo electrónico no válido";
    }
    return "";
  });

  // 8. Validar Teléfono
  validarCampoEnTiempoReal("telefono-cliente", (valor) => {
    if (!valor) {
      return "El teléfono es obligatorio";
    }
    if (!/^\d+$/.test(valor)) {
      return "El teléfono solo puede contener números";
    }
    if (valor.trim().length < 7 || valor.trim().length > 15) {
      return "El teléfono debe tener entre 7 y 15 dígitos";
    }
    return "";
  });

  // 9. Validar Procedencia
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
