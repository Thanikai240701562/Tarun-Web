<?php
/**
 * TeamForge My Teams & Projects Hub
 */
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

requireAuth();
$currentUser = currentUser();
$db = getDB();

// Handle Join by Code
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'join_by_code') {
    if (!verify_csrf()) {
        setFlash('danger', 'Security validation failed.');
    } else {
        $teamCode = strtoupper(trim(sanitize($_POST['team_code'] ?? '')));
        $reqRole = sanitize($_POST['requested_role'] ?? 'General Developer');

        if (empty($teamCode)) {
            setFlash('danger', 'Please enter a valid team code.');
        } else {
            $tStmt = $db->prepare("SELECT * FROM teams WHERE team_code = ? LIMIT 1");
            $tStmt->execute([$teamCode]);
            $targetTeam = $tStmt->fetch();

            if (!$targetTeam) {
                setFlash('danger', 'No team found matching code: ' . $teamCode);
            } else {
                // Check if already in team
                $isMem = $db->prepare("SELECT id FROM team_members WHERE team_id = ? AND user_id = ? AND status = 'active'");
                $isMem->execute([$targetTeam['id'], $currentUser['id']]);
                if ($isMem->fetch()) {
                    setFlash('info', 'You are already an active member of this team.');
                } else {
                    // Check if request pending
                    $insReq = $db->prepare("
                        INSERT INTO join_requests (team_id, user_id, requested_role, status, message)
                        VALUES (?, ?, ?, 'pending', 'Requesting to join via Team Code')
                        ON DUPLICATE KEY UPDATE status = 'pending'
                    ");
                    $insReq->execute([$targetTeam['id'], $currentUser['id'], $reqRole]);

                    // Notify leader
                    notifyUser(
                        $targetTeam['leader_id'],
                        'New Join Request',
                        $currentUser['full_name'] . ' submitted a request to join "' . $targetTeam['name'] . '" via team code.',
                        'teams/view.php?id=' . $targetTeam['id'],
                        'join_request'
                    );

                    setFlash('success', 'Join request submitted to team leader for ' . $targetTeam['name'] . '!');
                }
            }
        }
    }
    header('Location: ' . BASE_URL . '/teams/my_teams.php');
    exit;
}

// 1. Teams led by user
$ledStmt = $db->prepare("
    SELECT t.*, e.title as event_title,
           (SELECT COUNT(*) FROM team_members WHERE team_id = t.id AND status = 'active') as member_count,
           (SELECT COUNT(*) FROM join_requests WHERE team_id = t.id AND status = 'pending') as pending_requests
    FROM teams t
    LEFT JOIN events e ON t.event_id = e.id
    WHERE t.leader_id = ? AND t.status != 'archived'
    ORDER BY t.created_at DESC
");
$ledStmt->execute([$currentUser['id']]);
$ledTeams = $ledStmt->fetchAll();

// 2. Teams user is a member of (not leader)
$memberStmt = $db->prepare("
    SELECT t.*, tm.assigned_role, u.full_name as leader_name, e.title as event_title,
           (SELECT COUNT(*) FROM team_members WHERE team_id = t.id AND status = 'active') as member_count
    FROM teams t
    JOIN team_members tm ON t.id = tm.team_id
    JOIN users u ON t.leader_id = u.id
    LEFT JOIN events e ON t.event_id = e.id
    WHERE tm.user_id = ? AND tm.status = 'active' AND t.leader_id != ? AND t.status != 'archived'
    ORDER BY tm.joined_at DESC
");
$memberStmt->execute([$currentUser['id'], $currentUser['id']]);
$memberTeams = $memberStmt->fetchAll();

$pageTitle = "My Teams & Projects — " . APP_NAME;
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-0">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <h2 class="fw-extrabold text-navy mb-1" style="font-weight: 800;">My Teams & Projects</h2>
            <p class="text-muted small mb-0">Manage project teams you lead, collaborate with teammates, or join teams via team codes.</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-white border shadow-sm text-dark d-inline-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#joinByCodeModal">
                <i class="bi bi-key-fill text-warning"></i>
                <span>Join via Team Code</span>
            </button>
            <a href="<?= BASE_URL ?>/teams/create.php" class="btn btn-tf-primary d-inline-flex align-items-center gap-2">
                <i class="bi bi-plus-lg"></i>
                <span>Create New Team</span>
            </a>
        </div>
    </div>

    <!-- Teams Led by User -->
    <div class="card-tf mb-4">
        <div class="card-tf-header">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-award-fill text-primary fs-5"></i>
                <h5 class="card-tf-title">Teams You Lead (<?= count($ledTeams) ?>)</h5>
            </div>
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle">Team Leader Mode</span>
        </div>
        <div class="card-tf-body">
            <?php if (empty($ledTeams)): ?>
                <div class="empty-state py-4">
                    <div class="empty-state-icon"><i class="bi bi-flag"></i></div>
                    <div class="empty-state-title">You Haven't Created Any Teams Yet</div>
                    <div class="empty-state-text">Create a project team, define required skills, and recruit your peers or let the AI algorithm assemble an optimal squad.</div>
                    <a href="<?= BASE_URL ?>/teams/create.php" class="btn btn-tf-primary btn-sm">Create Your First Team</a>
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($ledTeams as $team): ?>
                        <div class="col-md-6 col-xl-4">
                            <div class="card-tf h-100 p-3 border d-flex flex-column justify-content-between card-tf-interactive bg-white">
                                <div>
                                    <div class="d-flex align-items-start justify-content-between mb-2">
                                        <h5 class="fw-bold text-dark mb-0"><?= e($team['name']) ?></h5>
                                        <span class="badge bg-light text-dark border font-mono small"><?= e($team['team_code']) ?></span>
                                    </div>
                                    <div class="text-primary small fw-semibold mb-2"><?= e($team['project_title']) ?></div>
                                    <p class="text-muted small mb-3" style="font-size:12px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                        <?= e($team['project_description']) ?>
                                    </p>

                                    <div class="d-flex flex-column gap-1 text-muted small mb-3" style="font-size:11px;">
                                        <div><i class="bi bi-people me-1"></i> <strong><?= $team['member_count'] ?> / <?= $team['required_team_size'] ?></strong> Members Enrolled</div>
                                        <?php if ($team['event_title']): ?>
                                            <div><i class="bi bi-calendar-event me-1"></i> Event: <strong><?= e($team['event_title']) ?></strong></div>
                                        <?php endif; ?>
                                        <?php if ($team['pending_requests'] > 0): ?>
                                            <div class="text-warning fw-bold"><i class="bi bi-bell-fill me-1"></i> <?= $team['pending_requests'] ?> Pending Join Request(s)</div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="pt-2 border-top d-flex gap-2">
                                    <a href="<?= BASE_URL ?>/teams/view.php?id=<?= $team['id'] ?>" class="btn btn-sm btn-tf-primary flex-grow-1" style="font-size:12px;">
                                        Manage Team &rarr;
                                    </a>
                                    <a href="<?= BASE_URL ?>/chat/index.php?team_id=<?= $team['id'] ?>" class="btn btn-sm btn-light border text-primary" title="Group Chat">
                                        <i class="bi bi-chat-dots-fill"></i>
                                    </a>
                                    <a href="<?= BASE_URL ?>/ai/generator.php?team_id=<?= $team['id'] ?>" class="btn btn-sm btn-light border text-primary" title="AI Matcher">
                                        <i class="bi bi-cpu-fill"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Teams Where User is Member -->
    <div class="card-tf mb-4">
        <div class="card-tf-header">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-person-check-fill text-success fs-5"></i>
                <h5 class="card-tf-title">Teams You Have Joined (<?= count($memberTeams) ?>)</h5>
            </div>
            <span class="badge bg-secondary-subtle text-secondary">Active Contributor</span>
        </div>
        <div class="card-tf-body">
            <?php if (empty($memberTeams)): ?>
                <div class="text-center py-4 text-muted small">
                    You are not currently a member of other student teams. Join via team code or explore the student directory.
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($memberTeams as $mTeam): ?>
                        <div class="col-md-6 col-xl-4">
                            <div class="card-tf h-100 p-3 border d-flex flex-column justify-content-between card-tf-interactive bg-white">
                                <div>
                                    <div class="d-flex align-items-start justify-content-between mb-2">
                                        <h5 class="fw-bold text-dark mb-0"><?= e($mTeam['name']) ?></h5>
                                        <span class="badge bg-light text-dark border font-mono small"><?= e($mTeam['team_code']) ?></span>
                                    </div>
                                    <div class="text-primary small fw-semibold mb-2"><?= e($mTeam['project_title']) ?></div>
                                    <div class="badge bg-primary-subtle text-primary border border-primary-subtle small mb-2">
                                        Your Role: <?= e($mTeam['assigned_role']) ?>
                                    </div>
                                    <div class="text-muted small mb-3" style="font-size:11px;">
                                        Team Leader: <strong><?= e($mTeam['leader_name']) ?></strong> &bull; <?= $mTeam['member_count'] ?>/<?= $mTeam['required_team_size'] ?> Members
                                    </div>
                                </div>

                                <div class="pt-2 border-top d-flex gap-2">
                                    <a href="<?= BASE_URL ?>/teams/view.php?id=<?= $mTeam['id'] ?>" class="btn btn-sm btn-tf-primary flex-grow-1" style="font-size:12px;">
                                        Team Dashboard &rarr;
                                    </a>
                                    <a href="<?= BASE_URL ?>/chat/index.php?team_id=<?= $mTeam['id'] ?>" class="btn btn-sm btn-light border text-primary" title="Group Chat">
                                        <i class="bi bi-chat-dots-fill"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal: Join by Code -->
<div class="modal fade" id="joinByCodeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow-lg">
            <form method="POST" action="<?= BASE_URL ?>/teams/my_teams.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="join_by_code">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold">Join Team via Team Code</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Enter Team Code *</label>
                        <input type="text" class="form-control font-mono text-uppercase" name="team_code" required placeholder="e.g. BYTE-7842, FORGE-A91B" style="letter-spacing:0.1em;">
                        <div class="form-text small">Ask the team leader for their unique 8-character team code.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Your Requested Role *</label>
                        <select class="form-select" name="requested_role">
                            <?php foreach (AVAILABLE_ROLES as $ar): ?>
                                <option value="<?= e($ar) ?>"><?= e($ar) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-tf-primary">Submit Join Request</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
