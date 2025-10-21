<?php
use App\Database;
use App\Auth;

$conn = Database::connection();
$canManage = Auth::canManageContent();

if (is_post() && $canManage) {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        die('Token CSRF inválido.');
    }
    $id = $_POST['id'] ?? null;
    $title = trim($_POST['title'] ?? '');
    $date = $_POST['date'] ?? '';
    $description = trim($_POST['description'] ?? '');

    if ($title && $date) {
        if ($id) {
            $stmt = $conn->prepare('UPDATE events SET title = :title, date = :date, description = :description WHERE id = :id');
            $stmt->execute(['title' => $title, 'date' => $date, 'description' => $description, 'id' => $id]);
            $message = 'Evento actualizado correctamente.';
        } else {
            $stmt = $conn->prepare('INSERT INTO events (title, date, description, created_by) VALUES (:title, :date, :description, :user_id)');
            $stmt->execute(['title' => $title, 'date' => $date, 'description' => $description, 'user_id' => Auth::user()['id']]);
            $message = 'Evento creado correctamente.';
        }
    } else {
        $error = 'El título y la fecha son obligatorios.';
    }
}

if ($canManage && isset($_GET['action'], $_GET['id']) && verify_csrf($_GET['token'] ?? '')) {
    if ($_GET['action'] === 'delete') {
        $stmt = $conn->prepare('DELETE FROM events WHERE id = :id');
        $stmt->execute(['id' => $_GET['id']]);
        $message = 'Evento eliminado.';
    } elseif ($_GET['action'] === 'edit') {
        $stmt = $conn->prepare('SELECT * FROM events WHERE id = :id');
        $stmt->execute(['id' => $_GET['id']]);
        $editing = $stmt->fetch();
    }
}

$events = $conn->query('SELECT * FROM events ORDER BY date ASC')->fetchAll();
?>
<div class="row g-4">
    <div class="col-12 col-xl-4">
        <div class="module-card">
            <h2 class="h4 mb-3">Calendario de eventos</h2>
            <p class="text-muted">Visualiza los eventos importantes de la organización.</p>
            <?php if (!empty($message)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($canManage): ?>
                <h5 class="mt-4 mb-3"><?php echo isset($editing) ? 'Editar evento' : 'Nuevo evento'; ?></h5>
                <form method="post" class="vstack gap-3">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                    <input type="hidden" name="id" value="<?php echo $editing['id'] ?? ''; ?>">
                    <div>
                        <label class="form-label">Título</label>
                        <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($editing['title'] ?? ''); ?>" required>
                    </div>
                    <div>
                        <label class="form-label">Fecha</label>
                        <input type="date" name="date" class="form-control" value="<?php echo htmlspecialchars($editing['date'] ?? ''); ?>" required>
                    </div>
                    <div>
                        <label class="form-label">Descripción</label>
                        <textarea name="description" class="form-control" rows="3"><?php echo htmlspecialchars($editing['description'] ?? ''); ?></textarea>
                    </div>
                    <div class="d-flex justify-content-between">
                        <?php if (isset($editing)): ?>
                            <a class="btn btn-outline-secondary" href="?module=calendar">Cancelar</a>
                        <?php endif; ?>
                        <button class="btn btn-primary btn-neumorphic" type="submit"><?php echo isset($editing) ? 'Actualizar' : 'Crear'; ?></button>
                    </div>
                </form>
            <?php else: ?>
                <div class="alert alert-info">Solo los publicadores y administradores pueden crear eventos.</div>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-12 col-xl-8">
        <div class="module-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">Listado de eventos</h5>
            </div>
            <div class="table-responsive table-neumorphic">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Título</th>
                            <th>Descripción</th>
                            <?php if ($canManage): ?><th class="text-end">Acciones</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($events as $event): ?>
                            <tr>
                                <td><?php echo format_date($event['date']); ?></td>
                                <td><?php echo htmlspecialchars($event['title']); ?></td>
                                <td><?php echo htmlspecialchars($event['description']); ?></td>
                                <?php if ($canManage): ?>
                                <td class="text-end">
                                    <a href="?module=calendar&action=edit&id=<?php echo $event['id']; ?>&token=<?php echo csrf_token(); ?>" class="btn btn-sm btn-outline-primary">Editar</a>
                                    <a href="?module=calendar&action=delete&id=<?php echo $event['id']; ?>&token=<?php echo csrf_token(); ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Deseas eliminar este evento?');">Eliminar</a>
                                </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($events)): ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">No hay eventos registrados.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
