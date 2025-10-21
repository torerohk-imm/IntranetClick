<?php
use App\Database;
use App\Auth;

$conn = Database::connection();
$canManage = Auth::canManageContent();

if ($canManage && is_post()) {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        die('Token CSRF inválido.');
    }

    if (isset($_POST['import_csv']) && isset($_FILES['csv_file'])) {
        $file = $_FILES['csv_file']['tmp_name'];
        if (($handle = fopen($file, 'r')) !== false) {
            // Skip header
            fgetcsv($handle, 1000, ',');
            $stmt = $conn->prepare('INSERT INTO contacts (name, job_title, email, phone, extension, created_by) VALUES (:name, :job_title, :email, :phone, :extension, :user_id)');
            while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                if (count($data) < 5) {
                    continue;
                }
                $stmt->execute([
                    'name' => $data[0],
                    'job_title' => $data[1],
                    'email' => $data[2],
                    'phone' => $data[3],
                    'extension' => $data[4],
                    'user_id' => Auth::user()['id'],
                ]);
            }
            fclose($handle);
            $message = 'Contactos importados correctamente.';
        }
    } else {
        $id = $_POST['id'] ?? null;
        $payload = [
            'name' => trim($_POST['name'] ?? ''),
            'job_title' => trim($_POST['job_title'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'extension' => trim($_POST['extension'] ?? ''),
        ];

        if ($payload['name']) {
            if ($id) {
                $payload['id'] = $id;
                $stmt = $conn->prepare('UPDATE contacts SET name = :name, job_title = :job_title, email = :email, phone = :phone, extension = :extension WHERE id = :id');
                $stmt->execute($payload);
                $message = 'Contacto actualizado.';
            } else {
                $stmt = $conn->prepare('INSERT INTO contacts (name, job_title, email, phone, extension, created_by) VALUES (:name, :job_title, :email, :phone, :extension, :user_id)');
                $stmt->execute($payload + ['user_id' => Auth::user()['id']]);
                $message = 'Contacto agregado.';
            }
        } else {
            $error = 'El nombre es obligatorio.';
        }
    }
}

if ($canManage && isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'delete' && verify_csrf($_GET['token'] ?? '')) {
    $stmt = $conn->prepare('DELETE FROM contacts WHERE id = :id');
    $stmt->execute(['id' => $_GET['id']]);
    $message = 'Contacto eliminado.';
}

if ($canManage && isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'edit') {
    $stmt = $conn->prepare('SELECT * FROM contacts WHERE id = :id');
    $stmt->execute(['id' => $_GET['id']]);
    $editing = $stmt->fetch();
}

$search = trim($_GET['q'] ?? '');
if ($search) {
    $stmt = $conn->prepare("SELECT * FROM contacts WHERE name LIKE :search OR job_title LIKE :search OR email LIKE :search ORDER BY name ASC");
    $stmt->execute(['search' => "%$search%"]);
    $contacts = $stmt->fetchAll();
} else {
    $contacts = $conn->query('SELECT * FROM contacts ORDER BY name ASC')->fetchAll();
}
?>
<div class="row g-4">
    <div class="col-12 col-xl-4">
        <div class="module-card">
            <h2 class="h4 mb-3">Directorio corporativo</h2>
            <p class="text-muted">Encuentra rápidamente la información de contacto del personal.</p>
            <?php if (!empty($message)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($canManage): ?>
                <h5 class="mt-4 mb-2"><?php echo isset($editing) ? 'Editar contacto' : 'Nuevo contacto'; ?></h5>
                <form method="post" class="vstack gap-3">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                    <input type="hidden" name="id" value="<?php echo $editing['id'] ?? ''; ?>">
                    <div>
                        <label class="form-label">Nombre</label>
                        <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($editing['name'] ?? ''); ?>" required>
                    </div>
                    <div>
                        <label class="form-label">Puesto</label>
                        <input type="text" name="job_title" class="form-control" value="<?php echo htmlspecialchars($editing['job_title'] ?? ''); ?>">
                    </div>
                    <div>
                        <label class="form-label">Correo</label>
                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($editing['email'] ?? ''); ?>">
                    </div>
                    <div>
                        <label class="form-label">Teléfono</label>
                        <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($editing['phone'] ?? ''); ?>">
                    </div>
                    <div>
                        <label class="form-label">Extensión</label>
                        <input type="text" name="extension" class="form-control" value="<?php echo htmlspecialchars($editing['extension'] ?? ''); ?>">
                    </div>
                    <div class="d-flex justify-content-between">
                        <?php if (isset($editing)): ?>
                            <a class="btn btn-outline-secondary" href="?module=directory">Cancelar</a>
                        <?php endif; ?>
                        <button class="btn btn-primary btn-neumorphic" type="submit"><?php echo isset($editing) ? 'Actualizar' : 'Guardar'; ?></button>
                    </div>
                </form>
                <hr>
                <h6 class="fw-semibold">Importar desde CSV</h6>
                <form method="post" enctype="multipart/form-data" class="vstack gap-3">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                    <input type="hidden" name="import_csv" value="1">
                    <div>
                        <input type="file" name="csv_file" accept=".csv" class="form-control" required>
                        <small class="text-muted">Formato: Nombre,Puesto,Correo,Teléfono,Extensión</small>
                    </div>
                    <button class="btn btn-outline-primary btn-neumorphic" type="submit">Importar</button>
                </form>
            <?php else: ?>
                <div class="alert alert-info">Solicita permisos de publicador para gestionar el directorio.</div>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-12 col-xl-8">
        <div class="module-card">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-3">
                <h5 class="mb-0">Contactos</h5>
                <form class="d-flex" method="get">
                    <input type="hidden" name="module" value="directory">
                    <input type="text" name="q" class="form-control me-2" placeholder="Buscar..." value="<?php echo htmlspecialchars($search); ?>">
                    <button class="btn btn-outline-secondary" type="submit">Buscar</button>
                </form>
            </div>
            <div class="table-responsive table-neumorphic">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Puesto</th>
                            <th>Correo</th>
                            <th>Teléfono</th>
                            <th>Extensión</th>
                            <?php if ($canManage): ?><th class="text-end">Acciones</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($contacts as $contact): ?>
                            <tr>
                                <td class="fw-semibold"><?php echo htmlspecialchars($contact['name']); ?></td>
                                <td><?php echo htmlspecialchars($contact['job_title']); ?></td>
                                <td><a href="mailto:<?php echo htmlspecialchars($contact['email']); ?>"><?php echo htmlspecialchars($contact['email']); ?></a></td>
                                <td><?php echo htmlspecialchars($contact['phone']); ?></td>
                                <td><?php echo htmlspecialchars($contact['extension']); ?></td>
                                <?php if ($canManage): ?>
                                <td class="text-end">
                                    <a href="?module=directory&action=edit&id=<?php echo $contact['id']; ?>" class="btn btn-sm btn-outline-primary">Editar</a>
                                    <a href="?module=directory&action=delete&id=<?php echo $contact['id']; ?>&token=<?php echo csrf_token(); ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Eliminar contacto?');">Eliminar</a>
                                </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($contacts)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No se encontraron registros.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
