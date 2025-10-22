<?php

$default = [
    'host' => '127.0.0.1',
    'port' => '3306',
    'database' => 'intranet',
    'username' => 'root',
    'password' => '',
    'socket' => null,
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
];

$localConfigPath = __DIR__ . '/database.local.php';

if (file_exists($localConfigPath)) {
    $overrides = require $localConfigPath;
    if (is_array($overrides)) {
        $default = array_merge($default, $overrides);
    }
}

return $default;
