<?php
use App\Database;
use App\Auth;

$conn = Database::connection();
$user = Auth::user();

// Load customization
$stmt = $conn->prepare('SELECT settings FROM user_customizations WHERE user_id = :user_id');
$stmt->execute(['user_id' => $user['id']]);
$settingsRow = $stmt->fetch();
$defaultSettings = [
    'layout' => 'grid',
    'visible_modules' => ['calendar', 'announcements', 'documents', 'quick-links']
];
$settings = $settingsRow ? json_decode($settingsRow['settings'], true) : $defaultSettings;

if (is_post() && isset($_POST['dashboard_settings'])) {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        die('Token CSRF inválido.');
    }
    $layout = $_POST['layout'] ?? 'grid';
    $visible = $_POST['visible_modules'] ?? [];
    $payload = json_encode([
        'layout' => in_array($layout, ['grid', 'list']) ? $layout : 'grid',
        'visible_modules' => array_values(array_intersect($visible, ['calendar', 'announcements', 'documents', 'quick-links']))
    ]);

    if ($settingsRow) {
        $stmt = $conn->prepare('UPDATE user_customizations SET settings = :settings WHERE user_id = :user_id');
    } else {
        $stmt = $conn->prepare('INSERT INTO user_customizations (user_id, settings) VALUES (:user_id, :settings)');
    }
    $stmt->execute(['user_id' => $user['id'], 'settings' => $payload]);
    $settings = json_decode($payload, true);
    $message = 'Preferencias actualizadas correctamente.';
}

// Fetch data for widgets
$events = $conn->query("SELECT id, title, date, description FROM events WHERE date >= CURDATE() ORDER BY date ASC LIMIT 5")->fetchAll();
$announcements = $conn->query("SELECT id, title, created_at FROM announcements ORDER BY created_at DESC LIMIT 5")->fetchAll();
$documents = $conn->query("SELECT documents.id, documents.name, folders.name AS folder_name, documents.updated_at FROM documents JOIN folders ON folders.id = documents.folder_id ORDER BY documents.updated_at DESC LIMIT 5")->fetchAll();
$quickLinks = $conn->query("SELECT id, title, url, target FROM quick_links ORDER BY created_at DESC LIMIT 6")->fetchAll();
?>
<div class="row g-4">
    <div class="col-12">
        <div class="module-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h2 class="h4 mb-0">Dashboard personalizado</h2>
                    <p class="text-muted mb-0">Configura los módulos que deseas ver</p>
                </div>
            </div>
            <?php if (!empty($message)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <form method="post" class="row g-3">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                <input type="hidden" name="dashboard_settings" value="1">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Diseño</label>
                    <select name="layout" class="form-select">
                        <option value="grid" <?php echo $settings['layout'] === 'grid' ? 'selected' : ''; ?>>Grid</option>
                        <option value="list" <?php echo $settings['layout'] === 'list' ? 'selected' : ''; ?>>Lista</option>
                    </select>
                </div>
                <div class="col-md-8">
                    <label class="form-label fw-semibold">Módulos visibles</label>
                    <div class="d-flex flex-wrap gap-3">
                        <?php
                        $moduleOptions = [
                            'calendar' => 'Próximos eventos',
                            'announcements' => 'Últimos anuncios',
                            'documents' => 'Documentos recientes',
                            'quick-links' => 'Enlaces rápidos'
                        ];
                        foreach ($moduleOptions as $key => $label):
                        ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="visible_modules[]" value="<?php echo $key; ?>" id="dash-<?php echo $key; ?>" <?php echo in_array($key, $settings['visible_modules'], true) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="dash-<?php echo $key; ?>"><?php echo $label; ?></label>
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
    <?php
    $layoutClass = $settings['layout'] === 'list' ? 'col-12' : 'col-md-6 col-xl-3';
    if (in_array('calendar', $settings['visible_modules'], true)):
    ?>
    <div class="<?php echo $layoutClass; ?>">
        <div class="module-card dashboard-widget">
            <div>
                <h5>Próximos eventos</h5>
                <ul class="list-unstyled mb-0">
                    <?php foreach ($events as $event): ?>
                        <li class="mb-3">
                            <div class="fw-semibold"><?php echo htmlspecialchars($event['title']); ?></div>
                            <small class="text-muted"><?php echo format_date($event['date']); ?></small>
                        </li>
                    <?php endforeach; ?>
                    <?php if (empty($events)): ?>
                        <li class="text-muted">No hay eventos programados.</li>
                    <?php endif; ?>
                </ul>
            </div>
            <a href="?module=calendar" class="btn btn-link p-0">Ver calendario</a>
        </div>
    </div>
    <?php endif; ?>
    <?php if (in_array('announcements', $settings['visible_modules'], true)): ?>
    <div class="<?php echo $layoutClass; ?>">
        <div class="module-card dashboard-widget">
            <div>
                <h5>Últimos anuncios</h5>
                <ul class="list-unstyled mb-0">
                    <?php foreach ($announcements as $item): ?>
                        <li class="mb-3">
                            <div class="fw-semibold"><?php echo htmlspecialchars($item['title']); ?></div>
                            <small class="text-muted"><?php echo format_datetime($item['created_at']); ?></small>
                        </li>
                    <?php endforeach; ?>
                    <?php if (empty($announcements)): ?>
                        <li class="text-muted">Sin anuncios recientes.</li>
                    <?php endif; ?>
                </ul>
            </div>
            <a href="?module=announcements" class="btn btn-link p-0">Ir al tablón</a>
        </div>
    </div>
    <?php endif; ?>
    <?php if (in_array('documents', $settings['visible_modules'], true)): ?>
    <div class="<?php echo $layoutClass; ?>">
        <div class="module-card dashboard-widget">
            <div>
                <h5>Documentos recientes</h5>
                <ul class="list-unstyled mb-0">
                    <?php foreach ($documents as $document): ?>
                        <li class="mb-3">
                            <div class="fw-semibold"><?php echo htmlspecialchars($document['name']); ?></div>
                            <small class="text-muted"><?php echo htmlspecialchars($document['folder_name']); ?> · <?php echo format_datetime($document['updated_at']); ?></small>
                        </li>
                    <?php endforeach; ?>
                    <?php if (empty($documents)): ?>
                        <li class="text-muted">Aún no se han cargado documentos.</li>
                    <?php endif; ?>
                </ul>
            </div>
            <a href="?module=documents" class="btn btn-link p-0">Abrir repositorio</a>
        </div>
    </div>
    <?php endif; ?>
    <?php if (in_array('quick-links', $settings['visible_modules'], true)): ?>
    <div class="<?php echo $layoutClass; ?>">
        <div class="module-card dashboard-widget">
            <div>
                <h5>Accesos rápidos</h5>
                <div class="d-flex flex-column gap-2">
                    <?php foreach ($quickLinks as $link): ?>
                        <a class="btn btn-outline-primary btn-neumorphic" href="<?php echo htmlspecialchars($link['url']); ?>" target="<?php echo htmlspecialchars($link['target']); ?>"><?php echo htmlspecialchars($link['title']); ?></a>
                    <?php endforeach; ?>
                    <?php if (empty($quickLinks)): ?>
                        <span class="text-muted">No hay accesos disponibles.</span>
                    <?php endif; ?>
                </div>
            </div>
            <a href="?module=quick-links" class="btn btn-link p-0">Gestionar enlaces</a>
        </div>
    </div>
    <?php endif; ?>
</div>
