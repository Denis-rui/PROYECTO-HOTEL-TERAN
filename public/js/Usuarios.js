const obtenerDatosUsuarioDesdeBoton = (boton) => ({
  id: boton.dataset.id || "",
  nombre: boton.dataset.nombre || "",
  usuario: boton.dataset.usuario || "",
  gmail: boton.dataset.correo || "",
  telefono: boton.dataset.telefono || "",
  dni: boton.dataset.dni || "",
  fecha_nacimiento: boton.dataset.fechaNacimiento || "",
  rol: boton.dataset.usuarioRol || "",
  password: "",
});

const eliminarUsuarioPorId = async (idUsuario) => {
  const confirmado = await window.Confirmar?.(
    "¿Estás seguro de eliminar este usuario?",
  );
  if (!confirmado) return;

  const csrfToken = typeof CSRF_TOKEN !== "undefined" ? CSRF_TOKEN : "";

  try {
    const res = await fetch(BASE_URL + "Usuario/eliminar", {
      method: "DELETE", // ← Cambiar POST → DELETE
      headers: {
        "Content-Type": "application/json",
        "X-CSRF-Token": csrfToken,
      },
      body: JSON.stringify({ id: idUsuario }),
    });
    const data = await res.json();
    if (data.exito) {
      window.Notificar?.("Usuario eliminado correctamente.", "exito");
      setTimeout(() => window.location.reload(), 1500);
    } else {
      window.Notificar?.(
        data.mensaje || data.error || "Error al eliminar usuario.",
        "error",
      );
    }
  } catch (error) {
    console.error("Error al eliminar usuario:", error);
    window.Notificar?.("Error de conexión.", "error");
  }
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

window.actualizarUsuarioExistente = async (datosUsuario) => {
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

  const csrfToken = typeof CSRF_TOKEN !== "undefined" ? CSRF_TOKEN : "";

  try {
    const res = await fetch(BASE_URL + "Usuario/actualizarAdmin", {
      method: "PUT", // ← Cambiar POST → PUT
      headers: {
        "Content-Type": "application/json",
        "X-CSRF-Token": csrfToken,
      },
      body: JSON.stringify(datos),
    });
    const data = await res.json();
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
  } catch (error) {
    console.error("Error al actualizar usuario:", error);
    const respuesta = { exito: false, error: "Error de conexión." };
    window.mostrarMensajeModalUsuario?.(respuesta.error, "error");
    return respuesta;
  }
};

document.addEventListener("DOMContentLoaded", window.inicializarUsuarios);
