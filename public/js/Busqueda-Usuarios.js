let controladorBusquedaUsuarios = null;

const debounce = (fn, delay = 350) => {
  let temporizador;
  return (...args) => {
    clearTimeout(temporizador);
    temporizador = setTimeout(() => fn(...args), delay);
  };
};

const crearFilaUsuario = (usuario) => {
  const fila = document.createElement("tr");
  fila.dataset.estado = usuario.estado || "activo";

  [usuario.id, usuario.nombre_usuario, usuario.nombre_completo, usuario.rol].forEach((valor) => {
    const td = document.createElement("td");
    td.textContent = valor ?? ""; // escapa cualquier HTML automáticamente
    fila.appendChild(td);
  });

  const tdEstado = document.createElement("td");
  const badge = document.createElement("span");
  badge.className = `badge ${usuario.estado === "activo" ? "badge-activo" : "badge-inactivo"}`;
  badge.textContent = usuario.estado === "activo" ? "Activo" : "Inactivo";
  tdEstado.appendChild(badge);
  fila.appendChild(tdEstado);

  const tdAcciones = document.createElement("td");

  const btnEditar = document.createElement("button");
  btnEditar.type = "button";
  btnEditar.className = "btnEditarUsuario";
  btnEditar.textContent = "✏️";
  Object.assign(btnEditar.dataset, {
    id: usuario.id,
    nombre: usuario.nombre_completo || "",
    usuario: usuario.nombre_usuario || "",
    correo: usuario.correo || "",
    telefono: usuario.telefono || "",
    dni: usuario.dni || "",
    fechaNacimiento: usuario.fecha_nacimiento || "",
    usuarioRol: usuario.rol || "",
  });

  const btnEliminar = document.createElement("button");
  btnEliminar.type = "button";
  btnEliminar.className = "btnEliminarUsuario";
  btnEliminar.textContent = "🗑️";
  btnEliminar.dataset.id = usuario.id;

  tdAcciones.append(btnEditar, btnEliminar);
  fila.appendChild(tdAcciones);

  return fila;
};

const renderizarUsuarios = (usuarios) => {
  const cuerpoTabla = document.getElementById("tabla-usuarios-body");
  cuerpoTabla.innerHTML = "";

  if (!usuarios.length) {
    const fila = document.createElement("tr");
    const td = document.createElement("td");
    td.colSpan = 6;
    td.style.textAlign = "center";
    td.textContent = "No se encontraron usuarios.";
    fila.appendChild(td);
    cuerpoTabla.appendChild(fila);
    return;
  }

  const fragmento = document.createDocumentFragment();
  usuarios.forEach((usuario) => fragmento.appendChild(crearFilaUsuario(usuario)));
  cuerpoTabla.appendChild(fragmento);
};

const buscarUsuarios = async (termino) => {
  if (controladorBusquedaUsuarios) {
    controladorBusquedaUsuarios.abort();
  }
  controladorBusquedaUsuarios = new AbortController();

  try {
    const respuesta = await fetch(
      `${BASE_URL}Usuario/buscar?q=${encodeURIComponent(termino)}`,
      { signal: controladorBusquedaUsuarios.signal }
    );

    if (!respuesta.ok) throw new Error("Respuesta no válida del servidor.");

    const datos = await respuesta.json();
    const usuarios = Array.isArray(datos) ? datos : [];

    window.destruirTablaUsuarios?.();
    renderizarUsuarios(usuarios);

    if (usuarios.length > 0) {
      window.inicializarTablaUsuarios?.();
    }
  } catch (error) {
    if (error.name === "AbortError") return;
    window.Notificar?.("Error al buscar usuarios.", "error");
  }
};

const buscarUsuariosConDebounce = debounce(buscarUsuarios, 350);

window.inicializarBusquedaUsuarios = () => {
  const inputBusqueda = document.getElementById("buscarUsuarioInput");
  if (!inputBusqueda) return;

  inputBusqueda.addEventListener("input", (evento) => {
    const termino = evento.target.value.trim();
    buscarUsuariosConDebounce(termino);
  });
};

document.addEventListener("DOMContentLoaded", window.inicializarBusquedaUsuarios);