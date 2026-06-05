<section class="modal-usuario" id="contenedor-modal-usuario">
  <div class="contenedor-modal" role="dialog" aria-modal="true">
    <h3 id="titulo-modal-id" class="titulo-modal">Nuevo Usuario</h3>

    <form id="form-nuevo-editar-usuario" class="formulario-modal" novalidate>
      <input type="hidden" id="id-usuario" name="id-usuario" />

      <div class="label-input-modal">
        <label for="nombre" class="nombre">Nombre</label>
        <input
          type="text"
          id="nombre"
          name="nombre"
          placeholder="Nombre completo"
          class="input-modal"
          required />
      </div>

      <div class="label-input-modal">
        <label for="usuario" class="usuario">Usuario</label>
        <input
          type="text"
          name="usuario"
          id="usuario"
          placeholder="Usuario"
          class="input-modal"
          required />
      </div>

      <div class="label-input-modal">
        <label for="gmail" class="gmail">Gmail</label>
        <input
          type="email"
          name="gmail"
          id="gmail"
          placeholder="correo@gmail.com"
          class="input-modal"
          required />
      </div>

      <div class="label-input-modal">
        <label for="telefono" class="telefono">Telefono</label>
        <input
          type="tel"
          name="telefono"
          id="telefono"
          placeholder="987654321"
          class="input-modal"
          required />
      </div>

      <div class="label-input-modal">
        <label for="dni" class="dni">DNI</label>
        <input
          type="text"
          name="dni"
          id="dni"
          placeholder="12345678"
          class="input-modal"
          required />
      </div>

      <div class="label-input-modal">
        <label for="rol" class="rol">Rol</label>
        <select
          name="rol"
          id="rol"
          class="input-modal seleccionar-rol"
          required>
          <option value="">Seleccione un rol</option>
          <option value="administrador">Administrador</option>
          <option value="recepcionista">Recepcionista</option>
          <option value="recepcionista">Recepcionista</option>
        </select>
      </div>

      <div class="label-input-modal">
        <label for="password" class="password">Contrasena</label>
        <input
          type="password"
          name="password"
          id="password"
          placeholder="Contrasena"
          class="input-modal" />
      </div>

      <div class="label-input-modal">
        <label for="fecha_nacimiento" class="fecha_nacimiento">Fecha de Nacimiento</label>
        <input
          type="date"
          name="fecha_nacimiento"
          id="fecha_nacimiento"
          class="input-modal"
          required>
      </div>

      <div class="div-mensaje-exito-error" id="error-exito-modal-usuario"></div>

      <button type="button" id="btn-cancelar-usuario" class="btn-cancelar btn">
        Cancelar
      </button>

      <button class="btn-guardar btn" type="submit">Guardar Usuario</button>
    </form>
  </div>
</section>
