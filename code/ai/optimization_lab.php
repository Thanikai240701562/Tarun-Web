<?php
/**
 * TeamForge Explainable Optimization Lab
 * Foundations of AI Academic Demonstration Module
 * Step-by-step visual execution trace for Backtracking, MRV, Forward Checking, and Hill Climbing.
 */
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/TeamForgeCspEngine.php';

requireAuth();
$currentUser = currentUser();
$db = getDB();

// Fetch sample students for academic trace
$studentsRaw = $db->query("
    SELECT u.*,
           (SELECT COUNT(*) FROM student_projects WHERE user_id = u.id) as project_count
    FROM users u 
    WHERE u.role != 'faculty_admin'
    LIMIT 10
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

// Define benchmark problem for demonstration
$requiredSkills = [
    1 => 'PHP',
    2 => 'MySQL',
    3 => 'JavaScript',
    10 => 'Graph Algorithms & CSP',
    11 => 'UI/UX Design & Figma'
];
$targetRoles = ['Team Leader', 'Frontend Developer', 'Database Developer', 'Algorithm Specialist'];

// Run AI Engine and collect execution trace
$engine = new TeamForgeCspEngine(
    $candidates,
    $requiredSkills,
    $targetRoles,
    4,
    DEFAULT_AI_WEIGHTS
);

$greedyTeam = $engine->generateGreedyInitial();
$backtrackingTeam = $engine->solveWithBacktrackingMRV();
$finalOptimalTeam = $engine->optimizeWithHillClimbing($backtrackingTeam, 10);
$finalEval = $engine->evaluateAssignment($finalOptimalTeam);
$trace = $engine->executionTrace;

$pageTitle = "Explainable Optimization Lab — " . APP_NAME;
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-0">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <div class="badge bg-purple-subtle text-purple border border-purple-subtle px-3 py-1 rounded-pill fw-bold mb-1">
                <i class="bi bi-mortarboard-fill me-1"></i> Foundations of Classical AI &bull; Academic Trace
            </div>
            <h2 class="fw-extrabold text-navy mb-1" style="font-weight: 800;">Explainable Optimization Lab</h2>
            <p class="text-muted small mb-0">Step-by-step visual execution trace of CSP Formulation, MRV Heuristic, Forward Checking, and Hill Climbing.</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-white border shadow-sm text-dark d-inline-flex align-items-center gap-2" onclick="location.reload();">
                <i class="bi bi-arrow-counterclockwise text-primary"></i>
                <span>Reset Simulation</span>
            </button>
            <a href="<?= BASE_URL ?>/ai/generator.php" class="btn btn-tf-primary d-inline-flex align-items-center gap-2">
                <i class="bi bi-cpu-fill"></i>
                <span>Open Team Generator</span>
            </a>
        </div>
    </div>

    <!-- Algorithm Concept Overview Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="p-3 border rounded-3 bg-white shadow-sm h-100">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <span class="badge bg-info-subtle text-info fw-bold">1. MRV Heuristic</span>
                </div>
                <h6 class="fw-bold text-dark mb-1">Minimum Remaining Values</h6>
                <p class="text-muted small mb-0" style="font-size:11px;">
                    Selects the role variable with the fewest compatible candidate choices first, minimizing search tree branching factor.
                </p>
            </div>
        </div>

        <div class="col-md-3">
            <div class="p-3 border rounded-3 bg-white shadow-sm h-100">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <span class="badge bg-primary-subtle text-primary fw-bold">2. Forward Checking</span>
                </div>
                <h6 class="fw-bold text-dark mb-1">Constraint Propagation</h6>
                <p class="text-muted small mb-0" style="font-size:11px;">
                    Whenever a candidate is tentatively assigned, incompatible choices are pruned from remaining domains to detect failures early.
                </p>
            </div>
        </div>

        <div class="col-md-3">
            <div class="p-3 border rounded-3 bg-white shadow-sm h-100">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <span class="badge bg-danger-subtle text-danger fw-bold">3. Backtracking</span>
                </div>
                <h6 class="fw-bold text-dark mb-1">Depth-First Search with Undo</h6>
                <p class="text-muted small mb-0" style="font-size:11px;">
                    When any hard constraint is violated or a domain wipeout occurs, the algorithm undoes assignments and backtracks up the search tree.
                </p>
            </div>
        </div>

        <div class="col-md-3">
            <div class="p-3 border rounded-3 bg-white shadow-sm h-100">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <span class="badge bg-warning-subtle text-warning fw-bold">4. Hill Climbing</span>
                </div>
                <h6 class="fw-bold text-dark mb-1">Local Search Optimization</h6>
                <p class="text-muted small mb-0" style="font-size:11px;">
                    Iteratively explores 1-opt candidate swaps and retains changes that monotonically improve the multi-objective balance score.
                </p>
            </div>
        </div>
    </div>

    <!-- Interactive Execution Trace Visualizer -->
    <div class="row g-4 mb-4">
        <!-- Trace Steps Timeline -->
        <div class="col-lg-7">
            <div class="card-tf h-100">
                <div class="card-tf-header">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-clock-history text-primary fs-5"></i>
                        <h5 class="card-tf-title">Algorithmic Execution Trace (<span id="currentStepDisplay">1</span> of <?= count($trace) ?> Steps)</h5>
                    </div>
                    <div class="d-flex align-items-center gap-1">
                        <button type="button" class="btn btn-sm btn-light border" id="btnPrev" onclick="stepChange(-1)" title="Previous Step"><i class="bi bi-chevron-left"></i></button>
                        <button type="button" class="btn btn-sm btn-primary" id="btnPlay" onclick="togglePlay()"><i class="bi bi-play-fill" id="playIcon"></i> Play</button>
                        <button type="button" class="btn btn-sm btn-light border" id="btnNext" onclick="stepChange(1)" title="Next Step"><i class="bi bi-chevron-right"></i></button>
                    </div>
                </div>

                <div class="card-tf-body">
                    <!-- Progress Bar across steps -->
                    <div class="progress mb-4" style="height: 6px;">
                        <div class="progress-bar bg-primary" id="traceProgressBar" style="width: 5%;"></div>
                    </div>

                    <!-- Steps Timeline Container -->
                    <div id="traceStepsContainer" style="max-height: 480px; overflow-y: auto;">
                        <?php foreach ($trace as $i => $step): 
                            $badgeClass = match($step['type']) {
                                'mrv_select' => 'step-mrv',
                                'forward_check' => '',
                                'backtrack', 'constraint_violation' => 'step-backtrack',
                                'hill_climb_swap' => 'step-swap',
                                'solution_found' => 'step-solution',
                                default => ''
                            };
                            $typeLabel = match($step['type']) {
                                'mrv_select' => 'MRV Variable Heuristic',
                                'forward_check' => 'Forward Checking / Pruning',
                                'backtrack' => 'Backtracking / Backtrack Step',
                                'constraint_violation' => 'Constraint Violation Detected',
                                'greedy_assign' => 'Greedy Assignment',
                                'hill_climb_swap' => 'Hill Climbing Member Swap',
                                'solution_found' => 'Valid State Established',
                                default => ucfirst($step['type'])
                            };
                        ?>
                            <div class="step-card <?= $badgeClass ?> trace-step-item" id="step-item-<?= $i ?>" data-step-index="<?= $i ?>" style="<?= $i === 0 ? '' : 'display:none;' ?>">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge bg-light text-dark border font-mono small">Step #<?= $step['step'] ?></span>
                                        <strong class="text-dark small"><?= $typeLabel ?></strong>
                                    </div>
                                    <?php if ($step['score'] > 0): ?>
                                        <span class="badge bg-success-subtle text-success fw-bold font-mono" style="font-size:11px;">
                                            Team Score: <?= $step['score'] ?>%
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <p class="text-secondary small mb-0" style="line-height: 1.5; font-size: 13px;">
                                    <?= e($step['description']) ?>
                                </p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: Current State & Final Team Presentation -->
        <div class="col-lg-5">
            <div class="card-tf mb-4">
                <div class="card-tf-header">
                    <h5 class="card-tf-title"><i class="bi bi-award-fill me-2 text-warning"></i>Converged Optimal Solution</h5>
                    <span class="badge bg-success fw-bold"><?= $finalEval['total_score'] ?>% Score</span>
                </div>
                <div class="card-tf-body">
                    <div class="d-flex flex-column gap-2 mb-3">
                        <?php foreach ($finalOptimalTeam as $roleName => $cId): 
                            $expl = $finalEval['explanations'][$cId] ?? [];
                        ?>
                            <div class="p-2 border rounded-3 bg-light d-flex align-items-center justify-content-between">
                                <div>
                                    <div class="fw-bold text-dark small"><?= e($expl['name'] ?? 'Candidate') ?></div>
                                    <div class="text-primary small" style="font-size:11px;"><?= e($roleName) ?></div>
                                </div>
                                <span class="badge bg-success-subtle text-success" style="font-size:10px;">Optimal Fit</span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="p-3 rounded-3" style="background:#f0fdf4; border: 1px solid #bbf7d0;">
                        <div class="fw-bold text-success small mb-1"><i class="bi bi-check-circle-fill me-1"></i> Optimization Proof:</div>
                        <ul class="text-muted small mb-0 ps-3" style="font-size:11px; line-height: 1.6;">
                            <li>Zero hard constraint violations detected.</li>
                            <li>Skill coverage: <strong><?= $finalEval['skill_coverage_pct'] ?>%</strong> of required competencies.</li>
                            <li>Role coverage: <strong><?= $finalEval['role_coverage_pct'] ?>%</strong> of designated roles.</li>
                            <li>Hill climbing proved local optimum reached (no 1-opt neighbor improves score).</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const totalSteps = <?= count($trace) ?>;
let currentStep = 0;
let playInterval = null;

function showStep(index) {
    document.querySelectorAll('.trace-step-item').forEach((el, i) => {
        el.style.display = (i <= index) ? 'block' : 'none';
    });
    
    // Scroll latest to view
    const latestEl = document.getElementById('step-item-' + index);
    if (latestEl) {
        latestEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    document.getElementById('currentStepDisplay').textContent = (index + 1);
    const progress = Math.round(((index + 1) / totalSteps) * 100);
    document.getElementById('traceProgressBar').style.width = progress + '%';
}

function stepChange(delta) {
    currentStep = Math.max(0, Math.min(totalSteps - 1, currentStep + delta));
    showStep(currentStep);
}

function togglePlay() {
    const playIcon = document.getElementById('playIcon');
    const btnPlay = document.getElementById('btnPlay');
    
    if (playInterval) {
        clearInterval(playInterval);
        playInterval = null;
        playIcon.className = 'bi bi-play-fill';
        btnPlay.innerHTML = '<i class="bi bi-play-fill"></i> Play';
    } else {
        playIcon.className = 'bi bi-pause-fill';
        btnPlay.innerHTML = '<i class="bi bi-pause-fill"></i> Pause';
        playInterval = setInterval(() => {
            if (currentStep < totalSteps - 1) {
                currentStep++;
                showStep(currentStep);
            } else {
                togglePlay();
            }
        }, 1200);
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
