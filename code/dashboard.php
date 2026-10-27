<?php
/**
 * TeamForge Main Dashboard (Role-Aware)
 */
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';

requireAuth();
$currentUser = currentUser();
$db = getDB();

// 1. Fetch Metrics
// Teams count
$myTeams = getUserActiveTeams($currentUser['id']);
$teamCount = count($myTeams);

// Events registered
$evtStmt = $db->prepare("SELECT COUNT(*) FROM event_registrations WHERE user_id = ? AND status != 'cancelled'");
$evtStmt->execute([$currentUser['id']]);
$registeredEventsCount = (int)$evtStmt->fetchColumn();

// Pending invitations received
$invStmt = $db->prepare("SELECT COUNT(*) FROM team_invitations WHERE recipient_id = ? AND status = 'pending'");
$invStmt->execute([$currentUser['id']]);
$pendingInvitesCount = (int)$invStmt->fetchColumn();

// Assigned tasks count
$taskStmt = $db->prepare("
    SELECT COUNT(*) 
    FROM task_assignments ta
    JOIN project_tasks pt ON ta.task_id = pt.id
    WHERE ta.user_id = ? AND pt.status != 'completed'
");
$taskStmt->execute([$currentUser['id']]);
$pendingTasksCount = (int)$taskStmt->fetchColumn();

// 2. Fetch Active College Events (Spotlight)
$activeEventsStmt = $db->query("
    SELECT e.*, 
           (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.id AND status != 'cancelled') as participant_count
    FROM events e 
    WHERE e.status = 'active' 
    ORDER BY e.event_date ASC 
    LIMIT 3
");
$activeEvents = $activeEventsStmt->fetchAll();

// 3. Fetch Primary Active Team (if any)
$primaryTeam = !empty($myTeams) ? $myTeams[0] : null;
$teamMembers = [];
$missingSkills = [];
if ($primaryTeam) {
    $membersStmt = $db->prepare("
        SELECT tm.*, u.full_name, u.avatar_url, u.email, u.department
        FROM team_members tm
        JOIN users u ON tm.user_id = u.id
        WHERE tm.team_id = ? AND tm.status = 'active'
    ");
    $membersStmt->execute([$primaryTeam['id']]);
    $teamMembers = $membersStmt->fetchAll();

    // Check missing required skills for this team
    $skillsStmt = $db->prepare("
        SELECT s.name 
        FROM team_skills ts
        JOIN skills s ON ts.skill_id = s.id
        WHERE ts.team_id = ? AND ts.is_required = 1
        AND ts.skill_id NOT IN (
            SELECT ss.skill_id 
            FROM team_members tm 
            JOIN student_skills ss ON tm.user_id = ss.user_id 
            WHERE tm.team_id = ? AND tm.status = 'active'
        )
    ");
    $skillsStmt->execute([$primaryTeam['id'], $primaryTeam['id']]);
    $missingSkills = $skillsStmt->fetchAll(PDO::FETCH_COLUMN);
}

// 4. Pending Invitations list
$invListStmt = $db->prepare("
    SELECT ti.*, t.name as team_name, t.project_title, u.full_name as sender_name
    FROM team_invitations ti
    JOIN teams t ON ti.team_id = t.id
    JOIN users u ON ti.sender_id = u.id
    WHERE ti.recipient_id = ? AND ti.status = 'pending'
    ORDER BY ti.created_at DESC
");
$invListStmt->execute([$currentUser['id']]);
$pendingInvitations = $invListStmt->fetchAll();

$pageTitle = "Dashboard — " . APP_NAME;
include __DIR__ . '/includes/header.php';
?>

<div class="container-fluid px-0">
    <!-- Welcome Header Banner -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <h2 class="fw-extrabold text-navy mb-1" style="font-weight: 800;">
                Hello, <?= e(explode(' ', $currentUser['full_name'])[0]) ?> 👋
            </h2>
            <p class="text-muted small mb-0">
                Welcome to your TeamForge collaboration command center.
                <?php if ($currentUser['role'] === ROLE_TEAM_LEADER): ?>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle ms-1">Team Leader Mode</span>
                <?php elseif ($currentUser['role'] === ROLE_FACULTY_ADMIN): ?>
                    <span class="badge bg-purple-subtle text-purple border border-purple-subtle ms-1">Faculty Mentor Mode</span>
                <?php endif; ?>
            </p>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <a href="<?= BASE_URL ?>/ai/generator.php" class="btn btn-tf-primary d-inline-flex align-items-center gap-2">
                <i class="bi bi-cpu-fill"></i>
                <span>Generate AI Team</span>
            </a>
            <a href="<?= BASE_URL ?>/teams/create.php" class="btn btn-white border shadow-sm text-dark d-inline-flex align-items-center gap-2">
                <i class="bi bi-plus-lg text-primary"></i>
                <span>Create Team</span>
            </a>
        </div>
    </div>

    <!-- 4 Stats Cards Grid -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card shadow-sm">
                <div class="stat-icon bg-primary-subtle text-primary">
                    <i class="bi bi-people-fill"></i>
                </div>
                <div>
                    <div class="stat-value"><?= $teamCount ?></div>
                    <div class="stat-label">Active Teams</div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="stat-card shadow-sm">
                <div class="stat-icon bg-info-subtle text-info">
                    <i class="bi bi-calendar-event-fill"></i>
                </div>
                <div>
                    <div class="stat-value"><?= $registeredEventsCount ?></div>
                    <div class="stat-label">Enrolled Events</div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="stat-card shadow-sm">
                <div class="stat-icon bg-warning-subtle text-warning">
                    <i class="bi bi-envelope-open-fill"></i>
                </div>
                <div>
                    <div class="stat-value"><?= $pendingInvitesCount ?></div>
                    <div class="stat-label">Pending Invitations</div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="stat-card shadow-sm">
                <div class="stat-icon bg-success-subtle text-success">
                    <i class="bi bi-check2-circle"></i>
                </div>
                <div>
                    <div class="stat-value"><?= $pendingTasksCount ?></div>
                    <div class="stat-label">Assigned Tasks</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Dashboard Row -->
    <div class="row g-4 mb-4">
        <!-- Left: Primary Active Team Card -->
        <div class="col-lg-7">
            <div class="card-tf h-100">
                <div class="card-tf-header">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-folder-symlink-fill text-primary fs-5"></i>
                        <h5 class="card-tf-title">Current Active Project</h5>
                    </div>
                    <?php if ($primaryTeam): ?>
                        <span class="badge bg-light text-dark border font-mono">Code: <?= e($primaryTeam['team_code']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="card-tf-body">
                    <?php if (!$primaryTeam): ?>
                        <div class="empty-state py-4">
                            <div class="empty-state-icon"><i class="bi bi-people"></i></div>
                            <div class="empty-state-title">You're Not in a Team Yet</div>
                            <div class="empty-state-text">Explore open college events or use the Classical AI Team Generator to find teammates matching your skill set.</div>
                            <div class="d-flex justify-content-center gap-2">
                                <a href="<?= BASE_URL ?>/ai/generator.php" class="btn btn-tf-primary btn-sm">
                                    <i class="bi bi-cpu-fill me-1"></i> Match with AI
                                </a>
                                <a href="<?= BASE_URL ?>/students/index.php" class="btn btn-outline-secondary btn-sm">
                                    <i class="bi bi-search me-1"></i> Browse Students
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="d-flex align-items-start justify-content-between mb-3">
                            <div>
                                <h4 class="fw-bold text-dark mb-1"><?= e($primaryTeam['name']) ?></h4>
                                <div class="text-primary fw-semibold small mb-2"><?= e($primaryTeam['project_title']) ?></div>
                                <p class="text-muted small mb-0" style="line-height:1.5;"><?= e($primaryTeam['project_description']) ?></p>
                            </div>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 fw-bold">
                                <?= count($teamMembers) ?> / <?= (int)$primaryTeam['required_team_size'] ?> Members
                            </span>
                        </div>

                        <!-- Missing skills alert if any -->
                        <?php if (!empty($missingSkills)): ?>
                            <div class="alert alert-warning d-flex align-items-center gap-2 p-2 small mb-3 rounded-3" role="alert">
                                <i class="bi bi-exclamation-triangle-fill fs-6 text-warning flex-shrink-0"></i>
                                <div>
                                    <strong>Missing Skill Coverage:</strong> Team needs <?= implode(', ', array_map('e', $missingSkills)) ?>. Use AI Matcher to find complementary candidates.
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Member Rosters -->
                        <div class="mb-3">
                            <div class="small fw-bold text-muted text-uppercase mb-2" style="font-size:0.72rem;">Team Members</div>
                            <div class="row g-2">
                                <?php foreach ($teamMembers as $tm): ?>
                                    <div class="col-sm-6">
                                        <div class="d-flex align-items-center gap-2 p-2 border rounded-3 bg-light">
                                            <?= getAvatar(['full_name' => $tm['full_name'], 'avatar_url' => $tm['avatar_url']], 34) ?>
                                            <div class="overflow-hidden">
                                                <div class="fw-bold small text-truncate text-dark"><?= e($tm['full_name']) ?></div>
                                                <div class="text-muted text-truncate" style="font-size: 11px;"><?= e($tm['assigned_role']) ?></div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="d-flex flex-wrap gap-2 pt-2 border-top">
                            <a href="<?= BASE_URL ?>/teams/view.php?id=<?= $primaryTeam['id'] ?>" class="btn btn-sm btn-tf-primary">
                                <i class="bi bi-speedometer2 me-1"></i> Team Dashboard
                            </a>
                            <a href="<?= BASE_URL ?>/chat/index.php?team_id=<?= $primaryTeam['id'] ?>" class="btn btn-sm btn-tf-outline">
                                <i class="bi bi-chat-dots me-1"></i> Ephemeral Group Chat
                            </a>
                            <a href="<?= BASE_URL ?>/tasks/index.php?team_id=<?= $primaryTeam['id'] ?>" class="btn btn-sm btn-light border">
                                <i class="bi bi-kanban me-1"></i> Task Board
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right: Pending Invitations & Quick Tools -->
        <div class="col-lg-5">
            <!-- Pending Invitations -->
            <div class="card-tf mb-4">
                <div class="card-tf-header">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-envelope-fill text-warning fs-5"></i>
                        <h5 class="card-tf-title">Pending Team Invitations</h5>
                    </div>
                    <span class="badge bg-warning-subtle text-warning fw-bold"><?= count($pendingInvitations) ?></span>
                </div>
                <div class="card-tf-body">
                    <?php if (empty($pendingInvitations)): ?>
                        <div class="text-center py-3 text-muted small">
                            <i class="bi bi-inbox fs-3 d-block mb-1 text-secondary opacity-50"></i>
                            No pending invitations at the moment.
                        </div>
                    <?php else: ?>
                        <div class="d-flex flex-column gap-2">
                            <?php foreach ($pendingInvitations as $inv): ?>
                                <div class="p-3 border rounded-3 bg-light">
                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                        <div class="fw-bold small text-dark"><?= e($inv['team_name']) ?></div>
                                        <span class="badge bg-info-subtle text-info" style="font-size:10px;"><?= e($inv['invited_role']) ?></span>
                                    </div>
                                    <div class="text-muted small mb-2" style="font-size: 11px;">
                                        Invited by <?= e($inv['sender_name']) ?> &bull; <?= timeAgo($inv['created_at']) ?>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <form method="POST" action="<?= BASE_URL ?>/teams/respond_invite.php" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="invite_id" value="<?= $inv['id'] ?>">
                                            <input type="hidden" name="decision" value="accept">
                                            <button type="submit" class="btn btn-sm btn-success px-3 py-1" style="font-size:12px;">Accept</button>
                                        </form>
                                        <form method="POST" action="<?= BASE_URL ?>/teams/respond_invite.php" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="invite_id" value="<?= $inv['id'] ?>">
                                            <input type="hidden" name="decision" value="reject">
                                            <button type="submit" class="btn btn-sm btn-outline-danger px-3 py-1" style="font-size:12px;">Decline</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Classical AI Spotlight Card -->
            <div class="card-tf p-4" style="background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%); color: white;">
                <div class="d-flex align-items-center gap-2 mb-2 text-warning">
                    <i class="bi bi-lightbulb-fill"></i>
                    <span class="small fw-bold text-uppercase" style="letter-spacing:0.05em;">AI Algorithm Engine</span>
                </div>
                <h5 class="fw-bold mb-2">Need a Balanced Team Fast?</h5>
                <p class="text-light opacity-75 small mb-3">
                    Our classical AI engine executes Backtracking with MRV heuristic & Forward Checking to assemble an optimal team satisfying all hard constraints.
                </p>
                <div class="d-flex gap-2">
                    <a href="<?= BASE_URL ?>/ai/generator.php" class="btn btn-sm btn-tf-primary">
                        Launch AI Generator &rarr;
                    </a>
                    <a href="<?= BASE_URL ?>/ai/optimization_lab.php" class="btn btn-sm btn-outline-light">
                        Lab Trace Demo
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- College Events Spotlight Section -->
    <div class="card-tf mb-4">
        <div class="card-tf-header">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-calendar-check-fill text-primary fs-5"></i>
                <h5 class="card-tf-title">Upcoming College Hackathons & Events</h5>
            </div>
            <a href="<?= BASE_URL ?>/events/index.php" class="small fw-semibold text-primary text-decoration-none">View All Events &rarr;</a>
        </div>
        <div class="card-tf-body">
            <div class="row g-3">
                <?php foreach ($activeEvents as $evt): ?>
                    <div class="col-md-4">
                        <div class="p-3 border rounded-3 bg-white h-100 d-flex flex-column justify-content-between">
                            <div>
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle text-uppercase" style="font-size:10px;">
                                        <?= e(str_replace('_', ' ', $evt['event_type'])) ?>
                                    </span>
                                    <span class="text-muted small" style="font-size:11px;">
                                        <i class="bi bi-calendar-event me-1"></i> <?= formatDate($evt['event_date']) ?>
                                    </span>
                                </div>
                                <h6 class="fw-bold text-dark mb-1"><?= e($evt['title']) ?></h6>
                                <p class="text-muted small mb-3" style="font-size:12px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                    <?= e($evt['description']) ?>
                                </p>
                            </div>
                            <div class="d-flex align-items-center justify-content-between pt-2 border-top">
                                <span class="small text-muted" style="font-size:11px;">
                                    <i class="bi bi-people me-1"></i> <?= (int)$evt['participant_count'] ?> / <?= (int)$evt['max_participants'] ?>
                                </span>
                                <a href="<?= BASE_URL ?>/events/view.php?id=<?= $evt['id'] ?>" class="btn btn-sm btn-tf-outline py-1 px-2" style="font-size:11px;">
                                    View Details
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
