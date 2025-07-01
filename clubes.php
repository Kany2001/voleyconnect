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

$clubes = [];
$mensaje_feedback = '';
$tipo_mensaje = '';

// Variables para búsqueda y filtro
$search_query = isset($_GET['search']) ? $conn->real_escape_string(trim($_GET['search'])) : '';
$filter_ciudad = isset($_GET['ciudad']) ? $conn->real_escape_string(trim($_GET['ciudad'])) : '';

// Construir la consulta base
$query_clubes = "
    SELECT
        c.id_club,
        c.nombre_oficial,
        c.ciudad,
        u.nombre_usuario,
        u.foto_perfil_url,
        u.biografia
    FROM clubes c
    JOIN usuarios u ON c.id_usuario = u.id_usuario
    WHERE 1=1 -- Condición siempre verdadera para facilitar añadir AND
";

// Añadir condición de búsqueda si se proporcionó un término
if (!empty($search_query)) {
    $query_clubes .= " AND (c.nombre_oficial LIKE '%$search_query%' OR u.biografia LIKE '%$search_query%')";
}

// Añadir condición de filtro por ciudad si se seleccionó una
if (!empty($filter_ciudad)) {
    $query_clubes .= " AND c.ciudad = '$filter_ciudad'";
}

$query_clubes .= " ORDER BY c.nombre_oficial ASC";

$resultado_clubes = $conn->query($query_clubes);

if ($resultado_clubes) {
    if ($resultado_clubes->num_rows > 0) {
        while ($fila = $resultado_clubes->fetch_assoc()) {
            $clubes[] = $fila;
        }
    } else {
        $mensaje_feedback = "No se encontraron clubes con los criterios de búsqueda/filtro.";
        $tipo_mensaje = "info";
    }
} else {
    $mensaje_feedback = "Error al cargar los clubes: " . $conn->error;
    $tipo_mensaje = "danger";
}

// Obtener una lista de ciudades únicas para el filtro dropdown
$ciudades = [];
$query_ciudades = "SELECT DISTINCT ciudad FROM clubes ORDER BY ciudad ASC";
$resultado_ciudades = $conn->query($query_ciudades);
if ($resultado_ciudades && $resultado_ciudades->num_rows > 0) {
    while ($fila_ciudad = $resultado_ciudades->fetch_assoc()) {
        $ciudades[] = $fila_ciudad['ciudad'];
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clubes - VoleyConnect</title>
    <link rel="stylesheet" href="css/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">VoleyConnect</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">Inicio</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="perfil.php">Perfil</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" aria-current="page" href="clubes.php">Clubes</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="eventos.php">Eventos</a>
                    </li>
                     <li class="nav-item">
                        <a class="nav-link" href="mensajes.php">Mensajes</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <?php echo htmlspecialchars($nombre_usuario_logueado); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                            <li><a class="dropdown-item" href="perfil.php">Ver Perfil</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php">Cerrar Sesión</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-5 pt-4">
        <div class="row justify-content-center">
            <div class="col-md-9">
                <h1 class="my-4 text-center">Explora Clubes de Voleibol</h1>

                <?php if (!empty($mensaje_feedback)): ?>
                    <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($mensaje_feedback); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="card mb-4 p-3">
                    <form action="clubes.php" method="GET" class="row g-3 align-items-center">
                        <div class="col-md-6">
                            <label for="search_input" class="visually-hidden">Buscar Club</label>
                            <input type="text" class="form-control" id="search_input" name="search" placeholder="Buscar por nombre o biografía..." value="<?php echo htmlspecialchars($search_query); ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="filter_ciudad" class="visually-hidden">Filtrar por Ciudad</label>
                            <select class="form-select" id="filter_ciudad" name="ciudad">
                                <option value="">Todas las ciudades</option>
                                <?php foreach ($ciudades as $ciudad_option): ?>
                                    <option value="<?php echo htmlspecialchars($ciudad_option); ?>" <?php echo ($filter_ciudad === $ciudad_option) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($ciudad_option); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex">
                            <button type="submit" class="btn btn-primary w-100 me-2"><i class="fas fa-search me-1"></i>Buscar</button>
                            <?php if (!empty($search_query) || !empty($filter_ciudad)): ?>
                                <a href="clubes.php" class="btn btn-outline-secondary w-100"><i class="fas fa-times me-1"></i>Limpiar</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>


                <div class="row">
                    <?php if (!empty($clubes)): ?>
                        <?php foreach ($clubes as $club): ?>
                            <div class="col-lg-6 mb-4">
                                <div class="card h-100">
                                    <div class="card-body d-flex align-items-center">
                                        <img src="<?php echo htmlspecialchars($club['foto_perfil_url'] ?: 'img/default-avatar.png'); ?>" alt="Logo del Club" class="profile-pic me-3" style="width: 80px; height: 80px;">
                                        <div>
                                            <h5 class="card-title mb-1"><?php echo htmlspecialchars($club['nombre_oficial']); ?></h5>
                                            <p class="card-subtitle text-muted mb-2"><i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($club['ciudad']); ?></p>
                                            <p class="card-text text-truncate mb-2"><?php echo htmlspecialchars($club['biografia'] ?: 'Club de voleibol.'); ?></p>
                                            <a href="ver_club.php?id=<?php echo htmlspecialchars($club['id_club']); ?>" class="btn btn-sm btn-outline-primary">Ver Perfil del Club</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-12 text-center">
                            <p class="lead mt-5"><?php echo htmlspecialchars($mensaje_feedback); ?></p>
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