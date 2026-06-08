// Crear contenedor de notificaciones si no existe
window.Notificar = (mensaje, tipo = "info") => {
  let icon = "info";
  if (tipo === "exito") icon = "success";
  if (tipo === "error") icon = "error";

  Swal.fire({
    toast: true,
    position: "top-end",
    icon: icon,
    title: mensaje,
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
  });
};

window.Confirmar = (mensaje) => {
  return Swal.fire({
    title: "Confirmación",
    text: mensaje,
    icon: "question",
    showCancelButton: true,
    confirmButtonText: "Aceptar",
    cancelButtonText: "Cancelar",
  }).then((resultado) => {
    return resultado.isConfirmed;
  });
};

window.SolicitarDato = (titulo, mensaje) => {
  return Swal.fire({
    title: titulo,
    text: mensaje,
    input: "text",
    inputPlaceholder: "Escribe aquí...",
    showCancelButton: true,
    confirmButtonText: "Aceptar",
    cancelButtonText: "Cancelar",
  }).then((resultado) => {
    if (resultado.isConfirmed) {
      return resultado.value?.trim() || null;
    }
    return null;
  });
};
