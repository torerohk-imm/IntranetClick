<?php
use App\Database;
use App\Auth;

if (!Auth::canManageUsers()) {
    authorize_users();
}

$conn = Database::connection();
$roles = $conn->query('SELECT * FROM roles ORDER BY id ASC')->fetchAll();

function getSetting($key, $default = null)
{
    $stmt = Database::connection()->prepare('SELECT value FROM settings WHERE `key` = :key LIMIT 1');
    $stmt->execute(['key' => $key]);
    $row = $stmt->fetch();
    return $row ? $row['value'] : $default;
}

function setSetting($key, $value)
{
    $stmt = Database::connection()->prepare('INSERT INTO settings (`key`, value) VALUES (:key, :value) ON DUPLICATE KEY UPDATE value = VALUES(value)');
    $stmt->execute(['key' => $key, 'value' => $value]);
}

if (is_post()) {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        die('Token CSRF inválido.');
    }

    if (isset($_POST['create_user']) || isset($_POST['update_user'])) {
        $payload = [
            'name' => trim($_POST['name'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'role_id' => (int)($_POST['role_id'] ?? 0),
        ];
        $password = $_POST['password'] ?? '';
        if (!$payload['name'] || !filter_var($payload['email'], FILTER_VALIDATE_EMAIL)) {
            $error = 'Nombre y correo válidos son obligatorios.';
        } else {
            if (isset($_POST['create_user'])) {
                if (!$password) {
                    $error = 'La contraseña es obligatoria al crear un usuario.';
                } else {
                    $stmt = $conn->prepare('INSERT INTO users (name, email, password, role_id) VALUES (:name, :email, :password, :role_id)');
                    $stmt->execute($payload + ['password' => password_hash($password, PASSWORD_BCRYPT)]);
                    $message = 'Usuario creado correctamente.';
                }
            } else {
                $userId = (int)$_POST['id'];
                $stmt = $conn->prepare('UPDATE users SET name = :name, email = :email, role_id = :role_id WHERE id = :id');
                $stmt->execute($payload + ['id' => $userId]);
                if ($password) {
                    $stmt = $conn->prepare('UPDATE users SET password = :password WHERE id = :id');
                    $stmt->execute(['password' => password_hash($password, PASSWORD_BCRYPT), 'id' => $userId]);
                }
                $message = 'Usuario actualizado.';
            }
        }
    } elseif (isset($_POST['update_settings'])) {
        $name = trim($_POST['site_name'] ?? 'Intranet');
        $primary = $_POST['color_primary'] ?? '#4f46e5';
        $secondary = $_POST['color_secondary'] ?? '#0ea5e9';
        $background = $_POST['color_background'] ?? '#f1f5f9';
        $card = $_POST['color_card'] ?? '#ffffff';
        $logoPath = getSetting('site_logo');

        if (!empty($_FILES['site_logo']['name'])) {
            $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/svg+xml' => 'svg'];
            $type = mime_content_type($_FILES['site_logo']['tmp_name']);
            if (isset($allowed[$type])) {
                $filename = uniqid('logo_') . '.' . $allowed[$type];
                $destination = upload_dir('branding') . DIRECTORY_SEPARATOR . $filename;
                if (move_uploaded_file($_FILES['site_logo']['tmp_name'], $destination)) {
                    $logoPath = 'uploads/branding/' . $filename;
                }
            }
        }

        setSetting('site_name', $name);
        setSetting('color_primary', $primary);
        setSetting('color_secondary', $secondary);
        setSetting('color_background', $background);
        setSetting('color_card', $card);
        if ($logoPath) {
            setSetting('site_logo', $logoPath);
        }
        $message = 'Configuración actualizada.';
    }
}

if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'delete' && verify_csrf($_GET['token'] ?? '')) {
    $id = (int)$_GET['id'];
    if ($id !== Auth::user()['id']) {
        $stmt = $conn->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $message = 'Usuario eliminado.';
    } else {
        $error = 'No puedes eliminar tu propio usuario.';
    }
}

if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'edit') {
    $stmt = $conn->prepare('SELECT * FROM users WHERE id = :id');
    $stmt->execute(['id' => $_GET['id']]);
    $editing = $stmt->fetch();
}

$users = $conn->query('SELECT users.*, roles.name AS role_name FROM users JOIN roles ON roles.id = users.role_id ORDER BY users.created_at DESC')->fetchAll();
$settings = [
    'site_name' => getSetting('site_name', 'Intranet Corporativa'),
    'color_primary' => getSetting('color_primary', '#4f46e5'),
    'color_secondary' => getSetting('color_secondary', '#0ea5e9'),
    'color_background' => getSetting('color_background', '#f1f5f9'),
    'color_card' => getSetting('color_card', '#ffffff'),
    'site_logo' => getSetting('site_logo', ''),
];
?>
<div class="row g-4">
    <div class="col-12 col-xl-5">
        <div class="module-card">
            <h2 class="h4 mb-3">Gestión de usuarios</h2>
            <p class="text-muted">Administra cuentas, roles y restablece contraseñas.</p>
            <?php if (!empty($message)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="post" class="vstack gap-3">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                <?php if (isset($editing)): ?>
                    <input type="hidden" name="update_user" value="1">
                    <input type="hidden" name="id" value="<?php echo $editing['id']; ?>">
                <?php else: ?>
                    <input type="hidden" name="create_user" value="1">
                <?php endif; ?>
                <div>
                    <label class="form-label">Nombre completo</label>
                    <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($editing['name'] ?? ''); ?>" required>
                </div>
                <div>
                    <label class="form-label">Correo electrónico</label>
                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($editing['email'] ?? ''); ?>" required>
                </div>
                <div>
                    <label class="form-label">Rol</label>
                    <select name="role_id" class="form-select" required>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?php echo $role['id']; ?>" <?php echo (($editing['role_id'] ?? '') == $role['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($role['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label">Contraseña <?php echo isset($editing) ? '(dejar vacío para mantener)' : ''; ?></label>
                    <input type="password" name="password" class="form-control" <?php echo isset($editing) ? '' : 'required'; ?>>
                </div>
                <div class="d-flex justify-content-between">
                    <?php if (isset($editing)): ?>
                        <a href="?module=admin" class="btn btn-outline-secondary">Cancelar</a>
                        <button class="btn btn-primary btn-neumorphic" type="submit">Actualizar usuario</button>
                    <?php else: ?>
                        <button class="btn btn-primary btn-neumorphic ms-auto" type="submit">Crear usuario</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    <div class="col-12 col-xl-7">
        <div class="module-card mb-4">
            <h5 class="mb-3">Usuarios existentes</h5>
            <div class="table-responsive table-neumorphic">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Correo</th>
                            <th>Rol</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $userRow): ?>
                            <tr>
                                <td class="fw-semibold"><?php echo htmlspecialchars($userRow['name']); ?></td>
                                <td><?php echo htmlspecialchars($userRow['email']); ?></td>
                                <td><?php echo htmlspecialchars($userRow['role_name']); ?></td>
                                <td class="text-end">
                                    <a href="?module=admin&action=edit&id=<?php echo $userRow['id']; ?>" class="btn btn-sm btn-outline-primary">Editar</a>
                                    <?php if ($userRow['id'] !== Auth::user()['id']): ?>
                                        <a href="?module=admin&action=delete&id=<?php echo $userRow['id']; ?>&token=<?php echo csrf_token(); ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Eliminar usuario?');">Eliminar</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">No hay usuarios registrados.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="module-card">
            <h5 class="mb-3">Personalización del sitio</h5>
            <form method="post" enctype="multipart/form-data" class="row g-3">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                <input type="hidden" name="update_settings" value="1">
                <div class="col-12">
                    <label class="form-label">Nombre de la intranet</label>
                    <input type="text" name="site_name" class="form-control" value="<?php echo htmlspecialchars($settings['site_name']); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Color primario</label>
                    <input type="color" name="color_primary" class="form-control form-control-color" value="<?php echo htmlspecialchars($settings['color_primary']); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Color secundario</label>
                    <input type="color" name="color_secondary" class="form-control form-control-color" value="<?php echo htmlspecialchars($settings['color_secondary']); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Color de fondo</label>
                    <input type="color" name="color_background" class="form-control form-control-color" value="<?php echo htmlspecialchars($settings['color_background']); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Color de tarjetas</label>
                    <input type="color" name="color_card" class="form-control form-control-color" value="<?php echo htmlspecialchars($settings['color_card']); ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Logotipo</label>
                    <input type="file" name="site_logo" class="form-control" accept="image/*">
                        <?php if (!empty($settings['site_logo'])): ?>
                            <img src="<?php echo htmlspecialchars(base_url($settings['site_logo'])); ?>" alt="Logo" class="img-fluid mt-2" style="max-height: 90px;">
                        <?php endif; ?>
                </div>
                <div class="col-12 text-end">
                    <button class="btn btn-primary btn-neumorphic" type="submit">Guardar personalización</button>
                </div>
            </form>
        </div>
    </div>
</div>
