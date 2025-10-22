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

$monthParam = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $monthParam)) {
    $monthParam = date('Y-m');
}

$monthDate = DateTime::createFromFormat('Y-m', $monthParam) ?: new DateTime('first day of this month');
$monthDate->setDate((int)$monthDate->format('Y'), (int)$monthDate->format('m'), 1);
$monthKey = $monthDate->format('Y-m');

$monthNames = [
    1 => 'enero',
    2 => 'febrero',
    3 => 'marzo',
    4 => 'abril',
    5 => 'mayo',
    6 => 'junio',
    7 => 'julio',
    8 => 'agosto',
    9 => 'septiembre',
    10 => 'octubre',
    11 => 'noviembre',
    12 => 'diciembre',
];

$monthLabel = $monthNames[(int)$monthDate->format('n')] . ' ' . $monthDate->format('Y');

$prevMonth = (clone $monthDate)->modify('-1 month')->format('Y-m');
$nextMonth = (clone $monthDate)->modify('+1 month')->format('Y-m');

$startOfGrid = clone $monthDate;
$dayOfWeek = (int)$startOfGrid->format('N');
$startOfGrid->modify('-' . ($dayOfWeek - 1) . ' days');
$endOfGrid = clone $startOfGrid;
$endOfGrid->modify('+41 days');

$stmt = $conn->prepare('SELECT id, title, date, description FROM events WHERE date BETWEEN :start AND :end ORDER BY date ASC');
$stmt->execute([
    'start' => $startOfGrid->format('Y-m-d'),
    'end' => $endOfGrid->format('Y-m-d'),
]);
$calendarEvents = $stmt->fetchAll();

$eventsByDate = [];
foreach ($calendarEvents as $eventRow) {
    $eventsByDate[$eventRow['date']][] = $eventRow;
}

$monthlyEvents = array_values(array_filter($calendarEvents, function ($row) use ($monthKey) {
    return substr($row['date'], 0, 7) === $monthKey;
}));

$weekdays = ['LUNES', 'MARTES', 'MIÉRCOLES', 'JUEVES', 'VIERNES', 'SÁBADO', 'DOMINGO'];
$today = date('Y-m-d');
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
                            <a class="btn btn-outline-secondary" href="?module=calendar&month=<?php echo urlencode($monthKey); ?>">Cancelar</a>
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
        <div class="module-card calendar-shell">
            <div class="calendar-surface">
                <div class="calendar-header">
                    <a class="calendar-nav-btn" href="?module=calendar&month=<?php echo urlencode($prevMonth); ?>" aria-label="Mes anterior">&lsaquo;</a>
                    <div>
                        <span class="calendar-month"><?php echo htmlspecialchars($monthLabel); ?></span>
                    </div>
                    <a class="calendar-nav-btn" href="?module=calendar&month=<?php echo urlencode($nextMonth); ?>" aria-label="Mes siguiente">&rsaquo;</a>
                </div>
                <div class="calendar-weekdays">
                    <?php foreach ($weekdays as $dayLabel): ?>
                        <span><?php echo $dayLabel; ?></span>
                    <?php endforeach; ?>
                </div>
                <div class="calendar-grid">
                    <?php
                    $cursor = clone $startOfGrid;
                    for ($i = 0; $i < 42; $i++):
                        $dateKey = $cursor->format('Y-m-d');
                        $classes = [];
                        if ($cursor->format('Y-m') !== $monthKey) {
                            $classes[] = 'muted';
                        }
                        if ($dateKey === $today) {
                            $classes[] = 'today';
                        }
                    ?>
                    <div class="calendar-day <?php echo implode(' ', $classes); ?>">
                        <span class="calendar-day-number"><?php echo $cursor->format('j'); ?></span>
                        <?php if (!empty($eventsByDate[$dateKey])): ?>
                            <?php foreach ($eventsByDate[$dateKey] as $eventItem): ?>
                                <span class="calendar-event" title="<?php echo htmlspecialchars($eventItem['description']); ?>"><?php echo htmlspecialchars($eventItem['title']); ?></span>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <?php
                        $cursor->modify('+1 day');
                    endfor;
                    ?>
                </div>
                <div class="calendar-events-list">
                    <h6 class="text-uppercase small fw-semibold text-danger mb-3">Eventos del mes</h6>
                    <ul class="list-unstyled mb-0">
                        <?php foreach ($monthlyEvents as $event): ?>
                            <li class="calendar-events-item">
                                <div>
                                    <span class="calendar-events-date"><?php echo format_date($event['date']); ?></span>
                                    <span class="calendar-events-title"><?php echo htmlspecialchars($event['title']); ?></span>
                                </div>
                                <?php if ($canManage): ?>
                                <div class="calendar-event-actions">
                                    <a href="?module=calendar&action=edit&id=<?php echo $event['id']; ?>&token=<?php echo htmlspecialchars(csrf_token()); ?>&month=<?php echo urlencode($monthKey); ?>" class="btn btn-sm btn-outline-primary">Editar</a>
                                    <a href="?module=calendar&action=delete&id=<?php echo $event['id']; ?>&token=<?php echo htmlspecialchars(csrf_token()); ?>&month=<?php echo urlencode($monthKey); ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Deseas eliminar este evento?');">Eliminar</a>
                                </div>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                        <?php if (empty($monthlyEvents)): ?>
                            <li class="text-muted">Este mes no tiene eventos registrados.</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
