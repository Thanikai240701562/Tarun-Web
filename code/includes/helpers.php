<?php
/**
 * TeamForge Core Helper Functions
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Escape HTML output securely to prevent XSS
 */
function e(?string $string): string {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Clean user string input
 */
function sanitize(?string $data): string {
    return trim(strip_tags($data ?? ''));
}

/**
 * Generate or retrieve CSRF token
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Render a hidden CSRF input field
 */
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

/**
 * Verify CSRF token from POST or request headers
 */
function verify_csrf(?string $token = null): bool {
    $submittedToken = $token ?? $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if (empty($submittedToken) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $submittedToken);
}

/**
 * Flash messaging system (success, error, warning, info)
 */
function setFlash(string $type, string $message): void {
    if (!isset($_SESSION['flash_messages'])) {
        $_SESSION['flash_messages'] = [];
    }
    $_SESSION['flash_messages'][] = [
        'type' => $type,
        'message' => $message
    ];
}

function getFlashes(): array {
    $flashes = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);
    return $flashes;
}

/**
 * Render flash messages alert boxes
 */
function renderFlashes(): string {
    $flashes = getFlashes();
    if (empty($flashes)) return '';

    $html = '<div class="flash-container mb-4">';
    foreach ($flashes as $f) {
        $alertClass = match($f['type']) {
            'success' => 'alert-success',
            'error', 'danger' => 'alert-danger',
            'warning' => 'alert-warning',
            default => 'alert-info'
        };
        $icon = match($f['type']) {
            'success' => 'bi-check-circle-fill',
            'error', 'danger' => 'bi-exclamation-octagon-fill',
            'warning' => 'bi-exclamation-triangle-fill',
            default => 'bi-info-circle-fill'
        };
        $html .= '<div class="alert ' . $alertClass . ' alert-dismissible fade show d-flex align-items-center shadow-sm" role="alert">';
        $html .= '<i class="bi ' . $icon . ' me-2 fs-5"></i>';
        $html .= '<div>' . e($f['message']) . '</div>';
        $html .= '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        $html .= '</div>';
    }
    $html .= '</div>';
    return $html;
}

/**
 * Format date to human friendly string
 */
function formatDate(?string $dateStr, string $format = 'M d, Y'): string {
    if (!$dateStr) return 'N/A';
    return date($format, strtotime($dateStr));
}

/**
 * Relative time representation (e.g. 2 hours ago)
 */
function timeAgo(?string $datetime): string {
    if (!$datetime) return 'Never';
    $time = strtotime($datetime);
    $diff = time() - $time;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M d, Y', $time);
}

/**
 * Log audit events to database
 */
function logAudit(?int $userId, string $action, string $entityType, ?int $entityId = null, array $details = []): void {
    try {
        $db = getDB();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $stmt = $db->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details_json, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $userId,
            $action,
            $entityType,
            $entityId,
            json_encode($details),
            $ip
        ]);
    } catch (Exception $e) {
        error_log("Failed to write audit log: " . $e->getMessage());
    }
}

/**
 * Create in-app notification
 */
function notifyUser(int $userId, string $title, string $message, ?string $link = null, string $type = 'system'): void {
    try {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO notifications (user_id, title, message, link, type) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $title, $message, $link, $type]);
    } catch (Exception $e) {
        error_log("Failed to create notification: " . $e->getMessage());
    }
}

/**
 * Generate Avatar URL or initials badge
 */
function getAvatar(array $user, int $size = 40): string {
    if (!empty($user['avatar_url'])) {
        return '<img src="' . e($user['avatar_url']) . '" alt="' . e($user['full_name']) . '" class="rounded-circle" width="' . $size . '" height="' . $size . '" style="object-fit:cover;">';
    }
    // Initials fallback
    $words = explode(' ', trim($user['full_name'] ?? 'User'));
    $initials = '';
    foreach (array_slice($words, 0, 2) as $w) {
        $initials .= strtoupper(substr($w, 0, 1));
    }
    return '<div class="avatar-initials rounded-circle d-inline-flex align-items-center justify-content-center text-white fw-bold shadow-sm" style="width:' . $size . 'px; height:' . $size . 'px; font-size:' . round($size * 0.4) . 'px; background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);">' . e($initials) . '</div>';
}

/**
 * Render role badge badge
 */
function getRoleBadge(string $role): string {
    return match($role) {
        ROLE_FACULTY_ADMIN => '<span class="badge bg-purple-subtle text-purple border border-purple-subtle"><i class="bi bi-shield-check me-1"></i>Faculty / Admin</span>',
        ROLE_TEAM_LEADER => '<span class="badge bg-primary-subtle text-primary border border-primary-subtle"><i class="bi bi-award-fill me-1"></i>Team Leader</span>',
        default => '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle"><i class="bi bi-person-fill me-1"></i>Student</span>'
    };
}
