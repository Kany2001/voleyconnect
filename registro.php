<?php
session_start();
include 'conexion.php';

$mensaje_feedback = '';
$tipo_mensaje = '';

if (isset($_POST['register'])) {
    $nombre_usuario = $conn->real_escape_string(trim($_POST['nombre_usuario']));
    $email = $conn->real_escape_string(trim($_POST['email']));
    $password = $_POST['password']; // Se hashificará
    $tipo_usuario = $conn->real_escape_string($_POST['tipo_usuario']);

    // Validaciones básicas
    if (empty($nombre_usuario) || empty($email) || empty($password) || empty($tipo_usuario)) {
        $mensaje_feedback = "Todos los campos son obligatorios.";
        $tipo_mensaje = "danger";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensaje_feedback = "El formato del email no es válido.";
        $tipo_mensaje = "danger";
    } elseif (strlen($password) < 6) {
        $mensaje_feedback = "La contraseña debe tener al menos 6 caracteres.";
        $tipo_mensaje = "danger";
    } else {
        // Verificar si el nombre de usuario o email ya existen
        $stmt_check = $conn->prepare("SELECT id_usuario FROM usuarios WHERE nombre_usuario = ? OR email = ?");
        $stmt_check->bind_param("ss", $nombre_usuario, $email);
        $stmt_check->execute();
        $resultado_check = $stmt_check->get_result();

        if ($resultado_check->num_rows > 0) {
            $mensaje_feedback = "El nombre de usuario o el email ya están registrados.";
            $tipo_mensaje = "danger";
        } else {
            // Hashear la contraseña
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Insertar el nuevo usuario
            // Corregido: password_hash a contrasena para coincidir con el esquema de la BD
            $stmt_insert = $conn->prepare("INSERT INTO usuarios (nombre_usuario, email, contrasena, tipo_usuario) VALUES (?, ?, ?, ?)");
            $stmt_insert->bind_param("ssss", $nombre_usuario, $email, $hashed_password, $tipo_usuario);

            if ($stmt_insert->execute()) {
                $mensaje_feedback = "¡Registro exitoso! Ya puedes iniciar sesión.";
                $tipo_mensaje = "success";
                // Redirigir al login después de un registro exitoso
                header("Location: login.php?msg=" . urlencode($mensaje_feedback) . "&type=" . $tipo_mensaje);
                exit();
            } else {
                $mensaje_feedback = "Error al registrar el usuario: " . $stmt_insert->error;
                $tipo_mensaje = "danger";
            }
            $stmt_insert->close();
        }
        $stmt_check->close();
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - VoleyConnect</title>
    <link rel="stylesheet" href="css/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">VoleyConnect</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">Inicio</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="perfil.php">Perfil</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="clubes.php">Clubes</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="eventos.php">Eventos</a>
                    </li>
                     <li class="nav-item">
                        <a class="nav-link" href="mensajes.php">Mensajes</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">Iniciar Sesión</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" aria-current="page" href="register.php">Registro</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-5 pt-4">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card mt-5">
                    <div class="card-header text-center">
                        <h2>Registro de Usuario</h2>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($mensaje_feedback)): ?>
                            <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
                                <?php echo htmlspecialchars($mensaje_feedback); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        <form action="register.php" method="POST">
                            <div class="mb-3">
                                <label for="nombre_usuario" class="form-label">Nombre de Usuario</label>
                                <input type="text" class="form-control" id="nombre_usuario" name="nombre_usuario" required>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Contraseña</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <div class="mb-3">
                                <label for="tipo_usuario" class="form-label">Tipo de Usuario</label>
                                <select class="form-select" id="tipo_usuario" name="tipo_usuario" required>
                                    <option value="">Selecciona tu tipo</option>
                                    <option value="fanatico">Fanático</option>
                                    <option value="jugador">Jugador</option>
                                    <option value="entrenador">Entrenador</option>
                                    <option value="club">Club</option>
                                </select>
                            </div>
                            <div class="d-grid gap-2">
                                <button type="submit" name="register" class="btn btn-primary">Registrarse</button>
                            </div>
                        </form>
                        <div class="mt-3 text-center">
                            <p>¿Ya tienes una cuenta? <a href="login.php">Inicia Sesión aquí</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="js/bootstrap/bootstrap.bundle.min.js"></script>
    <script src="js/scripts.js"></script>
</body>
</html>