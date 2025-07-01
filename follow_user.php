<?php
session_start();
include 'conexion.php';

header('Content-Type: application/json');

// 1. Verificar autenticación
if (!isset($_SESSION['id_usuario'])) {
    echo json_encode(['success' => false, 'message' => 'Usuario no autenticado.']);
    exit();
}

$id_seguidor = $_SESSION['id_usuario'];
$id_seguido = isset($_POST['id_seguido']) ? intval($_POST['id_seguido']) : 0;

// 2. Validar el ID del usuario a seguir
if ($id_seguido <= 0 || $id_seguido == $id_seguidor) {
    echo json_encode(['success' => false, 'message' => 'ID de usuario a seguir inválido o es tu propio perfil.']);
    exit();
}

$action = ''; // Para indicar si fue 'followed' o 'unfollowed'

// 3. Verificar si ya se sigue al usuario
$stmt_check = $conn->prepare("SELECT id_seguimiento FROM seguimientos WHERE id_seguidor = ? AND id_seguido = ?");
$stmt_check->bind_param("ii", $id_seguidor, $id_seguido);
$stmt_check->execute();
$resultado_check = $stmt_check->get_result();

if ($resultado_check->num_rows > 0) {
    // Si ya lo sigue, entonces dejar de seguir
    $stmt_delete = $conn->prepare("DELETE FROM seguimientos WHERE id_seguidor = ? AND id_seguido = ?");
    $stmt_delete->bind_param("ii", $id_seguidor, $id_seguido);
    if ($stmt_delete->execute()) {
        $action = 'unfollowed';
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al dejar de seguir: ' . $stmt_delete->error]);
        $stmt_delete->close();
        $conn->close();
        exit();
    }
    $stmt_delete->close();
} else {
    // Si no lo sigue, entonces seguir
    $stmt_insert = $conn->prepare("INSERT INTO seguimientos (id_seguidor, id_seguido) VALUES (?, ?)");
    $stmt_insert->bind_param("ii", $id_seguidor, $id_seguido);
    if ($stmt_insert->execute()) {
        $action = 'followed';
        // Futuro: Aquí se puede añadir la lógica para crear una notificación
        // `tipo_notificacion = 'seguimiento'`
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al seguir: ' . $stmt_insert->error]);
        $stmt_insert->close();
        $conn->close();
        exit();
    }
    $stmt_insert->close();
}

// 4. Contar el total de seguidores y seguidos después de la operación
$total_seguidores = 0;
$stmt_count_seguidores = $conn->prepare("SELECT COUNT(*) AS total_seguidores FROM seguimientos WHERE id_seguido = ?");
$stmt_count_seguidores->bind_param("i", $id_seguido);
$stmt_count_seguidores->execute();
$resultado_count_seguidores = $stmt_count_seguidores->get_result();
if ($fila = $resultado_count_seguidores->fetch_assoc()) {
    $total_seguidores = $fila['total_seguidores'];
}
$stmt_count_seguidores->close();

$total_seguidos = 0;
$stmt_count_seguidos = $conn->prepare("SELECT COUNT(*) AS total_seguidos FROM seguimientos WHERE id_seguidor = ?");
$stmt_count_seguidos->bind_param("i", $id_seguidor);
$stmt_count_seguidos->execute();
$resultado_count_seguidos = $stmt_count_seguidos->get_result();
if ($fila = $resultado_count_seguidos->fetch_assoc()) {
    $total_seguidos = $fila['total_seguidos'];
}
$stmt_count_seguidos->close();

// 5. Devolver una respuesta JSON
echo json_encode([
    'success' => true,
    'action' => $action,
    'total_seguidores' => $total_seguidores,
    'total_seguidos' => $total_seguidos
]);

$conn->close();
?>