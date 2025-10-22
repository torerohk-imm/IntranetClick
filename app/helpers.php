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
