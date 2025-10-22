<?php
session_start();

require __DIR__ . '/../vendor/autoload.php';

use App\Auth;

if (!Auth::check()) {
    header('Location: login.php');
    exit;
}

$user = Auth::user();
$config = app_settings();

$module = $_GET['module'] ?? 'dashboard';
$modules = [
    'dashboard' => ['label' => 'Dashboard', 'nav' => true],
    'calendar' => ['label' => 'Calendario', 'nav' => true],
    'directory' => ['label' => 'Directorio', 'nav' => true],
    'announcements' => ['label' => 'Tablón de anuncios', 'nav' => true],
    'organigram' => ['label' => 'Organigrama', 'nav' => true],
    'quick-links' => ['label' => 'Botonera', 'nav' => true],
    'embedded' => ['label' => 'Sitios embebidos', 'nav' => true],
    'documents' => ['label' => 'Repositorio', 'nav' => true],
    'admin' => ['label' => 'Administración', 'nav' => true],
    'profile' => ['label' => 'Preferencias', 'nav' => false],
];

if (!array_key_exists($module, $modules)) {
    $module = 'dashboard';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($config['name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/styles.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style><?php echo theme_styles(); ?></style>
</head>
<body>
<nav class="navbar navbar-expand-lg px-4 py-3 mb-4">
    <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="index.php">
        <?php if (!empty($config['brand_logo'])): ?>
            <img src="<?php echo htmlspecialchars($config['brand_logo']); ?>" alt="Logo" style="height: 36px;">
        <?php endif; ?>
        <span><?php echo htmlspecialchars($config['name']); ?></span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
        <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav me-auto">
            <?php foreach ($modules as $key => $meta): ?>
                <?php if (!$meta['nav']) continue; ?>
                <?php if ($key === 'admin' && !Auth::canManageUsers()) continue; ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo $module === $key ? 'active fw-semibold' : ''; ?>" href="?module=<?php echo urlencode($key); ?>"><?php echo htmlspecialchars($meta['label']); ?></a>
                </li>
            <?php endforeach; ?>
        </ul>
        <div class="dropdown ms-lg-auto">
            <button class="btn btn-profile dropdown-toggle d-flex align-items-center gap-3" type="button" id="profileMenu" data-bs-toggle="dropdown" aria-expanded="false">
                <div class="text-end">
                    <span class="fw-semibold d-block"><?php echo htmlspecialchars($user['name']); ?></span>
                    <small class="text-muted d-block"><?php echo htmlspecialchars($user['email']); ?></small>
                </div>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow profile-menu" aria-labelledby="profileMenu">
                <li class="px-3 py-2">
                    <div class="fw-semibold"><?php echo htmlspecialchars($user['name']); ?></div>
                    <small class="text-muted d-block mb-1"><?php echo htmlspecialchars($user['email']); ?></small>
                    <span class="badge-role"><?php echo htmlspecialchars($user['role_name']); ?></span>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="?module=profile&section=password#profile-password">Cambiar contraseña</a></li>
                <li><a class="dropdown-item" href="?module=profile&section=name#profile-name">Modificar nombre</a></li>
                <li><a class="dropdown-item" href="?module=profile&section=dashboard#profile-dashboard">Personalizar dashboard</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="logout.php">Cerrar sesión</a></li>
            </ul>
        </div>
    </div>
</nav>
<div class="container pb-5">
    <?php include __DIR__ . '/../modules/' . $module . '.php'; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
