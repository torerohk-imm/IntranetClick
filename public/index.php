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
$allowedModules = [
    'dashboard' => 'Dashboard',
    'calendar' => 'Calendario',
    'directory' => 'Directorio',
    'announcements' => 'Tablón de anuncios',
    'organigram' => 'Organigrama',
    'quick-links' => 'Botonera',
    'embedded' => 'Sitios embebidos',
    'documents' => 'Repositorio',
    'admin' => 'Administración',
];

if (!array_key_exists($module, $allowedModules)) {
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
            <?php foreach ($allowedModules as $key => $label): ?>
                <?php if ($key === 'admin' && !Auth::canManageUsers()) continue; ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo $module === $key ? 'active fw-semibold' : ''; ?>" href="?module=<?php echo urlencode($key); ?>"><?php echo htmlspecialchars($label); ?></a>
                </li>
            <?php endforeach; ?>
        </ul>
        <div class="d-flex align-items-center gap-3">
            <span class="badge-role"><?php echo htmlspecialchars($user['role_name']); ?></span>
            <div class="text-end">
                <div class="fw-semibold"><?php echo htmlspecialchars($user['name']); ?></div>
                <small class="text-muted"><?php echo htmlspecialchars($user['email']); ?></small>
            </div>
            <a href="logout.php" class="btn btn-outline-secondary btn-sm">Salir</a>
        </div>
    </div>
</nav>
<div class="container pb-5">
    <?php include __DIR__ . '/../modules/' . $module . '.php'; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
