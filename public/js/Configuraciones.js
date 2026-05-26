window.inicializarConfiguraciones = () => {
  const formulario = document.getElementById("formulario");

  fetch(BASE_URL + "Configuracion/obtener")
    .then((res) => res.json())
    .then((hotel) => {
      document.getElementById("nombre").value    = hotel.nombre        ?? "";
      document.getElementById("ruc").value       = hotel.ruc           ?? "";
      document.getElementById("telefono").value  = hotel.telefono      ?? "";
      document.getElementById("email").value     = hotel.email         ?? "";
      document.getElementById("ubicacion").value = hotel.direccion     ?? "";
      document.getElementById("ciudad").value    = hotel.ciudad_region ?? "";
      document.getElementById("descripcion").value = hotel.descripcion ?? "";
      document.getElementById("monedas").value   = hotel.moneda        ?? "";
      document.getElementById("check-in").value  = hotel.check_in  ? hotel.check_in.slice(0, 5)  : "";
      document.getElementById("checkout").value  = hotel.check_out ? hotel.check_out.slice(0, 5) : "";
      document.getElementById("web-redes").value = hotel.web           ?? "";
      document.getElementById("porcentaje_adelanto").value   = hotel.porcentaje_adelanto             ?? 50;
      document.getElementById("porcentaje_penalidad").value  = hotel.porcentaje_penalidad_cancelacion ?? 25;
    })
    .catch(() => console.error("Error al cargar datos del hotel"));

  formulario.addEventListener("submit", function (e) {
    e.preventDefault();
    if (!validarFormulario()) return;

    const datos = {
      nombre:               document.getElementById("nombre").value,
      ruc:                  document.getElementById("ruc").value,
      telefono:             document.getElementById("telefono").value,
      email:                document.getElementById("email").value,
      direccion:            document.getElementById("ubicacion").value,
      ciudad_region:        document.getElementById("ciudad").value,
      descripcion:          document.getElementById("descripcion").value,
      monedas:              document.getElementById("monedas").value,
      check_in:             document.getElementById("check-in").value,
      check_out:            document.getElementById("checkout").value,
      web_redes:            document.getElementById("web-redes").value,
      porcentaje_adelanto:  document.getElementById("porcentaje_adelanto").value,
      porcentaje_penalidad: document.getElementById("porcentaje_penalidad").value,
    };

    // Mostrar modal de confirmación antes de enviar
    const modalConfirm = document.getElementById("modal-confirmar-guardar");
    const btnConfirm = document.getElementById("btn-confirmar-guardar");
    const btnCancelar = document.getElementById("btn-cancelar-guardar");
    const mensajeModal = document.getElementById("mensaje-modal-confirm");

    modalConfirm.style.display = "flex";

    function enviarDatos() {
      // deshabilitar botones para evitar envíos dobles
      btnConfirm.disabled = true;
      btnCancelar.disabled = true;

      fetch(BASE_URL + "Configuracion/actualizar", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(datos),
      })
        .then((res) => res.json())
        .then((data) => {
          if (data.exito) {
            mensajeModal.className = "div-mensaje-exito-error exito";
            mensajeModal.textContent = "Configuración guardada correctamente.";
            setTimeout(() => location.reload(), 800);
          } else {
            mensajeModal.className = "div-mensaje-exito-error error";
            mensajeModal.textContent = "Error al guardar. Intenta de nuevo.";
            btnConfirm.disabled = false;
            btnCancelar.disabled = false;
          }
        })
        .catch(() => {
          mensajeModal.className = "div-mensaje-exito-error error";
          mensajeModal.textContent = "Error de conexión.";
          btnConfirm.disabled = false;
          btnCancelar.disabled = false;
        });
    }

    function cerrarModal() {
      mensajeModal.textContent = "";
      modalConfirm.style.display = "none";
      btnConfirm.disabled = false;
      btnCancelar.disabled = false;
      // eliminar listeners para evitar duplicados
      btnConfirm.removeEventListener("click", enviarDatos);
      btnCancelar.removeEventListener("click", cerrarModal);
    }

    btnConfirm.addEventListener("click", enviarDatos);
    btnCancelar.addEventListener("click", cerrarModal);
  });

  function validarFormulario() {
    limpiarErrores();
    let valido = true;

    const nombre   = document.getElementById("nombre");
    const ruc      = document.getElementById("ruc");
    const telefono = document.getElementById("telefono");
    const correo   = document.getElementById("email");

    if (nombre.value.trim() === "") {
      mostrarError(nombre, "error-nombre", "El nombre es obligatorio");
      valido = false;
    }
    if (ruc.value.trim().length !== 11 || isNaN(ruc.value)) {
      mostrarError(ruc, "error-ruc", "El RUC debe tener 11 dígitos");
      valido = false;
    }
    if (telefono.value.trim().length !== 9) {
      mostrarError(telefono, "error-telefono", "Teléfono inválido");
      valido = false;
    }
    if (!correo.value.includes("@") || !correo.value.includes(".")) {
      mostrarError(correo, "error-email", "Correo inválido");
      valido = false;
    }

    return valido;
  }

  function mostrarError(input, idError, mensaje) {
    document.getElementById(idError).textContent = mensaje;
    input.classList.add("input-error");
  }

  function limpiarErrores() {
    formulario.querySelectorAll(".error").forEach((e) => (e.textContent = ""));
    formulario.querySelectorAll(".form-input").forEach((i) => i.classList.remove("input-error"));
  }

  // ---- Modal Tipo Habitación ----
  const modal        = document.getElementById("contenedor-modal-tipo-habitacion");
  const tituloModal  = document.getElementById("titulo-modal-tipo");
  const formTipo     = document.getElementById("form-tipo-habitacion");
  const mensajeModal = document.getElementById("error-exito-modal-tipo");

  document.getElementById("btnNuevoTipoHabitacion").addEventListener("click", () => {
    tituloModal.textContent = "Nuevo Tipo de Habitación";
    formTipo.reset();
    document.getElementById("id-tipo").value = "";
    mensajeModal.textContent = "";
    modal.style.display = "flex";
  });

  document.querySelectorAll(".btn-editar-tipo").forEach((btn) => {
    btn.addEventListener("click", () => {
      tituloModal.textContent = "Editar Tipo de Habitación";
      document.getElementById("id-tipo").value         = btn.dataset.id;
      document.getElementById("tipo-habitacion").value = btn.dataset.tipo;
      document.getElementById("precio-base").value     = btn.dataset.precio;
      mensajeModal.textContent = "";
      modal.style.display = "flex";
    });
  });

  document.getElementById("btn-cancelar-tipo").addEventListener("click", () => {
    modal.style.display = "none";
  });


  formTipo.addEventListener("submit", async (e) => {
    e.preventDefault();

    const tipo   = document.getElementById("tipo-habitacion").value.trim();
    const precio = document.getElementById("precio-base").value.trim();

    if (!tipo || !precio) {
      mensajeModal.className = "div-mensaje-exito-error error";
      mensajeModal.textContent = "Todos los campos son obligatorios";
      return;
    }

    const body = new FormData();
    body.append("id",          document.getElementById("id-tipo").value);
    body.append("tipo",        tipo);
    body.append("precio_base", precio);

    const res = await fetch(BASE_URL + "Configuracion/guardarTipo", { method: "POST", body });

    if (res.ok) {
      modal.style.display = "none";
      location.reload();
    } else {
      mensajeModal.className = "div-mensaje-exito-error error";
      mensajeModal.textContent = "Error al guardar. Intenta de nuevo.";
    }
  });
};

document.addEventListener('DOMContentLoaded', window.inicializarConfiguraciones);