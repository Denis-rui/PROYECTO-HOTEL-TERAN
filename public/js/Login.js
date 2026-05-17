document.addEventListener("click", (e) => {
  if (e.target.id === "btnLogin") {
    const tipoUsuario = document.getElementById("tipousuario").value;
    const usuario = document.getElementById("usuario").value.trim();
    const contrasena = document.getElementById("contrasena").value;

    if (tipoUsuario === "") {
      document.getElementById("error").textContent =
        "Elija un tipo de usuario.";
      document.getElementById("error").classList.add("error-login");
      return;
    }
    if (usuario === "") {
      document.getElementById("error").textContent =
        "Ingrese su nombre de usuario.";
      document.getElementById("error").classList.add("error-login");
      return;
    }
    if (contrasena === "") {
      document.getElementById("error").textContent = "Ingrese su contraseña.";
      document.getElementById("error").classList.add("error-login");
      return;
    }

    // if (usuario === "admin" && contrasena === "admin123") {
    //   window.cambiarPagina?.("nav", "./public/views/Nav.php");
    //   window.cambiarPagina?.("app", "./public/views/Dashboard.php");
    // }
    // Simulación de autenticación
  }
});
