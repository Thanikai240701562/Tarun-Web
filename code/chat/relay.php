<?php
/**
 * TeamForge Ephemeral Team Chat Relay
 * Privacy Preserving Architecture (Section 15 & 19)
 * Strictly zero persistent storage in MySQL.
 * Uses a rolling ephemeral buffer with strict session authorization.
 */
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

$currentUser = currentUser();
$teamId = (int)($_REQUEST['team_id'] ?? 0);

if ($teamId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid team ID']);
    exit;
}

// Strict Server-Side Membership Authorization
// Prevents users from guessing room IDs
if (!isUserMemberOf($currentUser['id'], $teamId) && $currentUser['role'] !== ROLE_FACULTY_ADMIN) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied: You are not an accepted member of this team']);
    exit;
}

// Temporary in-memory ephemeral buffer directory
$bufferDir = __DIR__ . '/../storage/ephemeral_chat';
if (!is_dir($bufferDir)) {
    @mkdir($bufferDir, 0755, true);
}
$bufferFile = $bufferDir . '/team_' . $teamId . '.json';

// Helper to read current sliding buffer
function getEphemeralBuffer(string $file): array {
    if (!file_exists($file)) return [];
    $data = @file_get_contents($file);
    if (!$data) return [];
    $messages = json_decode($data, true) ?: [];
    // Expire messages older than 3 hours
    $now = time();
    return array_values(array_filter($messages, fn($m) => ($now - $m['timestamp']) < 10800));
}

// Helper to save buffer (keeps last 30 ephemeral messages max)
function saveEphemeralBuffer(string $file, array $messages): void {
    $slice = array_slice($messages, -30);
    @file_put_contents($file, json_encode($slice), LOCK_EX);
}

// Handle GET: Fetch recent ephemeral messages since last_id
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $since = (int)($_GET['since'] ?? 0);
    $messages = getEphemeralBuffer($bufferFile);
    
    $newMessages = [];
    foreach ($messages as $msg) {
        if ($msg['id'] > $since) {
            $newMessages[] = $msg;
        }
    }

    echo json_encode([
        'status' => 'success',
        'messages' => $newMessages,
        'server_time' => time()
    ]);
    exit;
}

// Handle POST: Send a message
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $content = trim($input['content'] ?? '');

    if (empty($content)) {
        http_response_code(400);
        echo json_encode(['error' => 'Message content cannot be empty']);
        exit;
    }

    if (strlen($content) > 1000) {
        http_response_code(400);
        echo json_encode(['error' => 'Message exceeds maximum 1000 characters limit']);
        exit;
    }

    // Sanitize and escape content to prevent XSS
    $safeContent = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');

    $messages = getEphemeralBuffer($bufferFile);
    $newId = count($messages) > 0 ? (end($messages)['id'] + 1) : 1;

    $newMsg = [
        'id' => $newId,
        'user_id' => $currentUser['id'],
        'sender_name' => $currentUser['full_name'],
        'role' => $currentUser['role'],
        'content' => $safeContent,
        'timestamp' => time(),
        'time_str' => date('H:i')
    ];

    $messages[] = $newMsg;
    saveEphemeralBuffer($bufferFile, $messages);

    echo json_encode([
        'status' => 'success',
        'message' => $newMsg
    ]);
    exit;
}
