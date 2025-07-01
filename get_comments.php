<?php
session_start();
include 'conexion.php';

header('Content-Type: application/json');

$id_publicacion = isset($_GET['id_publicacion']) ? intval($_GET['id_publicacion']) : 0;

if ($id_publicacion <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de publicación inválido.']);
    $conn->close();
    exit();
}

$comentarios = [];
$stmt_comentarios = $conn->prepare("
    SELECT c.*, u.nombre_usuario, u.foto_perfil_url
    FROM comentarios c
    JOIN usuarios u ON c.id_usuario = u.id_usuario
    WHERE c.id_publicacion = ?
    ORDER BY c.fecha_comentario ASC
");
$stmt_comentarios->bind_param("i", $id_publicacion);
$stmt_comentarios->execute();
$resultado_comentarios = $stmt_comentarios->get_result();

while ($fila = $resultado_comentarios->fetch_assoc()) {
    $comentarios[] = $fila;
}

$stmt_comentarios->close();
$conn->close();

echo json_encode(['success' => true, 'comments' => $comentarios]);
?>