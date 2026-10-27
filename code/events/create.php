<?php
/**
 * TeamForge Create College Event (Faculty / Admin Only)
 */
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole(ROLE_FACULTY_ADMIN);
$currentUser = currentUser();
$db = getDB();

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Security session expired. Please refresh and try again.';
    } else {
        $title = sanitize($_POST['title'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $eventType = sanitize($_POST['event_type'] ?? 'hackathon');
        $venue = sanitize($_POST['venue'] ?? '');
        $eventDate = sanitize($_POST['event_date'] ?? '');
        $regStart = sanitize($_POST['reg_start_date'] ?? '');
        $regEnd = sanitize($_POST['reg_end_date'] ?? '');
        $maxParticipants = max(10, min(1000, (int)($_POST['max_participants'] ?? 100)));
        $minTeamSize = max(2, min(10, (int)($_POST['min_team_size'] ?? 2)));
        $maxTeamSize = max($minTeamSize, min(10, (int)($_POST['max_team_size'] ?? 5)));
        $requiredSkills = sanitize($_POST['required_skills'] ?? '');
        $eligibility = sanitize($_POST['eligibility_criteria'] ?? '');
        $formationPolicy = in_array($_POST['team_formation_policy'] ?? '', ['open', 'faculty_approval', 'strict_leader']) ? $_POST['team_formation_policy'] : 'open';

        if (empty($title) || empty($description) || empty($venue) || empty($eventDate) || empty($regEnd)) {
            $error = 'Please complete all required event information fields.';
        } elseif (strtotime($regEnd) > strtotime($eventDate)) {
            $error = 'Registration deadline cannot be after the event date.';
        } else {
            try {
                $insStmt = $db->prepare("
                    INSERT INTO events (
                        title, description, organizer_id, event_type, venue,
                        event_date, reg_start_date, reg_end_date, max_participants,
                        min_team_size, max_team_size, required_skills, eligibility_criteria,
                        team_formation_policy, status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
                ");
                $insStmt->execute([
                    $title, $description, $currentUser['id'], $eventType, $venue,
                    $eventDate, $regStart, $regEnd, $maxParticipants,
                    $minTeamSize, $maxTeamSize, $requiredSkills, $eligibility,
                    $formationPolicy
                ]);
                $newEventId = (int)$db->lastInsertId();

                logAudit($currentUser['id'], 'EVENT_PUBLISHED', 'event', $newEventId, ['title' => $title]);
                setFlash('success', 'Event published successfully! Students can now enroll and form teams.');
                header('Location: ' . BASE_URL . '/events/view.php?id=' . $newEventId);
                exit;
            } catch (Exception $e) {
                $error = 'Failed to create event: ' . $e->getMessage();
            }
        }
    }
}

$pageTitle = "Publish College Event — " . APP_NAME;
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-0">
    <div class="mb-3">
        <a href="<?= BASE_URL ?>/events/index.php" class="text-decoration-none text-muted small">
            &larr; Back to Events Directory
        </a>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-9">
            <div class="card-tf shadow-lg border p-4 p-md-5">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge bg-purple-subtle text-purple border p-2"><i class="bi bi-shield-check"></i> Faculty Console</span>
                </div>
                <h3 class="fw-bold text-dark mb-1">Publish College Event / Hackathon</h3>
                <p class="text-muted small mb-4">Create an official university competition, symposium, or project expo with custom team formation rules.</p>

                <?php if ($error): ?>
                    <div class="alert alert-danger d-flex align-items-center p-3 mb-4 rounded-3" role="alert">
                        <i class="bi bi-exclamation-triangle-fill fs-5 me-2 flex-shrink-0"></i>
                        <div class="small fw-medium"><?= e($error) ?></div>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?= BASE_URL ?>/events/create.php">
                    <?= csrf_field() ?>

                    <div class="row g-3 mb-3">
                        <div class="col-md-8">
                            <label class="form-label small fw-semibold">Event Title *</label>
                            <input type="text" class="form-control" name="title" required placeholder="e.g. AI-Forge National Hackathon 2026">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Event Category *</label>
                            <select class="form-select" name="event_type" required>
                                <option value="hackathon">Hackathon</option>
                                <option value="coding_competition">Coding Competition</option>
                                <option value="project_expo">Project Expo</option>
                                <option value="symposium">Symposium</option>
                                <option value="workshop">Workshop</option>
                                <option value="innovation_challenge">Innovation Challenge</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Venue / Campus Location *</label>
                            <input type="text" class="form-control" name="venue" required placeholder="e.g. Innovation Hall B, Main Campus">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Event Date *</label>
                            <input type="date" class="form-control" name="event_date" required min="<?= date('Y-m-d') ?>">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Registration Starts *</label>
                            <input type="date" class="form-control" name="reg_start_date" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Registration Deadline *</label>
                            <input type="date" class="form-control" name="reg_end_date" required min="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Maximum Participants</label>
                            <input type="number" class="form-control" name="max_participants" value="120" min="10" max="1000">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Min Team Size</label>
                            <input type="number" class="form-control" name="min_team_size" value="2" min="1" max="10">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Max Team Size</label>
                            <input type="number" class="form-control" name="max_team_size" value="4" min="2" max="10">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Team Formation Policy</label>
                            <select class="form-select" name="team_formation_policy">
                                <option value="open">Open (Students form freely)</option>
                                <option value="faculty_approval">Faculty Approval Required</option>
                                <option value="strict_leader">Designated Leader Only</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Recommended Skills or Themes</label>
                        <input type="text" class="form-control" name="required_skills" placeholder="e.g. PHP, MySQL, React, Classical AI, Embedded Systems, UI/UX">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Eligibility Criteria</label>
                        <textarea class="form-control" name="eligibility_criteria" rows="2" placeholder="e.g. Open to 2nd, 3rd, and 4th year undergraduate engineering students across all branches."></textarea>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-semibold">Comprehensive Event Description *</label>
                        <textarea class="form-control" name="description" rows="4" required placeholder="Detail the event objectives, evaluation criteria, prize categories, and team deliverables..."></textarea>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="<?= BASE_URL ?>/events/index.php" class="btn btn-light">Cancel</a>
                        <button type="submit" class="btn btn-tf-primary px-4">Publish Event Now</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
