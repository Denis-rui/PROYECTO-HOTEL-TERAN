let modoFormularioCliente = "nuevo";

const obtenerContenedorModalCliente = () => {
  let contenedor = document.getElementById("contenedor-modal-cliente");

  if (!contenedor) {
    contenedor = document.createElement("div");
    contenedor.id = "contenedor-modal-cliente";
    document.body.appendChild(contenedor);
  }

  return contenedor;
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

const obtenerDatosFormularioCliente = () => ({
  id: document.getElementById("id-cliente").value.trim(),
  nombre: document.getElementById("nombre-cliente").value.trim(),
  documento: document.getElementById("dni-cliente").value.trim(),
  gmail: document.getElementById("gmail-cliente").value.trim(),
  telefono: document.getElementById("telefono-cliente").value.trim(),
  nacionalidad: document.getElementById("nacionalidad-cliente").value.trim(),
  reservaciones: Number(document.getElementById("reservaciones-cliente").value),
  metodoPago: document.getElementById("metodo-pago-cliente").value,
  preferencias: document.getElementById("preferencias-cliente").value.trim(),
  observaciones: document.getElementById("observaciones-cliente").value.trim(),
});

const validarFormularioCliente = (datos) => {
  const reglas = {
    nombre: /^[a-zA-ZÀ-ÿ\s]{3,}$/,
    documento: /^\d{8}$/,
    gmail: /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
    telefono: /^\d{9}$/,
  };

  if (!reglas.nombre.test(datos.nombre)) {
    return "Nombre inválido";
  }

  if (!reglas.documento.test(datos.documento)) {
    return "DNI inválido (8 dígitos)";
  }

  if (datos.gmail && !reglas.gmail.test(datos.gmail)) {
    return "Correo inválido";
  }

  if (!reglas.telefono.test(datos.telefono)) {
    return "Teléfono inválido (9 dígitos)";
  }

  return "";
};

const completarFormularioCliente = (datos = null) => {
  const titulo = document.getElementById("titulo-modal-cliente");
  if (!titulo) return;

  if (modoFormularioCliente === "editar" && datos) {
    titulo.textContent = "Editar Cliente";

    document.getElementById("id-cliente").value = datos.id;
    document.getElementById("nombre-cliente").value = datos.nombre;
    document.getElementById("dni-cliente").value = datos.documento;
    document.getElementById("gmail-cliente").value = datos.gmail || "";
    document.getElementById("telefono-cliente").value = datos.telefono || "";
    document.getElementById("nacionalidad-cliente").value =
      datos.nacionalidad || "";
    document.getElementById("reservaciones-cliente").value =
      datos.reservaciones || 0;
    document.getElementById("metodo-pago-cliente").value = datos.metodoPago;
    document.getElementById("preferencias-cliente").value =
      datos.preferencias || "";
    document.getElementById("observaciones-cliente").value =
      datos.observaciones || "";

    return;
  }

  titulo.textContent = "Nuevo Cliente";
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
    const ensureClientesJsLoaded = () => new Promise((resolve, reject) => {
      if (typeof window.registrarClienteNuevo === 'function' && typeof window.actualizarClienteExistente === 'function') {
        return resolve();
      }

      const scriptId = 'dynamic-clientes-js';
      if (document.getElementById(scriptId)) {
        // already loading; wait a bit for it to be available
        const checkInterval = setInterval(() => {
          if (typeof window.registrarClienteNuevo === 'function' && typeof window.actualizarClienteExistente === 'function') {
            clearInterval(checkInterval);
            resolve();
          }
        }, 200);
        // timeout
        setTimeout(() => {
          clearInterval(checkInterval);
          if (typeof window.registrarClienteNuevo === 'function' && typeof window.actualizarClienteExistente === 'function') {
            resolve();
          } else {
            reject(new Error('No se pudo cargar Clientes.js'));
          }
        }, 5000);
        return;
      }

      const script = document.createElement('script');
      script.id = scriptId;
      script.src = BASE_URL + 'public/js/Clientes.js';
      script.async = true;
      script.onload = () => {
        if (typeof window.registrarClienteNuevo === 'function' || typeof window.actualizarClienteExistente === 'function') {
          resolve();
        } else {
          reject(new Error('Clientes.js cargado pero funciones no expuestas'));
        }
      };
      script.onerror = () => reject(new Error('Error al cargar Clientes.js'));
      document.body.appendChild(script);
    });

    // Ensure Clientes.js is available if needed
    try {
      await ensureClientesJsLoaded();
    } catch (err) {
      throw new Error('No se pudo cargar el módulo de clientes: ' + err.message);
    }

    if (modoFormularioCliente === "editar") {
      await window.actualizarClienteExistente(datos);
    } else {
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

  // Remove existing listeners before adding new ones
  form.removeEventListener("submit", manejarEnvioFormularioCliente);
  btnCancelar.removeEventListener("click", cerrarModalCliente);

  form.addEventListener("submit", manejarEnvioFormularioCliente);
  btnCancelar.addEventListener("click", cerrarModalCliente);
};

const abrirModalCliente = (modo, datos = null) => {
  modoFormularioCliente = modo;
  const contenedor = document.getElementById("contenedor-modal-cliente");
  if (!contenedor) return;

  // Use flex so the modal background centers the dialog (.modal-cliente is a flex container)
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
