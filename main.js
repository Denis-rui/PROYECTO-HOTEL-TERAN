// main.js - Centralizador de inicializaciones y permisos
document.addEventListener("DOMContentLoaded", () => {
    // Aplicar permisos de usuario
    const tipoUsuario = localStorage.getItem("tipoUsuario");
    const opcionesNav = document.querySelectorAll("[data-rol]");
    opcionesNav.forEach((opcion) => {
        const rolesPermitidos = opcion.getAttribute("data-rol").split(",");
        if (!rolesPermitidos.includes(tipoUsuario)) {
            opcion.style.display = "none";
        }
    });

    // Detectar página actual y llamar a su inicializador si existe
    const currentUrl = new URLSearchParams(window.location.search).get('url') || '';
    const urlLower = currentUrl.toLowerCase();

    if (urlLower.includes("usuario")) {
        window.inicializarUsuarios?.();
    } else if (urlLower.includes("configuracion")) {
        window.inicializarConfiguraciones?.();
    } else if (urlLower.includes("dashboard")) {
        window.inicializarDashboard?.();
        window.configurarBtnNuevaReserva?.();
    } else if (urlLower.includes("reserva")) {
        window.inicializarReservas?.();
        window.configurarBtnNuevaReserva?.();
    } else if (urlLower.includes("habitacion")) {
        window.configurarBtnNuevaHabitacion?.();
        window.actualizarHabitaciones?.();
    } else if (urlLower.includes("cliente")) {
        window.inicializarClientes?.();
    }
});
