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

$conversaciones = [];
$usuarios_para_conversar = [];
$mensaje_feedback = '';
$tipo_mensaje = '';

// Mensajes de feedback después de redirección
if (isset($_GET['msg']) && isset($_GET['type'])) {
    $mensaje_feedback = htmlspecialchars($_GET['msg']);
    $tipo_mensaje = htmlspecialchars($_GET['type']);
}

// --- Obtener conversaciones existentes ---
// Seleccionamos los usuarios con los que el usuario logueado ha intercambiado mensajes
// Ya sea como remitente o destinatario, y obtenemos el último mensaje de esa conversación.
$query_conversaciones = "
    SELECT
        CASE
            WHEN m.id_remitente = ? THEN m.id_destinatario
            ELSE m.id_remitente
        END AS id_interlocutor,
        u.nombre_usuario AS nombre_interlocutor,
        u.foto_perfil_url,
        MAX(m.fecha_envio) AS ultima_fecha_envio,
        (SELECT contenido_mensaje FROM mensajes WHERE (id_remitente = ? AND id_destinatario = u.id_usuario) OR (id_remitente = u.id_usuario AND id_destinatario = ?) ORDER BY fecha_envio DESC LIMIT 1) AS ultimo_mensaje_contenido
    FROM mensajes m
    JOIN usuarios u ON (u.id_usuario = m.id_remitente OR u.id_usuario = m.id_destinatario)
    WHERE (m.id_remitente = ? OR m.id_destinatario = ?)
    GROUP BY id_interlocutor, nombre_interlocutor, foto_perfil_url
    ORDER BY ultima_fecha_envio DESC";

$stmt_conversaciones = $conn->prepare($query_conversaciones);
$stmt_conversaciones->bind_param("iiiii",
    $id_usuario_logueado,
    $id_usuario_logueado,
    $id_usuario_logueado,
    $id_usuario_logueado,
    $id_usuario_logueado
);
$stmt_conversaciones->execute();
$resultado_conversaciones = $stmt_conversaciones->get_result();

if ($resultado_conversaciones->num_rows > 0) {
    $conversaciones = $resultado_conversaciones->fetch_all(MYSQLI_ASSOC);
}
$stmt_conversaciones->close();

// --- Obtener usuarios para iniciar nuevas conversaciones (SOLO SI HAY SEGUIMIENTO MUTUO) ---
// Excluye al usuario logueado
$stmt_usuarios = $conn->prepare("
    SELECT
        u.id_usuario,
        u.nombre_usuario
    FROM
        usuarios u
    JOIN
        seguimientos s1 ON u.id_usuario = s1.id_seguido AND s1.id_seguidor = ?
    JOIN
        seguimientos s2 ON u.id_usuario = s2.id_seguidor AND s2.id_seguido = ?
    WHERE
        u.id_usuario != ?
    ORDER BY
        u.nombre_usuario ASC
");
$stmt_usuarios->bind_param("iii", $id_usuario_logueado, $id_usuario_logueado, $id_usuario_logueado);
$stmt_usuarios->execute();
$resultado_usuarios = $stmt_usuarios->get_result();
if ($resultado_usuarios->num_rows > 0) {
    $usuarios_para_conversar = $resultado_usuarios->fetch_all(MYSQLI_ASSOC);
}
$stmt_usuarios->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mensajes - VoleyConnect</title>
    <link rel="stylesheet" href="css/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <?php include 'navbar.php'; // Incluye la barra de navegación ?>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8">
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <h4 class="card-title text-center mb-4">Tus Conversaciones</h4>
                        <?php if (!empty($mensaje_feedback)): ?>
                            <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
                                <?php echo htmlspecialchars($mensaje_feedback); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($conversaciones)): ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($conversaciones as $conv): ?>
                                    <li class="list-group-item d-flex align-items-center justify-content-between">
                                        <div class="d-flex align-items-center">
                                            <img src="<?php echo htmlspecialchars($conv['foto_perfil_url'] ?? 'img/default-avatar.png'); ?>" alt="Foto de Perfil" class="profile-pic-sm rounded-circle me-3">
                                            <div>
                                                <h6 class="mb-0 fw-bold"><?php echo htmlspecialchars($conv['nombre_interlocutor']); ?></h6>
                                                <small class="text-muted text-truncate" style="max-width: 250px; display: block;">
                                                    <?php echo htmlspecialchars($conv['ultimo_mensaje_contenido'] ?: 'No hay mensajes aún.'); ?>
                                                </small>
                                                <small class="text-muted">
                                                    Último mensaje: <?php echo date('d/m/Y H:i', strtotime($conv['ultima_fecha_envio'])); ?>
                                                </small>
                                            </div>
                                        </div>
                                        <a href="ver_conversacion.php?interlocutor_id=<?php echo htmlspecialchars($conv['id_interlocutor']); ?>" class="btn btn-outline-primary btn-sm">Ver Chat</a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <div class="alert alert-info text-center" role="alert">
                                No tienes conversaciones activas. ¡Inicia una nueva!
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Iniciar Nueva Conversación</h5>
                        <?php if (!empty($usuarios_para_conversar)): ?>
                            <form action="ver_conversacion.php" method="GET">
                                <div class="mb-3">
                                    <label for="interlocutor_id" class="form-label">Selecciona un usuario:</label>
                                    <select class="form-select" id="interlocutor_id" name="interlocutor_id" required>
                                        <option value="">Seleccionar...</option>
                                        <?php foreach ($usuarios_para_conversar as $usuario): ?>
                                            <option value="<?php echo htmlspecialchars($usuario['id_usuario']); ?>">
                                                <?php echo htmlspecialchars($usuario['nombre_usuario']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary float-end"><i class="fas fa-paper-plane me-1"></i>Iniciar Conversación</button>
                            </form>
                        <?php else: ?>
                            <div class="alert alert-info text-center" role="alert">
                                Para iniciar una nueva conversación, tú y el otro usuario deben seguirse mutuamente. No hay usuarios disponibles.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="js/bootstrap/bootstrap.bundle.min.js"></script>
    <script src="js/scripts.js"></script>
</body>
</html>