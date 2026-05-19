function mostrarAlerta(mensaje, exito) {
  const alerta = document.getElementById('alerta');
  alerta.textContent = mensaje;
  alerta.style.cssText = `
    display: block;
    position: fixed;
    top: 80px;
    right: 24px;
    z-index: 9999;
    padding: 12px 20px;
    border-radius: 8px;
    font-weight: 500;
    font-size: 14px;s
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    background-color: ${exito ? '#d4edda' : '#f8d7da'};
    color: ${exito ? '#155724' : '#721c24'};
    border: 1px solid ${exito ? '#c3e6cb' : '#f5c6cb'};
    transition: opacity 0.3s ease;
  `;
  setTimeout(() => {
    alerta.style.opacity = '0';
    setTimeout(() => {
      alerta.style.display = 'none';
      alerta.style.opacity = '1';
    }, 300);
  }, 3500);
}

// Guardar perfil personal
document.getElementById('btnGuardarPerfil').addEventListener('click', async () => {
  const form = document.getElementById('formPerfilPersonal');
  const body = new FormData(form);

  const res  = await fetch(BASE_URL + '?url=Perfil/actualizarPerfil', { method: 'POST', body });
  const json = await res.json();

  mostrarAlerta(json.message, json.success);
});

// Cambiar contraseña
document.getElementById('btnCambiarClave').addEventListener('click', async () => {
    const claveActual  = document.getElementById('clave_actual').value.trim();
    const claveNueva   = document.getElementById('clave_nueva').value.trim();
    const confirmar    = document.getElementById('confirmar_clave').value.trim();

    // Validaciones
    if (!claveActual || !claveNueva || !confirmar) {
        mostrarAlerta('Todos los campos son obligatorios', false);
        return;
    }

    if (claveNueva.length < 6) {
        mostrarAlerta('La nueva contraseña debe tener al menos 6 caracteres', false);
        return;
    }

    if (claveNueva !== confirmar) {
        mostrarAlerta('Las contraseñas no coinciden', false);
        return;
    }

    const form = document.getElementById('formCambiarClave');
    const body = new FormData(form);

    const res  = await fetch(BASE_URL + '?url=Perfil/cambiarClave', { method: 'POST', body });
    const json = await res.json();

    mostrarAlerta(json.message, json.success);

    if (json.success) form.reset();
});