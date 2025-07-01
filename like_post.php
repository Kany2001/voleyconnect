<?php
session_start();
include 'conexion.php';

header('Content-Type: application/json');

if (!isset($_SESSION['id_usuario'])) {
    echo json_encode(['success' => false, 'message' => 'Usuario no autenticado.']);
    exit();
}

$id_usuario = $_SESSION['id_usuario'];
$id_publicacion = isset($_POST['id_publicacion']) ? intval($_POST['id_publicacion']) : 0;

if ($id_publicacion <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de publicación inválido.']);
    exit();
}

// Verificar si el usuario ya dio "Me gusta"
$stmt_check = $conn->prepare("SELECT id_like FROM likes WHERE id_usuario = ? AND id_publicacion = ?");
$stmt_check->bind_param("ii", $id_usuario, $id_publicacion);
$stmt_check->execute();
$resultado_check = $stmt_check->get_result();

$action = ''; // Para indicar si fue liked o unliked

if ($resultado_check->num_rows > 0) {
    // Si ya dio "Me gusta", entonces quitarlo
    $stmt_delete = $conn->prepare("DELETE FROM likes WHERE id_usuario = ? AND id_publicacion = ?");
    $stmt_delete->bind_param("ii", $id_usuario, $id_publicacion);
    if ($stmt_delete->execute()) {
        $action = 'unliked';
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al quitar Me gusta: ' . $stmt_delete->error]);
        $stmt_delete->close();
        $conn->close();
        exit();
    }
    $stmt_delete->close();
} else {
    // Si no ha dado "Me gusta", agregarlo
    $stmt_insert = $conn->prepare("INSERT INTO likes (id_usuario, id_publicacion) VALUES (?, ?)");
    $stmt_insert->bind_param("ii", $id_usuario, $id_publicacion);
    if ($stmt_insert->execute()) {
        $action = 'liked';
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al dar Me gusta: ' . $stmt_insert->error]);
        $stmt_insert->close();
        $conn->close();
        exit();
    }
    $stmt_insert->close();
}

// Contar el total de "Me gusta" después de la operación
$stmt_count = $conn->prepare("SELECT COUNT(*) AS total_likes FROM likes WHERE id_publicacion = ?");
$stmt_count->bind_param("i", $id_publicacion);
$stmt_count->execute();
$resultado_count = $stmt_count->get_result();
$total_likes = $resultado_count->fetch_assoc()['total_likes'];
$stmt_count->close();

echo json_encode(['success' => true, 'action' => $action, 'total_likes' => $total_likes]);

$conn->close();
?>