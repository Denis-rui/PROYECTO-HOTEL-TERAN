// main.js - Centralizador de inicializaciones y permisos
const runInitializers = () => {
    // Aplicar permisos de usuario
    const tipoUsuario = localStorage.getItem("tipoUsuario");
    const opcionesNav = document.querySelectorAll("[data-rol]");
    opcionesNav.forEach((opcion) => {
        const rolesRaw = opcion.getAttribute("data-rol");
        if (!rolesRaw) return;

        const rolesPermitidos = rolesRaw.split(",").map((rol) => rol.trim()).filter(Boolean);
        if (!tipoUsuario || !rolesPermitidos.includes(tipoUsuario)) {
            opcion.style.display = "none";
        }
    });

    // Detectar pagina actual y llamar a su inicializador si existe
    const currentUrl = new URLSearchParams(window.location.search).get("url") || "";
    const pathName = window.location.pathname || "";
    const rutaDetectada = `${currentUrl} ${pathName}`.toLowerCase();

    if (rutaDetectada.includes("usuario")) {
        window.inicializarUsuarios?.();
    } else if (rutaDetectada.includes("configuracion")) {
        window.inicializarConfiguraciones?.();
    } else if (rutaDetectada.includes("dashboard")) {
        window.inicializarDashboard?.();
        window.configurarBtnNuevaReserva?.();
    } else if (rutaDetectada.includes("reserva")) {
        window.inicializarReservas?.();
        window.configurarBtnNuevaReserva?.();
    } else if (rutaDetectada.includes("habitacion")) {
        window.configurarBtnNuevaHabitacion?.();
        window.actualizarHabitaciones?.();
    } else if (rutaDetectada.includes("cliente")) {
        window.inicializarClientes?.();
    }
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', runInitializers);
} else {
    runInitializers();
}
