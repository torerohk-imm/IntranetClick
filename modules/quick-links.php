<?php
use App\Database;
use App\Auth;

$conn = Database::connection();
$canManage = Auth::canManageContent();

if ($canManage && is_post()) {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        die('Token CSRF inválido.');
    }
    $id = $_POST['id'] ?? null;
    $payload = [
        'title' => trim($_POST['title'] ?? ''),
        'url' => trim($_POST['url'] ?? ''),
        'target' => in_array($_POST['target'] ?? '_self', ['_self', '_blank'], true) ? $_POST['target'] : '_self',
        'icon' => trim($_POST['icon'] ?? ''),
    ];
    if ($payload['title'] && $payload['url']) {
        if ($id) {
            $payload['id'] = $id;
            $stmt = $conn->prepare('UPDATE quick_links SET title = :title, url = :url, target = :target, icon = :icon WHERE id = :id');
            $stmt->execute($payload);
            $message = 'Enlace actualizado.';
        } else {
            $stmt = $conn->prepare('INSERT INTO quick_links (title, url, target, icon, created_by) VALUES (:title, :url, :target, :icon, :created_by)');
            $stmt->execute($payload + ['created_by' => Auth::user()['id']]);
            $message = 'Enlace creado.';
        }
    } else {
        $error = 'El título y la URL son obligatorios.';
    }
}

if ($canManage && isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'delete' && verify_csrf($_GET['token'] ?? '')) {
    $stmt = $conn->prepare('DELETE FROM quick_links WHERE id = :id');
    $stmt->execute(['id' => $_GET['id']]);
    $message = 'Enlace eliminado.';
}

if ($canManage && isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'edit') {
    $stmt = $conn->prepare('SELECT * FROM quick_links WHERE id = :id');
    $stmt->execute(['id' => $_GET['id']]);
    $editing = $stmt->fetch();
}

$links = $conn->query('SELECT * FROM quick_links ORDER BY created_at DESC')->fetchAll();
?>
<div class="row g-4">
    <div class="col-12 col-lg-4">
        <div class="module-card">
            <h2 class="h4 mb-3">Botonera de enlaces rápidos</h2>
            <p class="text-muted">Centraliza accesos a herramientas externas o internas con un clic.</p>
            <?php if (!empty($message)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($canManage): ?>
                <form method="post" class="vstack gap-3">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                    <input type="hidden" name="id" value="<?php echo $editing['id'] ?? ''; ?>">
                    <div>
                        <label class="form-label">Título</label>
                        <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($editing['title'] ?? ''); ?>" required>
                    </div>
                    <div>
                        <label class="form-label">URL</label>
                        <input type="url" name="url" class="form-control" value="<?php echo htmlspecialchars($editing['url'] ?? ''); ?>" required>
                    </div>
                    <div>
                        <label class="form-label">Destino</label>
                        <select name="target" class="form-select">
                            <option value="_self" <?php echo (($editing['target'] ?? '_self') === '_self') ? 'selected' : ''; ?>>Abrir en la misma pestaña</option>
                            <option value="_blank" <?php echo (($editing['target'] ?? '') === '_blank') ? 'selected' : ''; ?>>Abrir en nueva pestaña</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Icono (opcional)</label>
                        <input type="text" name="icon" class="form-control" placeholder="Ej. bi bi-link-45deg" value="<?php echo htmlspecialchars($editing['icon'] ?? ''); ?>">
                        <small class="text-muted">Compatible con <a href="https://icons.getbootstrap.com/" target="_blank">Bootstrap Icons</a>.</small>
                    </div>
                    <div class="d-flex justify-content-between">
                        <?php if (isset($editing)): ?>
                            <a href="?module=quick-links" class="btn btn-outline-secondary">Cancelar</a>
                        <?php endif; ?>
                        <button class="btn btn-primary btn-neumorphic" type="submit"><?php echo isset($editing) ? 'Actualizar' : 'Crear'; ?></button>
                    </div>
                </form>
            <?php else: ?>
                <div class="alert alert-info">Solo publicadores y administradores pueden administrar enlaces.</div>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-12 col-lg-8">
        <div class="module-card">
            <h5 class="mb-4">Accesos disponibles</h5>
            <div class="quick-links">
                <?php foreach ($links as $link): ?>
                    <a class="quick-link-item text-decoration-none" href="<?php echo htmlspecialchars($link['url']); ?>" target="<?php echo htmlspecialchars($link['target']); ?>">
                        <?php if (!empty($link['icon'])): ?>
                            <i class="<?php echo htmlspecialchars($link['icon']); ?> fs-2 mb-2"></i>
                        <?php endif; ?>
                        <h6 class="fw-semibold"><?php echo htmlspecialchars($link['title']); ?></h6>
                        <span class="small text-muted"><?php echo htmlspecialchars($link['url']); ?></span>
                        <?php if ($canManage): ?>
                            <div class="mt-3 d-flex gap-2 justify-content-center">
                                <a href="?module=quick-links&action=edit&id=<?php echo $link['id']; ?>" class="btn btn-sm btn-outline-primary">Editar</a>
                                <a href="?module=quick-links&action=delete&id=<?php echo $link['id']; ?>&token=<?php echo csrf_token(); ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Eliminar enlace?');">Eliminar</a>
                            </div>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
                <?php if (empty($links)): ?>
                    <div class="text-muted">No se han agregado enlaces aún.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
