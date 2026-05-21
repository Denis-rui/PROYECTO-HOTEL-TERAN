const obtenerDatosUsuarioDesdeBoton = (boton) => ({
  id: boton.dataset.id || "",
  nombre: boton.dataset.nombre || "",
  usuario: boton.dataset.usuario || "",
  gmail: boton.dataset.correo || "",
  telefono: boton.dataset.telefono || "",
  dni: boton.dataset.dni || "",
  fecha_nacimiento: boton.dataset.fechaNacimiento || "",
  rol: boton.dataset.rol || "",
  password: "",
});

const eliminarUsuarioPorId = (idUsuario) => {
  if (!confirm("¿Estás seguro de eliminar este usuario?")) return;

  fetch(BASE_URL + "Usuario/eliminar", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ accion: "eliminar", id: idUsuario }),
  })
    .then((res) => res.json())
    .then((data) => {
      if (data.exito) {
        window.location.reload();
      } else {
        alert("Error al eliminar usuario.");
      }
    })
    .catch(() => alert("Error de conexión."));
};

const abrirModalParaEditar = (boton) => {
  window.abrirModalUsuario("editar", obtenerDatosUsuarioDesdeBoton(boton));
};

const configurarEventosUsuarios = () => {
  const botonNuevoUsuario = document.getElementById("btnNuevoUsuario");
  const cuerpoTabla = document.getElementById("tabla-usuarios-body");

  if (botonNuevoUsuario) {
    botonNuevoUsuario.addEventListener("click", () => {
      window.abrirModalUsuario("nuevo");
    });
  }

  if (cuerpoTabla) {
    cuerpoTabla.addEventListener("click", (evento) => {
      const botonEditar = evento.target.closest(".btnEditarUsuario");
      if (botonEditar) {
        abrirModalParaEditar(botonEditar);
        return;
      }
      const botonEliminar = evento.target.closest(".btnEliminarUsuario");
      if (botonEliminar) {
        eliminarUsuarioPorId(botonEliminar.dataset.id);
      }
    });
  }
};

window.inicializarUsuarios = () => {
  if (!document.getElementById("tabla-usuarios-body")) {
    return;
  }

  configurarEventosUsuarios();
};

window.registrarUsuarioNuevo = (datosUsuario) => {
  const datos = {
    accion: "crear",
    nombre_completo: datosUsuario.nombre,
    nombre_usuario: datosUsuario.usuario,
    correo: datosUsuario.gmail,
    telefono: datosUsuario.telefono,
    dni: datosUsuario.dni,
    fecha_nacimiento: datosUsuario.fecha_nacimiento,
    contrasenia: datosUsuario.password,
    rol: datosUsuario.rol,
  };

  return fetch(BASE_URL + "Usuario/crear", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(datos),
  })
    .then((res) => res.json())
    .then((data) => {
      if (data.exito) {
        window.location.reload();
        return data;
      } else {
        window.mostrarMensajeModalUsuario?.(
          data.error || "Error al registrar usuario.",
          "error",
        );
        return data;
      }
    })
    .catch(() => {
      const respuesta = { exito: false, error: "Error de conexion." };
      window.mostrarMensajeModalUsuario?.(respuesta.error, "error");
      return respuesta;
    });
};

window.actualizarUsuarioExistente = (datosUsuario) => {
  const datos = {
    accion: "actualizar_admin",
    id: datosUsuario.id,
    nombre_completo: datosUsuario.nombre,
    nombre_usuario: datosUsuario.usuario,
    correo: datosUsuario.gmail,
    telefono: datosUsuario.telefono,
    dni: datosUsuario.dni,
    fecha_nacimiento: datosUsuario.fecha_nacimiento,
    contrasenia: datosUsuario.password,
    rol: datosUsuario.rol,
  };

  return fetch(BASE_URL + "Usuario/actualizarAdmin", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(datos),
  })
    .then((res) => res.json())
    .then((data) => {
      if (data.exito) {
        window.location.reload();
        return data;
      } else {
        window.mostrarMensajeModalUsuario?.(
          data.error || "Error al actualizar usuario.",
          "error",
        );
        return data;
      }
    })
    .catch(() => {
      const respuesta = { exito: false, error: "Error de conexion." };
      window.mostrarMensajeModalUsuario?.(respuesta.error, "error");
      return respuesta;
    });
};

document.addEventListener("DOMContentLoaded", window.inicializarUsuarios);
