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
$contenido_comentario = trim($_POST['contenido'] ?? ''); // Obtiene el contenido de la columna 'contenido'

// Validar que los datos recibidos son correctos
if ($id_publicacion <= 0 || empty($contenido_comentario)) {
    echo json_encode(['success' => false, 'message' => 'Datos de comentario inválidos.']);
    exit();
}

// Insertar el nuevo comentario en la base de datos
$stmt_insert = $conn->prepare("INSERT INTO comentarios (id_publicacion, id_usuario, contenido) VALUES (?, ?, ?)");
$stmt_insert->bind_param("iis", $id_publicacion, $id_usuario, $contenido_comentario);

if ($stmt_insert->execute()) {
    $stmt_insert->close();
    
    // Después de insertar, obtener y devolver todos los comentarios actualizados para esa publicación.
    $stmt_fetch = $conn->prepare("SELECT c.id_comentario, c.contenido, c.fecha_comentario, u.nombre_usuario, u.foto_perfil_url FROM comentarios c JOIN usuarios u ON c.id_usuario = u.id_usuario WHERE c.id_publicacion = ? ORDER BY c.fecha_comentario ASC");
    $stmt_fetch->bind_param("i", $id_publicacion);
    $stmt_fetch->execute();
    $resultado = $stmt_fetch->get_result();
    $comentarios = $resultado->fetch_all(MYSQLI_ASSOC);
    $stmt_fetch->close();

    echo json_encode([
        'success' => true,
        'message' => 'Comentario publicado.',
        'comments' => $comentarios
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Error al guardar el comentario: ' . $stmt_insert->error
    ]);
}

$conn->close();
?>