<?php
/**
 * TeamForge API Bootstrap
 * Unified JSON API layer for HTML + CSS + JS Frontend
 */

if (!defined('IS_API_REQUEST')) {
    define('IS_API_REQUEST', true);
}

// Session initialization before headers
require_once __DIR__ . '/../config/constants.php';

if (session_status() === PHP_SESSION_NONE) {
    if (!headers_sent()) {
        ini_set('session.cookie_httponly', '1');
        ini_set('session.use_only_cookies', '1');
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            ini_set('session.cookie_secure', '1');
        }
        session_name(SESSION_COOKIE_NAME);
    }
    @session_start();
}

if (!headers_sent()) {
    header('Content-Type: application/json; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, X-Requested-With');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
}

$reqMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($reqMethod === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

function jsonResponse(array $data, int $statusCode = 200): void {
    if (!headers_sent()) {
        http_response_code($statusCode);
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function jsonError(string $message, int $statusCode = 400, array $extra = []): void {
    if (!headers_sent()) {
        http_response_code($statusCode);
    }
    echo json_encode(array_merge(['success' => false, 'error' => $message], $extra), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function getJsonInput(): array {
    $raw = file_get_contents('php://input');
    if (!empty($raw)) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }
    return $_POST ?? [];
}

/**
 * Safe database checker
 */
function isDbAvailable(): bool {
    try {
        $conn = Database::tryConnection();
        return $conn !== null;
    } catch (\Throwable $e) {
        return false;
    }
}
