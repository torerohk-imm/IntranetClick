<?php

use App\Auth;
use App\Database;

function view(string $path, array $data = []): void
{
    extract($data);
    include __DIR__ . '/../views/' . $path . '.php';
}

function app_settings(): array
{
    static $settings = null;
    if ($settings !== null) {
        return $settings;
    }

    $settings = require __DIR__ . '/../config/app.php';

    try {
        $conn = Database::connection();
        $stmt = $conn->query('SELECT `key`, value FROM settings');
        foreach ($stmt as $row) {
            switch ($row['key']) {
                case 'site_name':
                    $settings['name'] = $row['value'];
                    break;
                case 'site_logo':
                    $settings['brand_logo'] = '/' . ltrim($row['value'], '/');
                    break;
                case 'color_primary':
                    $settings['theme']['primary'] = $row['value'];
                    break;
                case 'color_secondary':
                    $settings['theme']['secondary'] = $row['value'];
                    break;
                case 'color_background':
                    $settings['theme']['background'] = $row['value'];
                    break;
                case 'color_card':
                    $settings['theme']['card'] = $row['value'];
                    break;
            }
        }
    } catch (\Throwable $e) {
        // Ignore if settings table does not exist yet
    }

    return $settings;
}

function theme_styles(): string
{
    $settings = app_settings();
    $primary = $settings['theme']['primary'] ?? '#4f46e5';
    $secondary = $settings['theme']['secondary'] ?? '#0ea5e9';
    $background = $settings['theme']['background'] ?? '#e0e5ec';
    $card = $settings['theme']['card'] ?? '#f1f5f9';

    return ":root { --primary: $primary; --secondary: $secondary; --background: $background; --card-bg: $card; }";
}

function base_url(string $path = ''): string
{
    $config = app_settings();
    return rtrim($config['base_url'], '/') . '/' . ltrim($path, '/');
}

function public_path(string $path = ''): string
{
    static $publicDir = null;
    if ($publicDir === null) {
        $publicDir = realpath(__DIR__ . '/../public');
    }

    if ($publicDir === false) {
        throw new RuntimeException('No se encontró el directorio público.');
    }

    if ($path === '') {
        return $publicDir;
    }

    $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($path, '/\\'));
    return $publicDir . DIRECTORY_SEPARATOR . $normalized;
}

function upload_dir(string $subdir = ''): string
{
    $config = app_settings();
    $base = rtrim($config['upload_path'], DIRECTORY_SEPARATOR);

    if (!is_dir($base)) {
        mkdir($base, 0775, true);
    }

    if ($subdir === '') {
        return $base;
    }

    $path = $base . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, trim($subdir, '/\\'));
    if (!is_dir($path)) {
        mkdir($path, 0775, true);
    }

    return $path;
}

function upload_url(string $path = ''): string
{
    $config = app_settings();
    $base = rtrim($config['upload_url'] ?? '/uploads', '/');
    if ($path === '') {
        return $base;
    }

    return $base . '/' . ltrim($path, '/');
}

function upload_image(array $file, string $subdir, string $prefix = 'img', array $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp']): array
{
    if (empty($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE || empty($file['name'])) {
        return [null, null];
    }

    $errorCode = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($errorCode !== UPLOAD_ERR_OK) {
        return [null, 'No se pudo subir la imagen (código de error ' . $errorCode . ').'];
    }

    $tmpPath = $file['tmp_name'] ?? '';
    if (!$tmpPath || !is_uploaded_file($tmpPath)) {
        return [null, 'El archivo recibido no es válido.'];
    }

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($extension === 'jpeg') {
        $extension = 'jpg';
    }

    if (!in_array($extension, $allowedExtensions, true)) {
        return [null, 'Formato de imagen no permitido.'];
    }

    $allowedMime = [
        'jpg' => ['image/jpeg', 'image/pjpeg'],
        'png' => ['image/png'],
        'gif' => ['image/gif'],
        'webp' => ['image/webp'],
        'svg' => ['image/svg+xml'],
    ];

    $mime = null;
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = finfo_file($finfo, $tmpPath) ?: null;
            finfo_close($finfo);
        }
    }

    if (!$mime && function_exists('mime_content_type')) {
        $mime = @mime_content_type($tmpPath) ?: null;
    }

    if (!$mime && !empty($file['type'])) {
        $mime = $file['type'];
    }

    if ($mime && isset($allowedMime[$extension]) && !in_array($mime, $allowedMime[$extension], true)) {
        return [null, 'El archivo debe ser una imagen válida.'];
    }

    $filename = $prefix . '_' . uniqid('', true) . '.' . $extension;
    $destination = upload_dir($subdir) . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($tmpPath, $destination)) {
        return [null, 'No fue posible guardar la imagen en el servidor.'];
    }

    @chmod($destination, 0644);

    $relative = trim($subdir, '/\\');
    $relative = $relative !== '' ? 'uploads/' . $relative . '/' . $filename : 'uploads/' . $filename;

    return [$relative, null];
}

function delete_public_file(?string $relativePath): void
{
    if (empty($relativePath)) {
        return;
    }

    $filePath = public_path($relativePath);
    if (is_file($filePath)) {
        unlink($filePath);
    }
}

function is_post(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(string $token): bool
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function authorize_content(): void
{
    if (!Auth::canManageContent()) {
        http_response_code(403);
        echo 'No tienes permisos para realizar esta acción.';
        exit;
    }
}

function authorize_users(): void
{
    if (!Auth::canManageUsers()) {
        http_response_code(403);
        echo 'No tienes permisos para realizar esta acción.';
        exit;
    }
}

function format_datetime(string $datetime): string
{
    return date('d/m/Y H:i', strtotime($datetime));
}

function format_date(string $date): string
{
    return date('d/m/Y', strtotime($date));
}

function dashboard_default_settings(): array
{
    return [
        'layout' => 'grid',
        'visible_modules' => ['calendar', 'announcements', 'documents', 'quick-links'],
    ];
}

function get_user_dashboard_settings(int $userId): array
{
    $defaults = dashboard_default_settings();
    $stmt = Database::connection()->prepare('SELECT settings FROM user_customizations WHERE user_id = :user_id');
    $stmt->execute(['user_id' => $userId]);
    $row = $stmt->fetch();

    if (!$row) {
        return $defaults;
    }

    $settings = json_decode($row['settings'], true);
    if (!is_array($settings)) {
        return $defaults;
    }

    $layout = in_array($settings['layout'] ?? '', ['grid', 'list'], true) ? $settings['layout'] : $defaults['layout'];
    $visible = array_values(array_intersect($settings['visible_modules'] ?? [], $defaults['visible_modules']));
    if (empty($visible)) {
        $visible = $defaults['visible_modules'];
    }

    return [
        'layout' => $layout,
        'visible_modules' => $visible,
    ];
}

function save_user_dashboard_settings(int $userId, array $settings): array
{
    $defaults = dashboard_default_settings();
    $normalized = [
        'layout' => in_array($settings['layout'] ?? '', ['grid', 'list'], true) ? $settings['layout'] : $defaults['layout'],
        'visible_modules' => array_values(array_intersect($settings['visible_modules'] ?? [], $defaults['visible_modules'])),
    ];

    if (empty($normalized['visible_modules'])) {
        $normalized['visible_modules'] = $defaults['visible_modules'];
    }

    $stmt = Database::connection()->prepare('INSERT INTO user_customizations (user_id, settings) VALUES (:user_id, :settings) ON DUPLICATE KEY UPDATE settings = VALUES(settings)');
    $stmt->execute([
        'user_id' => $userId,
        'settings' => json_encode($normalized, JSON_UNESCAPED_UNICODE),
    ]);

    return $normalized;
}
