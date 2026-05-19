<section class="layout">
  <!--parte lateral-->
  <div class="parte-lateral">
    <div class="perfil-cargo"><?= $_SESSION['rol'] ?? 'Usuario' ?></div>
    <div class="perfil-rol"><?= $_SESSION['usuario'] ?? '' ?></div>
  </div>

  <!--Formularios --->
  <div class="contenido">

    <div id="alerta"></div>
    
    <form class="form" id="formPerfilPersonal">
      <h2>👤 Información Personal</h2>
      <div>
        <div class="form-campo">
          <label for="nombre_completo" class="form-label"
            >NOMBRE COMPLETO</label
          >
          <input
            type="text"
            id="nombre_completo"
            name="nombre_completo"
            class="form-input"
            value="<?= htmlspecialchars($perfil['nombre_completo'] ?? '') ?>"
          />
        </div>

        <div class="form-campo">
          <label for="usuario" class="form-label">USUARIO (LOGIN)</label>
          <input type="text" id="usuario" name="usuario" class="form-input" value="<?= htmlspecialchars($perfil['nombre_usuario'] ?? '') ?>">
        </div>

        <div class="form-campo">
          <label for="rol" class="form-label">CARGO (ROL)</label>
          <input type="text" id="rol" name="rol" class="form-input" readonly value="<?= htmlspecialchars($perfil['rol'] ?? '') ?>">
        </div>

        <div class="form-campo">
          <label for="email" class="form-label">EMAIL</label>
          <input type="email" id="email" name="email" class="form-input" value="<?= htmlspecialchars($perfil['correo'] ?? '') ?>">
        </div>

        <div class="form-campo">
          <label for="telefono" class="form-label">TELEFONO</label>
          <input type="tel" id="telefono" name="telefono" class="form-input" value = "<?= htmlspecialchars($perfil['telefono'] ?? '') ?>" />
        </div>
      </div>

      <button type="button" class="form-buttom" id ="btnGuardarPerfil">Guardar Perfil</button>
    </form>

    <form action="#" class="form" id="formCambiarClave">
      <h2>🔒 Cambiar Contraseña</h2>
      <div>
        <div class="form-campo full">
          <label for="clave_actual" class="form-label">CONTRASEÑA ACTUAL</label>
          <input
            type="password"
            id="clave_actual"
            name="clave_actual"
            class="form-input"
          />
        </div>

        <div class="form-campo">
          <label for="clave_nueva" class="form-label">NUEVA CONTRASEÑA</label>
          <input
            type="password"
            id="clave_nueva"
            name="clave_nueva"
            class="form-input"
          />
        </div>

        <div class="form-campo">
          <label for="confirmar_clave" class="form-label"
            >CONFIRMAR CONTRASEÑA</label
          >
          <input
            type="password"
            id="confirmar_clave"
            name="confirmar_clave"
            class="form-input"
          />
        </div>
      </div>

      <button type="button" class="form-buttom" id="btnCambiarClave">Actualizar Contraseña</button>
    </form>
  </div>
</section>
