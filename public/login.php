<?php
session_start();

require __DIR__ . '/../vendor/autoload.php';

use App\Auth;

$error = null;

if (is_post()) {
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $error = 'Debes ingresar un correo electrónico válido y contraseña.';
    } elseif (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Token CSRF inválido, intenta nuevamente.';
    } elseif (Auth::attempt($email, $password)) {
        redirect('index.php');
    } else {
        $error = 'Credenciales incorrectas.';
    }
}

$config = app_settings();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión - <?php echo htmlspecialchars($config['name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/styles.css" rel="stylesheet">
    <style><?php echo theme_styles(); ?></style>
</head>
<body class="d-flex align-items-center justify-content-center" style="min-height: 100vh;">
    <div class="card p-4" style="min-width: 360px;">
        <div class="text-center mb-4">
            <?php if (!empty($config['brand_logo'])): ?>
                <img src="<?php echo htmlspecialchars($config['brand_logo']); ?>" alt="Logo" style="height: 48px;" class="mb-2">
            <?php endif; ?>
            <h1 class="h4 fw-bold"><?php echo htmlspecialchars($config['name']); ?></h1>
            <p class="text-muted">Accede con tus credenciales</p>
        </div>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="post" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
            <div class="mb-3">
                <label for="email" class="form-label">Correo electrónico</label>
                <input type="email" class="form-control" id="email" name="email" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Contraseña</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary w-100 btn-neumorphic">Ingresar</button>
        </form>
    </div>
</body>
</html>
