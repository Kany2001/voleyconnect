<?php
session_start(); // Inicia la sesión
include 'conexion.php'; // Incluye la conexión a la base de datos

// Si el usuario ya está logueado, redirigir al dashboard
if (isset($_SESSION['id_usuario'])) {
    header("Location: dashboard.php");
    exit();
}

$mensaje_feedback = ''; // Variable para almacenar mensajes
$tipo_mensaje = '';

if (isset($_POST['submit_login'])) {
    $identificador = $conn->real_escape_string(trim($_POST['identificador']));
    $contrasena = $_POST['contrasena'];

    if (empty($identificador) || empty($contrasena)) {
        $mensaje_feedback = "Por favor, introduce tu nombre de usuario/email y contraseña.";
        $tipo_mensaje = "danger";
    } else {
        // Modificación: Asegúrate de seleccionar 'foto_perfil_url'
        $stmt = $conn->prepare("SELECT id_usuario, nombre_usuario, email, contrasena, tipo_usuario, foto_perfil_url FROM usuarios WHERE nombre_usuario = ? OR email = ?");
        $stmt->bind_param("ss", $identificador, $identificador);
        $stmt->execute();
        $resultado = $stmt->get_result();

        if ($resultado->num_rows === 1) {
            $usuario = $resultado->fetch_assoc();
            if (password_verify($contrasena, $usuario['contrasena'])) {
                // Contraseña correcta: Iniciar sesión
                $_SESSION['id_usuario'] = $usuario['id_usuario'];
                $_SESSION['nombre_usuario'] = $usuario['nombre_usuario'];
                $_SESSION['tipo_usuario'] = $usuario['tipo_usuario'];
                
                // --- CAMBIO CLAVE AÑADIDO ---
                // Guardar la URL de la foto de perfil en la sesión para usarla en la navbar
                $_SESSION['foto_perfil_url'] = $usuario['foto_perfil_url'];

                // Redirigir al dashboard después de iniciar sesión
                header("Location: dashboard.php");
                exit();
            } else {
                $mensaje_feedback = "Contraseña incorrecta.";
                $tipo_mensaje = "danger";
            }
        } else {
            $mensaje_feedback = "Nombre de usuario o correo electrónico no encontrado.";
            $tipo_mensaje = "danger";
        }

        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - VoleyConnect</title>
    <link rel="stylesheet" href="css/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="d-flex align-items-center justify-content-center" style="min-height: 100vh;">
    <div class="container my-5" style="max-width: 450px;">
        <div class="card p-4">
            <h1 class="card-title text-center mb-4">Iniciar Sesión</h1>
            <?php if (!empty($mensaje_feedback)): ?>
                <div class="alert alert-<?php echo $tipo_mensaje; ?>" role="alert">
                    <?php echo htmlspecialchars($mensaje_feedback); ?>
                </div>
            <?php endif; ?>
            <form action="login.php" method="POST">
                <div class="mb-3">
                    <label for="identificador" class="form-label">Nombre de Usuario o Correo Electrónico:</label>
                    <input type="text" class="form-control" id="identificador" name="identificador" required>
                </div>
                <div class="mb-3">
                    <label for="contrasena" class="form-label">Contraseña:</label>
                    <input type="password" class="form-control" id="contrasena" name="contrasena" required>
                </div>
                <button type="submit" name="submit_login" class="btn btn-primary w-100">Iniciar Sesión</button>
            </form>
            <p class="mt-3 text-center">¿No tienes una cuenta? <a href="registro.php">Regístrate aquí</a></p>
        </div>
    </div>
    
    <script src="js/bootstrap/bootstrap.bundle.min.js"></script>
</body>
</html>