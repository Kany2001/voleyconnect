<?php
session_start();
include 'conexion.php';

header('Content-Type: application/json');

// 1. Verificar autenticación
if (!isset($_SESSION['id_usuario'])) {
    echo json_encode(['success' => false, 'message' => 'Usuario no autenticado.']);
    exit();
}

$id_usuario = $_SESSION['id_usuario'];
$id_club = isset($_POST['id_club']) ? intval($_POST['id_club']) : 0;

// 2. Validar el ID del club
if ($id_club <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de club inválido.']);
    exit();
}

// 3. Verificar si el usuario ya es miembro del club
$stmt_check = $conn->prepare("SELECT id_miembro FROM clubes_miembros WHERE id_usuario = ? AND id_club = ?");
$stmt_check->bind_param("ii", $id_usuario, $id_club);
$stmt_check->execute();
$resultado_check = $stmt_check->get_result();

if ($resultado_check->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Ya eres miembro de este club.']);
    exit();
}

// 4. Obtener la configuración del club (membresia_abierta)
$stmt_club = $conn->prepare("SELECT membresia_abierta, nombre_oficial, id_usuario FROM clubes WHERE id_club = ?");
$stmt_club->bind_param("i", $id_club);
$stmt_club->execute();
$resultado_club = $stmt_club->get_result();

if ($resultado_club->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Club no encontrado.']);
    exit();
}

$club_data = $resultado_club->fetch_assoc();
$membresia_abierta = $club_data['membresia_abierta'];
$id_dueno_club = $club_data['id_usuario'];

// 5. Procesar la solicitud de unión
$action = '';
if ($membresia_abierta) {
    // Membresía abierta: Unir directamente
    $stmt_insert = $conn->prepare("INSERT INTO clubes_miembros (id_club, id_usuario) VALUES (?, ?)");
    $stmt_insert->bind_param("ii", $id_club, $id_usuario);
    
    // Y actualizar la columna id_club en la tabla usuarios para que el usuario "pertenezca" a un club
    $stmt_update_user = $conn->prepare("UPDATE usuarios SET id_club = ? WHERE id_usuario = ?");
    $stmt_update_user->bind_param("ii", $id_club, $id_usuario);

    if ($stmt_insert->execute() && $stmt_update_user->execute()) {
        $action = 'joined';
        $message = 'Te has unido al club ' . htmlspecialchars($club_data['nombre_oficial']) . ' con éxito.';
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al unirte al club: ' . $conn->error]);
        exit();
    }
} else {
    // Membresía cerrada: Notificar al dueño del club (futura implementación)
    // Por ahora, solo informaremos al usuario.
    $action = 'requested';
    $message = 'Tu solicitud para unirte al club ha sido enviada para aprobación.';
    
    // Futuro: Aquí puedes insertar una fila en una tabla de 'solicitudes_membresia'
    // Y crear una notificación para el dueño del club.
}

// 6. Devolver una respuesta JSON
echo json_encode([
    'success' => true,
    'action' => $action,
    'message' => $message
]);

$conn->close();
?>