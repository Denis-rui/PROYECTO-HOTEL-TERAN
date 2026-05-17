// Crear contenedor de notificaciones si no existe
const asegurarContenedor = () => {
  let container = document.getElementById("contenedor-notificaciones");
  if (!container) {
    container = document.createElement("div");
    container.id = "contenedor-notificaciones";
    document.body.appendChild(container);
  }
  return container;
};

/**
 * Muestra un toast de notificación (éxito, error, info)
 * @param {string} mensaje
 * @param {string} tipo 'exito', 'error', 'info'
 */
window.Notificar = (mensaje, tipo = "info") => {
  const container = asegurarContenedor();
  const notif = document.createElement("div");
  notif.className = `notificacion ${tipo}`;

  let icono = "🔔";
  if (tipo === "exito") icono = "✅";
  if (tipo === "error") icono = "❌";

  notif.innerHTML = `<span>${icono}</span> <span>${mensaje}</span>`;

  container.appendChild(notif);

  // Eliminar del DOM después de la animación
  setTimeout(() => {
    notif.remove();
  }, 4000);
};

/**
 * Muestra un modal de confirmación personalizado
 * @param {string} mensaje
 * @returns {Promise<boolean>}
 */
window.Confirmar = (mensaje) => {
  return new Promise((resolve) => {
    const overlay = document.createElement("div");
    overlay.className = "modal-overlay";

    overlay.innerHTML = `
            <div class="modal-confirmacion">
                <h3>Confirmación</h3>
                <p>${mensaje}</p>
                <div class="modal-botones">
                    <button class="btn-confirm btn-cancelar" id="confirm-cancel">Cancelar</button>
                    <button class="btn-confirm btn-aceptar" id="confirm-ok">Aceptar</button>
                </div>
            </div>
        `;

    document.body.appendChild(overlay);

    const cerrar = (valor) => {
      overlay.style.opacity = "0";
      setTimeout(() => {
        overlay.remove();
        resolve(valor);
      }, 300);
    };

    document.getElementById("confirm-ok").onclick = () => cerrar(true);
    document.getElementById("confirm-cancel").onclick = () => cerrar(false);
  });
};

/**
 * Muestra un modal para solicitar un dato (reemplaza al prompt del navegador)
 * @param {string} titulo
 * @param {string} mensaje
 * @returns {Promise<string|null>}
 */
window.SolicitarDato = (titulo, mensaje) => {
  return new Promise((resolve) => {
    const overlay = document.createElement("div");
    overlay.className = "modal-overlay";

    overlay.innerHTML = `
            <div class="modal-confirmacion modal-input">
                <h3>${titulo}</h3>
                <p>${mensaje}</p>
                <input type="text" id="modal-prompt-input" class="modal-input-field" placeholder="Escribe aquí...">
                <div class="modal-botones">
                    <button class="btn-confirm btn-cancelar" id="prompt-cancel">Cancelar</button>
                    <button class="btn-confirm btn-aceptar" id="prompt-ok">Aceptar</button>
                </div>
            </div>
        `;

    document.body.appendChild(overlay);
    const input = document.getElementById("modal-prompt-input");
    input.focus();

    const cerrar = (valor) => {
      overlay.style.opacity = "0";
      setTimeout(() => {
        overlay.remove();
        resolve(valor);
      }, 300);
    };

    document.getElementById("prompt-ok").onclick = () => {
      const val = input.value.trim();
      cerrar(val || null);
    };
    document.getElementById("prompt-cancel").onclick = () => cerrar(null);
    
    // Permitir Enter
    input.onkeyup = (e) => {
      if (e.key === "Enter") document.getElementById("prompt-ok").click();
    };
  });
};
