let listaUsuarios = [];
let textoBusquedaUsuarios = "";

const normalizarTextoBusqueda = (texto) => texto.toLowerCase().trim();

const obtenerUsuariosFiltradosPorNombre = () => {
  const criterioBusqueda = normalizarTextoBusqueda(textoBusquedaUsuarios);
  if (!criterioBusqueda) return listaUsuarios;
  return listaUsuarios.filter((usuario) =>
    normalizarTextoBusqueda(usuario.nombre_completo).includes(criterioBusqueda),
  );
};

const renderizarTablaUsuarios = (usuariosAMostrar = listaUsuarios) => {
  const cuerpoTabla = document.getElementById("contenido-usuarios");
  if (!cuerpoTabla) return;

  cuerpoTabla.innerHTML = "";
  usuariosAMostrar.forEach((usuario) => {
    cuerpoTabla.innerHTML += `<tr>
            <td>${usuario.id}</td>
            <td>${usuario.nombre_completo}</td>
            <td>${usuario.nombre_usuario}</td>
            <td>${usuario.correo || ""}</td>
            <td>${usuario.telefono || ""}</td>
            <td>${usuario.dni || ""}</td>
            <td>${usuario.rol}</td>
            <td>
                <button type="button" class="btnEditar" data-id="${usuario.id}">✏️</button>
                <button type="button" class="btnEliminar" data-id="${usuario.id}">🗑️</button>
            </td>
        </tr>`;
  });
};

const cargarUsuarios = () => {
  fetch(BASE_URL + "?url=Usuario/listar")
    .then((res) => res.json())
    .then((usuarios) => {
      listaUsuarios = usuarios;
      renderizarTablaUsuarios(obtenerUsuariosFiltradosPorNombre());
    })
    .catch(() => console.error("Error al cargar usuarios"));
};

const eliminarUsuarioPorId = (idUsuario) => {
  if (!confirm("¿Estás seguro de eliminar este usuario?")) return;

  fetch(BASE_URL + "?url=Usuario/eliminar", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ accion: "eliminar", id: idUsuario }),
  })
    .then((res) => res.json())
    .then((data) => {
      if (data.exito) {
        cargarUsuarios();
      } else {
        alert("Error al eliminar usuario.");
      }
    })
    .catch(() => alert("Error de conexión."));
};

const abrirModalParaEditar = (idUsuario) => {
  const usuarioSeleccionado = listaUsuarios.find(
    (usuario) => Number(usuario.id) === Number(idUsuario),
  );
  if (!usuarioSeleccionado) return;

  window.abrirModalUsuario("editar", {
    id: usuarioSeleccionado.id,
    nombre: usuarioSeleccionado.nombre_completo,
    usuario: usuarioSeleccionado.nombre_usuario,
    gmail: usuarioSeleccionado.correo,
    telefono: usuarioSeleccionado.telefono,
    dni: usuarioSeleccionado.dni,
    rol: usuarioSeleccionado.rol,
    password: "",
  });
};

const configurarEventosUsuarios = () => {
  const botonNuevoUsuario = document.getElementById("btnNuevoUsuario");
  const cuerpoTabla = document.getElementById("contenido-usuarios");
  const inputBuscarUsuario = document.getElementById("inputBuscarUsuario");

  if (botonNuevoUsuario) {
    botonNuevoUsuario.addEventListener("click", () => {
      window.abrirModalUsuario("nuevo");
    });
  }

  if (cuerpoTabla) {
    cuerpoTabla.addEventListener("click", (evento) => {
      const botonEditar = evento.target.closest(".btnEditar");
      if (botonEditar) {
        abrirModalParaEditar(botonEditar.dataset.id);
        return;
      }
      const botonEliminar = evento.target.closest(".btnEliminar");
      if (botonEliminar) {
        eliminarUsuarioPorId(botonEliminar.dataset.id);
      }
    });
  }

  if (inputBuscarUsuario) {
    inputBuscarUsuario.addEventListener("input", (evento) => {
      textoBusquedaUsuarios = evento.target.value;
      renderizarTablaUsuarios(obtenerUsuariosFiltradosPorNombre());
    });
  }
};

window.inicializarUsuarios = () => {
  cargarUsuarios();
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

  fetch(BASE_URL + "?url=Usuario/crear", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(datos),
  })
    .then((res) => res.json())
    .then((data) => {
      if (data.exito) {
        alert("Usuario registrado correctamente.");
        cargarUsuarios();
      } else {
        alert("Error al registrar usuario.");
      }
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
    rol: datosUsuario.rol,
  };

  fetch(BASE_URL + "?url=Usuario/actualizarAdmin", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(datos),
  })
    .then((res) => res.json())
    .then((data) => {
      if (data.exito) {
        alert("Usuario actualizado correctamente.");
        cargarUsuarios();
      } else {
        alert("Error al actualizar usuario.");
      }
    })
    .catch(() => alert("Error de conexión."));
};

window.obtenerListaUsuarios = () => listaUsuarios;
