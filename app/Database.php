<?php

namespace App;

use PDO;
use PDOException;
use function htmlspecialchars;

class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection === null) {
            $config = require __DIR__ . '/../config/database.php';

            if (!empty($config['socket'])) {
                $dsn = sprintf(
                    'mysql:unix_socket=%s;dbname=%s;charset=%s',
                    $config['socket'],
                    $config['database'],
                    $config['charset']
                );
            } else {
                $dsn = sprintf(
                    'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                    $config['host'],
                    $config['port'],
                    $config['database'],
                    $config['charset']
                );
            }

            try {
                self::$connection = new PDO($dsn, $config['username'], $config['password'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
            } catch (PDOException $e) {
                $message = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
                die('Database connection failed: ' . $message . '. Revisa la configuración en config/database.php o sobrescribe valores en config/database.local.php.');
            }
        }

        return self::$connection;
    }
}
