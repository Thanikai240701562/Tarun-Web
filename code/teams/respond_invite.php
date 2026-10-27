<?php
/**
 * TeamForge Respond to Team Invitation (Accept / Reject)
 * Uses strict MySQL transactions to prevent race conditions and over-capacity assignments.
 */
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

requireAuth();
$currentUser = currentUser();
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
    setFlash('danger', 'Security validation failed.');
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$inviteId = (int)($_POST['invite_id'] ?? 0);
$decision = sanitize($_POST['decision'] ?? ''); // 'accept' or 'reject'

if ($inviteId <= 0 || !in_array($decision, ['accept', 'reject'])) {
    setFlash('danger', 'Invalid invitation response parameters.');
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

try {
    $db->beginTransaction();

    // Lock invitation record
    $invStmt = $db->prepare("
        SELECT ti.*, t.name as team_name, t.required_team_size, t.leader_id
        FROM team_invitations ti
        JOIN teams t ON ti.team_id = t.id
        WHERE ti.id = ? AND ti.recipient_id = ?
        FOR UPDATE
    ");
    $invStmt->execute([$inviteId, $currentUser['id']]);
    $inv = $invStmt->fetch();

    if (!$inv) {
        $db->rollBack();
        setFlash('danger', 'Invitation not found or not addressed to you.');
        header('Location: ' . BASE_URL . '/dashboard.php');
        exit;
    }

    if ($inv['status'] !== 'pending') {
        $db->rollBack();
        setFlash('info', 'This invitation has already been ' . $inv['status'] . '.');
        header('Location: ' . BASE_URL . '/dashboard.php');
        exit;
    }

    if ($decision === 'reject') {
        $upd = $db->prepare("UPDATE team_invitations SET status = 'rejected' WHERE id = ?");
        $upd->execute([$inviteId]);

        // Notify team leader
        notifyUser(
            $inv['leader_id'],
            'Invitation Declined',
            $currentUser['full_name'] . ' declined the invitation to join "' . $inv['team_name'] . '".',
            'teams/view.php?id=' . $inv['team_id'],
            'invitation'
        );

        $db->commit();
        setFlash('info', 'You declined the team invitation.');
        header('Location: ' . BASE_URL . '/dashboard.php');
        exit;
    }

    // Accepting Invitation:
    // 1. Lock team membership count
    $capCheck = $db->prepare("SELECT COUNT(*) FROM team_members WHERE team_id = ? AND status = 'active' FOR UPDATE");
    $capCheck->execute([$inv['team_id']]);
    $currentCount = (int)$capCheck->fetchColumn();

    if ($currentCount >= (int)$inv['required_team_size']) {
        $db->rollBack();
        setFlash('warning', 'Unable to join: This team has already reached its maximum capacity (' . $inv['required_team_size'] . ').');
        header('Location: ' . BASE_URL . '/dashboard.php');
        exit;
    }

    // 2. Check if user is already an active member
    $existCheck = $db->prepare("SELECT id FROM team_members WHERE team_id = ? AND user_id = ? AND status = 'active'");
    $existCheck->execute([$inv['team_id'], $currentUser['id']]);
    if ($existCheck->fetch()) {
        $db->rollBack();
        setFlash('info', 'You are already an active member of this team.');
        header('Location: ' . BASE_URL . '/teams/view.php?id=' . $inv['team_id']);
        exit;
    }

    // 3. Insert membership record
    $insMem = $db->prepare("
        INSERT INTO team_members (team_id, user_id, assigned_role, status)
        VALUES (?, ?, ?, 'active')
        ON DUPLICATE KEY UPDATE assigned_role = VALUES(assigned_role), status = 'active'
    ");
    $insMem->execute([$inv['team_id'], $currentUser['id'], $inv['invited_role']]);

    // 4. Update user availability to 'in_team'
    $updUser = $db->prepare("UPDATE users SET availability_status = 'in_team' WHERE id = ?");
    $updUser->execute([$currentUser['id']]);

    // 5. Update invitation status to accepted
    $updInv = $db->prepare("UPDATE team_invitations SET status = 'accepted' WHERE id = ?");
    $updInv->execute([$inviteId]);

    // 6. Notify Team Leader
    notifyUser(
        $inv['leader_id'],
        'Invitation Accepted!',
        $currentUser['full_name'] . ' joined "' . $inv['team_name'] . '" as ' . $inv['invited_role'] . '.',
        'teams/view.php?id=' . $inv['team_id'],
        'team'
    );

    $db->commit();
    logAudit($currentUser['id'], 'INVITATION_ACCEPTED', 'team', $inv['team_id']);

    setFlash('success', 'You have joined ' . $inv['team_name'] . ' as ' . $inv['invited_role'] . '!');
    header('Location: ' . BASE_URL . '/teams/view.php?id=' . $inv['team_id']);
    exit;

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log("Failed to respond to invitation: " . $e->getMessage());
    setFlash('danger', 'An error occurred while processing your response: ' . $e->getMessage());
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}
