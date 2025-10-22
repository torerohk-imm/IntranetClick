<?php
use App\Database;
use App\Auth;

$conn = Database::connection();
$canManage = Auth::canManageContent();
$uploadDir = upload_dir('avatars');

if ($canManage && is_post()) {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        die('Token CSRF inválido.');
    }

    if (isset($_POST['create_unit'])) {
        $name = trim($_POST['name'] ?? '');
        $parent = $_POST['parent_id'] ?: null;
        if ($name) {
            $stmt = $conn->prepare('INSERT INTO org_units (name, parent_id) VALUES (:name, :parent_id)');
            $stmt->execute(['name' => $name, 'parent_id' => $parent]);
            $message = 'Departamento creado.';
        }
    } elseif (isset($_POST['create_member'])) {
        $payload = [
            'name' => trim($_POST['name'] ?? ''),
            'job_title' => trim($_POST['job_title'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'unit_id' => $_POST['unit_id'] ?: null,
            'manager_id' => $_POST['manager_id'] ?: null,
        ];
        $photo = null;
        if (!empty($_FILES['photo']['name'])) {
            $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
            $type = mime_content_type($_FILES['photo']['tmp_name']);
            if (isset($allowed[$type])) {
                $filename = uniqid('avatar_') . '.' . $allowed[$type];
                $destination = $uploadDir . DIRECTORY_SEPARATOR . $filename;
                if (move_uploaded_file($_FILES['photo']['tmp_name'], $destination)) {
                    $photo = 'uploads/avatars/' . $filename;
                }
            }
        }
        if ($payload['name']) {
            $stmt = $conn->prepare('INSERT INTO org_members (name, job_title, email, unit_id, manager_id, photo_path) VALUES (:name, :job_title, :email, :unit_id, :manager_id, :photo_path)');
            $stmt->execute($payload + ['photo_path' => $photo]);
            $message = 'Colaborador agregado.';
        } else {
            $error = 'El nombre del colaborador es obligatorio.';
        }
    }
}

if ($canManage && isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'delete_member' && verify_csrf($_GET['token'] ?? '')) {
    $stmt = $conn->prepare('SELECT photo_path FROM org_members WHERE id = :id');
    $stmt->execute(['id' => $_GET['id']]);
    if ($row = $stmt->fetch()) {
        delete_public_file($row['photo_path']);
    }
    $stmt = $conn->prepare('DELETE FROM org_members WHERE id = :id');
    $stmt->execute(['id' => $_GET['id']]);
    $message = 'Colaborador eliminado.';
}

$units = $conn->query('SELECT * FROM org_units ORDER BY name ASC')->fetchAll();
$members = $conn->query('SELECT * FROM org_members')->fetchAll();

$membersByManager = [];
foreach ($members as $member) {
    $manager = $member['manager_id'] ?: 'root';
    $membersByManager[$manager][] = $member;
}

function renderTree($membersByManager, $managerId = 'root', $membersLookup = [])
{
    if (empty($membersByManager[$managerId])) {
        return;
    }
    echo '<div class="organigram-children">';
    foreach ($membersByManager[$managerId] as $member) {
        echo '<div>'; 
        echo '<div class="organigram-node">';
        if (!empty($member['photo_path'])) {
            echo '<img src="' . htmlspecialchars(base_url($member['photo_path'])) . '" class="organigram-photo" alt="Foto">';
        }
        echo '<h6 class="mb-1">' . htmlspecialchars($member['name']) . '</h6>';
        echo '<p class="mb-1 text-muted">' . htmlspecialchars($member['job_title']) . '</p>';
        if (!empty($member['email'])) {
            echo '<a href="mailto:' . htmlspecialchars($member['email']) . '" class="small">' . htmlspecialchars($member['email']) . '</a>';
        }
        echo '</div>';
        renderTree($membersByManager, $member['id'], $membersLookup);
        echo '</div>';
    }
    echo '</div>';
}

$topMembers = $membersByManager['root'] ?? [];
?>
<div class="row g-4">
    <div class="col-12 col-xl-4">
        <div class="module-card">
            <h2 class="h4 mb-3">Organigrama</h2>
            <p class="text-muted">Define las jerarquías, responsables y equipos por departamento.</p>
            <?php if (!empty($message)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($canManage): ?>
                <h5 class="mt-3">Nuevo departamento</h5>
                <form method="post" class="vstack gap-3 mb-4">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                    <input type="hidden" name="create_unit" value="1">
                    <div>
                        <label class="form-label">Nombre</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div>
                        <label class="form-label">Depende de</label>
                        <select name="parent_id" class="form-select">
                            <option value="">Nivel raíz</option>
                            <?php foreach ($units as $unit): ?>
                                <option value="<?php echo $unit['id']; ?>"><?php echo htmlspecialchars($unit['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button class="btn btn-outline-primary btn-neumorphic" type="submit">Crear departamento</button>
                </form>
                <h5 class="mt-4">Nuevo colaborador</h5>
                <form method="post" enctype="multipart/form-data" class="vstack gap-3">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                    <input type="hidden" name="create_member" value="1">
                    <div>
                        <label class="form-label">Nombre</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div>
                        <label class="form-label">Puesto</label>
                        <input type="text" name="job_title" class="form-control" required>
                    </div>
                    <div>
                        <label class="form-label">Correo</label>
                        <input type="email" name="email" class="form-control">
                    </div>
                    <div>
                        <label class="form-label">Departamento</label>
                        <select name="unit_id" class="form-select">
                            <?php foreach ($units as $unit): ?>
                                <option value="<?php echo $unit['id']; ?>"><?php echo htmlspecialchars($unit['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Jefe directo</label>
                        <select name="manager_id" class="form-select">
                            <option value="">Ninguno</option>
                            <?php foreach ($members as $member): ?>
                                <option value="<?php echo $member['id']; ?>"><?php echo htmlspecialchars($member['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Fotografía</label>
                        <input type="file" name="photo" class="form-control" accept="image/*">
                    </div>
                    <button class="btn btn-primary btn-neumorphic" type="submit">Agregar colaborador</button>
                </form>
            <?php else: ?>
                <div class="alert alert-info">Solo publicadores y administradores pueden modificar el organigrama.</div>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-12 col-xl-8">
        <div class="module-card">
            <h5 class="mb-4">Estructura organizacional</h5>
            <?php if (!empty($topMembers)): ?>
                <div class="d-flex justify-content-center">
                    <?php foreach ($topMembers as $rootMember): ?>
                        <div class="text-center">
                            <div class="organigram-node">
                                <?php if (!empty($rootMember['photo_path'])): ?>
                                    <img src="<?php echo htmlspecialchars(base_url($rootMember['photo_path'])); ?>" class="organigram-photo" alt="Foto">
                                <?php endif; ?>
                                <h5 class="mb-1"><?php echo htmlspecialchars($rootMember['name']); ?></h5>
                                <p class="text-muted mb-1"><?php echo htmlspecialchars($rootMember['job_title']); ?></p>
                                <?php if (!empty($rootMember['email'])): ?>
                                    <a href="mailto:<?php echo htmlspecialchars($rootMember['email']); ?>" class="small"><?php echo htmlspecialchars($rootMember['email']); ?></a>
                                <?php endif; ?>
                                <?php if ($canManage): ?>
                                    <div class="mt-2">
                                        <a href="?module=organigram&action=delete_member&id=<?php echo $rootMember['id']; ?>&token=<?php echo csrf_token(); ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Eliminar colaborador?');">Eliminar</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php renderTree($membersByManager, $rootMember['id']); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center text-muted py-5">Agrega colaboradores para visualizar el organigrama.</div>
            <?php endif; ?>
        </div>
    </div>
</div>
