// Clientes.js - Enfocado en validaciones y manejo de modales
let listaClientesLocal = [];

const configurarEventosClientes = () => {
  const botonNuevoCliente = document.getElementById("btnNuevoCliente");
  const cuerpoTabla = document.getElementById("tabla-clientes-body");

  if (botonNuevoCliente) {
    botonNuevoCliente.addEventListener("click", () => {
      if (window.abrirModalCliente) {
        window.abrirModalCliente("nuevo");
      }
    });
  }

  if (cuerpoTabla) {
    cuerpoTabla.addEventListener("click", async (evento) => {
      const botonEditar = evento.target.closest(".btnEditarCliente");
      if (botonEditar) {
        const id = botonEditar.dataset.id;
        // Buscar datos en la tabla (ya que el JS no tiene la lista completa si es tradicional)
        const fila = botonEditar.closest("tr");
        const datos = {
          id: id,
          id_tipo_documento: botonEditar.dataset.tipoDocumento || "",
          nombre: fila.cells[1]?.innerText.trim() || "",
          documento: fila.cells[3]?.innerText.trim() || "",
          gmail: fila.cells[4]?.innerText.trim() || "",
          telefono: fila.cells[5]?.innerText.trim() || "",
          nacionalidad: "",
          reservaciones: Number((fila.cells[6]?.innerText.trim() || "0")),
        };

        if (window.abrirModalCliente) {
          window.abrirModalCliente("editar", datos);
        }
        return;
      }

      const botonEliminar = evento.target.closest(".btnEliminarCliente");
      if (botonEliminar) {
        if (confirm("¿Está seguro de eliminar este cliente?")) {
          try {
            const res = await fetch(BASE_URL + "Cliente/eliminar", {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              body: JSON.stringify({ id: botonEliminar.dataset.id }),
            });
            const resultado = await res.json();
            if (resultado.exito) {
              window.location.reload();
            } else {
              alert(resultado.mensaje || "Error al eliminar");
            }
          } catch (error) {
            console.error(error);
          }
        }
      }
    });
  }
};

window.inicializarClientes = () => {
  configurarEventosClientes();
};

// Exponer funciones necesarias para el modal
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
