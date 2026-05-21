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
          nombre: fila.cells[1].innerText,
          documento: fila.cells[2].innerText,
          gmail: fila.cells[3].innerText,
          telefono: fila.cells[4].innerText,
          nacionalidad: fila.cells[5].innerText,
          metodoPago: fila.cells[7].innerText
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
