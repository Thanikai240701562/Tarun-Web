<?php
/**
 * TeamForge Send Team Invitation Action Handler
 */
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

requireAuth();
$currentUser = currentUser();
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
    setFlash('danger', 'Invalid request or session token expired.');
    header('Location: ' . BASE_URL . '/students/index.php');
    exit;
}

$teamId = (int)($_POST['team_id'] ?? 0);
$recipientIdsRaw = $_POST['recipient_ids'] ?? '';
$invitedRole = sanitize($_POST['invited_role'] ?? 'General Developer');
$customMessage = sanitize($_POST['message'] ?? '');

if ($teamId <= 0 || empty($recipientIdsRaw)) {
    setFlash('danger', 'Please specify a valid team and at least one student.');
    header('Location: ' . BASE_URL . '/students/index.php');
    exit;
}

// Verify that currentUser is the designated team leader
$teamStmt = $db->prepare("SELECT * FROM teams WHERE id = ? LIMIT 1");
$teamStmt->execute([$teamId]);
$team = $teamStmt->fetch();

if (!$team || (int)$team['leader_id'] !== (int)$currentUser['id']) {
    setFlash('danger', 'Unauthorized. Only the designated Team Leader can send team invitations.');
    header('Location: ' . BASE_URL . '/students/index.php');
    exit;
}

// Check current team capacity
$memCountStmt = $db->prepare("SELECT COUNT(*) FROM team_members WHERE team_id = ? AND status = 'active'");
$memCountStmt->execute([$teamId]);
$currentMembersCount = (int)$memCountStmt->fetchColumn();

if ($currentMembersCount >= (int)$team['required_team_size']) {
    setFlash('warning', 'Your team is already at maximum capacity (' . $team['required_team_size'] . ' members). Increase team size before sending more invites.');
    header('Location: ' . BASE_URL . '/teams/view.php?id=' . $teamId);
    exit;
}

$recipientIds = array_filter(array_map('intval', explode(',', $recipientIdsRaw)));
$successCount = 0;

foreach ($recipientIds as $recId) {
    if ($recId === (int)$currentUser['id']) continue;

    // Check if recipient is already a team member
    $isMemStmt = $db->prepare("SELECT id FROM team_members WHERE team_id = ? AND user_id = ? AND status = 'active' LIMIT 1");
    $isMemStmt->execute([$teamId, $recId]);
    if ($isMemStmt->fetch()) continue;

    // Check if pending invite already exists
    $chkInv = $db->prepare("SELECT id FROM team_invitations WHERE team_id = ? AND recipient_id = ? AND status = 'pending' LIMIT 1");
    $chkInv->execute([$teamId, $recId]);
    if ($chkInv->fetch()) continue;

    // Insert invitation
    $insInv = $db->prepare("
        INSERT INTO team_invitations (team_id, sender_id, recipient_id, invited_role, status, message)
        VALUES (?, ?, ?, ?, 'pending', ?)
    ");
    $insInv->execute([$teamId, $currentUser['id'], $recId, $invitedRole, $customMessage]);
    
    // Notify recipient
    notifyUser(
        $recId,
        'Team Invitation Received',
        $currentUser['full_name'] . ' invited you to join "' . $team['name'] . '" as ' . $invitedRole . '.',
        'dashboard.php',
        'invitation'
    );
    
    $successCount++;
}

logAudit($currentUser['id'], 'INVITATIONS_SENT', 'team', $teamId, ['recipients_count' => $successCount]);

if ($successCount > 0) {
    setFlash('success', "Sent {$successCount} team invitation(s) successfully!");
} else {
    setFlash('info', 'No new invitations sent (candidates may already be members or have pending invites).');
}

header('Location: ' . BASE_URL . '/teams/view.php?id=' . $teamId);
exit;
