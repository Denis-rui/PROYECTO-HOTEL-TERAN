window.inicializarConfiguraciones = () => {
  const formulario = document.getElementById("formulario");

  fetch(BASE_URL + "?url=Configuracion/obtener")
    .then((res) => res.json())
    .then((hotel) => {
      document.getElementById("nombre").value = hotel.nombre ?? "";
      document.getElementById("ruc").value = hotel.ruc ?? "";
      document.getElementById("telefono").value = hotel.telefono ?? "";
      document.getElementById("email").value = hotel.email ?? "";
      document.getElementById("direccion").value = hotel.direccion ?? "";
      document.getElementById("ciudad-region").value =
        hotel.ciudad_region ?? "";
      document.getElementById("descripcion-slogan").value =
        hotel.descripcion ?? "";
      document.getElementById("monedas").value = hotel.moneda ?? "";
      document.getElementById("check-in").value = hotel.check_in
        ? hotel.check_in.slice(0, 5)
        : "";
      document.getElementById("check-out").value = hotel.check_out
        ? hotel.check_out.slice(0, 5)
        : "";
      document.getElementById("web-redes").value = hotel.web ?? "";
    })
    .catch(() => console.error("Error al cargar datos del hotel"));

  formulario.addEventListener("submit", function (e) {
    e.preventDefault();
    if (!validarFormulario()) return;

    const datos = {
      nombre: document.getElementById("nombre").value,
      ruc: document.getElementById("ruc").value,
      telefono: document.getElementById("telefono").value,
      email: document.getElementById("email").value,
      direccion: document.getElementById("direccion").value,
      "ciudad-region": document.getElementById("ciudad-region").value,
      "descripcion-slogan": document.getElementById("descripcion-slogan").value,
      monedas: document.getElementById("monedas").value,
      "check-in": document.getElementById("check-in").value,
      "check-out": document.getElementById("check-out").value,
      "web-redes": document.getElementById("web-redes").value,
    };

    fetch(BASE_URL + "?url=Configuracion/actualizar", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(datos),
    })
      .then((res) => res.json())
      .then((data) => {
        if (data.exito) {
          alert("Configuración guardada correctamente.");
        } else {
          alert("Error al guardar. Intenta de nuevo.");
        }
      })
      .catch(() => alert("Error de conexión."));
  });

  function validarFormulario() {
    limpiarErrores();
    let valido = true;

    const nombre_hotel = document.getElementById("nombre");
    const ruc = document.getElementById("ruc");
    const telefono = document.getElementById("telefono");
    const correo = document.getElementById("email");

    if (nombre_hotel.value.trim() === "") {
      mostrarError(nombre_hotel, "error-nombre", "El nombre es obligatorio");
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
    formulario
      .querySelectorAll(".form-input")
      .forEach((i) => i.classList.remove("input-error"));
  }
};
