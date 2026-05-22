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
  if (!datos.nombre || datos.nombre.length < 3) {
    return "El nombre es obligatorio y debe tener al menos 3 caracteres";
  }

  if (/\d/.test(datos.nombre)) {
    return "El nombre no puede contener numeros";
  }

  if (!datos.id_tipo_documento) {
    return "Seleccione un tipo de documento valido";
  }

  if (!datos.documento || datos.documento.length === 0) {
    return "El documento es obligatorio";
  }

  if (!/^\d+$/.test(datos.documento)) {
    return "El documento solo puede contener numeros";
  }

  if (!datos.gmail || datos.gmail.length === 0) {
    return "El correo electronico es obligatorio";
  }

  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(datos.gmail)) {
    return "Correo electronico no valido";
  }

  if (!datos.telefono || datos.telefono.length === 0) {
    return "El telefono es obligatorio";
  }

  if (!/^\d+$/.test(datos.telefono)) {
    return "El telefono solo puede contener numeros";
  }

  if (datos.telefono.length < 7 || datos.telefono.length > 15) {
    return "El telefono debe tener entre 7 y 15 digitos";
  }

  if (!datos.procedencia || datos.procedencia.length === 0) {
    return "La procedencia es obligatoria";
  }

  return "";
};

const completarFormularioCliente = (datos = null) => {
  const titulo = document.getElementById("titulo-modal-cliente");
  const formElement = document.getElementById("form-nuevo-editar-cliente");
  if (!titulo) return;

  if (modoFormularioCliente === "editar" && datos) {
    titulo.textContent = "Editar Cliente";

    document.getElementById("id-cliente").value = datos.id;
    document.getElementById("tipo-documento-cliente").value = datos.id_tipo_documento || "";
    document.getElementById("nombre-cliente").value = datos.nombre || "";
    document.getElementById("dni-cliente").value = datos.documento || "";
    document.getElementById("gmail-cliente").value = datos.gmail || "";
    document.getElementById("telefono-cliente").value = datos.telefono || "";
    document.getElementById("procedencia-cliente").value = datos.procedencia || "";
    document.getElementById("reservaciones-cliente").value = datos.reservaciones || "0";
    document.getElementById("observaciones-cliente").value = datos.observaciones || "";
    return;
  }

  titulo.textContent = "Nuevo Cliente";
  if (formElement) {
    formElement.reset();
    document.getElementById("reservaciones-cliente").value = "0";
    document.getElementById("id-cliente").value = "";
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
