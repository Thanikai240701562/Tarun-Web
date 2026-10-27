<?php
/**
 * TeamForge What-If Simulator
 * Hypothetical Scenario Analysis (Section 11)
 * Simulates algorithm responses to changed team sizes, skill constraints, and candidate availability
 * without persisting or modifying actual team membership records in MySQL.
 */
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/TeamForgeCspEngine.php';

requireAuth();
$currentUser = currentUser();
$db = getDB();

// Fetch sample student candidates
$studentsRaw = $db->query("
    SELECT u.*,
           (SELECT COUNT(*) FROM student_projects WHERE user_id = u.id) as project_count
    FROM users u 
    WHERE u.role != 'faculty_admin'
")->fetchAll();

$candIds = array_column($studentsRaw, 'id');
$inClause = implode(',', array_map('intval', $candIds));
$skillsQuery = $db->query("
    SELECT ss.user_id, ss.skill_id, ss.proficiency_level, ss.years_experience, s.name
    FROM student_skills ss
    JOIN skills s ON ss.skill_id = s.id
    WHERE ss.user_id IN ({$inClause})
")->fetchAll();

$skillsByCand = [];
foreach ($skillsQuery as $sq) {
    $skillsByCand[$sq['user_id']][$sq['skill_id']] = [
        'name' => $sq['name'],
        'prof' => (int)$sq['proficiency_level'],
        'years' => (float)$sq['years_experience']
    ];
}

$candidates = [];
foreach ($studentsRaw as $s) {
    $s['skills'] = $skillsByCand[$s['id']] ?? [];
    $candidates[] = $s;
}

$skillsCatalog = $db->query("SELECT id, name FROM skills ORDER BY name ASC")->fetchAll();

// Simulation Inputs
$simTeamSize = max(2, min(8, (int)($_GET['size'] ?? 4)));
$simSelectedSkills = !empty($_GET['skills']) ? array_map('intval', $_GET['skills']) : [1, 2, 3, 11];
$simAvailabilityStrict = (isset($_GET['strict_avail']) && $_GET['strict_avail'] === '1');

$reqSkillsMap = [];
foreach ($skillsCatalog as $sk) {
    if (in_array((int)$sk['id'], $simSelectedSkills, true)) {
        $reqSkillsMap[(int)$sk['id']] = $sk['name'];
    }
}

// Filter candidate pool if strict availability simulated
$simCandidatePool = $candidates;
if ($simAvailabilityStrict) {
    $simCandidatePool = array_filter($candidates, fn($c) => $c['availability_status'] === 'available');
}

$targetRoles = array_slice(AVAILABLE_ROLES, 0, $simTeamSize);

// Run Engine Simulation
$simEngine = new TeamForgeCspEngine(
    array_values($simCandidatePool),
    $reqSkillsMap,
    $targetRoles,
    $simTeamSize,
    DEFAULT_AI_WEIGHTS
);

$simInitial = $simEngine->generateGreedyInitial();
$simOptimal = $simEngine->optimizeWithHillClimbing($simInitial);
$simEval = $simEngine->evaluateAssignment($simOptimal);

$pageTitle = "What-If Simulator — " . APP_NAME;
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-0">
    <!-- Notice Banner -->
    <div class="alert alert-info border-info d-flex align-items-center gap-2 mb-4 p-3 shadow-sm rounded-3">
        <i class="bi bi-info-circle-fill fs-4 text-info flex-shrink-0"></i>
        <div>
            <strong>Hypothetical Simulation Mode Active:</strong> This sandbox demonstrates algorithmic sensitivity to changes in team size and requirements. Results are simulated and do NOT alter confirmed database rosters.
        </div>
    </div>

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <h2 class="fw-extrabold text-navy mb-1" style="font-weight: 800;">What-If Simulator</h2>
            <p class="text-muted small mb-0">Evaluate how altering team size, skill requirements, or availability policies impacts team viability.</p>
        </div>
        <a href="<?= BASE_URL ?>/ai/generator.php" class="btn btn-tf-primary">
            Apply to Real Team &rarr;
        </a>
    </div>

    <div class="row g-4">
        <!-- Left: Simulator Controls -->
        <div class="col-lg-4">
            <div class="card-tf p-4 shadow-sm">
                <h5 class="fw-bold text-dark mb-3"><i class="bi bi-sliders me-2 text-primary"></i>Hypothesis Parameters</h5>
                
                <form method="GET" action="<?= BASE_URL ?>/ai/simulator.php">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Hypothetical Team Size</label>
                        <select name="size" class="form-select" onchange="this.form.submit()">
                            <option value="2" <?= $simTeamSize === 2 ? 'selected' : '' ?>>2 Members (Duo)</option>
                            <option value="3" <?= $simTeamSize === 3 ? 'selected' : '' ?>>3 Members (Trio)</option>
                            <option value="4" <?= $simTeamSize === 4 ? 'selected' : '' ?>>4 Members (Standard Quad)</option>
                            <option value="5" <?= $simTeamSize === 5 ? 'selected' : '' ?>>5 Members (Expanded Squad)</option>
                            <option value="6" <?= $simTeamSize === 6 ? 'selected' : '' ?>>6 Members (Large Team)</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Simulated Availability Constraint</label>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="strict_avail" value="1" id="strictAvail" <?= $simAvailabilityStrict ? 'checked' : '' ?> onchange="this.form.submit()">
                            <label class="form-check-label small" for="strictAvail">
                                Require 100% Free Availability (Filter out busy peers)
                            </label>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-semibold d-block mb-1">Required Skills Included</label>
                        <div class="p-2 border rounded-3 bg-light" style="max-height: 200px; overflow-y: auto;">
                            <?php foreach ($skillsCatalog as $sk): 
                                $isChk = in_array((int)$sk['id'], $simSelectedSkills, true);
                            ?>
                                <div class="form-check mb-1">
                                    <input class="form-check-input" type="checkbox" name="skills[]" value="<?= $sk['id'] ?>" id="sim_sk_<?= $sk['id'] ?>" <?= $isChk ? 'checked' : '' ?>>
                                    <label class="form-check-label small" for="sim_sk_<?= $sk['id'] ?>">
                                        <?= e($sk['name']) ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-tf-primary w-100">
                        <i class="bi bi-arrow-repeat me-1"></i> Rerun Simulation
                    </button>
                </form>
            </div>
        </div>

        <!-- Right: Simulation Output & Trade-Off Comparison -->
        <div class="col-lg-8">
            <div class="card-tf p-4 shadow-sm mb-4">
                <div class="d-flex flex-wrap align-items-center justify-content-between pb-3 border-bottom mb-3">
                    <div>
                        <h5 class="fw-bold text-dark mb-1">Simulated Team Score: <span class="text-primary"><?= $simEval['total_score'] ?>%</span></h5>
                        <div class="text-muted small">Tested across <?= count($simCandidatePool) ?> eligible candidates.</div>
                    </div>
                    <span class="badge bg-purple-subtle text-purple border p-2 fw-bold font-mono">
                        <?= $simTeamSize ?> Role Slots Simulated
                    </span>
                </div>

                <!-- 4 Metrics Overview -->
                <div class="row g-3 mb-4">
                    <div class="col-sm-6 col-md-3">
                        <div class="p-2 border rounded-2 bg-light text-center">
                            <div class="fw-extrabold text-success fs-5"><?= $simEval['skill_coverage_pct'] ?>%</div>
                            <div class="text-muted small" style="font-size:10px;">Skill Coverage</div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-md-3">
                        <div class="p-2 border rounded-2 bg-light text-center">
                            <div class="fw-extrabold text-primary fs-5"><?= $simEval['role_coverage_pct'] ?>%</div>
                            <div class="text-muted small" style="font-size:10px;">Role Specialization</div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-md-3">
                        <div class="p-2 border rounded-2 bg-light text-center">
                            <div class="fw-extrabold text-info fs-5"><?= $simEval['experience_balance_score'] ?>%</div>
                            <div class="text-muted small" style="font-size:10px;">Experience Diversity</div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-md-3">
                        <div class="p-2 border rounded-2 bg-light text-center">
                            <div class="fw-extrabold text-warning fs-5"><?= $simEval['availability_score'] ?>%</div>
                            <div class="text-muted small" style="font-size:10px;">Bandwidth Index</div>
                        </div>
                    </div>
                </div>

                <!-- Simulated Lineup Roster -->
                <h6 class="fw-bold text-dark mb-2">Simulated Member Lineup:</h6>
                <div class="row g-2 mb-3">
                    <?php foreach ($simOptimal as $role => $candId): 
                        $expl = $simEval['explanations'][$candId] ?? [];
                    ?>
                        <div class="col-md-6">
                            <div class="p-2 border rounded-3 bg-light d-flex align-items-center justify-content-between">
                                <div>
                                    <div class="fw-bold text-dark small"><?= e($expl['name'] ?? 'Candidate') ?></div>
                                    <div class="text-primary small" style="font-size:11px;"><?= e($role) ?></div>
                                </div>
                                <span class="badge bg-light text-muted border" style="font-size:10px;">
                                    <?= e($expl['availability'] ?? 'available') ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Simulation Insight -->
                <div class="p-3 rounded-3 bg-light border text-muted small" style="line-height: 1.5; font-size:12px;">
                    <strong>Simulation Insight:</strong> 
                    <?php if ($simTeamSize < 3): ?>
                        A smaller team size (<?= $simTeamSize ?>) reduces scheduling overhead, but limits maximum skill diversity (coverage: <?= $simEval['skill_coverage_pct'] ?>%).
                    <?php elseif ($simTeamSize >= 5): ?>
                        An expanded team size (<?= $simTeamSize ?>) allows broader role specialization and QA redundancy, but increases candidate availability coordination complexity.
                    <?php else: ?>
                        The selected 4-person setup achieves optimal balance between complete hard skill coverage and communication bandwidth.
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
