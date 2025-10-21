<?php

namespace App;

use App\Database;

class Auth
{
    public static function attempt(string $email, string $password): bool
    {
        $stmt = Database::connection()->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            return true;
        }

        return false;
    }

    public static function user(): ?array
    {
        if (!isset($_SESSION['user_id'])) {
            return null;
        }

        $stmt = Database::connection()->prepare('SELECT users.*, roles.slug AS role_slug, roles.name AS role_name FROM users JOIN roles ON roles.id = users.role_id WHERE users.id = :id');
        $stmt->execute(['id' => $_SESSION['user_id']]);

        return $stmt->fetch() ?: null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function logout(): void
    {
        unset($_SESSION['user_id']);
        session_destroy();
    }

    public static function hasRole(string $role): bool
    {
        $user = self::user();
        return $user && $user['role_slug'] === $role;
    }

    public static function hasAnyRole(array $roles): bool
    {
        $user = self::user();
        return $user && in_array($user['role_slug'], $roles, true);
    }

    public static function canManageUsers(): bool
    {
        return self::hasRole('admin');
    }

    public static function canManageContent(): bool
    {
        return self::hasAnyRole(['admin', 'publisher']);
    }
}
