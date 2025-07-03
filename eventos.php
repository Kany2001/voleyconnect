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
$tipo_usuario_logueado = $_SESSION['tipo_usuario']; // 'fanatico' o 'club'

$mensaje_feedback = '';
$tipo_mensaje = '';

// --- Lógica para Crear un Nuevo Evento (Solo para Clubes) ---
if (isset($_POST['submit_evento']) && $tipo_usuario_logueado === 'club') {
    // Primero, necesitamos obtener el id_club del usuario logueado
    $id_club_creador = 0;
    $stmt_get_club_id = $conn->prepare("SELECT id_club FROM clubes WHERE id_usuario = ?");
    $stmt_get_club_id->bind_param("i", $id_usuario_logueado);
    $stmt_get_club_id->execute();
    $resultado_get_club_id = $stmt_get_club_id->get_result();
    if ($resultado_get_club_id->num_rows > 0) {
        $id_club_creador = $resultado_get_club_id->fetch_assoc()['id_club'];
    }
    $stmt_get_club_id->close();

    if ($id_club_creador > 0) {
        $nombre_evento = $conn->real_escape_string(trim($_POST['nombre_evento'] ?? ''));
        $descripcion = $conn->real_escape_string(trim($_POST['descripcion'] ?? ''));
        $fecha_hora_inicio_str = $conn->real_escape_string(trim($_POST['fecha_hora_inicio'] ?? ''));
        $fecha_hora_fin_str = $conn->real_escape_string(trim($_POST['fecha_hora_fin'] ?? ''));
        $ubicacion = $conn->real_escape_string(trim($_POST['ubicacion'] ?? ''));
        $tipo_evento = $conn->real_escape_string(trim($_POST['tipo_evento'] ?? 'partido'));
        $url_imagen_evento = '';

        // Validación de campos obligatorios
        if (empty($nombre_evento) || empty($fecha_hora_inicio_str) || empty($ubicacion)) {
            $mensaje_feedback = "Error: Los campos Nombre del Evento, Fecha y Hora de Inicio, y Ubicación son obligatorios.";
            $tipo_mensaje = "danger";
        } else {
            // Procesar la fecha y hora de inicio
            // Asumimos formato local (YYYY-MM-DDTHH:MM) y lo convertimos a formato MySQL (YYYY-MM-DD HH:MM:SS)
            $fecha_hora_inicio = date('Y-m-d H:i:s', strtotime($fecha_hora_inicio_str));
            $fecha_hora_fin = !empty($fecha_hora_fin_str) ? date('Y-m-d H:i:s', strtotime($fecha_hora_fin_str)) : NULL;

            // Lógica para subir IMAGEN del evento (similar a la de publicaciones/perfil)
            if (isset($_FILES['imagen_evento']) && $_FILES['imagen_evento']['error'] === UPLOAD_ERR_OK) {
                $file_name = $_FILES['imagen_evento']['name'];
                $file_tmp_name = $_FILES['imagen_evento']['tmp_name'];
                $file_size = $_FILES['imagen_evento']['size'];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

                $allowed_extensions = array('jpeg', 'jpg', 'png', 'gif');

                if (in_array($file_ext, $allowed_extensions)) {
                    if ($file_size < 5000000) { // Máximo 5MB para imágenes de evento
                        $new_file_name = uniqid('event_img_') . '.' . $file_ext;
                        $upload_dir = 'img/eventos/';

                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0777, true);
                        }

                        $destination_path = $upload_dir . $new_file_name;

                        if (move_uploaded_file($file_tmp_name, $destination_path)) {
                            $url_imagen_evento = $destination_path;
                        } else {
                            $mensaje_feedback = "Error al mover el archivo de imagen del evento. Revisa los permisos de la carpeta 'img/eventos'.";
                            $tipo_mensaje = "danger";
                        }
                    } else {
                        $mensaje_feedback = "La imagen del evento es demasiado grande. Máximo 5MB.";
                        $tipo_mensaje = "danger";
                    }
                } else {
                    $mensaje_feedback = "Tipo de archivo de imagen no permitido para el evento. Solo JPG, PNG, GIF.";
                    $tipo_mensaje = "danger";
                }
            } elseif ($_FILES['imagen_evento']['error'] !== UPLOAD_ERR_NO_FILE) {
                // Solo si hubo un error real en la subida que no sea NO_FILE
                if (empty($mensaje_feedback)) { // No sobrescribir si ya hay un error
                    $mensaje_feedback = "Error al subir la imagen del evento: " . $_FILES['imagen_evento']['error'];
                    $tipo_mensaje = "danger";
                }
            }

            // Si no hay errores previos, insertar el evento
            if (empty($mensaje_feedback)) {
                // Añadido publicado_por (id_usuario del club) y fecha_hora (usando fecha_hora_inicio)
                $stmt_insert_event = $conn->prepare("INSERT INTO eventos (id_club, nombre_evento, descripcion, fecha_hora_inicio, fecha_hora_fin, ubicacion, tipo_evento, url_imagen_evento, publicado_por, fecha_hora) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt_insert_event->bind_param("isssssssis",
                    $id_club_creador,
                    $nombre_evento,
                    $descripcion,
                    $fecha_hora_inicio, // Usado para fecha_hora_inicio
                    $fecha_hora_fin,
                    $ubicacion,
                    $tipo_evento,
                    $url_imagen_evento,
                    $id_usuario_logueado, // publicado_por es el id_usuario del club
                    $fecha_hora_inicio  // fecha_hora toma el valor de fecha_hora_inicio
                );

                if ($stmt_insert_event->execute()) {
                    header("Location: eventos.php?msg=" . urlencode("¡Evento '$nombre_evento' creado exitosamente!") . "&type=success");
                    exit();
                } else {
                    $mensaje_feedback = "Error al crear el evento: " . $stmt_insert_event->error;
                    $tipo_mensaje = "danger";
                }
                $stmt_insert_event->close();
            }
        }
    } else {
        $mensaje_feedback = "Error: No se pudo encontrar el ID del club asociado a su cuenta.";
        $tipo_mensaje = "danger";
    }
}

// Mensajes de feedback después de redirección
if (isset($_GET['msg']) && isset($_GET['type'])) {
    $mensaje_feedback = htmlspecialchars($_GET['msg']);
    $tipo_mensaje = htmlspecialchars($_GET['type']);
}

// --- Lógica para Obtener y Mostrar Eventos ---
$eventos = [];
$query_eventos = "
    SELECT
        e.*,
        c.nombre_oficial AS nombre_club,
        u.nombre_usuario AS nombre_usuario_club,
        u.foto_perfil_url AS foto_perfil_club
    FROM eventos e
    JOIN clubes c ON e.id_club = c.id_club
    JOIN usuarios u ON c.id_usuario = u.id_usuario
    WHERE e.fecha_hora_inicio >= NOW() -- Mostrar solo eventos futuros o que estén ocurriendo ahora
    ORDER BY e.fecha_hora_inicio ASC
";

$resultado_eventos = $conn->query($query_eventos);

if ($resultado_eventos) {
    if ($resultado_eventos->num_rows > 0) {
        while ($fila = $resultado_eventos->fetch_assoc()) {
            $eventos[] = $fila;
        }
    } else {
        $mensaje_eventos = "No hay eventos próximos registrados. ¡Sé el primero en crear uno!";
    }
} else {
    $mensaje_feedback = "Error al cargar los eventos: " . $conn->error;
    $tipo_mensaje = "danger";
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eventos - VoleyConnect</title>
    <link rel="stylesheet" href="css/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <?php include 'navbar.php'; // Incluir la barra de navegación estándar ?>

    <div class="container mt-5 pt-4">
        <div class="row justify-content-center">
            <div class="col-md-9">
                <h1 class="my-4 text-center">Próximos Eventos de Voleibol</h1>

                <?php if (!empty($mensaje_feedback)): ?>
                    <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($mensaje_feedback); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if ($tipo_usuario_logueado === 'club'): ?>
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Organiza un Nuevo Evento</h5>
                            <form action="eventos.php" method="POST" enctype="multipart/form-data">
                                <div class="mb-3">
                                    <label for="nombre_evento" class="form-label">Nombre del Evento <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="nombre_evento" name="nombre_evento" required>
                                </div>
                                <div class="mb-3">
                                    <label for="descripcion" class="form-label">Descripción</label>
                                    <textarea class="form-control" id="descripcion" name="descripcion" rows="3"></textarea>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="fecha_hora_inicio" class="form-label">Fecha y Hora de Inicio <span class="text-danger">*</span></label>
                                        <input type="datetime-local" class="form-control" id="fecha_hora_inicio" name="fecha_hora_inicio" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="fecha_hora_fin" class="form-label">Fecha y Hora de Fin (Opcional)</label>
                                        <input type="datetime-local" class="form-control" id="fecha_hora_fin" name="fecha_hora_fin">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="ubicacion" class="form-label">Ubicación <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="ubicacion" name="ubicacion" required placeholder="Ej: Gimnasio Municipal, Cancha La Paz">
                                </div>
                                <div class="mb-3">
                                    <label for="tipo_evento" class="form-label">Tipo de Evento</label>
                                    <select class="form-select" id="tipo_evento" name="tipo_evento">
                                        <option value="partido" selected>Partido</option>
                                        <option value="torneo">Torneo</option>
                                        <option value="entrenamiento">Entrenamiento Abierto</option>
                                        <option value="otro">Otro</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="imagen_evento" class="form-label">Imagen del Evento (Opcional):</label>
                                    <input type="file" class="form-control" id="imagen_evento" name="imagen_evento" accept="image/*">
                                    <small class="form-text text-muted">Formatos permitidos: JPG, PNG, GIF (Máx. 5MB)</small>
                                </div>
                                <button type="submit" name="submit_evento" class="btn btn-primary float-end"><i class="fas fa-plus-circle me-1"></i>Crear Evento</button>
                            </form>
                        </div>
                    </div>
                    <hr>
                <?php endif; ?>

                <h3 class="mb-3">Eventos Próximos</h3>
                <div class="row">
                    <?php if (!empty($eventos)): ?>
                        <?php foreach ($eventos as $evento): ?>
                            <div class="col-md-6 mb-4">
                                <div class="card h-100 event-card">
                                    <?php if (!empty($evento['url_imagen_evento'])): ?>
                                        <img src="<?php echo htmlspecialchars($evento['url_imagen_evento']); ?>" class="card-img-top event-img" alt="Imagen del Evento">
                                    <?php endif; ?>
                                    <div class="card-body">
                                        <h5 class="card-title"><?php echo htmlspecialchars($evento['nombre_evento']); ?></h5>
                                        <p class="card-text text-muted mb-2">
                                            <i class="fas fa-calendar-alt me-1"></i>
                                            <?php echo date('d/m/Y H:i', strtotime($evento['fecha_hora_inicio'])); ?>
                                            <?php if (!empty($evento['fecha_hora_fin'])): ?>
                                                - <?php echo date('H:i', strtotime($evento['fecha_hora_fin'])); ?>
                                            <?php endif; ?>
                                        </p>
                                        <p class="card-text text-muted mb-2"><i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($evento['ubicacion']); ?></p>
                                        <p class="card-text mb-2"><span class="badge bg-secondary"><?php echo htmlspecialchars(ucfirst($evento['tipo_evento'])); ?></span></p>
                                        <p class="card-text text-truncate"><?php echo htmlspecialchars($evento['descripcion'] ?: 'Sin descripción.'); ?></p>
                                        <div class="d-flex align-items-center mb-3">
                                            <img src="<?php echo htmlspecialchars($evento['foto_perfil_club'] ?: 'img/default-avatar.png'); ?>" alt="Logo Club" class="profile-pic-small me-2" style="width: 30px; height: 30px;">
                                            <small class="text-muted">Organizado por: **<?php echo htmlspecialchars($evento['nombre_club']); ?>**</small>
                                        </div>
                                        <a href="ver_evento.php?id=<?php echo htmlspecialchars($evento['id_evento']); ?>" class="btn btn-outline-primary btn-sm"><i class="fas fa-info-circle me-1"></i>Ver Detalles</a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-12 text-center">
                            <p class="lead mt-5"><?php echo $mensaje_eventos ?? "No hay eventos próximos registrados. ¡Sé el primero en crear uno!"; ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="js/bootstrap/bootstrap.bundle.min.js"></script>
    <script src="js/scripts.js"></script>
</body>
</html>