<?php
use App\Database;
use App\Auth;

$conn = Database::connection();
$user = Auth::user();
$canManage = Auth::canManageContent();

$messages = ['category' => null, 'general' => null, 'personal' => null];
$errors = ['category' => null, 'general' => null, 'personal' => null];
$editingCategory = null;
$editingLink = null;
$editingScope = null;
$postCategoryFocus = null;

if (is_post()) {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        die('Token CSRF inválido.');
    }

    if ($canManage && isset($_POST['create_category'])) {
        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            $errors['category'] = 'El nombre de la categoría es obligatorio.';
        } else {
            $stmt = $conn->prepare('INSERT INTO quick_link_categories (name, created_by) VALUES (:name, :created_by)');
            $stmt->execute([
                'name' => $name,
                'created_by' => $user['id'] ?? null,
            ]);
            $messages['category'] = 'Categoría creada correctamente.';
            $postCategoryFocus = (string)$conn->lastInsertId();
        }
    } elseif ($canManage && isset($_POST['update_category'])) {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        if (!$id || $name === '') {
            $errors['category'] = 'Selecciona una categoría válida e indica un nombre.';
        } else {
            $stmt = $conn->prepare('UPDATE quick_link_categories SET name = :name WHERE id = :id');
            $stmt->execute(['name' => $name, 'id' => $id]);
            $messages['category'] = 'Categoría actualizada.';
            $postCategoryFocus = (string)$id;
        }
    } elseif (isset($_POST['save_link'])) {
        $scope = $_POST['scope'] ?? 'general';
        $payload = [
            'title' => trim($_POST['title'] ?? ''),
            'url' => trim($_POST['url'] ?? ''),
            'target' => in_array($_POST['target'] ?? '_self', ['_self', '_blank'], true) ? $_POST['target'] : '_self',
            'icon' => trim($_POST['icon'] ?? ''),
        ];
        $id = (int)($_POST['id'] ?? 0);

        if ($payload['title'] === '' || $payload['url'] === '') {
            if ($scope === 'personal') {
                $errors['personal'] = 'El título y la URL son obligatorios.';
            } else {
                $errors['general'] = 'El título y la URL son obligatorios.';
            }
        } else {
            if ($scope === 'personal') {
                $postCategoryFocus = 'personal';
                if ($id) {
                    $stmt = $conn->prepare('SELECT id FROM quick_links WHERE id = :id AND is_personal = 1 AND owner_id = :owner');
                    $stmt->execute(['id' => $id, 'owner' => $user['id']]);
                    if (!$stmt->fetch()) {
                        $errors['personal'] = 'No puedes editar este enlace personal.';
                    }
                }
                if (empty($errors['personal'])) {
                    if ($id) {
                        $stmt = $conn->prepare('UPDATE quick_links SET title = :title, url = :url, target = :target, icon = :icon WHERE id = :id');
                        $stmt->execute($payload + ['id' => $id]);
                        $messages['personal'] = 'Enlace personal actualizado.';
                    } else {
                        $stmt = $conn->prepare('INSERT INTO quick_links (title, url, target, icon, is_personal, owner_id, created_by) VALUES (:title, :url, :target, :icon, 1, :owner, :created_by)');
                        $stmt->execute($payload + ['owner' => $user['id'], 'created_by' => $user['id']]);
                        $messages['personal'] = 'Enlace personal agregado.';
                    }
                }
            } else {
                if (!$canManage) {
                    $errors['general'] = 'No tienes permisos para administrar enlaces compartidos.';
                } else {
                    $categoryId = (int)($_POST['category_id'] ?? 0);
                    if (!$categoryId) {
                        $errors['general'] = 'Selecciona una categoría.';
                    } else {
                        $postCategoryFocus = (string)$categoryId;
                        if ($id) {
                            $stmt = $conn->prepare('SELECT id FROM quick_links WHERE id = :id AND is_personal = 0');
                            $stmt->execute(['id' => $id]);
                            if (!$stmt->fetch()) {
                                $errors['general'] = 'No se encontró el enlace a actualizar.';
                            }
                        }
                        if (empty($errors['general'])) {
                            if ($id) {
                                $stmt = $conn->prepare('UPDATE quick_links SET title = :title, url = :url, target = :target, icon = :icon, category_id = :category_id WHERE id = :id');
                                $stmt->execute($payload + ['category_id' => $categoryId, 'id' => $id]);
                                $messages['general'] = 'Enlace actualizado.';
                            } else {
                                $stmt = $conn->prepare('INSERT INTO quick_links (title, url, target, icon, category_id, created_by) VALUES (:title, :url, :target, :icon, :category_id, :created_by)');
                                $stmt->execute($payload + ['category_id' => $categoryId, 'created_by' => $user['id'] ?? null]);
                                $messages['general'] = 'Enlace creado.';
                            }
                        }
                    }
                }
            }
        }
    }
}

if ($canManage && isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'delete_category' && verify_csrf($_GET['token'] ?? '')) {
    $stmt = $conn->prepare('DELETE FROM quick_link_categories WHERE id = :id');
    $stmt->execute(['id' => (int)$_GET['id']]);
    $messages['category'] = 'Categoría eliminada.';
}

if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'delete_link' && verify_csrf($_GET['token'] ?? '')) {
    $stmt = $conn->prepare('SELECT id, is_personal, owner_id, category_id FROM quick_links WHERE id = :id');
    $stmt->execute(['id' => (int)$_GET['id']]);
    if ($link = $stmt->fetch()) {
        if ($link['is_personal']) {
            if ($link['owner_id'] == $user['id']) {
                $conn->prepare('DELETE FROM quick_links WHERE id = :id')->execute(['id' => $link['id']]);
                $messages['personal'] = 'Enlace personal eliminado.';
                $postCategoryFocus = 'personal';
            } else {
                $errors['personal'] = 'No puedes eliminar enlaces personales de otros usuarios.';
            }
        } elseif ($canManage) {
            $conn->prepare('DELETE FROM quick_links WHERE id = :id')->execute(['id' => $link['id']]);
            $messages['general'] = 'Enlace eliminado.';
            $postCategoryFocus = (string)$link['category_id'];
        } else {
            $errors['general'] = 'No tienes permisos para eliminar este enlace.';
        }
    }
}

if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'edit_link') {
    $stmt = $conn->prepare('SELECT * FROM quick_links WHERE id = :id');
    $stmt->execute(['id' => (int)$_GET['id']]);
    if ($link = $stmt->fetch()) {
        if ($link['is_personal']) {
            if ($link['owner_id'] == $user['id']) {
                $editingLink = $link;
                $editingScope = 'personal';
                $postCategoryFocus = 'personal';
            } else {
                $errors['personal'] = 'No puedes editar este enlace personal.';
            }
        } elseif ($canManage) {
            $editingLink = $link;
            $editingScope = 'general';
            $postCategoryFocus = (string)$link['category_id'];
        } else {
            $errors['general'] = 'No tienes permisos para editar este enlace.';
        }
    }
}

if ($canManage && isset($_GET['edit_category'])) {
    $stmt = $conn->prepare('SELECT * FROM quick_link_categories WHERE id = :id');
    $stmt->execute(['id' => (int)$_GET['edit_category']]);
    $editingCategory = $stmt->fetch();
}

$categories = $conn->query('SELECT id, name FROM quick_link_categories ORDER BY name ASC')->fetchAll();
$categoryMap = [];
foreach ($categories as $category) {
    $categoryMap[(string)$category['id']] = $category;
}
$firstCategoryId = !empty($categories) ? (string)$categories[0]['id'] : null;

$categoryParam = $_GET['category'] ?? null;
if ($postCategoryFocus !== null) {
    if ($postCategoryFocus !== 'personal' && !isset($categoryMap[$postCategoryFocus])) {
        $activeCategory = $firstCategoryId ?? 'personal';
    } else {
        $activeCategory = $postCategoryFocus;
    }
} elseif ($categoryParam === 'personal') {
    $activeCategory = 'personal';
} elseif ($categoryParam !== null && isset($categoryMap[$categoryParam])) {
    $activeCategory = $categoryParam;
} elseif ($editingScope === 'general' && $editingLink && isset($categoryMap[(string)$editingLink['category_id']])) {
    $activeCategory = (string)$editingLink['category_id'];
} elseif ($firstCategoryId !== null) {
    $activeCategory = $firstCategoryId;
} else {
    $activeCategory = 'personal';
}

$selectedCategory = $activeCategory !== 'personal' && isset($categoryMap[$activeCategory]) ? $categoryMap[$activeCategory] : null;

if ($activeCategory !== 'personal' && !$selectedCategory && $firstCategoryId !== null) {
    $activeCategory = $firstCategoryId;
    $selectedCategory = $categoryMap[$firstCategoryId];
}

if ($activeCategory !== 'personal') {
    $linksStmt = $conn->prepare('SELECT quick_links.*, users.name AS author FROM quick_links LEFT JOIN users ON users.id = quick_links.created_by WHERE quick_links.is_personal = 0 AND quick_links.category_id = :category ORDER BY quick_links.created_at DESC');
    $linksStmt->execute(['category' => (int)$activeCategory]);
    $links = $linksStmt->fetchAll();
} else {
    $linksStmt = $conn->prepare('SELECT * FROM quick_links WHERE is_personal = 1 AND owner_id = :owner ORDER BY created_at DESC');
    $linksStmt->execute(['owner' => $user['id']]);
    $links = $linksStmt->fetchAll();
}

$navCategories = $categories;
$navCategories[] = ['id' => 'personal', 'name' => 'Personales'];
?>
<div class="module-card mb-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div>
            <h2 class="h4 mb-1">Botonera de enlaces rápidos</h2>
            <?php if ($canManage): ?>
                <p class="text-muted mb-0">Centraliza accesos a herramientas externas o internas con un clic.</p>
            <?php endif; ?>
        </div>
    </div>
    <nav class="module-top-nav mt-3">
        <?php foreach ($navCategories as $navCategory): ?>
            <?php $navId = (string)$navCategory['id']; ?>
            <?php $isPersonal = $navId === 'personal'; ?>
            <?php $isActive = $activeCategory === $navId || ($isPersonal && $activeCategory === 'personal'); ?>
            <a class="module-top-nav-link <?php echo $isActive ? 'active' : ''; ?>" href="?module=quick-links&amp;category=<?php echo $isPersonal ? 'personal' : urlencode($navId); ?>">
                <?php echo htmlspecialchars($navCategory['name']); ?>
            </a>
        <?php endforeach; ?>
    </nav>
</div>
<div class="row g-4">
    <div class="col-12 col-xl-4">
        <?php if ($activeCategory === 'personal'): ?>
            <div class="module-card">
                <h5 class="mb-2">Tus enlaces personales</h5>
                <p class="text-muted small mb-3">Crea accesos rápidos visibles solo para ti.</p>
                <?php if ($messages['personal']): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($messages['personal']); ?></div>
                <?php endif; ?>
                <?php if ($errors['personal']): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($errors['personal']); ?></div>
                <?php endif; ?>
                <form method="post" class="vstack gap-3">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                    <input type="hidden" name="save_link" value="1">
                    <input type="hidden" name="scope" value="personal">
                    <input type="hidden" name="id" value="<?php echo ($editingScope === 'personal' && $editingLink) ? (int)$editingLink['id'] : ''; ?>">
                    <div>
                        <label class="form-label">Título</label>
                        <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars(($editingScope === 'personal' && $editingLink) ? $editingLink['title'] : ''); ?>" required>
                    </div>
                    <div>
                        <label class="form-label">URL</label>
                        <input type="url" name="url" class="form-control" value="<?php echo htmlspecialchars(($editingScope === 'personal' && $editingLink) ? $editingLink['url'] : ''); ?>" required>
                    </div>
                    <div>
                        <label class="form-label">Destino</label>
                        <select name="target" class="form-select">
                            <?php $personalTarget = ($editingScope === 'personal' && $editingLink) ? $editingLink['target'] : '_self'; ?>
                            <option value="_self" <?php echo $personalTarget === '_self' ? 'selected' : ''; ?>>Abrir en la misma pestaña</option>
                            <option value="_blank" <?php echo $personalTarget === '_blank' ? 'selected' : ''; ?>>Abrir en nueva pestaña</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Icono (opcional)</label>
                        <input type="text" name="icon" class="form-control" placeholder="Ej. bi bi-star" value="<?php echo htmlspecialchars(($editingScope === 'personal' && $editingLink) ? $editingLink['icon'] : ''); ?>">
                        <small class="text-muted">Usa clases de <a href="https://icons.getbootstrap.com/" target="_blank" rel="noopener">Bootstrap Icons</a>.</small>
                    </div>
                    <div class="d-flex justify-content-between">
                        <?php if ($editingScope === 'personal' && $editingLink): ?>
                            <a href="?module=quick-links&amp;category=personal" class="btn btn-outline-secondary">Cancelar</a>
                        <?php endif; ?>
                        <button class="btn btn-primary btn-neumorphic" type="submit"><?php echo ($editingScope === 'personal' && $editingLink) ? 'Actualizar' : 'Guardar'; ?></button>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <div class="module-card">
                <h5 class="mb-2">Enlaces compartidos</h5>
                <?php if ($canManage): ?>
                    <p class="text-muted small mb-3">Gestiona accesos visibles para toda la organización.</p>
                    <?php if ($messages['general']): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($messages['general']); ?></div>
                    <?php endif; ?>
                    <?php if ($errors['general']): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($errors['general']); ?></div>
                    <?php endif; ?>
                    <form method="post" class="vstack gap-3">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                        <input type="hidden" name="save_link" value="1">
                        <input type="hidden" name="scope" value="general">
                        <input type="hidden" name="id" value="<?php echo ($editingScope === 'general' && $editingLink) ? (int)$editingLink['id'] : ''; ?>">
                        <div>
                            <label class="form-label">Título</label>
                            <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars(($editingScope === 'general' && $editingLink) ? $editingLink['title'] : ''); ?>" required>
                        </div>
                        <div>
                            <label class="form-label">URL</label>
                            <input type="url" name="url" class="form-control" value="<?php echo htmlspecialchars(($editingScope === 'general' && $editingLink) ? $editingLink['url'] : ''); ?>" required>
                        </div>
                        <div>
                            <label class="form-label">Categoría</label>
                            <select name="category_id" class="form-select" required>
                                <option value="">Selecciona una categoría</option>
                                <?php foreach ($categories as $category): ?>
                                    <?php $categoryId = (string)$category['id']; ?>
                                    <?php $selected = ($editingScope === 'general' && $editingLink) ? ((string)$editingLink['category_id'] === $categoryId) : ($activeCategory === $categoryId); ?>
                                    <option value="<?php echo $category['id']; ?>" <?php echo $selected ? 'selected' : ''; ?>><?php echo htmlspecialchars($category['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Destino</label>
                            <?php $generalTarget = ($editingScope === 'general' && $editingLink) ? $editingLink['target'] : '_self'; ?>
                            <select name="target" class="form-select">
                                <option value="_self" <?php echo $generalTarget === '_self' ? 'selected' : ''; ?>>Abrir en la misma pestaña</option>
                                <option value="_blank" <?php echo $generalTarget === '_blank' ? 'selected' : ''; ?>>Abrir en nueva pestaña</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Icono (opcional)</label>
                            <input type="text" name="icon" class="form-control" placeholder="Ej. bi bi-link-45deg" value="<?php echo htmlspecialchars(($editingScope === 'general' && $editingLink) ? $editingLink['icon'] : ''); ?>">
                        </div>
                        <div class="d-flex justify-content-between">
                            <?php if ($editingScope === 'general' && $editingLink): ?>
                                <a href="?module=quick-links&amp;category=<?php echo urlencode($activeCategory); ?>" class="btn btn-outline-secondary">Cancelar</a>
                            <?php endif; ?>
                            <button class="btn btn-primary btn-neumorphic" type="submit"><?php echo ($editingScope === 'general' && $editingLink) ? 'Actualizar' : 'Guardar'; ?></button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="alert alert-info mb-0">Solo administradores y publicadores pueden agregar enlaces compartidos.</div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <?php if ($canManage): ?>
            <div class="module-card">
                <h5 class="mb-3">Categorías de enlaces</h5>
                <?php if ($messages['category']): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($messages['category']); ?></div>
                <?php endif; ?>
                <?php if ($errors['category']): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($errors['category']); ?></div>
                <?php endif; ?>
                <form method="post" class="vstack gap-3 mb-4">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                    <?php if ($editingCategory): ?>
                        <input type="hidden" name="update_category" value="1">
                        <input type="hidden" name="id" value="<?php echo (int)$editingCategory['id']; ?>">
                        <div>
                            <label class="form-label">Renombrar categoría</label>
                            <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($editingCategory['name']); ?>" required>
                        </div>
                        <div class="d-flex justify-content-between">
                            <a href="?module=quick-links" class="btn btn-outline-secondary">Cancelar</a>
                            <button class="btn btn-primary btn-neumorphic" type="submit">Actualizar</button>
                        </div>
                    <?php else: ?>
                        <input type="hidden" name="create_category" value="1">
                        <div>
                            <label class="form-label">Nueva categoría</label>
                            <input type="text" name="name" class="form-control" placeholder="Ej. Recursos Humanos" required>
                        </div>
                        <button class="btn btn-primary btn-neumorphic" type="submit">Crear</button>
                    <?php endif; ?>
                </form>
                <?php if (!empty($categories)): ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($categories as $category): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <span><?php echo htmlspecialchars($category['name']); ?></span>
                                <span class="btn-group btn-group-sm">
                                    <a class="btn btn-outline-primary" href="?module=quick-links&amp;edit_category=<?php echo $category['id']; ?>">Editar</a>
                                    <a class="btn btn-outline-danger" href="?module=quick-links&amp;action=delete_category&amp;id=<?php echo $category['id']; ?>&amp;token=<?php echo htmlspecialchars(csrf_token()); ?>" onclick="return confirm('¿Eliminar la categoría y sus enlaces asociados?');">Eliminar</a>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="text-muted">Aún no se han definido categorías.</div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    <div class="col-12 col-xl-8">
        <div class="module-card">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
                <h5 class="mb-0">
                    <?php if ($activeCategory === 'personal'): ?>
                        Mis accesos personales
                    <?php elseif ($selectedCategory): ?>
                        Enlaces: <?php echo htmlspecialchars($selectedCategory['name']); ?>
                    <?php else: ?>
                        Enlaces compartidos
                    <?php endif; ?>
                </h5>
                <?php if ($activeCategory !== 'personal' && $selectedCategory): ?>
                    <span class="badge bg-secondary">Categoría pública</span>
                <?php endif; ?>
            </div>
            <?php if (!empty($links)): ?>
                <div class="quick-links-list">
                    <?php foreach ($links as $link): ?>
                        <div class="quick-link-row">
                            <div class="quick-link-info">
                                <?php if (!empty($link['icon'])): ?>
                                    <i class="<?php echo htmlspecialchars($link['icon']); ?> quick-link-icon"></i>
                                <?php endif; ?>
                                <div class="quick-link-text">
                                    <a class="quick-link-title" href="<?php echo htmlspecialchars($link['url']); ?>" target="<?php echo htmlspecialchars($link['target']); ?>" rel="noopener">
                                        <?php echo htmlspecialchars($link['title']); ?>
                                    </a>
                                    <div class="quick-link-url" title="<?php echo htmlspecialchars($link['url']); ?>"><?php echo htmlspecialchars($link['url']); ?></div>
                                </div>
                            </div>
                            <div class="quick-link-actions">
                                <?php if ($link['is_personal']): ?>
                                    <?php if ($link['owner_id'] == $user['id']): ?>
                                        <a href="?module=quick-links&amp;action=edit_link&amp;id=<?php echo $link['id']; ?>&amp;category=personal" class="btn btn-sm btn-outline-primary">Editar</a>
                                        <a href="?module=quick-links&amp;action=delete_link&amp;id=<?php echo $link['id']; ?>&amp;category=personal&amp;token=<?php echo htmlspecialchars(csrf_token()); ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Eliminar este acceso personal?');">Eliminar</a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <?php if ($canManage): ?>
                                        <a href="?module=quick-links&amp;action=edit_link&amp;id=<?php echo $link['id']; ?>&amp;category=<?php echo urlencode($activeCategory); ?>" class="btn btn-sm btn-outline-primary">Editar</a>
                                        <a href="?module=quick-links&amp;action=delete_link&amp;id=<?php echo $link['id']; ?>&amp;category=<?php echo urlencode($activeCategory); ?>&amp;token=<?php echo htmlspecialchars(csrf_token()); ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Eliminar este enlace?');">Eliminar</a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-muted py-4 text-center">No hay enlaces registrados en esta categoría.</div>
            <?php endif; ?>
        </div>
    </div>
</div>
