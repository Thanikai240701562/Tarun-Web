<?php
/**
 * TeamForge Authentication & Session Management
 */

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/helpers.php';

// Session hardening
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', '1');
    }
    session_name(SESSION_COOKIE_NAME);
    session_start();
}

/**
 * Check if a user is currently logged in
 */
function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']) && !empty($_SESSION['user_role']);
}

/**
 * Get the currently authenticated user record from DB
 */
function currentUser(): ?array {
    static $cachedUser = null;
    if ($cachedUser !== null) {
        return $cachedUser;
    }

    if (!isLoggedIn()) {
        return null;
    }

    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        // User might have been deleted, invalidate session
        logoutUser();
        return null;
    }

    $cachedUser = $user;
    return $cachedUser;
}

/**
 * Enforce authentication on protected pages
 */
function requireAuth(?string $returnUrl = null): void {
    if (!isLoggedIn()) {
        $target = $returnUrl ?? $_SERVER['REQUEST_URI'] ?? (BASE_URL . '/dashboard.php');
        setFlash('warning', 'Please log in to access this page.');
        header('Location: ' . BASE_URL . '/login.php?redirect=' . urlencode($target));
        exit;
    }
}

/**
 * Enforce specific role-based permissions
 */
function requireRole(string|array $allowedRoles): void {
    requireAuth();
    $user = currentUser();
    $roles = is_array($allowedRoles) ? $allowedRoles : [$allowedRoles];

    if (!in_array($user['role'], $roles, true)) {
        setFlash('danger', 'Access denied. You do not possess the required permissions for this action.');
        header('Location: ' . BASE_URL . '/dashboard.php');
        exit;
    }
}

/**
 * Log in a user securely and regenerate session ID
 */
function loginUser(array $user): void {
    // Prevent session fixation
    session_regenerate_id(true);

    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_name'] = $user['full_name'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['logged_in_time'] = time();

    // Log the successful login in audit log
    logAudit($user['id'], 'LOGIN_SUCCESS', 'user', $user['id'], ['email' => $user['email']]);
}

/**
 * Log out user and wipe session securely
 */
function logoutUser(): void {
    if (isLoggedIn()) {
        logAudit($_SESSION['user_id'], 'LOGOUT', 'user', $_SESSION['user_id']);
    }

    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    session_destroy();
}

/**
 * Check if user is a designated Team Leader of a specific team
 */
function isUserTeamLeaderOf(int $userId, int $teamId): bool {
    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM teams WHERE id = ? AND leader_id = ? LIMIT 1");
    $stmt->execute([$teamId, $userId]);
    return (bool)$stmt->fetch();
}

/**
 * Check if user is an active member of a specific team
 */
function isUserMemberOf(int $userId, int $teamId): bool {
    $db = getDB();
    // Leader is always a member
    if (isUserTeamLeaderOf($userId, $teamId)) {
        return true;
    }
    $stmt = $db->prepare("SELECT id FROM team_members WHERE team_id = ? AND user_id = ? AND status = 'active' LIMIT 1");
    $stmt->execute([$teamId, $userId]);
    return (bool)$stmt->fetch();
}

/**
 * Get active teams for a user
 */
function getUserActiveTeams(int $userId): array {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT t.*, 'leader' AS user_relation
        FROM teams t
        WHERE t.leader_id = ? AND t.status != 'archived'
        UNION
        SELECT t.*, 'member' AS user_relation
        FROM teams t
        JOIN team_members tm ON t.id = tm.team_id
        WHERE tm.user_id = ? AND tm.status = 'active' AND t.leader_id != ? AND t.status != 'archived'
        ORDER BY created_at DESC
    ");
    $stmt->execute([$userId, $userId, $userId]);
    return $stmt->fetchAll();
}

/**
 * Get count of unread notifications
 */
function getUnreadNotificationCount(int $userId): int {
    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}
