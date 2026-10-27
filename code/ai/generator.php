<?php
/**
 * TeamForge AI Team Generator
 * Classical AI Constraint Satisfaction & Hill Climbing Formation
 */
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/TeamForgeCspEngine.php';

requireAuth();
$currentUser = currentUser();
$db = getDB();

$teamId = (int)($_GET['team_id'] ?? 0);
$eventId = (int)($_GET['event_id'] ?? 0);

// Fetch existing team if passed
$team = null;
if ($teamId > 0) {
    $tStmt = $db->prepare("SELECT * FROM teams WHERE id = ?");
    $tStmt->execute([$teamId]);
    $team = $tStmt->fetch();
    if ($team && empty($eventId) && !empty($team['event_id'])) {
        $eventId = (int)$team['event_id'];
    }
}

// Fetch all available skills
$skillsCatalog = $db->query("SELECT id, name, category FROM skills ORDER BY category, name ASC")->fetchAll();

// Fetch active events
$eventsList = $db->query("SELECT id, title, min_team_size, max_team_size FROM events WHERE status = 'active' ORDER BY event_date ASC")->fetchAll();

// User led teams for batch invite
$myLedTeamsStmt = $db->prepare("SELECT id, name, team_code FROM teams WHERE leader_id = ? AND status = 'recruiting'");
$myLedTeamsStmt->execute([$currentUser['id']]);
$myLedTeams = $myLedTeamsStmt->fetchAll();

// Default generator settings
$targetSize = $team ? (int)$team['required_team_size'] : 4;
$selectedSkillIds = [];
if ($team) {
    $tsStmt = $db->prepare("SELECT skill_id FROM team_skills WHERE team_id = ?");
    $tsStmt->execute([$teamId]);
    $selectedSkillIds = $tsStmt->fetchAll(PDO::FETCH_COLUMN);
} else {
    // Default skills: PHP, MySQL, JavaScript, UI/UX
    $selectedSkillIds = [1, 2, 3, 11];
}

$proposals = null;
$executionTrace = null;
$runStats = null;

// Handle AI Generation Execution
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        setFlash('danger', 'Security validation failed.');
    } else {
        $targetSize = max(2, min(8, (int)($_POST['target_size'] ?? 4)));
        $eventId = (int)($_POST['event_id'] ?? 0);
        $selectedSkillIds = array_map('intval', $_POST['skills'] ?? []);
        $weightSkill = (float)($_POST['weight_skill'] ?? 0.35);
        $weightRole = (float)($_POST['weight_role'] ?? 0.25);
        $weightExp = (float)($_POST['weight_exp'] ?? 0.15);
        $weightAvail = (float)($_POST['weight_avail'] ?? 0.15);

        // Fetch candidate pool from MySQL
        // If eventId is specified, candidate pool MUST be registered for this event!
        if ($eventId > 0) {
            $candStmt = $db->prepare("
                SELECT u.*,
                       (SELECT COUNT(*) FROM team_members tm JOIN teams t ON tm.team_id = t.id WHERE tm.user_id = u.id AND tm.status = 'active') as active_team_count,
                       (SELECT COUNT(*) FROM student_projects WHERE user_id = u.id) as project_count
                FROM users u
                JOIN event_registrations er ON u.id = er.user_id
                WHERE er.event_id = ? AND er.status != 'cancelled'
            ");
            $candStmt->execute([$eventId]);
        } else {
            $candStmt = $db->query("
                SELECT u.*,
                       (SELECT COUNT(*) FROM team_members tm JOIN teams t ON tm.team_id = t.id WHERE tm.user_id = u.id AND tm.status = 'active') as active_team_count,
                       (SELECT COUNT(*) FROM student_projects WHERE user_id = u.id) as project_count
                FROM users u
            ");
        }
        $candidatesRaw = $candStmt->fetchAll();

        // Attach skills to each candidate
        $candidates = [];
        $candIds = array_column($candidatesRaw, 'id');
        if (!empty($candIds)) {
            $inClause = implode(',', array_map('intval', $candIds));
            $skillsQuery = $db->query("
                SELECT ss.user_id, ss.skill_id, ss.proficiency_level, ss.years_experience, s.name
                FROM student_skills ss
                JOIN skills s ON ss.skill_id = s.id
                WHERE ss.user_id IN ({$inClause})
            ")->fetchAll();

            $skillsByCandidate = [];
            foreach ($skillsQuery as $sq) {
                $skillsByCandidate[$sq['user_id']][$sq['skill_id']] = [
                    'name' => $sq['name'],
                    'prof' => (int)$sq['proficiency_level'],
                    'years' => (float)$sq['years_experience']
                ];
            }

            foreach ($candidatesRaw as $cRow) {
                $cRow['skills'] = $skillsByCandidate[$cRow['id']] ?? [];
                $candidates[] = $cRow;
            }
        }

        // Map skill IDs to names
        $reqSkillMap = [];
        foreach ($skillsCatalog as $sc) {
            if (in_array((int)$sc['id'], $selectedSkillIds, true)) {
                $reqSkillMap[(int)$sc['id']] = $sc['name'];
            }
        }

        // Target roles list
        $targetRoles = array_slice(AVAILABLE_ROLES, 0, $targetSize);

        // Pin current user if they want to be on the team
        $lockedMembers = [];
        if ($currentUser['role'] !== ROLE_FACULTY_ADMIN) {
            $lockedMembers[$targetRoles[0]] = $currentUser['id'];
        }

        $weights = [
            'skill_coverage' => $weightSkill,
            'role_coverage' => $weightRole,
            'experience_balance' => $weightExp,
            'availability' => $weightAvail,
            'interest_alignment' => 0.10
        ];

        // Instantiate Classical AI Engine
        $aiEngine = new TeamForgeCspEngine(
            $candidates,
            $reqSkillMap,
            $targetRoles,
            $targetSize,
            $weights,
            $lockedMembers,
            [],
            $eventId > 0 ? $eventId : null
        );

        $proposals = $aiEngine->generateMultipleProposals();
        $executionTrace = $aiEngine->executionTrace;

        // Save optimization run to MySQL for explainability
        try {
            $runStmt = $db->prepare("
                INSERT INTO optimization_runs (
                    team_id, run_name, algorithm_used, input_params_json,
                    best_score, generated_assignments_json, status
                ) VALUES (?, ?, 'Backtracking_MRV_HillClimbing', ?, ?, ?, 'success')
            ");
            $runName = 'AI Team Generation Run ' . date('M d, H:i:s');
            $inputJson = json_encode([
                'target_size' => $targetSize,
                'event_id' => $eventId,
                'skills' => $selectedSkillIds,
                'weights' => $weights
            ]);
            $bestScore = $proposals[0]['evaluation']['total_score'] ?? 0.0;
            $assignmentsJson = json_encode($proposals);
            $runStmt->execute([$teamId > 0 ? $teamId : null, $runName, $inputJson, $bestScore, $assignmentsJson]);
            $runId = (int)$db->lastInsertId();

            // Record top steps in optimization_steps
            $stepStmt = $db->prepare("
                INSERT INTO optimization_steps (
                    run_id, step_number, step_type, description, current_score, state_snapshot_json
                ) VALUES (?, ?, ?, ?, ?, ?)
            ");
            foreach (array_slice($executionTrace, 0, 20) as $tStep) {
                $stepStmt->execute([
                    $runId,
                    $tStep['step'],
                    $tStep['type'],
                    $tStep['description'],
                    $tStep['score'],
                    json_encode($tStep['state'] ?? [])
                ]);
            }
        } catch (Exception $e) {
            error_log("Failed to persist optimization run: " . $e->getMessage());
        }
    }
}

$pageTitle = "AI Team Generator (Classical CSP) — " . APP_NAME;
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-0">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <div class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1 rounded-pill fw-bold mb-1">
                <i class="bi bi-cpu-fill me-1"></i> Classical AI Engine &bull; Zero External APIs
            </div>
            <h2 class="fw-extrabold text-navy mb-1" style="font-weight: 800;">AI Team Generator</h2>
            <p class="text-muted small mb-0">Constraint Satisfaction (CSP) &bull; Backtracking &bull; MRV Heuristic &bull; Forward Checking &bull; Hill Climbing</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= BASE_URL ?>/ai/optimization_lab.php" class="btn btn-white border shadow-sm text-dark d-inline-flex align-items-center gap-2">
                <i class="bi bi-terminal-fill text-primary"></i>
                <span>Explainable Optimization Lab</span>
            </a>
            <a href="<?= BASE_URL ?>/ai/simulator.php" class="btn btn-white border shadow-sm text-dark d-inline-flex align-items-center gap-2">
                <i class="bi bi-sliders2-vertical text-secondary"></i>
                <span>What-If Simulator</span>
            </a>
        </div>
    </div>

    <!-- AI Generator Input Configuration -->
    <div class="card-tf p-4 mb-4 bg-white shadow-sm">
        <h5 class="fw-bold text-dark mb-3"><i class="bi bi-gear-wide-connected me-2 text-primary"></i>Project Requirements & Formulation</h5>
        
        <form method="POST" action="<?= BASE_URL ?>/ai/generator.php<?= $teamId > 0 ? '?team_id='.$teamId : '' ?>">
            <?= csrf_field() ?>

            <div class="row g-3 mb-3">
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Target Team Size *</label>
                    <input type="number" class="form-control" name="target_size" value="<?= $targetSize ?>" min="2" max="8" required>
                    <div class="form-text small">Number of student slots ($V_1 \dots V_k$)</div>
                </div>

                <div class="col-md-5">
                    <label class="form-label small fw-semibold">Constrain by College Event</label>
                    <select class="form-select" name="event_id">
                        <option value="0">-- Open Campus (All Enrolled Students) --</option>
                        <?php foreach ($eventsList as $evt): ?>
                            <option value="<?= $evt['id'] ?>" <?= $eventId === (int)$evt['id'] ? 'selected' : '' ?>>
                                <?= e($evt['title']) ?> (Event Participants Only)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Leadership Assignment</label>
                    <div class="form-control bg-light d-flex align-items-center gap-2 small text-muted">
                        <i class="bi bi-lock-fill text-warning"></i>
                        <span>Pinned: <strong><?= e($currentUser['full_name']) ?></strong> (Slot 1)</span>
                    </div>
                </div>
            </div>

            <!-- Required Skills Checkboxes -->
            <div class="mb-3">
                <label class="form-label small fw-semibold d-block mb-1">Required Technical Skills ($Hard \cup Soft$ Constraints)</label>
                <div class="p-3 border rounded-3 bg-light" style="max-height: 160px; overflow-y: auto;">
                    <div class="row g-2">
                        <?php foreach ($skillsCatalog as $sk): 
                            $isChecked = in_array((int)$sk['id'], $selectedSkillIds, true);
                        ?>
                            <div class="col-sm-6 col-md-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="skills[]" value="<?= $sk['id'] ?>" id="gen_sk_<?= $sk['id'] ?>" <?= $isChecked ? 'checked' : '' ?>>
                                    <label class="form-check-label small" for="gen_sk_<?= $sk['id'] ?>">
                                        <?= e($sk['name']) ?>
                                    </label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Collapsible Multi-Objective Weights -->
            <div class="mb-4">
                <a class="text-decoration-none small fw-semibold text-primary d-inline-flex align-items-center gap-1" data-bs-toggle="collapse" href="#weightConfig">
                    <i class="bi bi-sliders"></i> Customize Multi-Objective Scoring Weights (Advanced)
                </a>
                <div class="collapse mt-2" id="weightConfig">
                    <div class="p-3 border rounded-3 bg-light">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label small fw-semibold">Skill Coverage ($W_{skill}$)</label>
                                <input type="number" step="0.05" class="form-control form-control-sm" name="weight_skill" value="0.35" min="0.1" max="0.7">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-semibold">Role Fit ($W_{role}$)</label>
                                <input type="number" step="0.05" class="form-control form-control-sm" name="weight_role" value="0.25" min="0.1" max="0.5">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-semibold">Experience Mix ($W_{exp}$)</label>
                                <input type="number" step="0.05" class="form-control form-control-sm" name="weight_exp" value="0.15" min="0.05" max="0.4">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-semibold">Availability ($W_{avail}$)</label>
                                <input type="number" step="0.05" class="form-control form-control-sm" name="weight_avail" value="0.15" min="0.05" max="0.4">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end">
                <button type="submit" class="btn btn-tf-primary px-4 py-2 fs-6">
                    <i class="bi bi-cpu-fill me-2"></i> Execute AI Formation Engine
                </button>
            </div>
        </form>
    </div>

    <!-- AI Results Presentation -->
    <?php if ($proposals !== null): ?>
        <div class="mb-4">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h4 class="fw-bold text-dark mb-0">Generated Team Proposals (Classical AI)</h4>
                <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 fw-bold">
                    <i class="bi bi-check-circle-fill me-1"></i> Hard Constraints 100% Satisfied
                </span>
            </div>

            <!-- Proposals Nav Tabs -->
            <ul class="nav nav-pills mb-3 gap-2" id="proposalTabs" role="tablist">
                <?php foreach ($proposals as $idx => $prop): ?>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?= $idx === 0 ? 'active' : '' ?> fw-semibold" id="tab-btn-<?= $prop['id'] ?>" data-bs-toggle="pill" data-bs-target="#prop-content-<?= $prop['id'] ?>" type="button" role="tab">
                            <?= $prop['id'] === 1 ? '★ ' : '' ?>Proposal <?= $prop['id'] ?> (Score: <?= $prop['evaluation']['total_score'] ?>%)
                        </button>
                    </li>
                <?php endforeach; ?>
            </ul>

            <div class="tab-content" id="proposalTabsContent">
                <?php foreach ($proposals as $idx => $prop): 
                    $eval = $prop['evaluation'];
                ?>
                    <div class="tab-pane fade <?= $idx === 0 ? 'show active' : '' ?>" id="prop-content-<?= $prop['id'] ?>" role="tabpanel">
                        <div class="card-tf shadow-lg border p-4 mb-4">
                            <!-- Proposal Summary Banner -->
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 pb-3 border-bottom mb-4">
                                <div>
                                    <h5 class="fw-bold text-dark mb-1"><?= e($prop['title']) ?></h5>
                                    <p class="text-muted small mb-0"><?= e($prop['description']) ?></p>
                                </div>
                                <div class="d-flex align-items-center gap-3">
                                    <div class="text-center p-2 rounded-3 bg-light border" style="min-width: 100px;">
                                        <div class="display-6 fw-extrabold text-primary mb-0" style="font-size: 2rem;"><?= $eval['total_score'] ?>%</div>
                                        <div class="small fw-bold text-muted text-uppercase" style="font-size: 10px;">Overall Balance</div>
                                    </div>
                                </div>
                            </div>

                            <!-- 4 Objective Sub-Scores -->
                            <div class="row g-3 mb-4">
                                <div class="col-sm-6 col-md-3">
                                    <div class="p-3 border rounded-3 bg-light">
                                        <div class="d-flex justify-content-between small text-muted mb-1">
                                            <span>Skill Coverage:</span>
                                            <strong class="text-dark"><?= $eval['skill_coverage_pct'] ?>%</strong>
                                        </div>
                                        <div class="progress" style="height:6px;">
                                            <div class="progress-bar bg-success" style="width: <?= $eval['skill_coverage_pct'] ?>%;"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-3">
                                    <div class="p-3 border rounded-3 bg-light">
                                        <div class="d-flex justify-content-between small text-muted mb-1">
                                            <span>Role Specialization:</span>
                                            <strong class="text-dark"><?= $eval['role_coverage_pct'] ?>%</strong>
                                        </div>
                                        <div class="progress" style="height:6px;">
                                            <div class="progress-bar bg-primary" style="width: <?= $eval['role_coverage_pct'] ?>%;"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-3">
                                    <div class="p-3 border rounded-3 bg-light">
                                        <div class="d-flex justify-content-between small text-muted mb-1">
                                            <span>Experience Diversity:</span>
                                            <strong class="text-dark"><?= $eval['experience_balance_score'] ?>%</strong>
                                        </div>
                                        <div class="progress" style="height:6px;">
                                            <div class="progress-bar bg-info" style="width: <?= $eval['experience_balance_score'] ?>%;"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-3">
                                    <div class="p-3 border rounded-3 bg-light">
                                        <div class="d-flex justify-content-between small text-muted mb-1">
                                            <span>Availability Rating:</span>
                                            <strong class="text-dark"><?= $eval['availability_score'] ?>%</strong>
                                        </div>
                                        <div class="progress" style="height:6px;">
                                            <div class="progress-bar bg-warning" style="width: <?= $eval['availability_score'] ?>%;"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Missing Skills Alert if any -->
                            <?php if (!empty($eval['missing_skills'])): ?>
                                <div class="alert alert-warning small d-flex align-items-center gap-2 mb-4 rounded-3">
                                    <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                                    <div>
                                        <strong>Missing Skill Notice:</strong> <?= implode(', ', array_map('e', $eval['missing_skills'])) ?> was not covered by this candidate pool.
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Generated Team Member Cards & Rationales -->
                            <h6 class="fw-bold text-dark mb-3"><i class="bi bi-person-badge-fill me-2 text-primary"></i>Assigned Team Composition & Selection Rationales</h6>
                            
                            <div class="row g-3 mb-4">
                                <?php 
                                $assignedIds = [];
                                foreach ($prop['assignment'] as $roleName => $studentId): 
                                    $assignedIds[] = $studentId;
                                    $explanation = $eval['explanations'][$studentId] ?? [];
                                ?>
                                    <div class="col-md-6">
                                        <div class="p-3 border rounded-3 bg-white h-100 shadow-sm d-flex flex-column justify-content-between">
                                            <div>
                                                <div class="d-flex align-items-start justify-content-between mb-2">
                                                    <div>
                                                        <h6 class="fw-bold text-dark mb-0"><?= e($explanation['name'] ?? 'Candidate') ?></h6>
                                                        <div class="badge bg-primary-subtle text-primary border border-primary-subtle small mt-1">
                                                            Assigned: <?= e($roleName) ?>
                                                        </div>
                                                    </div>
                                                    <span class="status-pill status-<?= e($explanation['availability'] ?? 'available') ?>" style="font-size:10px;">
                                                        <?= ucfirst($explanation['availability'] ?? 'available') ?>
                                                    </span>
                                                </div>

                                                <!-- Selection Explanation -->
                                                <div class="p-2 rounded-2 bg-light border small text-muted mb-2" style="font-size: 11px; line-height: 1.4;">
                                                    <strong>Algorithm Rationale:</strong> 
                                                    Covered <?= count($explanation['skills_covered'] ?? []) ?> required competencies: 
                                                    <span class="text-dark"><?= !empty($explanation['skills_covered']) ? implode(', ', $explanation['skills_covered']) : 'Complementary role capacity' ?></span>.
                                                    Portfolio shows <?= (int)($explanation['projects'] ?? 0) ?> verified project(s).
                                                </div>
                                            </div>

                                            <div class="d-flex justify-content-end pt-2 border-top">
                                                <a href="<?= BASE_URL ?>/profile.php?id=<?= $studentId ?>" target="_blank" class="text-primary small text-decoration-none fw-semibold" style="font-size:11px;">
                                                    View Full Profile &rarr;
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Batch Invitation Sender for this Proposal -->
                            <div class="p-3 rounded-3 bg-light border d-flex flex-wrap align-items-center justify-content-between gap-3">
                                <div>
                                    <div class="fw-bold text-dark small">Satisfied with Proposal <?= $prop['id'] ?>?</div>
                                    <div class="text-muted small" style="font-size:11px;">You can directly send official team invitations to all candidates in this generated lineup.</div>
                                </div>
                                <?php if (!empty($myLedTeams)): ?>
                                    <form method="POST" action="<?= BASE_URL ?>/teams/send_invite.php" class="d-flex align-items-center gap-2">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="recipient_ids" value="<?= implode(',', $assignedIds) ?>">
                                        <input type="hidden" name="invited_role" value="Assigned AI Role">
                                        <select class="form-select form-select-sm" name="team_id" style="width:200px;" required>
                                            <?php foreach ($myLedTeams as $mlt): ?>
                                                <option value="<?= $mlt['id'] ?>"><?= e($mlt['name']) ?> (<?= e($mlt['team_code']) ?>)</option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="btn btn-sm btn-tf-primary text-nowrap">
                                            <i class="bi bi-send-check-fill me-1"></i> Send Lineup Invites
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <a href="<?= BASE_URL ?>/teams/create.php" class="btn btn-sm btn-tf-primary">
                                        <i class="bi bi-plus-lg me-1"></i> Create Team With This Lineup
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
