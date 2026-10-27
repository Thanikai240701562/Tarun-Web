<?php
/**
 * TeamForge Faculty & Administrator Center
 * Section 18
 */
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole(ROLE_FACULTY_ADMIN);
$currentUser = currentUser();
$db = getDB();

// 1. Core University Statistics
$stats = [
    'students' => (int)$db->query("SELECT COUNT(*) FROM users WHERE role != 'faculty_admin'")->fetchColumn(),
    'teams'    => (int)$db->query("SELECT COUNT(*) FROM teams")->fetchColumn(),
    'events'   => (int)$db->query("SELECT COUNT(*) FROM events")->fetchColumn(),
    'verified_projects' => (int)$db->query("SELECT COUNT(*) FROM student_projects WHERE is_verified = 1")->fetchColumn()
];

// Handle Admin Actions (Verify Student Account, Toggle Event Status, Promote TL)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        setFlash('danger', 'Security validation failed.');
    } else {
        $action = $_POST['action'] ?? '';

        // 1. Toggle Student Account Verification
        if ($action === 'verify_user') {
            $uId = (int)($_POST['user_id'] ?? 0);
            $db->prepare("UPDATE users SET is_verified = 1 WHERE id = ?")->execute([$uId]);
            notifyUser($uId, 'Account Verified', 'Your college student credentials have been verified by university administration.', 'profile.php', 'system');
            logAudit($currentUser['id'], 'ADMIN_VERIFY_USER', 'user', $uId);
            setFlash('success', 'Student account verified successfully.');
        }

        // 2. Change Event Status (Active, Suspended, Closed)
        if ($action === 'change_event_status') {
            $eId = (int)($_POST['event_id'] ?? 0);
            $status = in_array($_POST['status'] ?? '', ['active', 'upcoming', 'registration_closed', 'completed', 'suspended']) ? $_POST['status'] : 'active';
            $db->prepare("UPDATE events SET status = ? WHERE id = ?")->execute([$status, $eId]);
            logAudit($currentUser['id'], 'ADMIN_CHANGE_EVENT_STATUS', 'event', $eId, ['status' => $status]);
            setFlash('info', 'Event status updated to ' . $status . '.');
        }
    }
    header('Location: ' . BASE_URL . '/admin/index.php');
    exit;
}

// Fetch Students List
$studentsStmt = $db->query("
    SELECT u.*,
           (SELECT COUNT(*) FROM student_projects WHERE user_id = u.id) as project_count,
           (SELECT COUNT(*) FROM team_members tm WHERE tm.user_id = u.id AND tm.status = 'active') as active_teams_count
    FROM users u
    WHERE u.role != 'faculty_admin'
    ORDER BY u.created_at DESC
");
$students = $studentsStmt->fetchAll();

// Fetch Events List
$eventsStmt = $db->query("
    SELECT e.*, u.full_name as organizer_name,
           (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.id AND status != 'cancelled') as participants_count,
           (SELECT COUNT(*) FROM teams WHERE event_id = e.id) as teams_count
    FROM events e
    JOIN users u ON e.organizer_id = u.id
    ORDER BY e.event_date ASC
");
$events = $eventsStmt->fetchAll();

// Fetch All Teams
$teamsStmt = $db->query("
    SELECT t.*, u.full_name as leader_name, e.title as event_title,
           (SELECT COUNT(*) FROM team_members WHERE team_id = t.id AND status = 'active') as member_count
    FROM teams t
    JOIN users u ON t.leader_id = u.id
    LEFT JOIN events e ON t.event_id = e.id
    ORDER BY t.created_at DESC
");
$allTeams = $teamsStmt->fetchAll();

$pageTitle = "Faculty & Admin Center — " . APP_NAME;
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-0">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <div class="badge bg-purple-subtle text-purple border border-purple-subtle px-3 py-1 rounded-pill fw-bold mb-1">
                <i class="bi bi-shield-lock-fill me-1"></i> Authorized Faculty Console
            </div>
            <h2 class="fw-extrabold text-navy mb-1" style="font-weight: 800;">Faculty & Administration Center</h2>
            <p class="text-muted small mb-0">Monitor campus team formation, manage college hackathons, verify student credentials, and inspect audit logs.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= BASE_URL ?>/events/create.php" class="btn btn-tf-primary d-inline-flex align-items-center gap-2">
                <i class="bi bi-plus-circle-fill"></i>
                <span>Publish Event</span>
            </a>
            <a href="<?= BASE_URL ?>/admin/audit_logs.php" class="btn btn-white border shadow-sm text-dark d-inline-flex align-items-center gap-2">
                <i class="bi bi-journal-text text-secondary"></i>
                <span>View Audit Trail</span>
            </a>
        </div>
    </div>

    <!-- 4 University Stats Metric Cards -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card shadow-sm">
                <div class="stat-icon bg-primary-subtle text-primary">
                    <i class="bi bi-mortarboard-fill"></i>
                </div>
                <div>
                    <div class="stat-value"><?= $stats['students'] ?></div>
                    <div class="stat-label">Enrolled Students</div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="stat-card shadow-sm">
                <div class="stat-icon bg-info-subtle text-info">
                    <i class="bi bi-diagram-3-fill"></i>
                </div>
                <div>
                    <div class="stat-value"><?= $stats['teams'] ?></div>
                    <div class="stat-label">Active Teams Formed</div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="stat-card shadow-sm">
                <div class="stat-icon bg-warning-subtle text-warning">
                    <i class="bi bi-calendar-check-fill"></i>
                </div>
                <div>
                    <div class="stat-value"><?= $stats['events'] ?></div>
                    <div class="stat-label">College Events & Expos</div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="stat-card shadow-sm">
                <div class="stat-icon bg-success-subtle text-success">
                    <i class="bi bi-patch-check-fill"></i>
                </div>
                <div>
                    <div class="stat-value"><?= $stats['verified_projects'] ?></div>
                    <div class="stat-label">Verified Project Records</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Admin Tabs: Students, Events, Teams -->
    <ul class="nav nav-tabs mb-4" id="adminTabs" role="tablist">
        <li class="nav-item">
            <button class="nav-link active fw-bold" id="students-tab" data-bs-toggle="tab" data-bs-target="#students-pane" type="button">
                <i class="bi bi-people-fill me-1 text-primary"></i> Students Roster (<?= count($students) ?>)
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link fw-bold" id="events-tab" data-bs-toggle="tab" data-bs-target="#events-pane" type="button">
                <i class="bi bi-calendar-event-fill me-1 text-primary"></i> College Events (<?= count($events) ?>)
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link fw-bold" id="teams-tab" data-bs-toggle="tab" data-bs-target="#teams-pane" type="button">
                <i class="bi bi-folder-fill me-1 text-primary"></i> Project Teams (<?= count($allTeams) ?>)
            </button>
        </li>
    </ul>

    <div class="tab-content" id="adminTabsContent">
        <!-- 1. Students Pane -->
        <div class="tab-pane fade show active" id="students-pane" role="tabpanel">
            <div class="card-tf shadow-sm">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size:13px;">
                        <thead class="table-light">
                            <tr>
                                <th>Student</th>
                                <th>Department & Year</th>
                                <th>Roll Number</th>
                                <th>Status</th>
                                <th>Projects</th>
                                <th>Teams</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $stu): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <?= getAvatar($stu, 34) ?>
                                            <div>
                                                <div class="fw-bold text-dark"><?= e($stu['full_name']) ?></div>
                                                <div class="text-muted" style="font-size:11px;"><?= e($stu['email']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div><?= e($stu['department']) ?></div>
                                        <span class="text-muted small" style="font-size:11px;"><?= e($stu['academic_year']) ?></span>
                                    </td>
                                    <td class="font-mono text-muted"><?= e($stu['student_id_number'] ?? 'N/A') ?></td>
                                    <td>
                                        <?php if ($stu['is_verified']): ?>
                                            <span class="badge bg-success-subtle text-success"><i class="bi bi-patch-check-fill me-1"></i>Verified</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary-subtle text-secondary">Unverified</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= (int)$stu['project_count'] ?></td>
                                    <td><?= (int)$stu['active_teams_count'] ?></td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <a href="<?= BASE_URL ?>/profile.php?id=<?= $stu['id'] ?>" class="btn btn-sm btn-light border py-1 px-2" style="font-size:11px;">
                                                Inspect
                                            </a>
                                            <?php if (!$stu['is_verified']): ?>
                                                <form method="POST" action="<?= BASE_URL ?>/admin/index.php" class="d-inline">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="verify_user">
                                                    <input type="hidden" name="user_id" value="<?= $stu['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-success py-1 px-2" style="font-size:11px;" title="Verify Student">
                                                        <i class="bi bi-check2"></i> Verify
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- 2. Events Pane -->
        <div class="tab-pane fade" id="events-pane" role="tabpanel">
            <div class="card-tf shadow-sm">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size:13px;">
                        <thead class="table-light">
                            <tr>
                                <th>Event Title</th>
                                <th>Type</th>
                                <th>Date & Venue</th>
                                <th>Registration</th>
                                <th>Teams</th>
                                <th>Status</th>
                                <th>Control</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($events as $evt): ?>
                                <tr>
                                    <td class="fw-bold text-dark"><?= e($evt['title']) ?></td>
                                    <td><span class="badge bg-light text-dark border text-uppercase" style="font-size:10px;"><?= e(str_replace('_', ' ', $evt['event_type'])) ?></span></td>
                                    <td>
                                        <div><?= formatDate($evt['event_date']) ?></div>
                                        <div class="text-muted small" style="font-size:11px;"><?= e($evt['venue']) ?></div>
                                    </td>
                                    <td><?= (int)$evt['participants_count'] ?> / <?= (int)$evt['max_participants'] ?></td>
                                    <td><?= (int)$evt['teams_count'] ?></td>
                                    <td>
                                        <span class="badge bg-<?= $evt['status'] === 'active' ? 'success' : ($evt['status'] === 'suspended' ? 'danger' : 'secondary') ?>">
                                            <?= ucfirst($evt['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <form method="POST" action="<?= BASE_URL ?>/admin/index.php" class="d-flex align-items-center gap-1">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="change_event_status">
                                            <input type="hidden" name="event_id" value="<?= $evt['id'] ?>">
                                            <select name="status" class="form-select form-select-sm" style="font-size:11px; width:110px;" onchange="this.form.submit()">
                                                <option value="active" <?= $evt['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                                <option value="suspended" <?= $evt['status'] === 'suspended' ? 'selected' : '' ?>>Suspend</option>
                                                <option value="registration_closed" <?= $evt['status'] === 'registration_closed' ? 'selected' : '' ?>>Close</option>
                                            </select>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- 3. Teams Pane -->
        <div class="tab-pane fade" id="teams-pane" role="tabpanel">
            <div class="card-tf shadow-sm">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size:13px;">
                        <thead class="table-light">
                            <tr>
                                <th>Team Name & Code</th>
                                <th>Project Title</th>
                                <th>Designated Leader</th>
                                <th>Roster Size</th>
                                <th>Associated Event</th>
                                <th>Formation Method</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allTeams as $tm): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-dark"><?= e($tm['name']) ?></div>
                                        <span class="badge bg-light text-dark border font-mono small"><?= e($tm['team_code']) ?></span>
                                    </td>
                                    <td><?= e($tm['project_title']) ?></td>
                                    <td><?= e($tm['leader_name']) ?></td>
                                    <td><?= $tm['member_count'] ?> / <?= $tm['required_team_size'] ?></td>
                                    <td><?= e($tm['event_title'] ?? 'Independent Project') ?></td>
                                    <td><span class="badge bg-info-subtle text-info"><?= ucfirst($tm['formation_method']) ?></span></td>
                                    <td>
                                        <a href="<?= BASE_URL ?>/teams/view.php?id=<?= $tm['id'] ?>" class="btn btn-sm btn-light border py-1 px-2" style="font-size:11px;">
                                            Inspect Team
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
