<?php
session_start();
include 'conexion.php';

header('Content-Type: application/json');

// Verificar si el usuario está logueado
if (!isset($_SESSION['id_usuario'])) {
    echo json_encode(['success' => false, 'message' => 'Usuario no autenticado.']);
    exit();
}

// Verificar si la petición es POST y proviene de AJAX
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    echo json_encode(['success' => false, 'message' => 'Petición inválida.']);
    exit();
}

$id_usuario = $_SESSION['id_usuario'];
$id_publicacion = isset($_POST['id_publicacion']) ? intval($_POST['id_publicacion']) : 0;
// Corregido para coincidir con el nombre enviado desde dashboard.php AJAX
$contenido_comentario_ajax = trim($_POST['contenido_comentario'] ?? '');

// Validar que los datos recibidos son correctos
if ($id_publicacion <= 0 || empty($contenido_comentario_ajax)) {
    echo json_encode(['success' => false, 'message' => 'Datos de comentario inválidos.']);
    exit();
}

// Insertar el nuevo comentario en la base de datos
// Corregido nombre de columna 'contenido' a 'contenido_texto'
$stmt_insert = $conn->prepare("INSERT INTO comentarios (id_publicacion, id_usuario, contenido_texto) VALUES (?, ?, ?)");
$stmt_insert->bind_param("iis", $id_publicacion, $id_usuario, $contenido_comentario_ajax);

if ($stmt_insert->execute()) {
    $id_nuevo_comentario = $stmt_insert->insert_id; // Obtener el ID del comentario insertado
    $stmt_insert->close();
    
    // Obtener solo el comentario recién insertado con los datos del usuario para la respuesta
    $stmt_fetch_new = $conn->prepare("
        SELECT c.contenido_texto, c.fecha_comentario, u.nombre_usuario, u.foto_perfil_url
        FROM comentarios c
        JOIN usuarios u ON c.id_usuario = u.id_usuario
        WHERE c.id_comentario = ?");
    $stmt_fetch_new->bind_param("i", $id_nuevo_comentario);
    $stmt_fetch_new->execute();
    $nuevo_comentario_data = $stmt_fetch_new->get_result()->fetch_assoc();
    $stmt_fetch_new->close();

    echo json_encode([
        'success' => true,
        'message' => 'Comentario publicado.',
        'id_comentario' => $id_nuevo_comentario,
        'contenido_comentario' => nl2br(htmlspecialchars($nuevo_comentario_data['contenido_texto'])),
        'fecha_comentario' => date('d M Y H:i', strtotime($nuevo_comentario_data['fecha_comentario'])),
        'nombre_usuario' => htmlspecialchars($nuevo_comentario_data['nombre_usuario']),
        'foto_perfil_url' => htmlspecialchars($nuevo_comentario_data['foto_perfil_url'] ?: 'img/default-avatar.png')
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Error al guardar el comentario: ' . $stmt_insert->error
    ]);
}

$conn->close();
?>