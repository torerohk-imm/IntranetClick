<?php
use App\Database;
use App\Auth;

$conn = Database::connection();
$user = Auth::user();

$settings = get_user_dashboard_settings($user['id']);

$events = $conn->query("SELECT id, title, date, description FROM events WHERE date >= CURDATE() ORDER BY date ASC LIMIT 5")->fetchAll();
$announcements = $conn->query("SELECT id, title, created_at FROM announcements ORDER BY created_at DESC LIMIT 5")->fetchAll();
$documents = $conn->query("SELECT documents.id, documents.name, folders.name AS folder_name, documents.updated_at FROM documents JOIN folders ON folders.id = documents.folder_id ORDER BY documents.updated_at DESC LIMIT 5")->fetchAll();
$quickLinks = $conn->query("SELECT id, title, url, target FROM quick_links ORDER BY created_at DESC LIMIT 6")->fetchAll();

$layoutClass = $settings['layout'] === 'list' ? 'col-12' : 'col-md-6 col-xl-3';
?>
<div class="row g-4">
    <div class="col-12">
        <div class="module-card">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div>
                    <h2 class="h4 mb-1">Hola, <?php echo htmlspecialchars($user['name']); ?></h2>
                    <p class="text-muted mb-0">Este es tu panel personalizado. Ajusta los módulos visibles desde el menú de perfil.</p>
                </div>
                <span class="badge-role"><?php echo htmlspecialchars($user['role_name']); ?></span>
            </div>
        </div>
    </div>
    <?php if (in_array('calendar', $settings['visible_modules'], true)): ?>
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
