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

$evento_data = [];
$mensaje_feedback = '';
$tipo_mensaje = '';

$id_evento = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_evento <= 0) {
    header("Location: eventos.php"); // Redirigir si no hay ID de evento válido
    exit();
}

// Obtener datos del evento
$query_evento = "
    SELECT
        e.*,
        c.nombre_oficial AS nombre_club,
        c.ciudad AS ciudad_club,
        c.direccion_cancha AS direccion_cancha_club,
        c.sitio_web AS sitio_web_club,
        c.contacto_email AS contacto_email_club,
        c.telefono_contacto AS telefono_contacto_club,
        c.color_primario AS color_primario_club,
        c.color_secundario AS color_secundario_club,
        u.nombre_usuario AS nombre_usuario_club,
        u.foto_perfil_url AS foto_perfil_club_usuario,
        u.biografia AS biografia_club_usuario
    FROM eventos e
    JOIN clubes c ON e.id_club = c.id_club
    JOIN usuarios u ON c.id_usuario = u.id_usuario
    WHERE e.id_evento = ?
";
$stmt_evento = $conn->prepare($query_evento);
$stmt_evento->bind_param("i", $id_evento);
$stmt_evento->execute();
$resultado_evento = $stmt_evento->get_result();

if ($resultado_evento->num_rows === 1) {
    $evento_data = $resultado_evento->fetch_assoc();
} else {
    $mensaje_feedback = "Evento no encontrado o ID inválido.";
    $tipo_mensaje = "danger";
    // Si el evento no se encuentra, podrías redirigir o mostrar un mensaje de error
}
$stmt_evento->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($evento_data['nombre_evento']) ? htmlspecialchars($evento_data['nombre_evento']) : 'Evento no encontrado'; ?> - VoleyConnect</title>
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
                        <a class="nav-link active" aria-current="page" href="eventos.php">Eventos</a>
                    </li>
                     <li class="nav-item">
                        <a class="nav-link" href="mensajes.php">Mensajes</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <?php echo htmlspecialchars($nombre_usuario_logueado); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                            <li><a class="dropdown-item" href="perfil.php">Ver Perfil</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php">Cerrar Sesión</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-5 pt-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <?php if (!empty($mensaje_feedback)): ?>
                    <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($mensaje_feedback); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($evento_data)): ?>
                    <div class="card mb-4">
                        <?php if (!empty($evento_data['url_imagen_evento'])): ?>
                            <img src="<?php echo htmlspecialchars($evento_data['url_imagen_evento']); ?>" class="card-img-top event-detail-img" alt="Imagen del Evento">
                        <?php endif; ?>
                        <div class="card-body">
                            <h1 class="card-title text-center"><?php echo htmlspecialchars($evento_data['nombre_evento']); ?></h1>
                            <p class="text-center text-muted"><span class="badge bg-info"><?php echo htmlspecialchars(ucfirst($evento_data['tipo_evento'])); ?></span></p>
                            <hr>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <p class="mb-1"><strong><i class="fas fa-calendar-alt me-2"></i>Fecha y Hora de Inicio:</strong></p>
                                    <p class="ms-4"><?php echo date('d/m/Y H:i', strtotime($evento_data['fecha_hora_inicio'])); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <?php if (!empty($evento_data['fecha_hora_fin'])): ?>
                                        <p class="mb-1"><strong><i class="fas fa-hourglass-end me-2"></i>Fecha y Hora de Fin:</strong></p>
                                        <p class="ms-4"><?php echo date('d/m/Y H:i', strtotime($evento_data['fecha_hora_fin'])); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <p class="mb-1"><strong><i class="fas fa-map-marker-alt me-2"></i>Ubicación:</strong></p>
                            <p class="ms-4"><?php echo nl2br(htmlspecialchars($evento_data['ubicacion'])); ?></p>

                            <?php if (!empty($evento_data['descripcion'])): ?>
                                <p class="mb-1"><strong><i class="fas fa-align-left me-2"></i>Descripción:</strong></p>
                                <p class="ms-4"><?php echo nl2br(htmlspecialchars($evento_data['descripcion'])); ?></p>
                            <?php endif; ?>

                            <hr>

                            <h5 class="mt-4 mb-3 text-center">Organizado por:</h5>
                            <div class="text-center">
                                <img src="<?php echo htmlspecialchars($evento_data['foto_perfil_club_usuario'] ?: 'img/default-avatar.png'); ?>" alt="Logo del Club" class="profile-pic mb-2" style="width: 100px; height: 100px;">
                                <h4><?php echo htmlspecialchars($evento_data['nombre_club']); ?></h4>
                                <p class="text-muted">@<?php echo htmlspecialchars($evento_data['nombre_usuario_club']); ?></p>
                                <p class="mb-1"><i class="fas fa-city me-2"></i><?php echo htmlspecialchars($evento_data['ciudad_club']); ?></p>
                                <p class="mb-1"><i class="fas fa-map-marked-alt me-2"></i><?php echo htmlspecialchars($evento_data['direccion_cancha_club']); ?></p>
                                <?php if (!empty($evento_data['sitio_web_club'])): ?>
                                    <p class="mb-1"><i class="fas fa-globe me-2"></i><a href="<?php echo htmlspecialchars($evento_data['sitio_web_club']); ?>" target="_blank"><?php echo htmlspecialchars($evento_data['sitio_web_club']); ?></a></p>
                                <?php endif; ?>
                                <?php if (!empty($evento_data['contacto_email_club'])): ?>
                                    <p class="mb-1"><i class="fas fa-envelope me-2"></i><a href="mailto:<?php echo htmlspecialchars($evento_data['contacto_email_club']); ?>"><?php echo htmlspecialchars($evento_data['contacto_email_club']); ?></a></p>
                                <?php endif; ?>
                                <?php if (!empty($evento_data['telefono_contacto_club'])): ?>
                                    <p class="mb-1"><i class="fas fa-phone me-2"></i><?php echo htmlspecialchars($evento_data['telefono_contacto_club']); ?></p>
                                <?php endif; ?>
                                <a href="ver_club.php?id=<?php echo htmlspecialchars($evento_data['id_club']); ?>" class="btn btn-outline-info mt-3"><i class="fas fa-building me-1"></i>Ver Perfil Completo del Club</a>
                            </div>

                            <div class="text-center mt-4">
                                <a href="eventos.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i>Volver a Eventos</a>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <h1 class="text-center my-5">Evento no encontrado.</h1>
                    <p class="text-center">Es posible que el evento haya sido cancelado o el enlace sea incorrecto.</p>
                    <div class="text-center">
                        <a href="eventos.php" class="btn btn-primary">Volver a la lista de eventos</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="js/bootstrap/bootstrap.bundle.min.js"></script>
    <script src="js/scripts.js"></script>
</body>
</html>