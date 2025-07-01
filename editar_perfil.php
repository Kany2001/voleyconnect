<?php
session_start();
include 'conexion.php';

// Redirigir si el usuario no está logueado
if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit();
}

$id_usuario_logueado = $_SESSION['id_usuario'];
$mensaje_feedback = '';
$tipo_mensaje = '';

// Obtener datos actuales del usuario
$stmt = $conn->prepare("SELECT nombre_usuario, email, foto_perfil_url FROM usuarios WHERE id_usuario = ?");
$stmt->bind_param("i", $id_usuario_logueado);
$stmt->execute();
$resultado = $stmt->get_result();
$usuario = $resultado->fetch_assoc();
$stmt->close();

if (!$usuario) {
    // Si por alguna razón no se encuentra el usuario (lo cual no debería pasar si está logueado)
    header("Location: dashboard.php");
    exit();
}

// Lógica para procesar el formulario de edición
if (isset($_POST['submit_edicion'])) {
    $nuevo_nombre_usuario = $conn->real_escape_string(trim($_POST['nombre_usuario']));
    $nuevo_email = $conn->real_escape_string(trim($_POST['email']));
    $nueva_contrasena = $_POST['contrasena'];
    $confirmar_contrasena = $_POST['confirmar_contrasena'];
    $foto_perfil_url = $usuario['foto_perfil_url']; // Mantener la actual por defecto

    // Validaciones básicas
    if (empty($nuevo_nombre_usuario) || empty($nuevo_email)) {
        $mensaje_feedback = "El nombre de usuario y el email no pueden estar vacíos.";
        $tipo_mensaje = "danger";
    } elseif ($nueva_contrasena !== $confirmar_contrasena) {
        $mensaje_feedback = "Las contraseñas no coinciden.";
        $tipo_mensaje = "danger";
    } else {
        // --- Manejo de la subida de imagen de perfil ---
        if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] == UPLOAD_ERR_OK) {
            $directorio_destino = 'img/perfiles/'; // Ruta para imágenes de perfil
            
            // Crear el directorio si no existe
            if (!is_dir($directorio_destino)) {
                mkdir($directorio_destino, 0777, true);
            }
            
            $nombre_archivo = uniqid() . '_' . basename($_FILES['foto_perfil']['name']);
            $ruta_completa = $directorio_destino . $nombre_archivo;
            
            if (move_uploaded_file($_FILES['foto_perfil']['tmp_name'], $ruta_completa)) {
                $foto_perfil_url = $ruta_completa;
                // Opcional: Eliminar la foto de perfil antigua si no es la por defecto
                if ($usuario['foto_perfil_url'] && $usuario['foto_perfil_url'] !== 'img/default-avatar.png' && file_exists($usuario['foto_perfil_url'])) {
                    unlink($usuario['foto_perfil_url']);
                }
            } else {
                $mensaje_feedback = "Error al subir la nueva foto de perfil.";
                $tipo_mensaje = "warning"; // Advertencia en lugar de error fatal
            }
        }

        // Preparar la consulta de actualización
        $query_update = "UPDATE usuarios SET nombre_usuario = ?, email = ?, foto_perfil_url = ?";
        $params = [$nuevo_nombre_usuario, $nuevo_email, $foto_perfil_url];
        $types = "sss";

        if (!empty($nueva_contrasena)) {
            $hash_contrasena = password_hash($nueva_contrasena, PASSWORD_DEFAULT);
            $query_update .= ", contrasena = ?";
            $params[] = $hash_contrasena;
            $types .= "s";
        }

        $query_update .= " WHERE id_usuario = ?";
        $params[] = $id_usuario_logueado;
        $types .= "i";

        $stmt_update = $conn->prepare($query_update);
        if ($stmt_update) {
            // Usar call_user_func_array para bind_param dinámicamente
            $bind_params = array_merge([$types], $params);
            call_user_func_array([$stmt_update, 'bind_param'], ref_values($bind_params));

            if ($stmt_update->execute()) {
                $mensaje_feedback = "¡Perfil actualizado con éxito!";
                $tipo_mensaje = "success";
                // Actualizar la sesión si el nombre de usuario o foto cambió
                $_SESSION['nombre_usuario'] = $nuevo_nombre_usuario;
                $_SESSION['foto_perfil_url'] = $foto_perfil_url;
                // Recargar datos del usuario para mostrar los cambios en el formulario
                $stmt_reload = $conn->prepare("SELECT nombre_usuario, email, foto_perfil_url FROM usuarios WHERE id_usuario = ?");
                $stmt_reload->bind_param("i", $id_usuario_logueado);
                $stmt_reload->execute();
                $usuario = $stmt_reload->get_result()->fetch_assoc();
                $stmt_reload->close();
            } else {
                $mensaje_feedback = "Error al actualizar el perfil: " . $stmt_update->error;
                $tipo_mensaje = "danger";
            }
            $stmt_update->close();
        } else {
            $mensaje_feedback = "Error interno al preparar la actualización.";
            $tipo_mensaje = "danger";
        }
    }
}

// Función auxiliar para bind_param
function ref_values($arr){
    if (strnatcmp(phpversion(),'5.3') >= 0) //Reference is required for PHP 5.3+
    {
        $refs = array();
        foreach($arr as $key => $value)
            $refs[$key] = &$arr[$key];
        return $refs;
    }
    return $arr;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Perfil - VoleyConnect</title>
    <link rel="stylesheet" href="css/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <h2 class="card-title text-center mb-4">Editar Perfil</h2>
                        
                        <?php if (!empty($mensaje_feedback)): ?>
                            <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
                                <?php echo htmlspecialchars($mensaje_feedback); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <form action="editar_perfil.php" method="POST" enctype="multipart/form-data">
                            <div class="text-center mb-4">
                                <img src="<?php echo htmlspecialchars($usuario['foto_perfil_url'] ?? 'img/default-avatar.png'); ?>" alt="Foto de Perfil Actual" class="profile-pic-lg rounded-circle mb-3">
                                <label for="foto_perfil" class="form-label d-block">Cambiar Foto de Perfil</label>
                                <input class="form-control mx-auto" style="max-width: 300px;" type="file" id="foto_perfil" name="foto_perfil" accept="image/*">
                            </div>

                            <div class="mb-3">
                                <label for="nombre_usuario" class="form-label">Nombre de Usuario:</label>
                                <input type="text" class="form-control" id="nombre_usuario" name="nombre_usuario" value="<?php echo htmlspecialchars($usuario['nombre_usuario']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Correo Electrónico:</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($usuario['email']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="contrasena" class="form-label">Nueva Contraseña (dejar vacío para no cambiar):</label>
                                <input type="password" class="form-control" id="contrasena" name="contrasena">
                            </div>
                            <div class="mb-3">
                                <label for="confirmar_contrasena" class="form-label">Confirmar Nueva Contraseña:</label>
                                <input type="password" class="form-control" id="confirmar_contrasena" name="confirmar_contrasena">
                            </div>
                            <button type="submit" name="submit_edicion" class="btn btn-primary w-100">Guardar Cambios</button>
                            <a href="perfil.php" class="btn btn-secondary w-100 mt-2">Cancelar</a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="js/bootstrap/bootstrap.bundle.min.js"></script>
    <script src="js/scripts.js"></script>
</body>
</html>