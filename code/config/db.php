<?php
/**
 * TeamForge Database Connection (PDO Singleton)
 */

require_once __DIR__ . '/constants.php';

class Database {
    private static ?PDO $instance = null;

    private function __construct() {}
    private function __clone() {}

    public static function tryConnection(): ?PDO {
        if (self::$instance !== null) {
            return self::$instance;
        }
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ];

        try {
            self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            return self::$instance;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function getConnection(): PDO {
        $conn = self::tryConnection();
        if ($conn === null) {
            if (defined('IS_API_REQUEST') && IS_API_REQUEST) {
                throw new PDOException("Database connection refused or offline.");
            }
            die("<div style='font-family:Segoe UI, sans-serif; padding:30px; text-align:center;'>
                    <h2 style='color:#ef4444;'>Database Connection Failed</h2>
                    <p>Could not connect to MySQL. Ensure MySQL is started in XAMPP.</p>
                 </div>");
        }
        return $conn;
    }
}

function getDB(): PDO {
    return Database::getConnection();
}

