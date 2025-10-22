<?php
use App\Database;
use App\Auth;

$conn = Database::connection();
$canManage = Auth::canManageContent();
$user = Auth::user();
$uploadDir = upload_dir('documents');

function syncFolderPermissions($folderId, $roles, $emails, $conn)
{
    $conn->prepare('DELETE FROM folder_permissions WHERE folder_id = :folder_id')->execute(['folder_id' => $folderId]);
    $stmtRole = $conn->prepare('INSERT INTO folder_permissions (folder_id, role_slug) VALUES (:folder_id, :role_slug)');
    foreach ($roles as $role) {
        $stmtRole->execute(['folder_id' => $folderId, 'role_slug' => $role]);
    }
    if (!empty($emails)) {
        $stmtUser = $conn->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $stmtInsertUser = $conn->prepare('INSERT INTO folder_permissions (folder_id, user_id) VALUES (:folder_id, :user_id)');
        foreach ($emails as $email) {
            $stmtUser->execute(['email' => $email]);
            if ($userRow = $stmtUser->fetch()) {
                $stmtInsertUser->execute(['folder_id' => $folderId, 'user_id' => $userRow['id']]);
            }
        }
    }
}

if ($canManage && is_post()) {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        die('Token CSRF inválido.');
    }

    if (isset($_POST['create_folder'])) {
        $name = trim($_POST['name'] ?? '');
        $parent = $_POST['parent_id'] ?: null;
        $roles = $_POST['allowed_roles'] ?? [];
        $emails = array_filter(array_map('trim', explode(',', $_POST['allowed_users'] ?? '')));
        if ($name) {
            $stmt = $conn->prepare('INSERT INTO folders (name, parent_id) VALUES (:name, :parent_id)');
            $stmt->execute(['name' => $name, 'parent_id' => $parent]);
            $folderId = $conn->lastInsertId();
            syncFolderPermissions($folderId, $roles, $emails, $conn);
            $message = 'Carpeta creada correctamente.';
        }
    } elseif (isset($_POST['update_folder'])) {
        $folderId = (int)($_POST['folder_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $roles = $_POST['allowed_roles'] ?? [];
        $emails = array_filter(array_map('trim', explode(',', $_POST['allowed_users'] ?? '')));
        if ($folderId && $name) {
            $stmt = $conn->prepare('UPDATE folders SET name = :name WHERE id = :id');
            $stmt->execute(['name' => $name, 'id' => $folderId]);
            syncFolderPermissions($folderId, $roles, $emails, $conn);
            $message = 'Carpeta actualizada.';
        }
    } elseif (isset($_POST['upload_document'])) {
        $folderId = (int)($_POST['folder_id'] ?? 0);
        if ($folderId && !empty($_FILES['file']['name'])) {
            $filename = $_FILES['file']['name'];
            $safeName = uniqid('doc_') . '_' . preg_replace('/[^A-Za-z0-9_\.-]/', '_', $filename);
            $destination = $uploadDir . DIRECTORY_SEPARATOR . $safeName;
            if (move_uploaded_file($_FILES['file']['tmp_name'], $destination)) {
                $stmt = $conn->prepare('INSERT INTO documents (folder_id, name, file_path, uploaded_by) VALUES (:folder_id, :name, :file_path, :uploaded_by)');
                $stmt->execute([
                    'folder_id' => $folderId,
                    'name' => $filename,
                    'file_path' => 'uploads/documents/' . $safeName,
                    'uploaded_by' => $user['id'],
                ]);
                $message = 'Archivo cargado correctamente.';
            } else {
                $error = 'No fue posible subir el archivo.';
            }
        }
    }
}

if ($canManage && isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'delete_folder' && verify_csrf($_GET['token'] ?? '')) {
    $stmt = $conn->prepare('DELETE FROM folders WHERE id = :id');
    $stmt->execute(['id' => $_GET['id']]);
    $message = 'Carpeta eliminada.';
}

if ($canManage && isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'delete_document' && verify_csrf($_GET['token'] ?? '')) {
    $stmt = $conn->prepare('SELECT file_path FROM documents WHERE id = :id');
    $stmt->execute(['id' => $_GET['id']]);
    if ($row = $stmt->fetch()) {
        delete_public_file($row['file_path']);
    }
    $stmt = $conn->prepare('DELETE FROM documents WHERE id = :id');
    $stmt->execute(['id' => $_GET['id']]);
    $message = 'Documento eliminado.';
}

$folders = $conn->query('SELECT * FROM folders ORDER BY name ASC')->fetchAll();
$permissions = $conn->query('SELECT * FROM folder_permissions')->fetchAll();
$documents = $conn->query('SELECT documents.*, users.name AS uploader FROM documents LEFT JOIN users ON users.id = documents.uploaded_by ORDER BY documents.created_at DESC')->fetchAll();

$permissionsByFolder = [];
foreach ($permissions as $permission) {
    $permissionsByFolder[$permission['folder_id']][] = $permission;
}

function folderIsAccessible($folderId, $permissionsByFolder, $user)
{
    if ($user['role_slug'] === 'admin') {
        return true;
    }
    if (empty($permissionsByFolder[$folderId])) {
        return true;
    }
    foreach ($permissionsByFolder[$folderId] as $perm) {
        if ($perm['role_slug'] && $perm['role_slug'] === $user['role_slug']) {
            return true;
        }
        if ($perm['user_id'] && $perm['user_id'] == $user['id']) {
            return true;
        }
    }
    return false;
}

$foldersByParent = [];
foreach ($folders as $folder) {
    $foldersByParent[$folder['parent_id'] ?? 0][] = $folder;
}

function renderFolderTree($parentId, $foldersByParent, $permissionsByFolder, $user, $currentFolderId)
{
    if (empty($foldersByParent[$parentId])) {
        return;
    }
    echo '<ul class="folder-tree">';
    foreach ($foldersByParent[$parentId] as $folder) {
        if (!folderIsAccessible($folder['id'], $permissionsByFolder, $user)) {
            continue;
        }
        $active = $folder['id'] == $currentFolderId ? 'fw-bold text-primary' : '';
        echo '<li><a class="text-decoration-none ' . $active . '" href="?module=documents&folder=' . $folder['id'] . '">' . htmlspecialchars($folder['name']) . '</a>';
        renderFolderTree($folder['id'], $foldersByParent, $permissionsByFolder, $user, $currentFolderId);
        echo '</li>';
    }
    echo '</ul>';
}

$currentFolderId = isset($_GET['folder']) ? (int)$_GET['folder'] : ($folders[0]['id'] ?? 0);
if ($currentFolderId && !folderIsAccessible($currentFolderId, $permissionsByFolder, $user)) {
    $currentFolderId = 0;
}

$currentDocuments = array_filter($documents, function ($doc) use ($currentFolderId) {
    return $currentFolderId ? $doc['folder_id'] == $currentFolderId : false;
});

$currentFolder = null;
foreach ($folders as $folder) {
    if ($folder['id'] == $currentFolderId) {
        $currentFolder = $folder;
        break;
    }
}
?>
<div class="row g-4">
    <div class="col-12 col-lg-4">
        <div class="module-card">
            <h2 class="h4 mb-3">Repositorio documental</h2>
            <p class="text-muted">Organiza archivos por carpetas y controla quién puede ver cada sección.</p>
            <?php if (!empty($message)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <h5 class="mt-3">Carpetas</h5>
            <?php renderFolderTree(0, $foldersByParent, $permissionsByFolder, $user, $currentFolderId); ?>
            <?php if ($canManage): ?>
                <hr>
                <h6 class="fw-semibold">Crear carpeta</h6>
                <form method="post" class="vstack gap-3">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                    <input type="hidden" name="create_folder" value="1">
                    <div>
                        <label class="form-label">Nombre</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div>
                        <label class="form-label">Carpeta padre</label>
                        <select name="parent_id" class="form-select">
                            <option value="">Nivel raíz</option>
                            <?php foreach ($folders as $folder): ?>
                                <option value="<?php echo $folder['id']; ?>"><?php echo htmlspecialchars($folder['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Roles con acceso</label>
                        <?php $roles = ['user' => 'Usuario final', 'publisher' => 'Publicador', 'admin' => 'Administrador']; ?>
                        <?php foreach ($roles as $roleSlug => $roleLabel): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="allowed_roles[]" value="<?php echo $roleSlug; ?>" id="role-create-<?php echo $roleSlug; ?>">
                                <label class="form-check-label" for="role-create-<?php echo $roleSlug; ?>"><?php echo $roleLabel; ?></label>
                            </div>
                        <?php endforeach; ?>
                        <small class="text-muted">Si no seleccionas roles, todos podrán acceder.</small>
                    </div>
                    <div>
                        <label class="form-label">Usuarios específicos</label>
                        <input type="text" name="allowed_users" class="form-control" placeholder="Correos separados por coma">
                    </div>
                    <button class="btn btn-outline-primary btn-neumorphic" type="submit">Crear carpeta</button>
                </form>
                <?php if ($currentFolder): ?>
                    <hr>
                    <h6 class="fw-semibold">Editar carpeta actual</h6>
                    <?php
                    $currentPerms = $permissionsByFolder[$currentFolder['id']] ?? [];
                    $selectedRoles = array_unique(array_filter(array_column($currentPerms, 'role_slug')));
                    $selectedUsers = [];
                    foreach ($currentPerms as $perm) {
                        if ($perm['user_id']) {
                            $stmt = $conn->prepare('SELECT email FROM users WHERE id = :id');
                            $stmt->execute(['id' => $perm['user_id']]);
                            if ($row = $stmt->fetch()) {
                                $selectedUsers[] = $row['email'];
                            }
                        }
                    }
                    ?>
                    <form method="post" class="vstack gap-3">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                        <input type="hidden" name="update_folder" value="1">
                        <input type="hidden" name="folder_id" value="<?php echo $currentFolder['id']; ?>">
                        <div>
                            <label class="form-label">Nombre</label>
                            <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($currentFolder['name']); ?>" required>
                        </div>
                        <div>
                            <label class="form-label">Roles con acceso</label>
                            <?php foreach ($roles as $roleSlug => $roleLabel): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="allowed_roles[]" value="<?php echo $roleSlug; ?>" id="role-edit-<?php echo $roleSlug; ?>" <?php echo in_array($roleSlug, $selectedRoles, true) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="role-edit-<?php echo $roleSlug; ?>"><?php echo $roleLabel; ?></label>
                                </div>
                            <?php endforeach; ?>
                            <small class="text-muted">Si no seleccionas roles, todos podrán acceder.</small>
                        </div>
                        <div>
                            <label class="form-label">Usuarios específicos</label>
                            <input type="text" name="allowed_users" class="form-control" value="<?php echo htmlspecialchars(implode(', ', $selectedUsers)); ?>" placeholder="Correos separados por coma">
                        </div>
                        <div class="d-flex justify-content-between">
                            <button class="btn btn-primary btn-neumorphic" type="submit">Guardar cambios</button>
                            <a href="?module=documents&action=delete_folder&id=<?php echo $currentFolder['id']; ?>&token=<?php echo csrf_token(); ?>" class="btn btn-outline-danger" onclick="return confirm('¿Eliminar carpeta y su contenido?');">Eliminar</a>
                        </div>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-12 col-lg-8">
        <div class="module-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0"><?php echo $currentFolder ? htmlspecialchars($currentFolder['name']) : 'Selecciona una carpeta'; ?></h5>
                <?php if ($canManage && $currentFolder): ?>
                    <form method="post" enctype="multipart/form-data" class="d-flex gap-2 align-items-center">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                        <input type="hidden" name="upload_document" value="1">
                        <input type="hidden" name="folder_id" value="<?php echo $currentFolder['id']; ?>">
                        <input type="file" name="file" class="form-control" required>
                        <button class="btn btn-primary btn-neumorphic" type="submit">Subir</button>
                    </form>
                <?php endif; ?>
            </div>
            <?php if ($currentFolder): ?>
                <div class="table-responsive table-neumorphic">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Fecha</th>
                                <th>Subido por</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($currentDocuments as $document): ?>
                                <tr>
                                    <td>
                                        <a href="<?php echo htmlspecialchars(base_url($document['file_path'])); ?>" download><?php echo htmlspecialchars($document['name']); ?></a>
                                    </td>
                                    <td><?php echo format_datetime($document['created_at']); ?></td>
                                    <td><?php echo htmlspecialchars($document['uploader'] ?? 'Sistema'); ?></td>
                                    <td class="text-end">
                                        <a href="<?php echo htmlspecialchars(base_url($document['file_path'])); ?>" class="btn btn-sm btn-outline-primary" download>Descargar</a>
                                        <?php if ($canManage): ?>
                                            <a href="?module=documents&action=delete_document&id=<?php echo $document['id']; ?>&token=<?php echo csrf_token(); ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Eliminar archivo?');">Eliminar</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($currentDocuments)): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">No hay archivos en esta carpeta.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center text-muted py-5">Selecciona una carpeta para ver su contenido.</div>
            <?php endif; ?>
        </div>
    </div>
</div>
