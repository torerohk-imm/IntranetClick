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
        'layout' => in_array($_POST['layout'] ?? 'grid-2', ['grid-1', 'grid-2', 'grid-3'], true) ? $_POST['layout'] : 'grid-2',
        'height' => max(200, min(1200, (int)($_POST['height'] ?? 480))),
    ];

    if ($payload['title'] && $payload['url']) {
        if ($id) {
            $payload['id'] = $id;
            $stmt = $conn->prepare('UPDATE embedded_sites SET title = :title, url = :url, layout = :layout, height = :height WHERE id = :id');
            $stmt->execute($payload);
            $message = 'Sitio actualizado.';
        } else {
            $stmt = $conn->prepare('INSERT INTO embedded_sites (title, url, layout, height, created_by) VALUES (:title, :url, :layout, :height, :created_by)');
            $stmt->execute($payload + ['created_by' => Auth::user()['id']]);
            $message = 'Sitio agregado.';
        }
    } else {
        $error = 'El título y la URL son obligatorios.';
    }
}

if ($canManage && isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'delete' && verify_csrf($_GET['token'] ?? '')) {
    $stmt = $conn->prepare('DELETE FROM embedded_sites WHERE id = :id');
    $stmt->execute(['id' => $_GET['id']]);
    $message = 'Sitio eliminado.';
}

if ($canManage && isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'edit') {
    $stmt = $conn->prepare('SELECT * FROM embedded_sites WHERE id = :id');
    $stmt->execute(['id' => $_GET['id']]);
    $editing = $stmt->fetch();
}

$sites = $conn->query('SELECT * FROM embedded_sites ORDER BY created_at DESC')->fetchAll();
$gridClass = !empty($sites) ? ($sites[0]['layout'] ?? 'grid-2') : 'grid-2';
?>
<div class="row g-4">
    <div class="col-12 col-lg-4">
        <div class="module-card">
            <h2 class="h4 mb-3">Sitios embebidos</h2>
            <p class="text-muted">Incorpora dashboards externos, métricas o contenidos web dentro de la intranet.</p>
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
                        <label class="form-label">Distribución</label>
                        <select name="layout" class="form-select">
                            <?php $selectedLayout = $editing['layout'] ?? 'grid-2'; ?>
                            <option value="grid-1" <?php echo $selectedLayout === 'grid-1' ? 'selected' : ''; ?>>Una columna</option>
                            <option value="grid-2" <?php echo $selectedLayout === 'grid-2' ? 'selected' : ''; ?>>Dos columnas</option>
                            <option value="grid-3" <?php echo $selectedLayout === 'grid-3' ? 'selected' : ''; ?>>Tres columnas</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Altura (px)</label>
                        <input type="number" name="height" class="form-control" min="200" max="1200" value="<?php echo htmlspecialchars($editing['height'] ?? 480); ?>">
                    </div>
                    <div class="d-flex justify-content-between">
                        <?php if (isset($editing)): ?>
                            <a href="?module=embedded" class="btn btn-outline-secondary">Cancelar</a>
                        <?php endif; ?>
                        <button class="btn btn-primary btn-neumorphic" type="submit"><?php echo isset($editing) ? 'Actualizar' : 'Agregar'; ?></button>
                    </div>
                </form>
            <?php else: ?>
                <div class="alert alert-info">Solicita permisos de publicador para administrar los sitios embebidos.</div>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-12 col-lg-8">
        <div class="module-card">
            <h5 class="mb-4">Vista previa</h5>
            <?php if (!empty($sites)): ?>
                <div class="iframe-grid <?php echo htmlspecialchars($sites[0]['layout'] ?? 'grid-2'); ?>">
                    <?php foreach ($sites as $site): ?>
                        <div class="card p-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="mb-0"><?php echo htmlspecialchars($site['title']); ?></h6>
                                <?php if ($canManage): ?>
                                    <div class="d-flex gap-2">
                                        <a href="?module=embedded&action=edit&id=<?php echo $site['id']; ?>" class="btn btn-sm btn-outline-primary">Editar</a>
                                        <a href="?module=embedded&action=delete&id=<?php echo $site['id']; ?>&token=<?php echo csrf_token(); ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Eliminar sitio?');">Eliminar</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <iframe src="<?php echo htmlspecialchars($site['url']); ?>" class="w-100" style="border-radius: 12px; border: none; height: <?php echo (int)$site['height']; ?>px;" loading="lazy"></iframe>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center text-muted py-5">Agrega sitios embebidos para visualizar contenido externo.</div>
            <?php endif; ?>
        </div>
    </div>
</div>
