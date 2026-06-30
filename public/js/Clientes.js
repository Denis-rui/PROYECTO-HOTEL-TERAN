// Clientes.js - Gestion de clientes con busqueda en tiempo real y perfil

const configurarEventosClientes = () => {
  const botonNuevoCliente = document.getElementById("btnNuevoCliente");
  const cuerpoTabla = document.getElementById("tabla-clientes-body");
  const inputBuscar = document.getElementById("inputBuscarCliente");

  if (botonNuevoCliente) {
    botonNuevoCliente.addEventListener("click", () => {
      if (window.abrirModalCliente) {
        window.abrirModalCliente("nuevo");
      }
    });
  }

  if (inputBuscar) {
    const form = inputBuscar.closest("form");
    if (form) {
      form.addEventListener("submit", (e) => e.preventDefault());
    }

    inputBuscar.addEventListener("input", () => {
      const normalizarTexto = (valor) =>
        String(valor || "")
          .normalize("NFD")
          .replace(/[\u0300-\u036f]/g, "")
          .toLowerCase()
          .trim();

      const texto = normalizarTexto(inputBuscar.value);
      const filas = cuerpoTabla ? cuerpoTabla.querySelectorAll("tr") : [];

      filas.forEach((fila) => {
        const nombre = normalizarTexto(fila.cells[1]?.innerText);
        const documento = normalizarTexto(fila.cells[3]?.innerText);
        const coincide = nombre.includes(texto) || documento.includes(texto);
        fila.style.display = coincide ? "" : "none";
      });
    });
  }

  if (cuerpoTabla) {
    cuerpoTabla.addEventListener("click", async (evento) => {
      const botonVerPerfil = evento.target.closest(".btnVerPerfil");
      if (botonVerPerfil) {
        const fila = botonVerPerfil.closest("tr");
        const datosCliente = {
          nombre: fila.cells[1]?.innerText.trim() || "",
          documento: fila.cells[3]?.innerText.trim() || "",
          email: fila.cells[4]?.innerText.trim() || "",
          procedencia: fila.cells[5]?.innerText.trim() || "",
          telefono: fila.cells[6]?.innerText.trim() || "",
          observaciones: fila.cells[8]?.innerText.trim() || "",
        };
        mostrarPerfilCliente(datosCliente);
        return;
      }

      const botonEditar = evento.target.closest(".btnEditarCliente");
      if (botonEditar) {
        const id = botonEditar.dataset.id;
        const fila = botonEditar.closest("tr");
        const datos = {
          id: id,
          id_tipo_documento: botonEditar.dataset.tipoDocumento || "",
          nombre: fila.cells[1]?.innerText.trim() || "",
          documento: fila.cells[3]?.innerText.trim() || "",
          gmail: fila.cells[4]?.innerText.trim() || "",
          telefono: fila.cells[6]?.innerText.trim() || "",
          procedencia: fila.cells[5]?.innerText.trim() || "",
          reservaciones: Number(fila.cells[7]?.innerText.trim() || "0"),
          observaciones: fila.cells[8]?.innerText.trim() || "",
        };

        if (window.abrirModalCliente) {
          window.abrirModalCliente("editar", datos);
        }
        return;
      }

      const botonInhabilitar = evento.target.closest(".btnInhabilitarCliente");
      if (botonInhabilitar) {
        Swal.fire({
          title: "¿Está seguro?",
          text: "¿Está seguro de que desea inhabilitar este cliente?",
          icon: "warning",
          showCancelButton: true,
          confirmButtonColor: "#d33",
          cancelButtonColor: "#6c757d",
          confirmButtonText: "Sí, inhabilitar",
          cancelButtonText: "Cancelar",
        }).then((result) => {
          if (result.isConfirmed) {
            cambiarEstadoCliente(
              "eliminar",
              botonInhabilitar.dataset.id,
              "Cliente inhabilitado correctamente",
            );
          }
        });
        return;
      }

      const botonHabilitar = evento.target.closest(".btnHabilitarCliente");
      if (botonHabilitar) {
        Swal.fire({
          title: "¿Está seguro?",
          text: "¿Está seguro de que desea habilitar este cliente?",
          icon: "question",
          showCancelButton: true,
          confirmButtonColor: "#28a745",
          cancelButtonColor: "#6c757d",
          confirmButtonText: "Sí, habilitar",
          cancelButtonText: "Cancelar",
        }).then((result) => {
          if (result.isConfirmed) {
            cambiarEstadoCliente(
              "habilitar",
              botonHabilitar.dataset.id,
              "Cliente habilitado correctamente",
            );
          }
        });
      }
    });
  }
};

const cambiarEstadoCliente = async (accion, idCliente, mensajeExito) => {
  const metodo = accion === "eliminar" ? "DELETE" : "PUT";
  const csrfToken = typeof CSRF_TOKEN !== "undefined" ? CSRF_TOKEN : "";

  try {
    const res = await fetch(BASE_URL + `Cliente/${accion}`, {
      method: metodo,
      headers: {
        "Content-Type": "application/json",
        "X-CSRF-Token": csrfToken,
      },
      body: JSON.stringify({ id: idCliente }),
    });

    const resultado = await res.json();
    if (resultado.exito) {
      Swal.fire({
        title: "¡Éxito!",
        text: mensajeExito,
        icon: "success",
        confirmButtonColor: "#28a745",
        confirmButtonText: "Aceptar",
      }).then(() => {
        window.location.reload();
      });
    } else {
      Swal.fire({
        title: "Error",
        text: resultado.mensaje || "No se pudo cambiar el estado del cliente",
        icon: "error",
        confirmButtonColor: "#d33",
        confirmButtonText: "Aceptar",
      });
    }
  } catch (error) {
    console.error(error);
    Swal.fire({
      title: "Error",
      text: "Error al cambiar el estado del cliente",
      icon: "error",
      confirmButtonColor: "#d33",
      confirmButtonText: "Aceptar",
    });
  }
};

const mostrarPerfilCliente = (cliente) => {
  const modalHTML = `
    <div class="modal-perfil-cliente" id="modal-perfil-cliente" style="display: flex; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); align-items: center; justify-content: center; z-index: 1000;">
      <div class="contenedor-perfil" style="background: white; border-radius: 8px; padding: 30px; max-width: 500px; width: 90%; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); max-height: 90vh; overflow-y: auto;">
        <h2 style="margin-top: 0; color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px;">Perfil del Cliente</h2>

        <div style="display: grid; gap: 15px; margin: 20px 0;">
          <div>
            <label style="font-weight: bold; color: #555;">Nombre:</label>
            <p style="margin: 5px 0; color: #333;">${cliente.nombre}</p>
          </div>
          <div>
            <label style="font-weight: bold; color: #555;">Documento:</label>
            <p style="margin: 5px 0; color: #333;">${cliente.documento}</p>
          </div>
          <div>
            <label style="font-weight: bold; color: #555;">Email:</label>
            <p style="margin: 5px 0; color: #333;"><a href="mailto:${cliente.email}" style="color: #007bff;">${cliente.email}</a></p>
          </div>
          <div>
            <label style="font-weight: bold; color: #555;">Telefono:</label>
            <p style="margin: 5px 0; color: #333;"><a href="tel:${cliente.telefono}" style="color: #007bff;">${cliente.telefono}</a></p>
          </div>
          <div>
            <label style="font-weight: bold; color: #555;">Procedencia:</label>
            <p style="margin: 5px 0; color: #333;">${cliente.procedencia}</p>
          </div>
          <div>
            <label style="font-weight: bold; color: #555;">Observaciones:</label>
            <p style="margin: 5px 0; color: #333; word-wrap: break-word; white-space: pre-wrap; line-height: 1.5;">${cliente.observaciones || "(Sin observaciones)"}</p>
          </div>
        </div>

        <button onclick="cerrarPerfilCliente()" style="width: 100%; padding: 10px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; font-weight: bold;">Cerrar</button>
      </div>
    </div>
  `;

  const modalAnterior = document.getElementById("modal-perfil-cliente");
  if (modalAnterior) {
    modalAnterior.remove();
  }

  document.body.insertAdjacentHTML("beforeend", modalHTML);
};

const cerrarPerfilCliente = () => {
  const modal = document.getElementById("modal-perfil-cliente");
  if (modal) {
    modal.remove();
  }
};

let clientesInicializados = false;

window.inicializarClientes = () => {
  if (clientesInicializados) return;
  clientesInicializados = true;
  configurarEventosClientes();
};

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () =>
    window.inicializarClientes(),
  );
} else {
  window.inicializarClientes();
}

window.registrarClienteNuevo = async (datos) => {
  try {
    const csrfToken = typeof CSRF_TOKEN !== "undefined" ? CSRF_TOKEN : "";
    const res = await fetch(BASE_URL + "Cliente/registrar", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-CSRF-Token": csrfToken,
      },
      body: JSON.stringify(datos),
    });
    const resultado = await res.json();
    if (resultado.exito) {
      Swal.fire({
        title: "¡Éxito!",
        text: "Cliente registrado correctamente",
        icon: "success",
        confirmButtonColor: "#28a745",
        confirmButtonText: "Aceptar",
      }).then(() => {
        window.location.reload();
      });
    } else {
      throw new Error(resultado.mensaje || "Error al registrar");
    }
  } catch (error) {
    Swal.fire({
      title: "Error",
      text: error.message,
      icon: "error",
      confirmButtonColor: "#d33",
      confirmButtonText: "Aceptar",
    });
    throw error;
  }
};

window.actualizarClienteExistente = async (datos) => {
  try {
    const csrfToken = typeof CSRF_TOKEN !== "undefined" ? CSRF_TOKEN : "";
    const res = await fetch(BASE_URL + "Cliente/actualizar", {
      method: "PUT", // ← Cambiar POST → PUT
      headers: {
        "Content-Type": "application/json",
        "X-CSRF-Token": csrfToken,
      },
      body: JSON.stringify(datos),
    });
    const resultado = await res.json();
    if (resultado.exito) {
      Swal.fire({
        title: "¡Éxito!",
        text: "Cliente actualizado correctamente",
        icon: "success",
        confirmButtonColor: "#28a745",
        confirmButtonText: "Aceptar",
      }).then(() => {
        window.location.reload();
      });
    } else {
      throw new Error(resultado.mensaje || "Error al actualizar");
    }
  } catch (error) {
    Swal.fire({
      title: "Error",
      text: error.message,
      icon: "error",
      confirmButtonColor: "#d33",
      confirmButtonText: "Aceptar",
    });
    throw error;
  }
};
