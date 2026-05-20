let modoFormularioUsuario = "nuevo";
const obtenerElementoModalUsuario = (selector) => {
  const contenedorModal = document.getElementById("contenedor-modal-usuario");

  if (!contenedorModal) {
    return null;
  }

  return contenedorModal.querySelector(selector);
};

const obtenerContenedorModalUsuario = () => {
  let contenedorModal = document.getElementById("contenedor-modal-usuario");

  if (!contenedorModal) {
    contenedorModal = document.createElement("div");
    contenedorModal.id = "contenedor-modal-usuario";
    document.body.appendChild(contenedorModal);
  }

  return contenedorModal;
};

const mostrarMensajeModal = (mensaje, tipo = "error") => {
  const elementoMensaje = obtenerElementoModalUsuario(
    "#error-exito-modal-usuario",
  );

  if (!elementoMensaje) {
    return;
  }

  elementoMensaje.textContent = mensaje;
  elementoMensaje.classList.remove("error", "exito");
  if (tipo) {
    elementoMensaje.classList.add(tipo);
  }
};

const limpiarMensajeModal = () => {
  mostrarMensajeModal("", "");
};

const obtenerDatosFormularioUsuario = () => ({
  id: obtenerElementoModalUsuario("#id-usuario")?.value.trim() || "",
  nombre: obtenerElementoModalUsuario("#nombre")?.value.trim() || "",
  usuario: obtenerElementoModalUsuario("#usuario")?.value.trim() || "",
  gmail: obtenerElementoModalUsuario("#gmail")?.value.trim() || "",
  telefono: obtenerElementoModalUsuario("#telefono")?.value.trim() || "",
  dni: obtenerElementoModalUsuario("#dni")?.value.trim() || "",
  fecha_nacimiento:
    obtenerElementoModalUsuario("#fecha_nacimiento")?.value || "",
  rol: obtenerElementoModalUsuario("#rol")?.value || "",
  password: obtenerElementoModalUsuario("#password")?.value || "",
});

const calcularEdad = (fechaNacimiento) => {
  const fecha = new Date(`${fechaNacimiento}T00:00:00`);

  if (Number.isNaN(fecha.getTime())) {
    return Number.NaN;
  }

  const hoy = new Date();
  let edad = hoy.getFullYear() - fecha.getFullYear();
  const mesActual = hoy.getMonth();
  const diaActual = hoy.getDate();

  if (
    mesActual < fecha.getMonth() ||
    (mesActual === fecha.getMonth() && diaActual < fecha.getDate())
  ) {
    edad -= 1;
  }

  return edad;
};

const validarFormularioUsuario = (datosUsuario) => {
  const reglas = {
    nombre: /^[a-zA-ZÀ-ÿ\s]{3,}$/,
    usuario: /^[a-zA-Z0-9_]{3,}$/,
    gmail: /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
    telefono: /^\d{9}$/,
    dni: /^\d{8}$/,
    rol: /^(administrador|recepcionista)$/,
    password: /^.{5,}$/,
  };

  if (!reglas.nombre.test(datosUsuario.nombre)) {
    return "Nombre invalido. Minimo 3 caracteres, solo letras y espacios.";
  }

  if (!reglas.usuario.test(datosUsuario.usuario)) {
    return "Usuario invalido. Minimo 3 caracteres, solo letras, numeros y guion bajo.";
  }

  if (!reglas.gmail.test(datosUsuario.gmail)) {
    return "Gmail invalido. Debe tener un formato de correo valido.";
  }

  if (!reglas.telefono.test(datosUsuario.telefono)) {
    return "Telefono invalido. Debe tener 9 digitos.";
  }

  if (!reglas.dni.test(datosUsuario.dni)) {
    return "DNI invalido. Debe tener 8 digitos.";
  }

  if (!datosUsuario.fecha_nacimiento) {
    return "Fecha de nacimiento obligatoria.";
  }

  const edad = calcularEdad(datosUsuario.fecha_nacimiento);

  if (Number.isNaN(edad)) {
    return "Fecha de nacimiento invalida.";
  }

  if (edad < 18) {
    return "El usuario debe ser mayor de edad.";
  }

  if (!reglas.rol.test(datosUsuario.rol)) {
    return "Rol invalido. Debe ser administrador o recepcionista.";
  }

  if (
    modoFormularioUsuario === "nuevo" &&
    !reglas.password.test(datosUsuario.password)
  ) {
    return "Contrasena invalida. Minimo 5 caracteres.";
  }

  return "";
};

const completarFormularioUsuario = (datosUsuario = null) => {
  const campoId = obtenerElementoModalUsuario("#id-usuario");
  const campoNombre = obtenerElementoModalUsuario("#nombre");
  const campoUsuario = obtenerElementoModalUsuario("#usuario");
  const campoGmail = obtenerElementoModalUsuario("#gmail");
  const campoTelefono = obtenerElementoModalUsuario("#telefono");
  const campoDni = obtenerElementoModalUsuario("#dni");
  const campoFechaNacimiento = obtenerElementoModalUsuario("#fecha_nacimiento");
  const campoRol = obtenerElementoModalUsuario("#rol");
  const campoPassword = obtenerElementoModalUsuario("#password");
  const tituloModal = obtenerElementoModalUsuario("#titulo-modal-id");

  if (
    !campoId ||
    !campoNombre ||
    !campoUsuario ||
    !campoGmail ||
    !campoTelefono ||
    !campoDni ||
    !campoFechaNacimiento ||
    !campoRol ||
    !campoPassword ||
    !tituloModal
  ) {
    return;
  }

  if (modoFormularioUsuario === "editar" && datosUsuario) {
    tituloModal.textContent = "Editar Usuario";
    campoId.value = datosUsuario.id;
    campoNombre.value = datosUsuario.nombre;
    campoUsuario.value = datosUsuario.usuario;
    campoGmail.value = datosUsuario.gmail || "";
    campoTelefono.value = datosUsuario.telefono || "";
    campoDni.value = datosUsuario.dni || "";
    campoFechaNacimiento.value = datosUsuario.fecha_nacimiento || "";
    campoRol.value = datosUsuario.rol;
    campoPassword.value = datosUsuario.password;
    campoPassword.required = false;
    return;
  }

  tituloModal.textContent = "Nuevo Usuario";
  campoId.value = "";
  campoNombre.value = "";
  campoUsuario.value = "";
  campoGmail.value = "";
  campoTelefono.value = "";
  campoDni.value = "";
  campoFechaNacimiento.value = "";
  campoRol.value = "";
  campoPassword.value = "";
  campoPassword.required = true;
};

const manejarEnvioFormularioUsuario = (evento) => {
  evento.preventDefault();
  limpiarMensajeModal();

  const datosUsuario = obtenerDatosFormularioUsuario();
  const mensajeError = validarFormularioUsuario(datosUsuario);

  if (mensajeError) {
    mostrarMensajeModal(mensajeError, "error");
    return;
  }

  const accion =
    modoFormularioUsuario === "editar"
      ? window.actualizarUsuarioExistente
      : window.registrarUsuarioNuevo;

  if (typeof accion !== "function") {
    mostrarMensajeModal("No se pudo procesar el formulario.", "error");
    return;
  }

  Promise.resolve(accion(datosUsuario)).then((respuesta) => {
    if (respuesta?.exito) {
      cerrarModalUsuario();
    }
  });
};

const configurarEventosModalUsuario = () => {
  const formulario = obtenerElementoModalUsuario("#form-nuevo-editar-usuario");
  const botonCancelar = obtenerElementoModalUsuario("#btn-cancelar-usuario");

  if (!formulario || !botonCancelar) {
    return;
  }

  formulario.onsubmit = manejarEnvioFormularioUsuario;
  botonCancelar.onclick = cerrarModalUsuario;
};

const abrirModalUsuario = (modo, datosUsuario = null) => {
  const contenedorModal = obtenerContenedorModalUsuario();
  modoFormularioUsuario = modo;

  if (contenedorModal) {
    contenedorModal.classList.add("activo");
    completarFormularioUsuario(datosUsuario);
    configurarEventosModalUsuario();
  }
};

const cerrarModalUsuario = () => {
  const contenedorModal = document.getElementById("contenedor-modal-usuario");

  if (!contenedorModal) {
    return;
  }

  contenedorModal.classList.remove("activo");
};

window.abrirModalUsuario = abrirModalUsuario;
window.cerrarModalUsuario = cerrarModalUsuario;
window.mostrarMensajeModalUsuario = mostrarMensajeModal;
