<?php
// Inicia la sesión si no está ya iniciada.
// Esto es crucial para acceder a las variables de sesión como $_SESSION['id_usuario'].
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Variables para la barra de navegación. Se inicializan con valores por defecto.
$is_logged_in = isset($_SESSION['id_usuario']);
$nombre_usuario_nav = '';
$foto_perfil_url_nav = 'img/default-avatar.png'; // Foto por defecto
$id_usuario_nav = '';
$tipo_usuario_nav = '';

// Si el usuario está autenticado, recupera sus datos de la sesión.
if ($is_logged_in) {
    $nombre_usuario_nav = htmlspecialchars($_SESSION['nombre_usuario'] ?? 'Usuario');
    $foto_perfil_url_nav = htmlspecialchars($_SESSION['foto_perfil_url'] ?? 'img/default-avatar.png');
    $id_usuario_nav = htmlspecialchars($_SESSION['id_usuario'] ?? '');
    $tipo_usuario_nav = htmlspecialchars($_SESSION['tipo_usuario'] ?? 'usuario_normal');
}
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="dashboard.php">VoleyConnect</a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto align-items-center">
                <?php if ($is_logged_in): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">Inicio</a>
                    </li>
                    
                    <li class="nav-item me-3">
                        <a class="nav-link position-relative" href="mensajes.php" title="Mensajes">
                            <i class="fas fa-envelope fa-lg"></i>
                            </a>
                    </li>
                    
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <img src="<?php echo $foto_perfil_url_nav; ?>" alt="Avatar" class="rounded-circle me-2" style="width: 35px; height: 35px; object-fit: cover;">
                            <?php echo $nombre_usuario_nav; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                            <li>
                                <h6 class="dropdown-header text-truncate" style="max-width: 200px;"><?php echo $nombre_usuario_nav; ?></h6>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="perfil.php?id=<?php echo $id_usuario_nav; ?>&tipo=<?php echo $tipo_usuario_nav; ?>">Ver Perfil</a></li>
                            <li><a class="dropdown-item" href="editar_perfil.php">Editar Perfil</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php">Cerrar Sesión</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">Iniciar Sesión</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link btn btn-primary text-white ms-lg-2" href="registro.php">Regístrate</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
