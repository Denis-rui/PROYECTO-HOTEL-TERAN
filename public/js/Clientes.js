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
    let temporizadorBusqueda = null;
    inputBuscar.addEventListener("keyup", () => {
      const form = inputBuscar.closest("form");
      if (!form) return;
      if (temporizadorBusqueda) clearTimeout(temporizadorBusqueda);

      temporizadorBusqueda = setTimeout(() => {
        form.submit();
      }, 400);
    });
  }

  if (cuerpoTabla) {
    cuerpoTabla.addEventListener("click", async (evento) => {
      const botonVerPerfil = evento.target.closest(".btnVerPerfil");
      if (botonVerPerfil) {
        const id = botonVerPerfil.dataset.id;
        const fila = botonVerPerfil.closest("tr");
        const datosCliente = {
          id: id,
          nombre: fila.cells[1]?.innerText.trim() || "",
          documento: fila.cells[3]?.innerText.trim() || "",
          email: fila.cells[4]?.innerText.trim() || "",
          procedencia: fila.cells[5]?.innerText.trim() || "",
          telefono: fila.cells[6]?.innerText.trim() || "",
          observaciones: fila.cells[8]?.innerText.trim() || ""
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
        if (confirm("Esta seguro de que desea inhabilitar este cliente?")) {
          try {
            const res = await fetch(BASE_URL + "Cliente/eliminar", {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              body: JSON.stringify({ id: botonInhabilitar.dataset.id }),
            });
            const resultado = await res.json();
            if (resultado.exito) {
              alert("Cliente inhabilitado correctamente");
              window.location.reload();
            } else {
              alert(resultado.mensaje || "Error al inhabilitar");
            }
          } catch (error) {
            console.error(error);
            alert("Error al inhabilitar el cliente");
          }
        }
      }
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
            <label style="font-weight: bold; color: #555;">ID:</label>
            <p style="margin: 5px 0; color: #333;">${cliente.id}</p>
          </div>
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
  document.addEventListener("DOMContentLoaded", () => window.inicializarClientes());
} else {
  window.inicializarClientes();
}

window.registrarClienteNuevo = async (datos) => {
  const res = await fetch(BASE_URL + "Cliente/registrar", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(datos),
  });
  const resultado = await res.json();
  if (resultado.exito) {
    window.location.reload();
  } else {
    throw new Error(resultado.mensaje || "Error al registrar");
  }
};

window.actualizarClienteExistente = async (datos) => {
  const res = await fetch(BASE_URL + "Cliente/actualizar", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(datos),
  });
  const resultado = await res.json();
  if (resultado.exito) {
    window.location.reload();
  } else {
    throw new Error(resultado.mensaje || "Error al actualizar");
  }
};
