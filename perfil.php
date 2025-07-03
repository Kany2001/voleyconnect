<?php
session_start();
include 'conexion.php'; // Incluye la conexión a la base de datos

// Verificar si el usuario está autenticado
if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php?redirect_url=" . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

$id_usuario_logueado = $_SESSION['id_usuario'];
$tipo_usuario_logueado = $_SESSION['tipo_usuario'];
$es_mi_perfil = false;

// Determinar si se está viendo un perfil de usuario o de club
$id_perfil = isset($_GET['id']) ? intval($_GET['id']) : null;
$tipo_perfil = isset($_GET['tipo']) ? $_GET['tipo'] : 'usuario_normal'; // Por defecto, es un perfil de usuario

// Si no se proporciona un ID, redirigir al perfil del usuario logueado
if (!$id_perfil) {
    $id_perfil = $id_usuario_logueado;
    $tipo_perfil = $tipo_usuario_logueado;
    $es_mi_perfil = true;
}

$perfil_data = null;
$perfil_publicaciones = [];

if ($tipo_perfil == 'usuario_normal') {
    // Lógica para perfil de USUARIO
    $stmt = $conn->prepare("SELECT id_usuario, nombre_usuario, foto_perfil_url, biografia, fecha_registro, id_club FROM usuarios WHERE id_usuario = ?");
    $stmt->bind_param("i", $id_perfil);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows > 0) {
        $perfil_data = $resultado->fetch_assoc();
        
        // Obtener publicaciones de este usuario
        $query_pub_perfil = "
            SELECT p.*, u_pub.nombre_usuario AS autor_nombre_publicacion, u_pub.foto_perfil_url AS autor_foto_publicacion,
                   (SELECT COUNT(*) FROM likes l WHERE l.id_publicacion = p.id_publicacion) AS total_likes,
                   (SELECT COUNT(*) FROM comentarios com WHERE com.id_publicacion = p.id_publicacion) AS total_comentarios,
                   EXISTS(SELECT 1 FROM likes l WHERE l.id_publicacion = p.id_publicacion AND l.id_usuario = ?) AS usuario_dio_like
            FROM publicaciones p
            JOIN usuarios u_pub ON p.id_usuario = u_pub.id_usuario
            WHERE p.id_usuario = ? ORDER BY p.fecha_publicacion DESC";
        $stmt_publicaciones = $conn->prepare($query_pub_perfil);
        // Necesitamos pasar $id_usuario_logueado para 'usuario_dio_like' y $id_perfil para p.id_usuario
        $stmt_publicaciones->bind_param("ii", $id_usuario_logueado, $id_perfil);
        $stmt_publicaciones->execute();
        $resultado_publicaciones = $stmt_publicaciones->get_result();
        if ($resultado_publicaciones) {
            while ($fila = $resultado_publicaciones->fetch_assoc()) {
                $perfil_publicaciones[] = $fila;
            }
        }
    } else {
        $mensaje_error = "Perfil de usuario no encontrado.";
    }

} elseif ($tipo_perfil == 'club') {
    // Lógica para perfil de CLUB
    // Se añade c.id_usuario a la consulta para poder obtener las publicaciones del club correctamente
    $stmt = $conn->prepare("SELECT c.id_club, c.id_usuario, c.nombre_oficial, c.ciudad, c.logo_url, c.descripcion, c.membresia_abierta FROM clubes c WHERE c.id_club = ?");
    $stmt->bind_param("i", $id_perfil); // $id_perfil aquí es clubes.id_club
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows > 0) {
        $perfil_data = $resultado->fetch_assoc();

        // Obtener miembros del club (usando la nueva tabla)
        $stmt_miembros = $conn->prepare("SELECT u.nombre_usuario, u.foto_perfil_url, cm.es_jugador FROM clubes_miembros cm JOIN usuarios u ON cm.id_usuario = u.id_usuario WHERE cm.id_club = ? ORDER BY cm.fecha_union");
        $stmt_miembros->bind_param("i", $perfil_data['id_club']); // Usar el id_club del perfil_data
        $stmt_miembros->execute();
        $resultado_miembros = $stmt_miembros->get_result();
        $miembros = [];
        while ($fila = $resultado_miembros->fetch_assoc()) {
            $miembros[] = $fila;
        }
        $perfil_data['miembros'] = $miembros;
        
        // Obtener publicaciones del club usando el id_usuario del club
        if (isset($perfil_data['id_usuario'])) {
            $id_usuario_del_club = $perfil_data['id_usuario'];
            $query_pub_club_perfil = "
                SELECT p.*, u_pub.nombre_usuario AS autor_nombre_publicacion, u_pub.foto_perfil_url AS autor_foto_publicacion,
                       (SELECT COUNT(*) FROM likes l WHERE l.id_publicacion = p.id_publicacion) AS total_likes,
                       (SELECT COUNT(*) FROM comentarios com WHERE com.id_publicacion = p.id_publicacion) AS total_comentarios,
                       EXISTS(SELECT 1 FROM likes l WHERE l.id_publicacion = p.id_publicacion AND l.id_usuario = ?) AS usuario_dio_like
                FROM publicaciones p
                JOIN usuarios u_pub ON p.id_usuario = u_pub.id_usuario
                WHERE p.id_usuario = ? ORDER BY p.fecha_publicacion DESC";
            $stmt_publicaciones_club = $conn->prepare($query_pub_club_perfil);
            // Necesitamos pasar $id_usuario_logueado para 'usuario_dio_like' y $id_usuario_del_club para p.id_usuario
            $stmt_publicaciones_club->bind_param("ii", $id_usuario_logueado, $id_usuario_del_club);
            $stmt_publicaciones_club->execute();
            $resultado_publicaciones = $stmt_publicaciones_club->get_result();
            if ($resultado_publicaciones) {
                while ($fila = $resultado_publicaciones->fetch_assoc()) {
                    $perfil_publicaciones[] = $fila;
                }
            }
            $stmt_publicaciones_club->close();
        } else {
             // Si no hay id_usuario en perfil_data, no se pueden cargar publicaciones
            // $perfil_publicaciones ya está inicializado como array vacío.
        }

    } else {
        $mensaje_error = "Perfil de club no encontrado.";
    }
} else {
    $mensaje_error = "Tipo de perfil inválido.";
}


if (!$perfil_data) {
    // Mostrar un mensaje de error si el perfil no existe
    // Podrías redirigir a una página de error o al dashboard
    // header("Location: dashboard.php");
    // exit();
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $perfil_data ? htmlspecialchars($perfil_data['nombre_usuario'] ?? $perfil_data['nombre_oficial']) : 'Perfil no encontrado'; ?> - VoleyConnect</title>
    <link rel="stylesheet" href="css/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>

    <?php include 'navbar.php'; // Incluye la barra de navegación ?>

    <main class="container mt-5">
        <?php if (isset($mensaje_error)): ?>
            <div class="alert alert-danger text-center"><?php echo $mensaje_error; ?></div>
        <?php else: ?>
            <div class="profile-header bg-white p-4 rounded-3 shadow-sm mb-4">
                <div class="d-flex align-items-center mb-3">
                    <img src="<?php echo htmlspecialchars($perfil_data['foto_perfil_url'] ?? $perfil_data['logo_url'] ?? 'img/default-avatar.png'); ?>" 
                         alt="Foto de perfil" class="rounded-circle me-4" style="width: 150px; height: 150px; object-fit: cover; border: 4px solid var(--red);">
                    <div>
                        <h2 class="mb-1 fw-bold"><?php echo htmlspecialchars($perfil_data['nombre_usuario'] ?? $perfil_data['nombre_oficial']); ?></h2>
                        <p class="text-muted mb-2">
                            <?php if ($tipo_perfil == 'usuario_normal'): ?>
                                <i class="fas fa-user me-1"></i> Usuario
                                <?php if ($perfil_data['id_club']): ?>
                                    <span class="badge bg-secondary ms-2">Miembro del Club</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <i class="fas fa-volleyball-ball me-1"></i> Club de Voleibol
                                <span class="badge bg-primary ms-2">
                                    <?php echo htmlspecialchars($perfil_data['ciudad']); ?>
                                </span>
                            <?php endif; ?>
                        </p>
                        <p class="text-secondary"><?php echo htmlspecialchars($perfil_data['biografia'] ?? $perfil_data['descripcion'] ?? 'Sin biografía/descripción.'); ?></p>
                        
                        <?php if (!$es_mi_perfil): ?>
                            <?php if ($tipo_perfil == 'usuario_normal'): ?>
                                <?php
                                    // Comprobar si el usuario logueado ya sigue a este perfil
                                    $stmt_sigue = $conn->prepare("SELECT id_seguimiento FROM seguimientos WHERE id_seguidor = ? AND id_seguido = ?");
                                    $stmt_sigue->bind_param("ii", $id_usuario_logueado, $id_perfil);
                                    $stmt_sigue->execute();
                                    $sigue_resultado = $stmt_sigue->get_result();
                                    $ya_sigue = $sigue_resultado->num_rows > 0;
                                ?>
                                <button class="btn btn-sm mt-2 follow-button <?php echo $ya_sigue ? 'btn-danger' : 'btn-primary'; ?>" 
                                        data-id="<?php echo $id_perfil; ?>">
                                    <i class="fas fa-user-<?php echo $ya_sigue ? 'minus' : 'plus'; ?> me-1"></i> 
                                    <?php echo $ya_sigue ? 'Dejar de Seguir' : 'Seguir'; ?>
                                </button>
                            <?php elseif ($tipo_perfil == 'club' && $tipo_usuario_logueado == 'usuario_normal'): ?>
                                <?php
                                    // Comprobar si el usuario logueado ya es miembro de este club
                                    $stmt_miembro = $conn->prepare("SELECT id_miembro FROM clubes_miembros WHERE id_usuario = ? AND id_club = ?");
                                    $stmt_miembro->bind_param("ii", $id_usuario_logueado, $id_perfil);
                                    $stmt_miembro->execute();
                                    $es_miembro = $stmt_miembro->get_result()->num_rows > 0;
                                ?>
                                <?php if (!$es_miembro): ?>
                                    <?php if ($perfil_data['membresia_abierta']): ?>
                                        <button class="btn btn-success btn-sm mt-2 join-club-button" data-id="<?php echo $id_perfil; ?>">
                                            <i class="fas fa-users me-1"></i> Unirse al Club
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-warning btn-sm mt-2" disabled>
                                            <i class="fas fa-hourglass-half me-1"></i> Membresía Cerrada
                                        </button>
                                        <?php endif; ?>
                                <?php else: ?>
                                    <button class="btn btn-secondary btn-sm mt-2" disabled>
                                        <i class="fas fa-check me-1"></i> Ya eres miembro
                                    </button>
                                <?php endif; ?>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($tipo_perfil == 'club' && isset($perfil_data['miembros'])): ?>
                    <hr>
                    <div class="members-list mt-4">
                        <h5 class="fw-bold mb-3">Miembros del Club (<?php echo count($perfil_data['miembros']); ?>)</h5>
                        <div class="row">
                            <?php foreach ($perfil_data['miembros'] as $miembro): ?>
                                <div class="col-6 col-md-3 col-lg-2 mb-3">
                                    <div class="d-flex flex-column align-items-center text-center">
                                        <img src="<?php echo htmlspecialchars($miembro['foto_perfil_url'] ?? 'img/default-avatar.png'); ?>" alt="Miembro" class="rounded-circle mb-2" style="width: 60px; height: 60px; object-fit: cover;">
                                        <span class="fw-bold text-truncate w-100"><?php echo htmlspecialchars($miembro['nombre_usuario']); ?></span>
                                        <?php if ($miembro['es_jugador']): ?>
                                            <span class="badge bg-info mt-1">Jugador</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary mt-1">Miembro</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="row">
                <div class="col-md-8 offset-md-2">
                    <h4 class="fw-bold mb-4">Publicaciones de <?php echo htmlspecialchars($perfil_data['nombre_usuario'] ?? $perfil_data['nombre_oficial']); ?></h4>

                    <?php if (!empty($perfil_publicaciones)): ?>
                        <?php foreach ($perfil_publicaciones as $publicacion): ?>
                            <div class="card post-card mb-3">
                                <div class="card-body">
                                    <div class="d-flex align-items-center mb-3">
                                        <img src="<?php echo htmlspecialchars($publicacion['autor_foto_publicacion'] ?: 'img/default-avatar.png'); ?>" alt="Avatar" class="post-profile-pic">
                                        <div class="ms-3 flex-grow-1">
                                            <h5 class="mb-0">
                                                <?php echo htmlspecialchars($publicacion['autor_nombre_publicacion']); ?>
                                            </h5>
                                            <small class="text-muted"><i class="far fa-clock me-1"></i><?php echo date('d M Y H:i', strtotime($publicacion['fecha_publicacion'])); ?></small>
                                        </div>
                                        <?php if ($publicacion['id_usuario'] == $id_usuario_logueado && $es_mi_perfil): // Solo mostrar botón de eliminar en el perfil propio y si es el autor ?>
                                            <a href="dashboard.php?delete_post=<?php echo htmlspecialchars($publicacion['id_publicacion']); ?>&redirect_url=<?php echo urlencode('perfil.php?id='.$id_perfil.'&tipo='.$tipo_perfil); ?>" class="btn btn-sm btn-outline-danger ms-auto" onclick="return confirm('¿Estás seguro de que quieres eliminar esta publicación?');">
                                                <i class="fas fa-trash-alt"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                    <p class="card-text"><?php echo nl2br(htmlspecialchars($publicacion['contenido_texto'])); ?></p>
                                    <?php if (!empty($publicacion['url_imagen'])): ?>
                                        <img src="<?php echo htmlspecialchars($publicacion['url_imagen']); ?>" class="img-fluid post-image" alt="Imagen de publicación">
                                    <?php endif; ?>
                                    <?php if (!empty($publicacion['url_video'])): ?>
                                        <div class="embed-responsive embed-responsive-16by9 post-video">
                                            <iframe class="embed-responsive-item" src="<?php echo htmlspecialchars($publicacion['url_video']); ?>" allowfullscreen></iframe>
                                        </div>
                                    <?php endif; ?>

                                    <hr>
                                    <div class="d-flex justify-content-around align-items-center">
                                        <button class="btn btn-link text-decoration-none like-btn" data-post-id="<?php echo htmlspecialchars($publicacion['id_publicacion']); ?>" <?php echo $es_mi_perfil ? '' : 'disabled'; // Deshabilitar si no es mi perfil para simplificar, idealmente el JS manejaría esto mejor ?>>
                                            <i class="fas fa-heart <?php echo $publicacion['usuario_dio_like'] ? 'text-danger' : ''; ?>"></i>
                                            <span class="like-count"><?php echo htmlspecialchars($publicacion['total_likes']); ?></span> Me gusta
                                        </button>
                                        <button class="btn btn-link text-decoration-none text-muted comment-toggle-btn" data-bs-toggle="collapse" data-bs-target="#comments-perfil-<?php echo htmlspecialchars($publicacion['id_publicacion']); ?>" aria-expanded="false" aria-controls="comments-perfil-<?php echo htmlspecialchars($publicacion['id_publicacion']); ?>">
                                            <i class="fas fa-comment"></i> <span class="comment-count"><?php echo htmlspecialchars($publicacion['total_comentarios']); ?></span> Comentarios
                                        </button>
                                    </div>

                                    <div class="collapse mt-3" id="comments-perfil-<?php echo htmlspecialchars($publicacion['id_publicacion']); ?>">
                                        <div class="comment-section">
                                            <!-- Aquí se cargarían/mostrarían los comentarios. Para simplificar, no se incluye la lógica completa de comentarios aquí -->
                                            <p class="text-muted text-center_">La sección de comentarios detallada se ve en el dashboard.</p>
                                            <?php if ($publicacion['total_comentarios'] > 0): ?>
                                                <div class="mt-2">
                                                <!-- Se podría hacer un fetch AJAX para los comentarios o mostrar algunos -->
                                                </div>
                                            <?php else: ?>
                                                 <p class="text-muted text-center">No hay comentarios aún.</p>
                                            <?php endif; ?>
                                            <!-- Formulario de comentario simplificado o enlace al post en dashboard -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="alert alert-info text-center" role="alert">
                            No hay publicaciones en este perfil.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <script src="js/bootstrap/bootstrap.bundle.min.js"></script>
    <script src="js/scripts.js"></script>
</body>
</html>