<?php
/**
 * TeamForge Event Details & Enrollment Handling
 */
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

requireAuth();
$currentUser = currentUser();
$db = getDB();

$eventId = (int)($_GET['id'] ?? 0);
if ($eventId <= 0) {
    setFlash('danger', 'Invalid event requested.');
    header('Location: ' . BASE_URL . '/events/index.php');
    exit;
}

// Fetch event details
$stmt = $db->prepare("
    SELECT e.*, u.full_name as organizer_name, u.email as organizer_email,
           (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.id AND status != 'cancelled') as current_participants
    FROM events e
    JOIN users u ON e.organizer_id = u.id
    WHERE e.id = ?
    LIMIT 1
");
$stmt->execute([$eventId]);
$event = $stmt->fetch();

if (!$event) {
    setFlash('danger', 'College event not found.');
    header('Location: ' . BASE_URL . '/events/index.php');
    exit;
}

// Check current user registration status
$regStmt = $db->prepare("SELECT * FROM event_registrations WHERE event_id = ? AND user_id = ? LIMIT 1");
$regStmt->execute([$eventId, $currentUser['id']]);
$userRegistration = $regStmt->fetch();

$isEnrolled = ($userRegistration && $userRegistration['status'] !== 'cancelled');
$isOrganizer = ($currentUser['id'] === (int)$event['organizer_id'] || $currentUser['role'] === ROLE_FACULTY_ADMIN);

$today = date('Y-m-d');
$isBeforeDeadline = ($today <= $event['reg_end_date']);
$isCapacityFull = ($event['current_participants'] >= $event['max_participants']);

// Handle Enrollment & Cancellation POST Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        setFlash('danger', 'Security validation failed.');
        header('Location: ' . BASE_URL . '/events/view.php?id=' . $eventId);
        exit;
    }

    $action = $_POST['action'] ?? '';

    // 1. Enroll in event
    if ($action === 'enroll') {
        if ($isEnrolled) {
            setFlash('info', 'You are already registered for this event.');
        } elseif (!$isBeforeDeadline) {
            setFlash('danger', 'Registration for this event has closed.');
        } elseif ($isCapacityFull) {
            setFlash('warning', 'This event has reached its maximum participant limit.');
        } else {
            try {
                $db->beginTransaction();
                // Check capacity again under lock
                $capCheck = $db->prepare("SELECT COUNT(*) FROM event_registrations WHERE event_id = ? AND status != 'cancelled' FOR UPDATE");
                $capCheck->execute([$eventId]);
                $curCount = (int)$capCheck->fetchColumn();

                if ($curCount >= $event['max_participants']) {
                    $db->rollBack();
                    setFlash('warning', 'Registration closed just now: capacity reached.');
                } else {
                    $enrollSql = "
                        INSERT INTO event_registrations (event_id, user_id, status)
                        VALUES (?, ?, 'approved')
                        ON DUPLICATE KEY UPDATE status = 'approved', registered_at = CURRENT_TIMESTAMP
                    ";
                    $enrollStmt = $db->prepare($enrollSql);
                    $enrollStmt->execute([$eventId, $currentUser['id']]);

                    $db->commit();
                    notifyUser($currentUser['id'], 'Enrolled in Event', 'You successfully registered for ' . $event['title'] . '.', 'events/view.php?id=' . $eventId, 'event');
                    logAudit($currentUser['id'], 'EVENT_ENROLLED', 'event', $eventId);
                    setFlash('success', 'You have successfully enrolled in ' . $event['title'] . '!');
                }
            } catch (Exception $e) {
                if ($db->inTransaction()) $db->rollBack();
                setFlash('danger', 'Registration failed: ' . $e->getMessage());
            }
        }
        header('Location: ' . BASE_URL . '/events/view.php?id=' . $eventId);
        exit;
    }

    // 2. Cancel Enrollment
    if ($action === 'cancel_enrollment') {
        if (!$isBeforeDeadline) {
            setFlash('danger', 'Cannot withdraw registration after the registration deadline.');
        } else {
            $cancelStmt = $db->prepare("UPDATE event_registrations SET status = 'cancelled' WHERE event_id = ? AND user_id = ?");
            $cancelStmt->execute([$eventId, $currentUser['id']]);
            notifyUser($currentUser['id'], 'Event Enrollment Cancelled', 'You cancelled registration for ' . $event['title'] . '.', 'events/view.php?id=' . $eventId, 'event');
            setFlash('info', 'Your enrollment has been cancelled.');
        }
        header('Location: ' . BASE_URL . '/events/view.php?id=' . $eventId);
        exit;
    }
}

// Fetch Enrolled Participants
$participantsStmt = $db->prepare("
    SELECT u.id, u.full_name, u.email, u.department, u.academic_year, u.avatar_url, er.registered_at
    FROM event_registrations er
    JOIN users u ON er.user_id = u.id
    WHERE er.event_id = ? AND er.status != 'cancelled'
    ORDER BY er.registered_at DESC
");
$participantsStmt->execute([$eventId]);
$participants = $participantsStmt->fetchAll();

// Fetch Teams formed for this event
$teamsStmt = $db->prepare("
    SELECT t.*, u.full_name as leader_name,
           (SELECT COUNT(*) FROM team_members WHERE team_id = t.id AND status = 'active') as member_count
    FROM teams t
    JOIN users u ON t.leader_id = u.id
    WHERE t.event_id = ?
");
$teamsStmt->execute([$eventId]);
$eventTeams = $teamsStmt->fetchAll();

$pageTitle = e($event['title']) . " — " . APP_NAME;
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-0">
    <div class="mb-3">
        <a href="<?= BASE_URL ?>/events/index.php" class="text-decoration-none text-muted small">
            &larr; Back to Events Directory
        </a>
    </div>

    <!-- Event Hero Banner -->
    <div class="card-tf shadow-sm mb-4">
        <div class="p-4 p-md-5" style="background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%); color: white; border-radius: 12px 12px 0 0;">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                <span class="badge bg-primary text-white text-uppercase px-3 py-2" style="font-size:11px;">
                    <?= e(str_replace('_', ' ', $event['event_type'])) ?>
                </span>
                <span class="badge bg-light text-dark px-3 py-2 fw-semibold">
                    Formation Policy: <?= ucfirst(str_replace('_', ' ', $event['team_formation_policy'])) ?>
                </span>
            </div>

            <h2 class="display-6 fw-bold text-white mb-2"><?= e($event['title']) ?></h2>
            <div class="d-flex flex-wrap gap-4 text-light opacity-75 small">
                <div><i class="bi bi-geo-alt-fill text-danger me-1"></i> Venue: <strong><?= e($event['venue']) ?></strong></div>
                <div><i class="bi bi-calendar3 text-primary me-1"></i> Event Date: <strong><?= formatDate($event['event_date']) ?></strong></div>
                <div><i class="bi bi-person-circle me-1"></i> Organized By: <strong><?= e($event['organizer_name']) ?></strong></div>
            </div>
        </div>

        <!-- Registration Status Bar -->
        <div class="p-3 bg-white border-top d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-4 text-muted small">
                <div>
                    <strong>Team Size:</strong> <?= $event['min_team_size'] ?> &ndash; <?= $event['max_team_size'] ?> students
                </div>
                <div>
                    <strong>Reg Period:</strong> <?= formatDate($event['reg_start_date']) ?> &ndash; <?= formatDate($event['reg_end_date']) ?>
                </div>
                <div>
                    <strong>Capacity:</strong> <?= (int)$event['current_participants'] ?> / <?= (int)$event['max_participants'] ?>
                </div>
            </div>

            <div class="d-flex align-items-center gap-2">
                <?php if ($isEnrolled): ?>
                    <span class="badge bg-success-subtle text-success border border-success-subtle p-2">
                        <i class="bi bi-check-circle-fill me-1"></i> You are Enrolled
                    </span>
                    <a href="<?= BASE_URL ?>/teams/create.php?event_id=<?= $eventId ?>" class="btn btn-sm btn-tf-primary">
                        <i class="bi bi-plus-circle me-1"></i> Form Team for Event
                    </a>
                    <?php if ($isBeforeDeadline): ?>
                        <form method="POST" action="<?= BASE_URL ?>/events/view.php?id=<?= $eventId ?>" onsubmit="return confirm('Cancel your event enrollment?');" class="d-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="cancel_enrollment">
                            <button type="submit" class="btn btn-sm btn-outline-danger">Withdraw</button>
                        </form>
                    <?php endif; ?>
                <?php else: ?>
                    <?php if ($isBeforeDeadline && !$isCapacityFull): ?>
                        <form method="POST" action="<?= BASE_URL ?>/events/view.php?id=<?= $eventId ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="enroll">
                            <button type="submit" class="btn btn-tf-primary">
                                <i class="bi bi-person-check-fill me-1"></i> Enroll in Event
                            </button>
                        </form>
                    <?php elseif ($isCapacityFull): ?>
                        <button class="btn btn-secondary disabled" disabled>Event Capacity Reached</button>
                    <?php else: ?>
                        <button class="btn btn-secondary disabled" disabled>Registration Closed</button>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Event Details & Rosters Grid -->
    <div class="row g-4">
        <!-- Left: Overview & Eligibility -->
        <div class="col-lg-7">
            <div class="card-tf mb-4">
                <div class="card-tf-header">
                    <h5 class="card-tf-title"><i class="bi bi-info-circle-fill me-2 text-primary"></i>Event Overview & Rules</h5>
                </div>
                <div class="card-tf-body">
                    <p class="text-dark mb-4" style="line-height: 1.7;"><?= nl2br(e($event['description'])) ?></p>

                    <h6 class="fw-bold text-dark mb-2"><i class="bi bi-tools text-primary me-2"></i>Recommended Skills & Themes</h6>
                    <p class="text-muted small mb-4">
                        <?= !empty($event['required_skills']) ? e($event['required_skills']) : 'Open to all domain technologies and stacks.' ?>
                    </p>

                    <h6 class="fw-bold text-dark mb-2"><i class="bi bi-shield-check text-success me-2"></i>Eligibility Criteria</h6>
                    <p class="text-muted small mb-0">
                        <?= !empty($event['eligibility_criteria']) ? nl2br(e($event['eligibility_criteria'])) : 'Open to all officially enrolled university students.' ?>
                    </p>
                </div>
            </div>

            <!-- Teams in this event -->
            <div class="card-tf mb-4">
                <div class="card-tf-header">
                    <h5 class="card-tf-title"><i class="bi bi-diagram-3-fill me-2 text-primary"></i>Teams Formed (<?= count($eventTeams) ?>)</h5>
                    <?php if ($isEnrolled): ?>
                        <a href="<?= BASE_URL ?>/teams/create.php?event_id=<?= $eventId ?>" class="btn btn-sm btn-tf-primary">
                            Create Team
                        </a>
                    <?php endif; ?>
                </div>
                <div class="card-tf-body">
                    <?php if (empty($eventTeams)): ?>
                        <div class="empty-state py-3">
                            <div class="empty-state-icon"><i class="bi bi-people"></i></div>
                            <div class="empty-state-title">No Teams Formed Yet</div>
                            <div class="empty-state-text">Be the first to create an eligible team or use the Classical AI Team Generator to match with enrolled participants.</div>
                        </div>
                    <?php else: ?>
                        <div class="row g-3">
                            <?php foreach ($eventTeams as $et): ?>
                                <div class="col-md-6">
                                    <div class="p-3 border rounded-3 bg-light h-100 d-flex flex-column justify-content-between">
                                        <div>
                                            <div class="d-flex justify-content-between align-items-start mb-1">
                                                <h6 class="fw-bold text-dark mb-0"><?= e($et['name']) ?></h6>
                                                <span class="badge bg-primary-subtle text-primary font-mono" style="font-size:10px;"><?= e($et['team_code']) ?></span>
                                            </div>
                                            <div class="text-primary small mb-2"><?= e($et['project_title']) ?></div>
                                            <div class="text-muted small" style="font-size:11px;">
                                                Led by <?= e($et['leader_name']) ?> &bull; <?= $et['member_count'] ?>/<?= $et['required_team_size'] ?> members
                                            </div>
                                        </div>
                                        <div class="pt-2 border-top mt-2">
                                            <a href="<?= BASE_URL ?>/teams/view.php?id=<?= $et['id'] ?>" class="btn btn-sm btn-white border w-100 text-primary fw-semibold" style="font-size:12px;">
                                                View Team &rarr;
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

        <!-- Right: Enrolled Students Directory -->
        <div class="col-lg-5">
            <div class="card-tf mb-4">
                <div class="card-tf-header">
                    <h5 class="card-tf-title"><i class="bi bi-people-fill me-2 text-primary"></i>Enrolled Participants (<?= count($participants) ?>)</h5>
                    <?php if (!empty($participants)): ?>
                        <a href="<?= BASE_URL ?>/ai/generator.php?event_id=<?= $eventId ?>" class="btn btn-sm btn-tf-primary" title="Generate AI team strictly from these enrolled participants">
                            <i class="bi bi-cpu-fill me-1"></i> AI Match
                        </a>
                    <?php endif; ?>
                </div>
                <div class="card-tf-body">
                    <?php if (empty($participants)): ?>
                        <div class="text-center py-4 text-muted small">
                            No students enrolled yet. Be the first to join!
                        </div>
                    <?php else: ?>
                        <div class="d-flex flex-column gap-2" style="max-height: 520px; overflow-y: auto;">
                            <?php foreach ($participants as $p): ?>
                                <div class="d-flex align-items-center justify-content-between p-2 rounded-3 border bg-light">
                                    <div class="d-flex align-items-center gap-2 overflow-hidden">
                                        <?= getAvatar($p, 36) ?>
                                        <div class="overflow-hidden">
                                            <div class="fw-bold small text-truncate text-dark"><?= e($p['full_name']) ?></div>
                                            <div class="text-muted text-truncate" style="font-size: 11px;">
                                                <?= e($p['department']) ?> (<?= e($p['academic_year']) ?>)
                                            </div>
                                        </div>
                                    </div>
                                    <a href="<?= BASE_URL ?>/profile.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-light border text-primary" style="font-size: 11px;">
                                        Profile
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
