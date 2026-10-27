<?php
/**
 * TeamForge Create Project / Team
 */
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

requireAuth();
$currentUser = currentUser();
$db = getDB();

$selectedEventId = (int)($_GET['event_id'] ?? 0);
$error = null;

// Fetch events list
$eventsList = $db->query("SELECT id, title, min_team_size, max_team_size FROM events WHERE status = 'active' ORDER BY event_date ASC")->fetchAll();

// Fetch skills catalog
$skillsList = $db->query("SELECT id, name, category FROM skills ORDER BY category, name ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Security session expired. Please refresh and try again.';
    } else {
        $name = sanitize($_POST['name'] ?? '');
        $projectTitle = sanitize($_POST['project_title'] ?? '');
        $projectDesc = sanitize($_POST['project_description'] ?? '');
        $projectCategory = sanitize($_POST['project_category'] ?? 'Web & AI Development');
        $eventId = (int)($_POST['event_id'] ?? 0);
        $eventId = $eventId > 0 ? $eventId : null;
        $teamSize = max(2, min(8, (int)($_POST['required_team_size'] ?? 4)));
        $prefExp = in_array($_POST['preferred_experience'] ?? '', ['beginner', 'intermediate', 'advanced', 'mixed']) ? $_POST['preferred_experience'] : 'mixed';
        $deadline = !empty($_POST['project_deadline']) ? sanitize($_POST['project_deadline']) : null;
        $visibility = in_array($_POST['visibility'] ?? '', ['public', 'private']) ? $_POST['visibility'] : 'public';
        $formationMethod = in_array($_POST['formation_method'] ?? '', ['manual', 'ai_generated', 'hybrid']) ? $_POST['formation_method'] : 'manual';
        $selectedSkillIds = $_POST['required_skills'] ?? [];

        if (empty($name) || empty($projectTitle) || empty($projectDesc)) {
            $error = 'Please fill in Team Name, Project Title, and Project Description.';
        } else {
            try {
                $db->beginTransaction();

                // Generate unique team code
                $teamCode = 'FORGE-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));

                $insTeam = $db->prepare("
                    INSERT INTO teams (
                        name, project_title, project_description, project_category,
                        event_id, leader_id, team_code, required_team_size,
                        preferred_experience, project_deadline, visibility,
                        formation_method, status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'recruiting')
                ");
                $insTeam->execute([
                    $name, $projectTitle, $projectDesc, $projectCategory,
                    $eventId, $currentUser['id'], $teamCode, $teamSize,
                    $prefExp, $deadline, $visibility,
                    $formationMethod
                ]);
                $teamId = (int)$db->lastInsertId();

                // Add creator as Team Leader in team_members
                $insMember = $db->prepare("
                    INSERT INTO team_members (team_id, user_id, assigned_role, status)
                    VALUES (?, ?, 'Team Leader & Full-Stack Developer', 'active')
                ");
                $insMember->execute([$teamId, $currentUser['id']]);

                // Update user status and promote to Team Leader if student
                $updUser = $db->prepare("
                    UPDATE users SET
                        role = CASE WHEN role = 'student' THEN 'team_leader' ELSE role END,
                        availability_status = 'in_team'
                    WHERE id = ?
                ");
                $updUser->execute([$currentUser['id']]);

                // Insert required skills
                if (!empty($selectedSkillIds) && is_array($selectedSkillIds)) {
                    $insSkill = $db->prepare("INSERT IGNORE INTO team_skills (team_id, skill_id, is_required) VALUES (?, ?, 1)");
                    foreach ($selectedSkillIds as $sId) {
                        $sId = (int)$sId;
                        if ($sId > 0) {
                            $insSkill->execute([$teamId, $sId]);
                        }
                    }
                }

                $db->commit();
                logAudit($currentUser['id'], 'TEAM_CREATED', 'team', $teamId, ['team_code' => $teamCode]);

                setFlash('success', "Team \"{$name}\" created successfully! Team code: <strong>{$teamCode}</strong>");
                header('Location: ' . BASE_URL . '/teams/view.php?id=' . $teamId);
                exit;

            } catch (Exception $e) {
                if ($db->inTransaction()) $db->rollBack();
                $error = 'Failed to create team: ' . $e->getMessage();
            }
        }
    }
}

$pageTitle = "Create Project Team — " . APP_NAME;
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-0">
    <div class="mb-3">
        <a href="<?= BASE_URL ?>/teams/my_teams.php" class="text-decoration-none text-muted small">
            &larr; Back to My Teams
        </a>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-9 col-xl-8">
            <div class="card-tf shadow-lg border p-4 p-md-5">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle p-2">
                        <i class="bi bi-people-fill"></i> New Team
                    </span>
                </div>
                <h3 class="fw-bold text-dark mb-1">Create Project & Team Roster</h3>
                <p class="text-muted small mb-4">Define your team objectives, select required skill sets, and initiate teammate recruitment.</p>

                <?php if ($error): ?>
                    <div class="alert alert-danger d-flex align-items-center p-3 mb-4 rounded-3" role="alert">
                        <i class="bi bi-exclamation-triangle-fill fs-5 me-2 flex-shrink-0"></i>
                        <div class="small fw-medium"><?= e($error) ?></div>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?= BASE_URL ?>/teams/create.php">
                    <?= csrf_field() ?>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Team Name *</label>
                            <input type="text" class="form-control" name="name" required placeholder="e.g. CodeCrafters, NeuralPulse">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Associated College Event (Optional)</label>
                            <select class="form-select" name="event_id">
                                <option value="0">-- Independent Coursework / Club Project --</option>
                                <?php foreach ($eventsList as $evt): ?>
                                    <option value="<?= $evt['id'] ?>" <?= $selectedEventId === (int)$evt['id'] ? 'selected' : '' ?>>
                                        <?= e($evt['title']) ?> (<?= $evt['min_team_size'] ?>-<?= $evt['max_team_size'] ?> members)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-8">
                            <label class="form-label small fw-semibold">Project Title *</label>
                            <input type="text" class="form-control" name="project_title" required placeholder="e.g. Decentralized Campus Peer Mentorship Platform">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Project Category</label>
                            <select class="form-select" name="project_category">
                                <option value="Web & AI Development">Web & AI Development</option>
                                <option value="Machine Learning & Data">Algorithms & Systems</option>
                                <option value="Mobile Applications">Mobile Applications</option>
                                <option value="IoT & Hardware">IoT & Hardware</option>
                                <option value="Civic & Social Tech">Civic & Social Tech</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Project Description & Goal *</label>
                        <textarea class="form-control" name="project_description" rows="3" required placeholder="Explain the problem statement, proposed architecture, and intended deliverable..."></textarea>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Target Team Size *</label>
                            <input type="number" class="form-control" name="required_team_size" value="4" min="2" max="8" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Experience Preference</label>
                            <select class="form-select" name="preferred_experience">
                                <option value="mixed" selected>Mixed (Balanced Mentorship)</option>
                                <option value="intermediate">Intermediate</option>
                                <option value="advanced">Advanced Specialists</option>
                                <option value="beginner">Beginner Friendly</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Project Deadline</label>
                            <input type="date" class="form-control" name="project_deadline" min="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Formation Method</label>
                            <select class="form-select" name="formation_method">
                                <option value="manual">Manual Selection</option>
                                <option value="ai_generated" selected>AI Classical Engine</option>
                                <option value="hybrid">Hybrid Approach</option>
                            </select>
                        </div>
                    </div>

                    <!-- Required Skills Multi-Selection -->
                    <div class="mb-4">
                        <label class="form-label small fw-semibold d-block mb-2">Required Skills for this Project</label>
                        <div class="p-3 border rounded-3 bg-light" style="max-height: 180px; overflow-y: auto;">
                            <div class="row g-2">
                                <?php foreach ($skillsList as $sk): ?>
                                    <div class="col-sm-6 col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="required_skills[]" value="<?= $sk['id'] ?>" id="sk_<?= $sk['id'] ?>">
                                            <label class="form-check-label small" for="sk_<?= $sk['id'] ?>">
                                                <?= e($sk['name']) ?>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="form-text small">Selected skills will be prioritized by the Classical AI Team Generator when matching candidates.</div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 border-top pt-3">
                        <a href="<?= BASE_URL ?>/teams/my_teams.php" class="btn btn-light">Cancel</a>
                        <button type="submit" class="btn btn-tf-primary px-4">Create Team & Launch</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
