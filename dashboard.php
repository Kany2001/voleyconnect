<?php
session_start();
include 'conexion.php';

if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit();
}

$id_usuario_logueado = $_SESSION['id_usuario'];
$nombre_usuario_logueado = $_SESSION['nombre_usuario'];
$tipo_usuario_logueado = $_SESSION['tipo_usuario'];

$mensaje_feedback = '';
$tipo_mensaje = '';

// --- Lógica para CREAR una nueva publicación (con imágenes/videos) ---
if (isset($_POST['submit_publicacion'])) {
    $contenido_texto = $conn->real_escape_string(trim($_POST['contenido_texto']));
    $url_imagen = '';
    $url_video = $conn->real_escape_string(trim($_POST['url_video'] ?? ''));

    // Validación de URL de video (YouTube/Vimeo)
    if (!empty($url_video)) {
        // Expresión regular para validar y extraer el ID de video de YouTube o Vimeo
        if (preg_match('/^(?:https?:\/\/)?(?:www\.)?(?:youtube\.com|youtu\.be)\/(?:watch\?v=|embed\/|v\/|)([a-zA-Z0-9_-]{11})(?:[^<>]*)$/i', $url_video, $matches_yt)) {
            $url_video = "https://www.youtube.com/embed/" . $matches_yt['1'];
        } elseif (preg_match('/^(?:https?:\/\/)?(?:www\.)?(vimeo\.com\/)?([0-9]+)([^<>]*)$/i', $url_video, $matches_vimeo)) {
            $url_video = "https://player.vimeo.com/video/" . $matches_vimeo['2'];
        } else {
            $mensaje_feedback = "URL de video no válida o no soportada. Solo se aceptan YouTube y Vimeo.";
            $tipo_mensaje = "danger";
            $url_video = ''; // Limpiar URL inválida
        }
    }

    // Manejo de la subida de imagen
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] == UPLOAD_ERR_OK) {
        $directorio_destino_img = 'img/publicaciones/';
        if (!is_dir($directorio_destino_img)) {
            mkdir($directorio_destino_img, 0777, true); // Asegurar que el directorio exista
        }
        $nombre_archivo = uniqid('post_img_') . '_' . basename($_FILES['imagen']['name']);
        $ruta_destino = $directorio_destino_img . $nombre_archivo;
        if (move_uploaded_file($_FILES['imagen']['tmp_name'], $ruta_destino)) {
            $url_imagen = $ruta_destino;
        } else {
            $mensaje_feedback = "Error al subir la imagen. Verifique los permisos de la carpeta 'img/publicaciones/'.";
            $tipo_mensaje = "danger";
        }
    }

    if (empty($mensaje_feedback)) { // Si no hubo errores en la subida/validación de URL
        $stmt_insert_publicacion = $conn->prepare("INSERT INTO publicaciones (id_usuario, contenido_texto, url_imagen, url_video) VALUES (?, ?, ?, ?)");
        $stmt_insert_publicacion->bind_param("isss", $id_usuario_logueado, $contenido_texto, $url_imagen, $url_video);

        if ($stmt_insert_publicacion->execute()) {
            $mensaje_feedback = "Publicación creada con éxito.";
            $tipo_mensaje = "success";
            // Redirigir para evitar reenvío del formulario
            header("Location: dashboard.php?msg=" . urlencode($mensaje_feedback) . "&type=" . $tipo_mensaje);
            exit();
        } else {
            $mensaje_feedback = "Error al crear la publicación: " . $stmt_insert_publicacion->error;
            $tipo_mensaje = "danger";
        }
        $stmt_insert_publicacion->close();
    }
}

// --- Lógica para ELIMINAR una publicación (solo el autor) ---
// Condición eliminada: && $tipo_usuario_logueado === 'club' para permitir a cualquier autor borrar sus posts.
// La verificación de autoría se hace más abajo.
if (isset($_GET['delete_post'])) {
    $id_publicacion_a_eliminar = intval($_GET['delete_post']);

    // Primero, verificar si la publicación pertenece al usuario logueado
    $stmt_check_owner = $conn->prepare("SELECT id_usuario FROM publicaciones WHERE id_publicacion = ?");
    $stmt_check_owner->bind_param("i", $id_publicacion_a_eliminar);
    $stmt_check_owner->execute();
    $result_check_owner = $stmt_check_owner->get_result();

    if ($result_check_owner->num_rows > 0) {
        $row_owner = $result_check_owner->fetch_assoc();
        $id_autor_publicacion = $row_owner['id_usuario'];

        if ($id_autor_publicacion == $id_usuario_logueado) { // Si el usuario logueado es el autor
            // Eliminar likes y comentarios asociados primero (para evitar errores de FK)
            $conn->begin_transaction();
            try {
                $stmt_delete_likes = $conn->prepare("DELETE FROM likes WHERE id_publicacion = ?");
                $stmt_delete_likes->bind_param("i", $id_publicacion_a_eliminar);
                $stmt_delete_likes->execute();

                $stmt_delete_comments = $conn->prepare("DELETE FROM comentarios WHERE id_publicacion = ?");
                $stmt_delete_comments->bind_param("i", $id_publicacion_a_eliminar);
                $stmt_delete_comments->execute();

                // Ahora eliminar la publicación
                $stmt_delete_post = $conn->prepare("DELETE FROM publicaciones WHERE id_publicacion = ?");
                $stmt_delete_post->bind_param("i", $id_publicacion_a_eliminar);

                if ($stmt_delete_post->execute()) {
                    $conn->commit();
                    $mensaje_feedback = "Publicación eliminada con éxito.";
                    $tipo_mensaje = "success";
                } else {
                    throw new Exception("Error al eliminar la publicación.");
                }
            } catch (Exception $e) {
                $conn->rollback();
                $mensaje_feedback = "Error al eliminar la publicación: " . $e->getMessage();
                $tipo_mensaje = "danger";
            }
        } else {
            $mensaje_feedback = "No tienes permiso para eliminar esta publicación.";
            $tipo_mensaje = "danger";
        }
    } else {
        $mensaje_feedback = "Publicación no encontrada.";
        $tipo_mensaje = "danger";
    }
    header("Location: dashboard.php?msg=" . urlencode($mensaje_feedback) . "&type=" . $tipo_mensaje);
    exit();
}


// Mensajes de feedback después de redirección
if (isset($_GET['msg']) && isset($_GET['type'])) {
    $mensaje_feedback = htmlspecialchars($_GET['msg']);
    $tipo_mensaje = htmlspecialchars($_GET['type']);
}

// --- Obtener publicaciones para el feed ---
$publicaciones = [];
$mensaje_muro = '';
$query_publicaciones = "
    SELECT
        p.id_publicacion,
        p.contenido_texto,
        p.url_imagen,
        p.url_video,
        p.fecha_publicacion,
        u.id_usuario,
        u.nombre_usuario,
        u.foto_perfil_url,
        u.tipo_usuario,
        c.nombre_oficial AS nombre_club,
        c.logo_url AS logo_club_url,
        (SELECT COUNT(*) FROM likes l WHERE l.id_publicacion = p.id_publicacion) AS total_likes,
        (SELECT COUNT(*) FROM likes l WHERE l.id_publicacion = p.id_publicacion AND l.id_usuario = ?) AS usuario_dio_like,
        (SELECT COUNT(*) FROM comentarios com WHERE com.id_publicacion = p.id_publicacion) AS total_comentarios
    FROM
        publicaciones p
    JOIN
        usuarios u ON p.id_usuario = u.id_usuario
    LEFT JOIN
        clubes c ON u.id_usuario = c.id_usuario AND u.tipo_usuario = 'club'
    ORDER BY
        p.fecha_publicacion DESC
";
$stmt_publicaciones = $conn->prepare($query_publicaciones);
$stmt_publicaciones->bind_param("i", $id_usuario_logueado); // Pasar id_usuario_logueado para verificar likes
$stmt_publicaciones->execute();
$resultado_publicaciones = $stmt_publicaciones->get_result();

if ($resultado_publicaciones->num_rows > 0) {
    while ($fila = $resultado_publicaciones->fetch_assoc()) {
        $publicaciones[] = $fila;
    }
} else {
    $mensaje_muro = "No hay publicaciones aún. ¡Sé el primero en publicar algo!";
}
$stmt_publicaciones->close();


// --- Obtener Eventos Próximos (simplificado, solo 3 eventos) ---
$eventos_proximos = [];
$mensaje_eventos = '';
$query_eventos = "
    SELECT
        e.id_evento,
        e.titulo,
        e.fecha_hora_inicio,
        e.ubicacion,
        c.nombre_oficial AS nombre_club,
        c.logo_url AS foto_perfil_club /* Se cambió de c.foto_perfil_url a c.logo_url */
    FROM
        eventos e
    JOIN
        clubes c ON e.id_club = c.id_club
    WHERE
        e.fecha_hora_inicio >= NOW()
    ORDER BY
        e.fecha_hora_inicio ASC
    LIMIT 3
";
$resultado_eventos = $conn->query($query_eventos);

if ($resultado_eventos->num_rows > 0) {
    while ($fila = $resultado_eventos->fetch_assoc()) {
        $eventos_proximos[] = $fila;
    }
} else {
    $mensaje_eventos = "No hay eventos próximos registrados. ¡Sé el primero en crear uno!";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - VoleyConnect</title>
    <link rel="stylesheet" href="css/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <?php include 'navbar.php'; // Asegúrate de que tu navbar.php exista y esté bien configurado ?>

    <div class="container main-content-area"> <?php if (!empty($mensaje_feedback)): ?>
            <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
                <?php echo $mensaje_feedback; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Crear Nueva Publicación</h5>
                        <form action="dashboard.php" method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="contenido_texto" class="form-label">¿Qué estás pensando, <?php echo htmlspecialchars($nombre_usuario_logueado); ?>?</label>
                                <textarea class="form-control" id="contenido_texto" name="contenido_texto" rows="3" placeholder="Escribe tu publicación aquí..." required></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="imagen" class="form-label">Subir Imagen (Opcional)</label>
                                <input class="form-control" type="file" id="imagen" name="imagen" accept="image/*">
                            </div>
                            <div class="mb-3">
                                <label for="url_video" class="form-label">Enlace a Video (YouTube/Vimeo - Opcional)</label>
                                <input type="url" class="form-control" id="url_video" name="url_video" placeholder="Ej: https://www.youtube.com/watch?v=..." pattern="^(https?:\/\/)?(www\.)?(youtube\.com|youtu\.be|vimeo\.com)\/.*$">
                            </div>
                            <button type="submit" name="submit_publicacion" class="btn btn-primary w-100"><i class="fas fa-paper-plane me-2"></i>Publicar</button>
                        </form>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Eventos Próximos</h5>
                        <?php if (!empty($eventos_proximos)): ?>
                            <?php foreach ($eventos_proximos as $evento): ?>
                                <div class="event-item d-flex align-items-start mb-3">
                                    <img src="<?php echo htmlspecialchars($evento['foto_perfil_club'] ?: 'img/default-club.png'); ?>" alt="Logo Club" class="profile-pic-event-club me-3">
                                    <div class="event-details flex-grow-1">
                                        <h6><?php echo htmlspecialchars($evento['titulo']); ?></h6>
                                        <small class="text-muted"><i class="far fa-calendar-alt me-1"></i><?php echo date('d M Y, H:i', strtotime($evento['fecha_hora_inicio'])); ?></small><br>
                                        <small class="text-muted"><i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($evento['ubicacion']); ?></small><br>
                                        <small class="text-muted">Organizado por: <strong><?php echo htmlspecialchars($evento['nombre_club']); ?></strong></small>
                                        <a href="ver_evento.php?id=<?php echo htmlspecialchars($evento['id_evento']); ?>" class="btn btn-outline-primary btn-sm mt-2"><i class="fas fa-info-circle me-1"></i>Ver Detalles</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <div class="text-center mt-3">
                                <a href="eventos.php" class="btn btn-secondary btn-sm"><i class="fas fa-calendar-alt me-1"></i>Ver Todos los Eventos</a>
                            </div>
                        <?php else: ?>
                            <p class="text-center text-muted">
                                <?php echo $mensaje_eventos ?? "No hay eventos próximos registrados. ¡Sé el primero en crear uno!"; ?>
                                <?php if ($tipo_usuario_logueado === 'club'): ?>
                                    <br><a href="eventos.php" class="btn btn-primary btn-sm mt-2"><i class="fas fa-plus-circle me-1"></i>Crear Evento</a> <!-- Corregido a eventos.php -->
                                <?php endif; ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <?php if (!empty($publicaciones)): ?>
                    <?php foreach ($publicaciones as $publicacion): ?>
                        <div class="card post-card">
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-3">
                                    <img src="<?php echo htmlspecialchars($publicacion['foto_perfil_url'] ?: 'img/default-avatar.png'); ?>" alt="Avatar" class="post-profile-pic">
                                    <div class="ms-3 flex-grow-1">
                                        <h5 class="mb-0">
                                            <?php echo htmlspecialchars($publicacion['nombre_usuario']); ?>
                                            <?php if ($publicacion['tipo_usuario'] === 'club' && !empty($publicacion['nombre_club'])): ?>
                                                <span class="badge bg-primary club-badge"><i class="fas fa-volleyball-ball me-1"></i><?php echo htmlspecialchars($publicacion['nombre_club']); ?></span>
                                            <?php endif; ?>
                                        </h5>
                                        <small class="text-muted"><i class="far fa-clock me-1"></i><?php echo date('d M Y H:i', strtotime($publicacion['fecha_publicacion'])); ?></small>
                                    </div>
                                    <?php if ($publicacion['id_usuario'] == $id_usuario_logueado): // Si el usuario es el autor de la publicación ?>
                                        <a href="dashboard.php?delete_post=<?php echo htmlspecialchars($publicacion['id_publicacion']); ?>" class="btn btn-sm btn-outline-danger ms-auto" onclick="return confirm('¿Estás seguro de que quieres eliminar esta publicación? Esto también eliminará sus likes y comentarios.');">
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
                                    <button class="btn btn-link text-decoration-none like-btn" data-post-id="<?php echo htmlspecialchars($publicacion['id_publicacion']); ?>">
                                        <i class="fas fa-heart <?php echo $publicacion['usuario_dio_like'] ? 'text-danger' : ''; ?>"></i>
                                        <span class="like-count"><?php echo htmlspecialchars($publicacion['total_likes']); ?></span> Me gusta
                                    </button>
                                    <button class="btn btn-link text-decoration-none text-muted comment-toggle-btn" data-bs-toggle="collapse" data-bs-target="#comments-<?php echo htmlspecialchars($publicacion['id_publicacion']); ?>" aria-expanded="false" aria-controls="comments-<?php echo htmlspecialchars($publicacion['id_publicacion']); ?>">
                                        <i class="fas fa-comment"></i> <span class="comment-count"><?php echo htmlspecialchars($publicacion['total_comentarios']); ?></span> Comentarios
                                    </button>
                                </div>

                                <div class="collapse mt-3" id="comments-<?php echo htmlspecialchars($publicacion['id_publicacion']); ?>">
                                    <div class="comment-section">
                                        <div class="comment-list" id="comment-list-<?php echo htmlspecialchars($publicacion['id_publicacion']); ?>">
                                            <?php
                                            // Obtener comentarios para esta publicación
                                            $query_comentarios = "
                                                SELECT
                                                    c.contenido_comentario,
                                                    c.fecha_comentario,
                                                    u.nombre_usuario,
                                                    u.foto_perfil_url
                                                FROM
                                                    comentarios c
                                                JOIN
                                                    usuarios u ON c.id_usuario = u.id_usuario
                                                WHERE
                                                    c.id_publicacion = ?
                                                ORDER BY
                                                    c.fecha_comentario ASC
                                            ";
                                            $stmt_comentarios = $conn->prepare($query_comentarios);
                                            $stmt_comentarios->bind_param("i", $publicacion['id_publicacion']);
                                            $stmt_comentarios->execute();
                                            $resultado_comentarios = $stmt_comentarios->get_result();
                                            ?>
                                            <?php if ($resultado_comentarios->num_rows > 0): ?>
                                                <?php while ($comentario = $resultado_comentarios->fetch_assoc()): ?>
                                                    <div class="comment-item">
                                                        <img src="<?php echo htmlspecialchars($comentario['foto_perfil_url'] ?: 'img/default-avatar.png'); ?>" alt="Avatar" class="profile-pic-small me-2">
                                                        <div class="comment-content">
                                                            <span class="username"><?php echo htmlspecialchars($comentario['nombre_usuario']); ?></span>
                                                            <small class="text-muted ms-1"><?php echo date('d M Y H:i', strtotime($comentario['fecha_comentario'])); ?></small>
                                                            <p class="comment-text mb-0"><?php echo nl2br(htmlspecialchars($comentario['contenido_comentario'])); ?></p>
                                                        </div>
                                                    </div>
                                                <?php endwhile; ?>
                                            <?php else: ?>
                                                <p class="no-comments text-center text-muted">Sé el primero en comentar esta publicación.</p>
                                            <?php endif; ?>
                                        </div>
                                        <form class="comment-form d-flex" data-post-id="<?php echo htmlspecialchars($publicacion['id_publicacion']); ?>">
                                            <textarea class="form-control me-2" name="contenido" placeholder="Escribe un comentario..." rows="1" required></textarea>
                                            <button type="submit" class="btn btn-primary">Comentar</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="alert alert-info text-center" role="alert">
                        <?php echo $mensaje_muro ?? "No hay publicaciones aún. ¡Sé el primero en publicar algo!"; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="js/bootstrap/bootstrap.bundle.min.js"></script>
    <script src="js/scripts.js"></script>
    <script>
        // Lógica AJAX para el botón "Me gusta"
        document.querySelectorAll('.like-btn').forEach(button => {
            button.addEventListener('click', function() {
                const postId = this.dataset.postId;
                const likeIcon = this.querySelector('.fas.fa-heart');
                const likeCountSpan = this.querySelector('.like-count');
                let currentLikes = parseInt(likeCountSpan.textContent);

                fetch('like_post.php', { // Asegúrate de que like_post.php esté correctamente configurado
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `id_publicacion=${postId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        likeCountSpan.textContent = data.total_likes;
                        if (data.action === 'liked') {
                            likeIcon.classList.add('text-danger');
                        } else {
                            likeIcon.classList.remove('text-danger');
                        }
                    } else {
                        alert(data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al procesar el "Me gusta".');
                });
            });
        });

        // Lógica AJAX para enviar comentarios
        document.querySelectorAll('.comment-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault(); // Evitar el envío normal del formulario

                const postId = this.dataset.postId;
                const textarea = this.elements.contenido; // Corrección del selector
                const commentContent = textarea.value.trim();
                const commentListContainer = document.getElementById(`comment-list-${postId}`);
                const commentCountSpan = document.querySelector(`#comments-${postId} .comment-count`);

                if (!commentContent) {
                    alert('El comentario no puede estar vacío.');
                    return;
                }

                fetch('comment_post.php', { // Corregido a comment_post.php
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `id_publicacion=${postId}&contenido_comentario=${encodeURIComponent(commentContent)}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        textarea.value = ''; // Limpiar textarea

                        // Actualizar dinámicamente la lista de comentarios
                        if (commentListContainer) {
                            // Si el mensaje "Sé el primero en comentar" está presente, quítalo
                            const noCommentsMessage = commentListContainer.querySelector('.no-comments');
                            if (noCommentsMessage) {
                                noCommentsMessage.remove();
                            }

                            const newCommentHtml = `
                                <div class="comment-item">
                                    <img src="${data.foto_perfil_url || 'img/default-avatar.png'}" alt="Avatar" class="profile-pic-small me-2">
                                    <div class="comment-content">
                                        <span class="username">${data.nombre_usuario}</span>
                                        <small class="text-muted ms-1">${data.fecha_comentario}</small>
                                        <p class="comment-text mb-0">${data.contenido_comentario}</p>
                                    </div>
                                </div>
                            `;
                            commentListContainer.insertAdjacentHTML('beforeend', newCommentHtml);
                            commentListContainer.scrollTop = commentListContainer.scrollHeight; // Desplazar al final

                            // Actualizar el contador de comentarios
                            if (commentCountSpan) {
                                commentCountSpan.textContent = parseInt(commentCountSpan.textContent) + 1;
                            }
                        }
                    } else {
                        alert(data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al añadir comentario.');
                });
            });
        });

        // Este script es para comentarios dinámicos. Si tu sistema de comentarios
        // es solo PHP, puedes eliminarlo o adaptarlo.
    </script>
</body>
</html>