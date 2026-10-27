<?php
/**
 * TeamForge Team Management Dashboard
 */
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

requireAuth();
$currentUser = currentUser();
$db = getDB();

$teamId = (int)($_GET['id'] ?? 0);
if ($teamId <= 0) {
    setFlash('danger', 'Invalid team requested.');
    header('Location: ' . BASE_URL . '/teams/my_teams.php');
    exit;
}

// Fetch team details
$teamStmt = $db->prepare("
    SELECT t.*, u.full_name as leader_name, u.email as leader_email, e.title as event_title, e.event_date
    FROM teams t
    JOIN users u ON t.leader_id = u.id
    LEFT JOIN events e ON t.event_id = e.id
    WHERE t.id = ?
    LIMIT 1
");
$teamStmt->execute([$teamId]);
$team = $teamStmt->fetch();

if (!$team) {
    setFlash('danger', 'Team not found.');
    header('Location: ' . BASE_URL . '/teams/my_teams.php');
    exit;
}

$isLeader = ((int)$team['leader_id'] === (int)$currentUser['id']);
$isMember = isUserMemberOf($currentUser['id'], $teamId);
$isAdmin = ($currentUser['role'] === ROLE_FACULTY_ADMIN);

// Privacy check: Non-members cannot view private teams unless admin
if ($team['visibility'] === 'private' && !$isMember && !$isAdmin) {
    setFlash('danger', 'This team is private. Access is restricted to accepted team members.');
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

// Handle Leader & Member Actions (Transfer Leader, Accept/Reject Request, Remove Member, Leave Team, Update Info)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        setFlash('danger', 'Security validation failed.');
        header('Location: ' . BASE_URL . '/teams/view.php?id=' . $teamId);
        exit;
    }

    $action = $_POST['action'] ?? '';

    // 1. Accept Join Request (Leader Only)
    if ($action === 'accept_request' && $isLeader) {
        $reqId = (int)($_POST['request_id'] ?? 0);
        try {
            $db->beginTransaction();
            $rStmt = $db->prepare("SELECT * FROM join_requests WHERE id = ? AND team_id = ? AND status = 'pending' FOR UPDATE");
            $rStmt->execute([$reqId, $teamId]);
            $req = $rStmt->fetch();

            if (!$req) {
                $db->rollBack();
                setFlash('danger', 'Join request not found or already processed.');
            } else {
                // Check capacity
                $memCount = (int)$db->query("SELECT COUNT(*) FROM team_members WHERE team_id = {$teamId} AND status = 'active'")->fetchColumn();
                if ($memCount >= (int)$team['required_team_size']) {
                    $db->rollBack();
                    setFlash('warning', 'Cannot accept: Team has reached its maximum size limit.');
                } else {
                    $insMem = $db->prepare("
                        INSERT INTO team_members (team_id, user_id, assigned_role, status)
                        VALUES (?, ?, ?, 'active')
                        ON DUPLICATE KEY UPDATE assigned_role = VALUES(assigned_role), status = 'active'
                    ");
                    $insMem->execute([$teamId, $req['user_id'], $req['requested_role']]);

                    $db->prepare("UPDATE join_requests SET status = 'accepted' WHERE id = ?")->execute([$reqId]);
                    $db->prepare("UPDATE users SET availability_status = 'in_team' WHERE id = ?")->execute([$req['user_id']]);

                    notifyUser($req['user_id'], 'Join Request Accepted', 'You have been accepted into ' . $team['name'] . ' as ' . $req['requested_role'] . '!', 'teams/view.php?id=' . $teamId, 'team');
                    $db->commit();
                    logAudit($currentUser['id'], 'JOIN_REQUEST_ACCEPTED', 'team', $teamId, ['accepted_user_id' => $req['user_id']]);
                    setFlash('success', 'Student accepted into team!');
                }
            }
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            setFlash('danger', 'Failed to accept request: ' . $e->getMessage());
        }
        header('Location: ' . BASE_URL . '/teams/view.php?id=' . $teamId);
        exit;
    }

    // 2. Reject Join Request (Leader Only)
    if ($action === 'reject_request' && $isLeader) {
        $reqId = (int)($_POST['request_id'] ?? 0);
        $db->prepare("UPDATE join_requests SET status = 'rejected' WHERE id = ? AND team_id = ?")->execute([$reqId, $teamId]);
        setFlash('info', 'Join request rejected.');
        header('Location: ' . BASE_URL . '/teams/view.php?id=' . $teamId);
        exit;
    }

    // 3. Remove Member (Leader Only)
    if ($action === 'remove_member' && $isLeader) {
        $targetUserId = (int)($_POST['user_id'] ?? 0);
        if ($targetUserId !== (int)$currentUser['id']) {
            $db->prepare("UPDATE team_members SET status = 'withdrawn' WHERE team_id = ? AND user_id = ?")->execute([$teamId, $targetUserId]);
            $db->prepare("UPDATE users SET availability_status = 'available' WHERE id = ?")->execute([$targetUserId]);
            notifyUser($targetUserId, 'Removed from Team', 'You were removed from ' . $team['name'] . ' by the team leader.', 'teams/my_teams.php', 'team');
            logAudit($currentUser['id'], 'MEMBER_REMOVED', 'team', $teamId, ['removed_user_id' => $targetUserId]);
            setFlash('info', 'Member marked as withdrawn from team.');
        }
        header('Location: ' . BASE_URL . '/teams/view.php?id=' . $teamId);
        exit;
    }

    // 4. Transfer Leadership (Leader Only - Section 8)
    if ($action === 'transfer_leadership' && $isLeader) {
        $newLeaderId = (int)($_POST['new_leader_id'] ?? 0);
        if ($newLeaderId > 0 && $newLeaderId !== (int)$currentUser['id']) {
            try {
                $db->beginTransaction();
                // Verify new leader is active member
                $chkNew = $db->prepare("SELECT id FROM team_members WHERE team_id = ? AND user_id = ? AND status = 'active'");
                $chkNew->execute([$teamId, $newLeaderId]);
                if (!$chkNew->fetch()) {
                    $db->rollBack();
                    setFlash('danger', 'New leader must be an active member of this team.');
                } else {
                    $db->prepare("UPDATE teams SET leader_id = ? WHERE id = ?")->execute([$newLeaderId, $teamId]);
                    $db->prepare("UPDATE users SET role = 'team_leader' WHERE id = ?")->execute([$newLeaderId]);
                    $db->prepare("UPDATE team_members SET assigned_role = 'Team Leader' WHERE team_id = ? AND user_id = ?")->execute([$teamId, $newLeaderId]);
                    $db->prepare("UPDATE team_members SET assigned_role = 'Senior Developer' WHERE team_id = ? AND user_id = ?")->execute([$teamId, $currentUser['id']]);

                    notifyUser($newLeaderId, 'Promoted to Team Leader', 'You are now the designated Team Leader of ' . $team['name'] . '.', 'teams/view.php?id=' . $teamId, 'team');
                    $db->commit();
                    logAudit($currentUser['id'], 'LEADERSHIP_TRANSFERRED', 'team', $teamId, ['from' => $currentUser['id'], 'to' => $newLeaderId]);
                    setFlash('success', 'Leadership successfully transferred!');
                }
            } catch (Exception $e) {
                if ($db->inTransaction()) $db->rollBack();
                setFlash('danger', 'Failed to transfer leadership: ' . $e->getMessage());
            }
        }
        header('Location: ' . BASE_URL . '/teams/view.php?id=' . $teamId);
        exit;
    }

    // 5. Leave Team (Member only, not sole leader)
    if ($action === 'leave_team' && $isMember && !$isLeader) {
        $db->prepare("UPDATE team_members SET status = 'withdrawn' WHERE team_id = ? AND user_id = ?")->execute([$teamId, $currentUser['id']]);
        $db->prepare("UPDATE users SET availability_status = 'available' WHERE id = ?")->execute([$currentUser['id']]);
        notifyUser($team['leader_id'], 'Member Withdrawn', $currentUser['full_name'] . ' has left team ' . $team['name'] . '.', 'teams/view.php?id=' . $teamId, 'team');
        logAudit($currentUser['id'], 'MEMBER_LEFT', 'team', $teamId);
        setFlash('info', 'You have left the team.');
        header('Location: ' . BASE_URL . '/teams/my_teams.php');
        exit;
    }

    // 6. Edit Team Info (Leader Only - Section 7)
    if ($action === 'edit_team' && $isLeader) {
        $name = sanitize($_POST['name'] ?? '');
        $projectTitle = sanitize($_POST['project_title'] ?? '');
        $projectDesc = sanitize($_POST['project_description'] ?? '');
        $projectCat = sanitize($_POST['project_category'] ?? 'Web & AI Development');
        $reqSize = max(2, min(8, (int)($_POST['required_team_size'] ?? 4)));
        $deadline = !empty($_POST['project_deadline']) ? sanitize($_POST['project_deadline']) : null;
        $visibility = in_array($_POST['visibility'] ?? '', ['public', 'private']) ? $_POST['visibility'] : 'public';

        if (!empty($name) && !empty($projectTitle)) {
            $upd = $db->prepare("
                UPDATE teams SET
                    name = ?, project_title = ?, project_description = ?,
                    project_category = ?, required_team_size = ?,
                    project_deadline = ?, visibility = ?
                WHERE id = ?
            ");
            $upd->execute([$name, $projectTitle, $projectDesc, $projectCat, $reqSize, $deadline, $visibility, $teamId]);
            logAudit($currentUser['id'], 'TEAM_UPDATED', 'team', $teamId);
            setFlash('success', 'Team information updated successfully.');
        } else {
            setFlash('danger', 'Team Name and Project Title cannot be blank.');
        }
        header('Location: ' . BASE_URL . '/teams/view.php?id=' . $teamId);
        exit;
    }
}

// Fetch Active Members
$membersStmt = $db->prepare("
    SELECT tm.*, u.full_name, u.email, u.student_id_number, u.department, u.degree, u.academic_year, u.avatar_url, u.github_url
    FROM team_members tm
    JOIN users u ON tm.user_id = u.id
    WHERE tm.team_id = ? AND tm.status = 'active'
    ORDER BY (tm.user_id = ?) DESC, tm.joined_at ASC
");
$membersStmt->execute([$teamId, $team['leader_id']]);
$members = $membersStmt->fetchAll();

// Fetch Pending Join Requests
$joinReqsStmt = $db->prepare("
    SELECT jr.*, u.full_name, u.email, u.department, u.academic_year, u.avatar_url
    FROM join_requests jr
    JOIN users u ON jr.user_id = u.id
    WHERE jr.team_id = ? AND jr.status = 'pending'
    ORDER BY jr.created_at DESC
");
$joinReqsStmt->execute([$teamId]);
$pendingJoinRequests = $joinReqsStmt->fetchAll();

// Fetch Required Skills & Verification of coverage
$reqSkillsStmt = $db->prepare("
    SELECT s.id, s.name, s.category,
           (SELECT COUNT(*) FROM team_members tm 
            JOIN student_skills ss ON tm.user_id = ss.user_id 
            WHERE tm.team_id = ? AND tm.status = 'active' AND ss.skill_id = s.id) as covering_members_count
    FROM team_skills ts
    JOIN skills s ON ts.skill_id = s.id
    WHERE ts.team_id = ?
");
$reqSkillsStmt->execute([$teamId, $teamId]);
$requiredSkills = $reqSkillsStmt->fetchAll();

$coveredSkillsCount = 0;
foreach ($requiredSkills as $rsk) {
    if ($rsk['covering_members_count'] > 0) $coveredSkillsCount++;
}
$coveragePct = count($requiredSkills) > 0 ? round(($coveredSkillsCount / count($requiredSkills)) * 100) : 100;

$pageTitle = e($team['name']) . " — Team Management";
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-0">
    <div class="mb-3">
        <a href="<?= BASE_URL ?>/teams/my_teams.php" class="text-decoration-none text-muted small">
            &larr; Back to My Teams
        </a>
    </div>

    <!-- Team Header Card -->
    <div class="card-tf shadow-sm mb-4">
        <div class="p-4 p-md-5" style="background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%); color: white; border-radius: 12px 12px 0 0;">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-primary px-3 py-2 text-uppercase" style="font-size:11px;">
                        <?= e($team['project_category']) ?>
                    </span>
                    <span class="badge bg-light text-dark font-mono px-3 py-2 fw-bold" style="font-size:12px;">
                        Team Code: <?= e($team['team_code']) ?>
                    </span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <?php if ($isLeader): ?>
                        <span class="badge bg-warning text-dark px-3 py-2 fw-bold">
                            <i class="bi bi-award-fill me-1"></i> You are Team Leader
                        </span>
                    <?php endif; ?>
                    <span class="badge bg-white text-dark px-3 py-2 fw-bold">
                        <?= count($members) ?> / <?= (int)$team['required_team_size'] ?> Members
                    </span>
                </div>
            </div>

            <h2 class="display-6 fw-bold text-white mb-2"><?= e($team['name']) ?></h2>
            <div class="text-info fw-semibold fs-5 mb-3"><?= e($team['project_title']) ?></div>
            <p class="text-light opacity-75 small mb-0" style="max-width: 800px; line-height: 1.6;">
                <?= nl2br(e($team['project_description'])) ?>
            </p>
        </div>

        <div class="p-3 bg-white border-top d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="d-flex flex-wrap align-items-center gap-4 text-muted small">
                <div><i class="bi bi-person-fill text-primary me-1"></i> Leader: <strong><?= e($team['leader_name']) ?></strong></div>
                <?php if ($team['event_title']): ?>
                    <div><i class="bi bi-calendar-event text-primary me-1"></i> Event: <strong><?= e($team['event_title']) ?></strong></div>
                <?php endif; ?>
                <div><i class="bi bi-hourglass-split text-primary me-1"></i> Deadline: <strong><?= formatDate($team['project_deadline']) ?></strong></div>
            </div>

            <!-- Quick Comms & Actions -->
            <div class="d-flex align-items-center gap-2">
                <?php if ($isLeader): ?>
                    <button class="btn btn-sm btn-white border shadow-sm text-dark" data-bs-toggle="modal" data-bs-target="#editTeamModal">
                        <i class="bi bi-pencil-square text-primary me-1"></i> Edit Info
                    </button>
                <?php endif; ?>
                <a href="<?= BASE_URL ?>/chat/index.php?team_id=<?= $teamId ?>" class="btn btn-sm btn-tf-primary">
                    <i class="bi bi-chat-dots-fill me-1"></i> Group Chat
                </a>
                <a href="<?= BASE_URL ?>/sessions/index.php?team_id=<?= $teamId ?>" class="btn btn-sm btn-tf-outline">
                    <i class="bi bi-clock-history me-1"></i> Sessions
                </a>
                <a href="<?= BASE_URL ?>/tasks/index.php?team_id=<?= $teamId ?>" class="btn btn-sm btn-light border">
                    <i class="bi bi-kanban-fill me-1"></i> Tasks
                </a>
                <?php if ($isMember && !$isLeader): ?>
                    <form method="POST" action="<?= BASE_URL ?>/teams/view.php?id=<?= $teamId ?>" onsubmit="return confirm('Are you sure you want to leave this team?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="leave_team">
                        <button type="submit" class="btn btn-sm btn-outline-danger">Leave Team</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Main Team Content Grid -->
    <div class="row g-4">
        <!-- Left: Team Roster & Pending Requests -->
        <div class="col-lg-7">
            <!-- Active Members Roster -->
            <div class="card-tf mb-4">
                <div class="card-tf-header">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-people-fill text-primary fs-5"></i>
                        <h5 class="card-tf-title">Team Roster (<?= count($members) ?> / <?= (int)$team['required_team_size'] ?>)</h5>
                    </div>
                    <?php if ($isLeader && count($members) < (int)$team['required_team_size']): ?>
                        <a href="<?= BASE_URL ?>/students/index.php" class="btn btn-sm btn-tf-primary">
                            <i class="bi bi-person-plus me-1"></i> Recruit Students
                        </a>
                    <?php endif; ?>
                </div>

                <div class="card-tf-body">
                    <div class="d-flex flex-column gap-3">
                        <?php foreach ($members as $mem): 
                            $isThisLeader = ((int)$mem['user_id'] === (int)$team['leader_id']);
                        ?>
                            <div class="p-3 border rounded-3 bg-white d-flex flex-wrap align-items-center justify-content-between gap-3">
                                <div class="d-flex align-items-center gap-3">
                                    <?= getAvatar($mem, 46) ?>
                                    <div>
                                        <div class="d-flex align-items-center gap-2">
                                            <h6 class="fw-bold text-dark mb-0"><?= e($mem['full_name']) ?></h6>
                                            <?php if ($isThisLeader): ?>
                                                <span class="badge bg-warning text-dark font-mono" style="font-size:10px;"><i class="bi bi-award-fill"></i> Leader</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="badge bg-primary-subtle text-primary border border-primary-subtle small my-1">
                                            <?= e($mem['assigned_role']) ?>
                                        </div>
                                        <div class="text-muted small" style="font-size:11px;">
                                            <?= e($mem['department']) ?> &bull; Joined <?= formatDate($mem['joined_at']) ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex align-items-center gap-2">
                                    <a href="<?= BASE_URL ?>/profile.php?id=<?= $mem['user_id'] ?>" class="btn btn-sm btn-light border text-primary" style="font-size:11px;">
                                        Profile
                                    </a>
                                    <?php if ($isLeader && !$isThisLeader): ?>
                                        <!-- Leadership Transfer button -->
                                        <form method="POST" action="<?= BASE_URL ?>/teams/view.php?id=<?= $teamId ?>" onsubmit="return confirm('Transfer Team Leadership to <?= e($mem['full_name']) ?>? You will become a regular member.');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="transfer_leadership">
                                            <input type="hidden" name="new_leader_id" value="<?= $mem['user_id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-warning" style="font-size:11px;" title="Transfer Leadership">
                                                <i class="bi bi-arrow-left-right"></i> Make Leader
                                            </button>
                                        </form>

                                        <!-- Remove Member -->
                                        <form method="POST" action="<?= BASE_URL ?>/teams/view.php?id=<?= $teamId ?>" onsubmit="return confirm('Remove <?= e($mem['full_name']) ?> from team?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="remove_member">
                                            <input type="hidden" name="user_id" value="<?= $mem['user_id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" style="font-size:11px;" title="Remove Member">
                                                <i class="bi bi-person-x"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Pending Join Requests (Leader Only) -->
            <?php if ($isLeader): ?>
                <div class="card-tf mb-4">
                    <div class="card-tf-header">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-envelope-open-fill text-warning fs-5"></i>
                            <h5 class="card-tf-title">Pending Join Requests (<?= count($pendingJoinRequests) ?>)</h5>
                        </div>
                    </div>
                    <div class="card-tf-body">
                        <?php if (empty($pendingJoinRequests)): ?>
                            <div class="text-center py-3 text-muted small">
                                No pending join requests.
                            </div>
                        <?php else: ?>
                            <div class="d-flex flex-column gap-2">
                                <?php foreach ($pendingJoinRequests as $pReq): ?>
                                    <div class="p-3 border rounded-3 bg-light d-flex flex-wrap align-items-center justify-content-between gap-3">
                                        <div class="d-flex align-items-center gap-3">
                                            <?= getAvatar($pReq, 38) ?>
                                            <div>
                                                <div class="fw-bold text-dark small"><?= e($pReq['full_name']) ?></div>
                                                <div class="text-muted small" style="font-size:11px;">
                                                    Requested: <strong><?= e($pReq['requested_role']) ?></strong> &bull; <?= e($pReq['department']) ?>
                                                </div>
                                                <?php if (!empty($pReq['message'])): ?>
                                                    <div class="text-secondary small fst-italic mt-1" style="font-size:11px;">"<?= e($pReq['message']) ?>"</div>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <div class="d-flex gap-2">
                                            <form method="POST" action="<?= BASE_URL ?>/teams/view.php?id=<?= $teamId ?>">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="accept_request">
                                                <input type="hidden" name="request_id" value="<?= $pReq['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-success px-3" style="font-size:12px;">Accept</button>
                                            </form>
                                            <form method="POST" action="<?= BASE_URL ?>/teams/view.php?id=<?= $teamId ?>">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="reject_request">
                                                <input type="hidden" name="request_id" value="<?= $pReq['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger px-3" style="font-size:12px;">Decline</button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Right: Required Skills Matrix & AI Assistant -->
        <div class="col-lg-5">
            <!-- Skill Coverage Analysis -->
            <div class="card-tf mb-4">
                <div class="card-tf-header">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-pie-chart-fill text-primary fs-5"></i>
                        <h5 class="card-tf-title">Required Skill Coverage</h5>
                    </div>
                    <span class="badge bg-<?= $coveragePct >= 100 ? 'success' : ($coveragePct >= 60 ? 'warning' : 'danger') ?> fw-bold">
                        <?= $coveragePct ?>% Covered
                    </span>
                </div>
                <div class="card-tf-body">
                    <div class="progress mb-3" style="height: 8px;">
                        <div class="progress-bar <?= $coveragePct >= 100 ? 'bg-success' : ($coveragePct >= 60 ? 'bg-warning' : 'bg-danger') ?>" style="width: <?= $coveragePct ?>%;"></div>
                    </div>

                    <?php if (empty($requiredSkills)): ?>
                        <div class="text-center py-3 text-muted small">
                            No specific required skills declared for this team.
                        </div>
                    <?php else: ?>
                        <div class="d-flex flex-column gap-2">
                            <?php foreach ($requiredSkills as $rsk): 
                                $isCovered = ($rsk['covering_members_count'] > 0);
                            ?>
                                <div class="d-flex align-items-center justify-content-between p-2 rounded-3 border <?= $isCovered ? 'bg-light' : 'bg-danger-subtle border-danger-subtle' ?>">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="bi bi-<?= $isCovered ? 'check-circle-fill text-success' : 'exclamation-circle-fill text-danger' ?>"></i>
                                        <span class="fw-semibold small text-dark"><?= e($rsk['name']) ?></span>
                                    </div>
                                    <span class="badge <?= $isCovered ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' ?> small" style="font-size:10px;">
                                        <?= $isCovered ? $rsk['covering_members_count'] . ' Member(s)' : 'Missing!' ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Smart Member Replacement Callout (Section 13) -->
            <div class="card-tf p-4 mb-4" style="background: linear-gradient(135deg, #f8fafc 0%, #eef2ff 100%); border: 1px solid #c7d2fe;">
                <div class="d-flex align-items-center gap-2 text-primary fw-bold mb-2 small text-uppercase">
                    <i class="bi bi-arrow-repeat"></i> Smart Member Replacement
                </div>
                <h6 class="fw-bold text-dark mb-2">Teammate Withdrew or Skill Gap?</h6>
                <p class="text-muted small mb-3">
                    The Smart Replacement engine calculates eligible peers across campus who cover exact missing skills without rebuilding your entire team.
                </p>
                <a href="<?= BASE_URL ?>/teams/smart_replace.php?team_id=<?= $teamId ?>" class="btn btn-sm btn-tf-primary">
                    <i class="bi bi-search me-1"></i> Find Smart Replacements &rarr;
                </a>
            </div>
        </div>
    </div>
</div>

<?php if ($isLeader): ?>
<!-- Modal: Edit Team Info -->
<div class="modal fade" id="editTeamModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <form method="POST" action="<?= BASE_URL ?>/teams/view.php?id=<?= $teamId ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="edit_team">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold">Edit Team & Project Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Team Name *</label>
                            <input type="text" class="form-control" name="name" value="<?= e($team['name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Project Category</label>
                            <select class="form-select" name="project_category">
                                <option value="Web & AI Development" <?= $team['project_category'] === 'Web & AI Development' ? 'selected' : '' ?>>Web & AI Development</option>
                                <option value="Algorithms & Systems" <?= $team['project_category'] === 'Algorithms & Systems' ? 'selected' : '' ?>>Algorithms & Systems</option>
                                <option value="Mobile Applications" <?= $team['project_category'] === 'Mobile Applications' ? 'selected' : '' ?>>Mobile Applications</option>
                                <option value="IoT & Hardware" <?= $team['project_category'] === 'IoT & Hardware' ? 'selected' : '' ?>>IoT & Hardware</option>
                                <option value="Civic & Social Tech" <?= $team['project_category'] === 'Civic & Social Tech' ? 'selected' : '' ?>>Civic & Social Tech</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Project Title *</label>
                        <input type="text" class="form-control" name="project_title" value="<?= e($team['project_title']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Project Description</label>
                        <textarea class="form-control" name="project_description" rows="3"><?= e($team['project_description']) ?></textarea>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Target Team Size</label>
                            <input type="number" class="form-control" name="required_team_size" value="<?= (int)$team['required_team_size'] ?>" min="<?= count($members) ?>" max="8">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Deadline</label>
                            <input type="date" class="form-control" name="project_deadline" value="<?= e($team['project_deadline']) ?>" min="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Team Visibility</label>
                            <select class="form-select" name="visibility">
                                <option value="public" <?= $team['visibility'] === 'public' ? 'selected' : '' ?>>Public (Visible in Directory)</option>
                                <option value="private" <?= $team['visibility'] === 'private' ? 'selected' : '' ?>>Private (Invite Only)</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-tf-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
