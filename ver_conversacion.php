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

$interlocutor_id = isset($_GET['interlocutor_id']) ? intval($_GET['interlocutor_id']) : 0;
$interlocutor_data = null;
$mensajes = [];
$mensaje_feedback = '';
$tipo_mensaje = '';

if ($interlocutor_id <= 0 || $interlocutor_id == $id_usuario_logueado) {
    header("Location: mensajes.php?msg=" . urlencode("ID de interlocutor inválido.") . "&type=danger");
    exit();
}

// --- Obtener datos del interlocutor ---
$query_interlocutor = "SELECT id_usuario, nombre_usuario, foto_perfil_url FROM usuarios WHERE id_usuario = ?";
$stmt_interlocutor = $conn->prepare($query_interlocutor);
$stmt_interlocutor->bind_param("i", $interlocutor_id);
$stmt_interlocutor->execute();
$resultado_interlocutor = $stmt_interlocutor->get_result();
if ($resultado_interlocutor->num_rows === 1) {
    $interlocutor_data = $resultado_interlocutor->fetch_assoc();
} else {
    // Interlocutor no encontrado
    header("Location: mensajes.php?msg=" . urlencode("El usuario con el que intentas chatear no existe.") . "&type=danger");
    exit();
}
$stmt_interlocutor->close();

// --- Verificar si existe un seguimiento mutuo ---
$es_seguimiento_mutuo = false;
$stmt_mutual_follow = $conn->prepare("
    SELECT
        COUNT(*) AS count
    FROM
        seguimientos s1
    JOIN
        seguimientos s2 ON s1.id_seguidor = s2.id_seguido AND s1.id_seguido = s2.id_seguidor
    WHERE
        s1.id_seguidor = ? AND s1.id_seguido = ?
");
$stmt_mutual_follow->bind_param("ii", $id_usuario_logueado, $interlocutor_id);
$stmt_mutual_follow->execute();
$resultado_mutual_follow = $stmt_mutual_follow->get_result();
$row_mutual_follow = $resultado_mutual_follow->fetch_assoc();
if ($row_mutual_follow['count'] > 0) {
    $es_seguimiento_mutuo = true;
}
$stmt_mutual_follow->close();


// --- Lógica para enviar mensaje ---
if (isset($_POST['submit_mensaje'])) {
    // Solo permitir enviar mensaje si hay seguimiento mutuo
    if (!$es_seguimiento_mutuo) {
        // Redirigir o mostrar error si no hay seguimiento mutuo
        $mensaje_feedback = "No puedes enviar mensajes a este usuario si no hay seguimiento mutuo.";
        $tipo_mensaje = "danger";
    } else {
        $contenido_mensaje = $conn->real_escape_string(trim($_POST['contenido_mensaje']));

        if (!empty($contenido_mensaje)) {
            $stmt_insert = $conn->prepare("INSERT INTO mensajes (id_remitente, id_destinatario, contenido_mensaje) VALUES (?, ?, ?)");
            $stmt_insert->bind_param("iis", $id_usuario_logueado, $interlocutor_id, $contenido_mensaje);

            if ($stmt_insert->execute()) {
                // Mensaje enviado con éxito, recargar la página para ver el nuevo mensaje
                // y limpiar el feedback de URL si lo hubiera
                header("Location: ver_conversacion.php?interlocutor_id=" . urlencode($interlocutor_id));
                exit();
            } else {
                $mensaje_feedback = "Error al enviar el mensaje: " . $stmt_insert->error;
                $tipo_mensaje = "danger";
            }
            $stmt_insert->close();
        } else {
            $mensaje_feedback = "El mensaje no puede estar vacío.";
            $tipo_mensaje = "warning";
        }
    }
}

// Mensajes de feedback después de redirección (p.ej., si viene de un error de interlocutor inválido)
if (isset($_GET['msg']) && isset($_GET['type'])) {
    $mensaje_feedback = htmlspecialchars($_GET['msg']);
    $tipo_mensaje = htmlspecialchars($_GET['type']);
}


// --- Obtener mensajes de la conversación ---
// Se obtienen los mensajes si son parte de la conversación entre el usuario logueado y el interlocutor
$query_mensajes = "
    SELECT
        m.id_mensaje,
        m.contenido_mensaje,
        m.fecha_envio,
        m.id_remitente,
        r.nombre_usuario AS nombre_remitente,
        r.foto_perfil_url AS foto_remitente_url
    FROM mensajes m
    JOIN usuarios r ON m.id_remitente = r.id_usuario
    WHERE (m.id_remitente = ? AND m.id_destinatario = ?)
    OR (m.id_remitente = ? AND m.id_destinatario = ?)
    ORDER BY m.fecha_envio ASC";

$stmt_mensajes = $conn->prepare($query_mensajes);
$stmt_mensajes->bind_param("iiii", $id_usuario_logueado, $interlocutor_id, $interlocutor_id, $id_usuario_logueado);
$stmt_mensajes->execute();
$resultado_mensajes = $stmt_mensajes->get_result();
if ($resultado_mensajes->num_rows > 0) {
    $mensajes = $resultado_mensajes->fetch_all(MYSQLI_ASSOC);
}
$stmt_mensajes->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat con <?php echo htmlspecialchars($interlocutor_data['nombre_usuario']); ?> - VoleyConnect</title>
    <link rel="stylesheet" href="css/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .chat-box {
            height: 400px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            border-radius: 0.5rem;
            padding: 15px;
            margin-bottom: 20px;
            background-color: #f8f9fa;
        }
        .message {
            display: flex;
            margin-bottom: 15px;
            align-items: flex-end; /* Alinea la parte inferior del avatar y el texto */
        }
        .message.sent {
            justify-content: flex-end;
        }
        .message.received {
            justify-content: flex-start;
        }
        .message-bubble {
            max-width: 75%;
            padding: 10px 15px;
            border-radius: 20px;
            position: relative;
            word-wrap: break-word; /* Para mensajes largos */
        }
        .message.sent .message-bubble {
            background-color: var(--dark-blue);
            color: var(--white);
            border-bottom-right-radius: 5px; /* Para la "cola" */
        }
        .message.received .message-bubble {
            background-color: #e2e6ea;
            color: var(--text-color);
            border-bottom-left-radius: 5px; /* Para la "cola" */
        }
        .message-time {
            font-size: 0.75em;
            color: #6c757d;
            margin-top: 5px;
            text-align: right;
        }
        .message.received .message-time {
            text-align: left;
        }
        .profile-pic-chat {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            object-fit: cover;
        }
        .message.sent .profile-pic-chat {
            margin-left: 10px; /* Avatar después del mensaje enviado */
        }
        .message.received .profile-pic-chat {
            margin-right: 10px; /* Avatar antes del mensaje recibido */
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white d-flex align-items-center">
                        <img src="<?php echo htmlspecialchars($interlocutor_data['foto_perfil_url'] ?? 'img/default-avatar.png'); ?>" alt="Foto de Perfil" class="profile-pic-chat me-3">
                        <h5 class="mb-0">Chat con <?php echo htmlspecialchars($interlocutor_data['nombre_usuario']); ?></h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($mensaje_feedback)): ?>
                            <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
                                <?php echo htmlspecialchars($mensaje_feedback); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <div class="chat-box" id="chat-box">
                            <?php if (!empty($mensajes)): ?>
                                <?php foreach ($mensajes as $mensaje): ?>
                                    <?php
                                    $es_mio = ($mensaje['id_remitente'] == $id_usuario_logueado);
                                    $clase_mensaje = $es_mio ? 'sent' : 'received';
                                    $foto_remitente = $mensaje['foto_remitente_url'] ?? 'img/default-avatar.png';
                                    ?>
                                    <div class="message <?php echo $clase_mensaje; ?>">
                                        <?php if (!$es_mio): // Si es mensaje recibido, mostrar avatar del remitente antes ?>
                                            <img src="<?php echo htmlspecialchars($foto_remitente); ?>" alt="Avatar" class="profile-pic-chat">
                                        <?php endif; ?>
                                        <div class="message-bubble">
                                            <?php echo nl2br(htmlspecialchars($mensaje['contenido_mensaje'])); ?>
                                            <div class="message-time">
                                                <?php echo date('H:i', strtotime($mensaje['fecha_envio'])); ?>
                                            </div>
                                        </div>
                                        <?php if ($es_mio): // Si es mensaje enviado, mostrar tu avatar después ?>
                                            <img src="<?php echo htmlspecialchars($_SESSION['foto_perfil_url'] ?? 'img/default-avatar.png'); ?>" alt="Tu Avatar" class="profile-pic-chat">
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-center text-muted">
                                    <?php if ($es_seguimiento_mutuo): ?>
                                        ¡Empieza tu conversación con <?php echo htmlspecialchars($interlocutor_data['nombre_usuario']); ?>!
                                    <?php else: ?>
                                        No hay mensajes. Para chatear, tú y <?php echo htmlspecialchars($interlocutor_data['nombre_usuario']); ?> deben seguirse mutuamente.
                                    <?php endif; ?>
                                </p>
                            <?php endif; ?>
                        </div>

                        <form action="ver_conversacion.php?interlocutor_id=<?php echo htmlspecialchars($interlocutor_id); ?>" method="POST" class="d-flex">
                            <?php if ($es_seguimiento_mutuo): ?>
                                <textarea class="form-control me-2" name="contenido_mensaje" placeholder="Escribe tu mensaje..." rows="1" required></textarea>
                                <button type="submit" name="submit_mensaje" class="btn btn-primary"><i class="fas fa-paper-plane"></i></button>
                            <?php else: ?>
                                <div class="alert alert-warning w-100 text-center me-2" role="alert">
                                    Para chatear, tú y <?php echo htmlspecialchars($interlocutor_data['nombre_usuario']); ?> deben seguirse mutuamente.
                                </div>
                                <textarea class="form-control me-2" name="contenido_mensaje" placeholder="Necesitan seguirse mutuamente para chatear..." rows="1" disabled></textarea>
                                <button type="submit" name="submit_mensaje" class="btn btn-primary" disabled><i class="fas fa-paper-plane"></i></button>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <div class="text-center mt-3">
                    <a href="mensajes.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i>Volver a Mensajes</a>
                </div>
            </div>
        </div>
    </div>

    <script src="js/bootstrap/bootstrap.bundle.min.js"></script>
    <script src="js/scripts.js"></script>
    <script>
        // Desplazar el chat al final al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            const chatBox = document.getElementById('chat-box');
            if (chatBox) {
                chatBox.scrollTop = chatBox.scrollHeight;
            }
        });
    </script>
</body>
</html>