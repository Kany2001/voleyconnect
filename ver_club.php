<?php
session_start();
include 'conexion.php';

// Redirigir si el usuario no está logueado
if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit();
}

$id_usuario_logueado = $_SESSION['id_usuario'];
$nombre_usuario_logueado = $_SESSION['nombre_usuario'];
$tipo_usuario_logueado = $_SESSION['tipo_usuario'];

$club_data = [];
$perfil_usuario_club = [];
$mensaje_feedback = '';
$tipo_mensaje = '';

$id_club = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_club <= 0) {
    header("Location: clubes.php"); // Redirigir si no hay ID de club válido
    exit();
}

// Obtener datos del club
$query_club = "
    SELECT
        c.*,
        u.nombre_usuario,
        u.email AS email_usuario,
        u.foto_perfil_url,
        u.biografia
    FROM clubes c
    JOIN usuarios u ON c.id_usuario = u.id_usuario
    WHERE c.id_club = ? AND u.tipo_usuario = 'club'
";
$stmt_club = $conn->prepare($query_club);
$stmt_club->bind_param("i", $id_club);
$stmt_club->execute();
$resultado_club = $stmt_club->get_result();

if ($resultado_club->num_rows === 1) {
    $club_data = $resultado_club->fetch_assoc();
    $perfil_usuario_club = [
        'nombre_usuario' => $club_data['nombre_usuario'],
        'email' => $club_data['email_usuario'],
        'foto_perfil_url' => $club_data['foto_perfil_url'],
        'biografia' => $club_data['biografia'],
        'tipo_usuario' => 'club' // Aseguramos el tipo para visualización
    ];
} else {
    $mensaje_feedback = "Club no encontrado o ID inválido.";
    $tipo_mensaje = "danger";
    // Si el club no se encuentra, podrías redirigir o mostrar un mensaje de error
}
$stmt_club->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($club_data['nombre_oficial']) ? htmlspecialchars($club_data['nombre_oficial']) : 'Club no encontrado'; ?> - VoleyConnect</title>
    <link rel="stylesheet" href="css/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <?php include 'navbar.php'; // Incluir la barra de navegación estándar ?>

    <div class="container mt-5 pt-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <?php if (!empty($mensaje_feedback)): ?>
                    <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($mensaje_feedback); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($club_data)): ?>
                    <div class="card mb-4">
                        <div class="card-body text-center">
                            <img src="<?php echo htmlspecialchars($perfil_usuario_club['foto_perfil_url'] ?: 'img/default-avatar.png'); ?>" alt="Logo del Club" class="profile-pic mb-3" style="width: 180px; height: 180px;">
                            <h1 class="card-title"><?php echo htmlspecialchars($club_data['nombre_oficial']); ?></h1>
                            <p class="text-muted">@<?php echo htmlspecialchars($perfil_usuario_club['nombre_usuario']); ?></p>

                            <hr>

                            <div class="text-start mb-4">
                                <h5>Información del Club</h5>
                                <p><strong>Ciudad:</strong> <?php echo htmlspecialchars($club_data['ciudad']); ?></p>
                                <p><strong>Dirección de la Cancha:</strong> <?php echo htmlspecialchars($club_data['direccion_cancha']); ?></p>
                                <?php if (!empty($club_data['sitio_web'])): ?>
                                    <p><strong>Sitio Web:</strong> <a href="<?php echo htmlspecialchars($club_data['sitio_web']); ?>" target="_blank"><?php echo htmlspecialchars($club_data['sitio_web']); ?></a></p>
                                <?php endif; ?>
                                <?php if (!empty($club_data['contacto_email'])): ?>
                                    <p><strong>Email de Contacto:</strong> <a href="mailto:<?php echo htmlspecialchars($club_data['contacto_email']); ?>"><?php echo htmlspecialchars($club_data['contacto_email']); ?></a></p>
                                <?php endif; ?>
                                <?php if (!empty($club_data['telefono_contacto'])): ?>
                                    <p><strong>Teléfono:</strong> <?php echo htmlspecialchars($club_data['telefono_contacto']); ?></p>
                                <?php endif; ?>
                                <p><strong>Biografía del Club:</strong><br><?php echo nl2br(htmlspecialchars($perfil_usuario_club['biografia'] ?: 'Este club aún no ha añadido una biografía.')); ?></p>
                                <p><strong>Colores del Club:</strong>
                                    <span style="display: inline-block; width: 20px; height: 20px; background-color: <?php echo htmlspecialchars($club_data['color_primario']); ?>; border: 1px solid #ccc; vertical-align: middle;"></span>
                                    <span style="display: inline-block; width: 20px; height: 20px; background-color: <?php echo htmlspecialchars($club_data['color_secundario']); ?>; border: 1px solid #ccc; vertical-align: middle;"></span>
                                </p>
                            </div>

                            <a href="clubes.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i>Volver a la lista de clubes</a>
                        </div>
                    </div>
                <?php else: ?>
                    <h1 class="text-center my-5">Club no encontrado.</h1>
                    <p class="text-center">Es posible que el club haya sido eliminado o el enlace sea incorrecto.</p>
                    <div class="text-center">
                        <a href="clubes.php" class="btn btn-primary">Volver a la lista de clubes</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="js/bootstrap/bootstrap.bundle.min.js"></script>
    <script src="js/scripts.js"></script>
</body>
</html>