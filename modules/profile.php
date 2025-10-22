<?php
use App\Database;
use App\Auth;

$conn = Database::connection();
$user = Auth::user();

$validSections = ['name', 'password', 'dashboard'];
$section = $_GET['section'] ?? 'name';
if (!in_array($section, $validSections, true)) {
    $section = 'name';
}

$messages = ['name' => null, 'password' => null, 'dashboard' => null];
$errors = ['name' => null, 'password' => null, 'dashboard' => null];
$dashboardSettings = get_user_dashboard_settings($user['id']);

if (is_post()) {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        die('Token CSRF inválido.');
    }

    if (isset($_POST['update_name'])) {
        $newName = trim($_POST['name'] ?? '');
        if ($newName === '') {
            $errors['name'] = 'El nombre no puede estar vacío.';
        } else {
            $stmt = $conn->prepare('UPDATE users SET name = :name WHERE id = :id');
            $stmt->execute(['name' => $newName, 'id' => $user['id']]);
            $messages['name'] = 'Nombre actualizado correctamente.';
            $user = Auth::user();
        }
        $section = 'name';
    } elseif (isset($_POST['change_password'])) {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if ($new !== $confirm) {
            $errors['password'] = 'La confirmación no coincide.';
        } elseif (strlen($new) < 8) {
            $errors['password'] = 'La nueva contraseña debe tener al menos 8 caracteres.';
        } else {
            $stmt = $conn->prepare('SELECT password FROM users WHERE id = :id');
            $stmt->execute(['id' => $user['id']]);
            $row = $stmt->fetch();
            if (!$row || !password_verify($current, $row['password'])) {
                $errors['password'] = 'La contraseña actual no es válida.';
            } else {
                $stmt = $conn->prepare('UPDATE users SET password = :password WHERE id = :id');
                $stmt->execute(['password' => password_hash($new, PASSWORD_BCRYPT), 'id' => $user['id']]);
                $messages['password'] = 'Contraseña actualizada correctamente.';
            }
        }
        $section = 'password';
    } elseif (isset($_POST['dashboard_settings'])) {
        $layout = $_POST['layout'] ?? 'grid';
        $visible = $_POST['visible_modules'] ?? [];
        $dashboardSettings = save_user_dashboard_settings($user['id'], [
            'layout' => $layout,
            'visible_modules' => $visible,
        ]);
        $messages['dashboard'] = 'Preferencias de dashboard guardadas.';
        $section = 'dashboard';
    }
}

$moduleOptions = [
    'calendar' => 'Próximos eventos',
    'announcements' => 'Últimos anuncios',
    'documents' => 'Documentos recientes',
    'quick-links' => 'Accesos rápidos',
];
?>
<div class="row g-4">
    <div class="col-12 col-lg-4">
        <div class="module-card" id="profile-name">
            <h2 class="h5 mb-2">Modificar nombre</h2>
            <p class="text-muted">Personaliza cómo te verán otros usuarios dentro de la intranet.</p>
            <?php if ($messages['name']): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($messages['name']); ?></div>
            <?php endif; ?>
            <?php if ($errors['name']): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($errors['name']); ?></div>
            <?php endif; ?>
            <form method="post" class="vstack gap-3">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                <input type="hidden" name="update_name" value="1">
                <div>
                    <label class="form-label">Nombre de visualización</label>
                    <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                </div>
                <button class="btn btn-primary btn-neumorphic" type="submit">Guardar cambios</button>
            </form>
        </div>
    </div>
    <div class="col-12 col-lg-4">
        <div class="module-card" id="profile-password">
            <h2 class="h5 mb-2">Cambiar contraseña</h2>
            <p class="text-muted">Mantén segura tu cuenta actualizando periódicamente tu contraseña.</p>
            <?php if ($messages['password']): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($messages['password']); ?></div>
            <?php endif; ?>
            <?php if ($errors['password']): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($errors['password']); ?></div>
            <?php endif; ?>
            <form method="post" class="vstack gap-3">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                <input type="hidden" name="change_password" value="1">
                <div>
                    <label class="form-label">Contraseña actual</label>
                    <input type="password" name="current_password" class="form-control" required>
                </div>
                <div>
                    <label class="form-label">Nueva contraseña</label>
                    <input type="password" name="new_password" class="form-control" required>
                    <small class="text-muted">Debe tener al menos 8 caracteres.</small>
                </div>
                <div>
                    <label class="form-label">Confirmar nueva contraseña</label>
                    <input type="password" name="confirm_password" class="form-control" required>
                </div>
                <button class="btn btn-primary btn-neumorphic" type="submit">Actualizar contraseña</button>
            </form>
        </div>
    </div>
    <div class="col-12">
        <div class="module-card" id="profile-dashboard">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-3 gap-3">
                <div>
                    <h2 class="h5 mb-1">Personalizar dashboard</h2>
                    <p class="text-muted mb-0">Elige el diseño y los módulos que deseas ver en tu página principal.</p>
                </div>
            </div>
            <?php if ($messages['dashboard']): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($messages['dashboard']); ?></div>
            <?php endif; ?>
            <?php if ($errors['dashboard']): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($errors['dashboard']); ?></div>
            <?php endif; ?>
            <form method="post" class="row g-4 align-items-end">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                <input type="hidden" name="dashboard_settings" value="1">
                <div class="col-12 col-md-4">
                    <label class="form-label fw-semibold">Diseño</label>
                    <select name="layout" class="form-select">
                        <option value="grid" <?php echo $dashboardSettings['layout'] === 'grid' ? 'selected' : ''; ?>>Cuadrícula</option>
                        <option value="list" <?php echo $dashboardSettings['layout'] === 'list' ? 'selected' : ''; ?>>Lista</option>
                    </select>
                </div>
                <div class="col-12 col-md-8">
                    <label class="form-label fw-semibold">Módulos visibles</label>
                    <div class="d-flex flex-wrap gap-3">
                        <?php foreach ($moduleOptions as $key => $label): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="visible_modules[]" value="<?php echo $key; ?>" id="dashboard-<?php echo $key; ?>" <?php echo in_array($key, $dashboardSettings['visible_modules'], true) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="dashboard-<?php echo $key; ?>"><?php echo $label; ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="col-12 text-end">
                    <button class="btn btn-primary btn-neumorphic" type="submit">Guardar preferencias</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php if (in_array($section, $validSections, true)): ?>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const target = document.getElementById('profile-<?php echo $section; ?>');
        if (target) {
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            target.classList.add('profile-highlight');
            setTimeout(() => target.classList.remove('profile-highlight'), 2000);
        }
    });
</script>
<?php endif; ?>
