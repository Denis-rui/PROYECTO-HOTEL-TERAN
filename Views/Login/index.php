<!doctype html>
<html lang="es">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="icon" href="<?= BASE_URL ?>public/assets/img/image.jpeg" />
    <title>Hotel Teran - Login</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>style.css" />
    <link rel="stylesheet" href="<?= BASE_URL ?>public/css/Login.css" />
</head>

<body>
    <div id="app">
        <section class="login">
            <div class="contenido-login">
                <div class="login-logo">
                    <img src="<?= BASE_URL ?>public/assets/img/image.jpeg" alt=" Teran Hotel Logo" />
                    <h1>Teran Hotel</h1>
                </div>
                <div class="formulario">
                    <form action="<?= BASE_URL ?>?url=Login/entrar" class="formulario-login" method="post">
                        <h2>Iniciar Sesión</h2>
                        <div class="tipousuario label-input">
                            <label for="tipousuario">Tipo de Usuario</label>
                            <select id="tipousuario" name="tipousuario" class="input" required>
                                <option value="" disabled selected>Seleccionar</option>
                                <option value="administrador">Administrador</option>
                                <option value="recepcionista">Recepcionista</option>
                            </select>
                        </div>
                        <div class="usuario label-input">
                            <label for="usuario">Usuario</label>
                            <input type="text" id="usuario" name="usuario" class="input" required />
                        </div>
                        <div class="contrasena label-input">
                            <label for="contrasena">Contraseña</label>
                            <input type="password" id="contrasena" name="contrasena" class="input" required />
                        </div>
                        <div id="error" class=""></div>
                        <?php if (isset($_GET["error"])): ?>
                            <div style="color:red; margin-bottom: 15px; font-weight: bold; text-align: center;">Credenciales incorrectas</div>
                        <?php endif; ?>
                        <button class="boton" id="btnLogin" type="submit">
                            Iniciar Sesión
                        </button>
                    </form>
                </div>
            </div>
        </section>
    </div>
</body>
</html>