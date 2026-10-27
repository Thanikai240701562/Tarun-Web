<?php
/**
 * TeamForge Scheduled Team Chat Sessions (Section 16)
 * Lists upcoming and past scheduled timed sessions with attendance logs.
 */
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

requireAuth();
$currentUser = currentUser();
$db = getDB();

$myTeams = getUserActiveTeams($currentUser['id']);
$teamId = (int)($_GET['team_id'] ?? 0);
if ($teamId <= 0 && !empty($myTeams)) {
    $teamId = (int)$myTeams[0]['id'];
}

$team = null;
$sessions = [];

if ($teamId > 0) {
    if (!isUserMemberOf($currentUser['id'], $teamId) && $currentUser['role'] !== ROLE_FACULTY_ADMIN) {
        setFlash('danger', 'Access denied.');
        header('Location: ' . BASE_URL . '/teams/my_teams.php');
        exit;
    }

    $teamStmt = $db->prepare("SELECT * FROM teams WHERE id = ?");
    $teamStmt->execute([$teamId]);
    $team = $teamStmt->fetch();

    if ($team) {
        $sessStmt = $db->prepare("
            SELECT ts.*, u.full_name as creator_name,
                   (SELECT COUNT(*) FROM session_attendance WHERE session_id = ts.id) as attendee_count
            FROM team_sessions ts
            JOIN users u ON ts.created_by = u.id
            WHERE ts.team_id = ?
            ORDER BY ts.session_date DESC, ts.start_time DESC
        ");
        $sessStmt->execute([$teamId]);
        $sessions = $sessStmt->fetchAll();
    }
}

$isLeader = ($team && (int)$team['leader_id'] === (int)$currentUser['id']);

// Handle Schedule Session POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isLeader) {
    if (!verify_csrf()) {
        setFlash('danger', 'Security validation failed.');
    } else {
        $title = sanitize($_POST['title'] ?? '');
        $agenda = sanitize($_POST['agenda'] ?? '');
        $date = sanitize($_POST['session_date'] ?? '');
        $startTime = sanitize($_POST['start_time'] ?? '');
        $endTime = sanitize($_POST['end_time'] ?? '');

        if (empty($title) || empty($date) || empty($startTime) || empty($endTime)) {
            setFlash('danger', 'Please complete all required session fields.');
        } else {
            $insSess = $db->prepare("
                INSERT INTO team_sessions (team_id, title, agenda, session_date, start_time, end_time, created_by, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'scheduled')
            ");
            $insSess->execute([$teamId, $title, $agenda, $date, $startTime, $endTime, $currentUser['id']]);
            $newSessionId = (int)$db->lastInsertId();

            // Notify all team members
            $mStmt = $db->prepare("SELECT user_id FROM team_members WHERE team_id = ? AND status = 'active' AND user_id != ?");
            $mStmt->execute([$teamId, $currentUser['id']]);
            $membersToNotify = $mStmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($membersToNotify as $mUserId) {
                notifyUser(
                    $mUserId,
                    'New Team Session Scheduled',
                    'A discussion session "' . $title . '" was scheduled for ' . formatDate($date) . ' at ' . $startTime . '.',
                    'sessions/index.php?team_id=' . $teamId,
                    'session'
                );
            }

            logAudit($currentUser['id'], 'SESSION_SCHEDULED', 'session', $newSessionId, ['title' => $title]);
            setFlash('success', 'Discussion session scheduled successfully! Teammates have been notified.');
            header('Location: ' . BASE_URL . '/sessions/index.php?team_id=' . $teamId);
            exit;
        }
    }
}

$pageTitle = "Scheduled Sessions — " . APP_NAME;
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-0">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <h2 class="fw-extrabold text-navy mb-1" style="font-weight: 800;">Scheduled Team Sessions</h2>
            <p class="text-muted small mb-0">Synchronous timed meetings with live agenda and automatic attendance verification.</p>
        </div>
        <?php if ($team && $isLeader): ?>
            <button class="btn btn-tf-primary d-inline-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#scheduleSessionModal">
                <i class="bi bi-clock-fill"></i>
                <span>Schedule New Session</span>
            </button>
        <?php endif; ?>
    </div>

    <?php if (!$team): ?>
        <div class="card-tf p-5 text-center">
            <div class="empty-state">
                <div class="empty-state-icon"><i class="bi bi-calendar-event"></i></div>
                <div class="empty-state-title">No Active Team Found</div>
                <div class="empty-state-text">Join or create a project team first to schedule synchronized meeting sessions.</div>
            </div>
        </div>
    <?php else: ?>
        <div class="card-tf p-3 mb-4 bg-white shadow-sm d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-3">
                <span class="p-2 bg-primary-subtle text-primary rounded-3"><i class="bi bi-clock-history fs-5"></i></span>
                <div>
                    <h5 class="fw-bold text-dark mb-0"><?= e($team['name']) ?></h5>
                    <div class="text-muted small" style="font-size:11px;"><?= e($team['project_title']) ?></div>
                </div>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= BASE_URL ?>/chat/index.php?team_id=<?= $teamId ?>" class="btn btn-sm btn-tf-outline">
                    <i class="bi bi-chat-dots-fill me-1"></i> Normal Chat
                </a>
            </div>
        </div>

        <!-- Sessions List -->
        <?php if (empty($sessions)): ?>
            <div class="card-tf p-5 text-center">
                <div class="empty-state">
                    <div class="empty-state-icon"><i class="bi bi-calendar-x"></i></div>
                    <div class="empty-state-title">No Scheduled Sessions Yet</div>
                    <div class="empty-state-text">The Team Leader can schedule text discussion sessions with specific agendas and automatic attendance recording.</div>
                    <?php if ($isLeader): ?>
                        <button class="btn btn-tf-primary btn-sm mt-2" data-bs-toggle="modal" data-bs-target="#scheduleSessionModal">
                            Schedule First Session
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($sessions as $sess): 
                    $now = time();
                    $sessStart = strtotime($sess['session_date'] . ' ' . $sess['start_time']);
                    $sessEnd = strtotime($sess['session_date'] . ' ' . $sess['end_time']);
                    $isOpen = ($now >= $sessStart && $now <= $sessEnd && $sess['status'] !== 'completed');
                    $isPast = ($now > $sessEnd || $sess['status'] === 'completed');
                ?>
                    <div class="col-md-6 col-xl-4">
                        <div class="card-tf h-100 p-4 border d-flex flex-column justify-content-between bg-white card-tf-interactive">
                            <div>
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <span class="badge bg-light text-dark border font-mono" style="font-size:11px;">
                                        <i class="bi bi-calendar3 me-1"></i> <?= formatDate($sess['session_date']) ?>
                                    </span>
                                    <?php if ($isOpen): ?>
                                        <span class="badge bg-success animate-pulse"><i class="bi bi-broadcast me-1"></i> Live Now</span>
                                    <?php elseif ($isPast): ?>
                                        <span class="badge bg-secondary-subtle text-secondary">Past / Closed</span>
                                    <?php else: ?>
                                        <span class="badge bg-primary-subtle text-primary">Scheduled</span>
                                    <?php endif; ?>
                                </div>

                                <h5 class="fw-bold text-dark mb-1"><?= e($sess['title']) ?></h5>
                                <div class="text-muted small mb-3" style="font-size:12px;">
                                    <i class="bi bi-clock me-1 text-primary"></i> <?= date('h:i A', strtotime($sess['start_time'])) ?> &ndash; <?= date('h:i A', strtotime($sess['end_time'])) ?>
                                </div>

                                <h6 class="fw-bold text-dark small mb-1" style="font-size:11px;">Agenda:</h6>
                                <p class="text-muted small mb-3" style="font-size:12px; line-height: 1.45;">
                                    <?= nl2br(e($sess['agenda'])) ?>
                                </p>
                            </div>

                            <div class="pt-3 border-top d-flex align-items-center justify-content-between">
                                <span class="text-muted small" style="font-size:11px;">
                                    <i class="bi bi-people-fill me-1"></i> <?= (int)$sess['attendee_count'] ?> Attended
                                </span>
                                <?php if ($isOpen || $isLeader): ?>
                                    <a href="<?= BASE_URL ?>/sessions/room.php?id=<?= $sess['id'] ?>" class="btn btn-sm btn-tf-primary">
                                        Join Session Room &rarr;
                                    </a>
                                <?php else: ?>
                                    <button class="btn btn-sm btn-light border text-muted disabled" disabled>
                                        Opens at Start Time
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Modal: Schedule Session -->
<?php if ($team && $isLeader): ?>
<div class="modal fade" id="scheduleSessionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow-lg">
            <form method="POST" action="<?= BASE_URL ?>/sessions/index.php?team_id=<?= $teamId ?>">
                <?= csrf_field() ?>
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold">Schedule Team Session</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Session Title *</label>
                        <input type="text" class="form-control" name="title" required placeholder="e.g. Sprint 1 Architecture Review & API Contract">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Session Date *</label>
                        <input type="date" class="form-control" name="session_date" required min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Start Time *</label>
                            <input type="time" class="form-control" name="start_time" required value="<?= date('H:i') ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">End Time *</label>
                            <input type="time" class="form-control" name="end_time" required value="<?= date('H:i', time() + 3600) ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Meeting Purpose & Agenda *</label>
                        <textarea class="form-control" name="agenda" rows="3" required placeholder="List the specific topics, deliverables, or questions to resolve during this timed session..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-tf-primary">Schedule Session</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
