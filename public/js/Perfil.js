// Guardar perfil personal
document.getElementById('btnGuardarPerfil').addEventListener('click', async () => {
  const confirmado = await window.Confirmar?.('¿Estás seguro de guardar estos cambios?');
  if (!confirmado) return;

  const form = document.getElementById('formPerfilPersonal');
  const body = new FormData(form);

  let json;
  try {
    const res = await fetch(BASE_URL + 'Perfil/actualizarPerfil', {
      method: 'POST',
      body,
      credentials: 'same-origin',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
      },
    });
    const text = await res.text();

    try {
      json = JSON.parse(text);
    } catch (parseError) {
      throw new Error(text.replace(/<[^>]*>/g, '').trim() || 'Respuesta inválida del servidor');
    }

    if (!res.ok) {
      throw new Error(json.message || 'Error al actualizar el perfil');
    }
  } catch (error) {
    window.Notificar?.(error.message || 'No se pudo actualizar el perfil. Intenta de nuevo.', 'error');
    console.error('Error actualizar perfil:', error);
    return;
  }

  if (json.success) {
    window.Notificar?.('Perfil actualizado correctamente.', 'exito');
  } else {
    window.Notificar?.(json.message || 'Error al actualizar el perfil.', 'error');
  }
});

// Funciones para mostrar/limpiar errores en cambiar contraseña
const limpiarErroresClave = () => {
  const erroresElementos = document.querySelectorAll("#formCambiarClave .error-validation");
  erroresElementos.forEach((elemento) => {
    elemento.textContent = "";
    elemento.style.display = "none";
  });

  const camposConError = document.querySelectorAll("#formCambiarClave .form-input.error");
  camposConError.forEach((campo) => {
    campo.classList.remove("error");
  });

  const mensajeDiv = document.getElementById("error-exito-cambiar-clave");
  if (mensajeDiv) {
    mensajeDiv.textContent = "";
    mensajeDiv.classList.remove("error", "exito");
  }
};

const mostrarErrorClave = (idCampo, mensaje) => {
  const campo = document.getElementById(idCampo);
  const elementoError = document.getElementById(`error-${idCampo}`);

  if (campo) {
    campo.classList.add("error");
  }

  if (elementoError) {
    elementoError.textContent = mensaje;
    elementoError.style.display = "block";
  }
};

const mostrarMensajeClave = (mensaje, tipo = "error") => {
  const elemento = document.getElementById("error-exito-cambiar-clave");
  if (!elemento) return;

  elemento.textContent = mensaje;
  elemento.classList.remove("error", "exito");

  if (tipo) elemento.classList.add(tipo);
};

// Cambiar contraseña
document.getElementById('btnCambiarClave').addEventListener('click', async () => {
    const claveActual  = document.getElementById('clave_actual').value.trim();
    const claveNueva   = document.getElementById('clave_nueva').value.trim();
    const confirmar    = document.getElementById('confirmar_clave').value.trim();

    limpiarErroresClave();
    let tieneErrores = false;

    // Validar contraseña actual
    if (!claveActual) {
        mostrarErrorClave('clave_actual', 'La contraseña actual es obligatoria');
        tieneErrores = true;
    }

    // Validar contraseña nueva
    if (!claveNueva) {
        mostrarErrorClave('clave_nueva', 'La nueva contraseña es obligatoria');
        tieneErrores = true;
    } else if (claveNueva.length < 6) {
        mostrarErrorClave('clave_nueva', 'La nueva contraseña debe tener al menos 6 caracteres');
        tieneErrores = true;
    }

    // Validar confirmación
    if (!confirmar) {
        mostrarErrorClave('confirmar_clave', 'Debe confirmar la contraseña');
        tieneErrores = true;
    } else if (claveNueva !== confirmar) {
        mostrarErrorClave('confirmar_clave', 'Las contraseñas no coinciden');
        tieneErrores = true;
    }

    if (tieneErrores) return;

    const confirmado = await window.Confirmar?.('¿Estás seguro de cambiar tu contraseña?');
    if (!confirmado) return;

    // Verificar que la contraseña actual es correcta
    const form = document.getElementById('formCambiarClave');
    const body = new FormData(form);

    const res  = await fetch(BASE_URL + 'Perfil/cambiarClave', {
      method: 'POST',
      body,
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
      },
    });
    const json = await res.json();

    // Si hay error de contraseña actual, mostrar error y retornar
    if (!json.success && json.message && (json.message.toLowerCase().includes('actual') || json.message.toLowerCase().includes('incorrecta') || json.message.toLowerCase().includes('no coincide'))) {
        mostrarErrorClave('clave_actual', 'La contraseña actual no es la correcta');
        return;
    }

    // Si hay otro error, mostrarlo
    if (!json.success) {
        mostrarMensajeClave(json.message || 'Error al cambiar la contraseña.', 'error');
        return;
    }

    // La contraseña se cambia solo después de la confirmación previa.
    window.Notificar?.('Contraseña actualizada correctamente.', 'exito');
    form.reset();
});
