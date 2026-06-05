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
    timer: 9000,
    timerProgressBar: true,
  });
};

window.Alerta = (mensaje, tipo = "info", titulo = "") => {
  const iconos = {
    exito: "success",
    error: "error",
    advertencia: "warning",
    info: "info",
  };

  return Swal.fire({
    icon: iconos[tipo] || tipo || "info",
    title: titulo || (tipo === "error" ? "Error" : "Aviso"),
    text: String(mensaje || ""),
    confirmButtonText: "Aceptar",
    confirmButtonColor: "#185025",
  });
};

window.Confirmar = (mensaje, opciones = {}) => {
  return Swal.fire({
    title: opciones.titulo || "Confirmación",
    text: mensaje,
    icon: opciones.icono || "question",
    showCancelButton: true,
    confirmButtonText: opciones.confirmar || "Aceptar",
    cancelButtonText: opciones.cancelar || "Cancelar",
    confirmButtonColor: opciones.colorConfirmar || "#185025",
    cancelButtonColor: opciones.colorCancelar || "#8f2f2f",
  }).then((resultado) => {
    return resultado.isConfirmed;
  });
};

window.SolicitarDato = (titulo, mensaje, opciones = {}) => {
  return Swal.fire({
    title: titulo,
    text: mensaje,
    input: opciones.tipo || "text",
    inputValue: opciones.valor ?? "",
    inputPlaceholder: opciones.placeholder || "Escribe aquí...",
    inputAttributes: opciones.atributos || {},
    showCancelButton: true,
    confirmButtonText: "Aceptar",
    cancelButtonText: "Cancelar",
    confirmButtonColor: "#185025",
    cancelButtonColor: "#8f2f2f",
    inputValidator: opciones.validar,
  }).then((resultado) => {
    if (resultado.isConfirmed) {
      return String(resultado.value ?? "").trim() || null;
    }
    return null;
  });
};
