<?php
/**
 * TeamForge Student Directory & Manual Team Builder
 */
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

requireAuth();
$currentUser = currentUser();
$db = getDB();

// Filters
$searchQuery = sanitize($_GET['q'] ?? '');
$deptFilter = sanitize($_GET['dept'] ?? '');
$yearFilter = sanitize($_GET['year'] ?? '');
$skillFilter = (int)($_GET['skill'] ?? 0);
$minProficiency = max(1, min(5, (int)($_GET['min_prof'] ?? 1)));
$availFilter = sanitize($_GET['avail'] ?? '');

// Active team of current user if they are leading one
$myLedTeamsStmt = $db->prepare("SELECT id, name, required_team_size, team_code FROM teams WHERE leader_id = ? AND status = 'recruiting'");
$myLedTeamsStmt->execute([$currentUser['id']]);
$myLedTeams = $myLedTeamsStmt->fetchAll();

// Build Query
$sql = "
    SELECT u.*,
           GROUP_CONCAT(CONCAT(s.id, ':', s.name, ':', ss.proficiency_level) SEPARATOR '|') as skills_data,
           (SELECT COUNT(*) FROM student_projects WHERE user_id = u.id) as project_count
    FROM users u
    LEFT JOIN student_skills ss ON u.id = ss.user_id
    LEFT JOIN skills s ON ss.skill_id = s.id
    WHERE u.id != ?
";
$params = [$currentUser['id']];

if (!empty($searchQuery)) {
    $sql .= " AND (u.full_name LIKE ? OR u.bio LIKE ? OR u.student_id_number LIKE ?)";
    $like = "%{$searchQuery}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
if (!empty($deptFilter)) {
    $sql .= " AND u.department = ?";
    $params[] = $deptFilter;
}
if (!empty($yearFilter)) {
    $sql .= " AND u.academic_year = ?";
    $params[] = $yearFilter;
}
if (!empty($availFilter)) {
    $sql .= " AND u.availability_status = ?";
    $params[] = $availFilter;
}

$sql .= " GROUP BY u.id";

// Skill filter post-aggregation or subquery
if ($skillFilter > 0) {
    $sql = "
        SELECT u.*,
               GROUP_CONCAT(CONCAT(s.id, ':', s.name, ':', ss.proficiency_level) SEPARATOR '|') as skills_data,
               (SELECT COUNT(*) FROM student_projects WHERE user_id = u.id) as project_count
        FROM users u
        INNER JOIN student_skills filter_ss ON u.id = filter_ss.user_id AND filter_ss.skill_id = ? AND filter_ss.proficiency_level >= ?
        LEFT JOIN student_skills ss ON u.id = ss.user_id
        LEFT JOIN skills s ON ss.skill_id = s.id
        WHERE u.id != ?
    ";
    $params = [$skillFilter, $minProficiency, $currentUser['id']];
    if (!empty($searchQuery)) {
        $sql .= " AND (u.full_name LIKE ? OR u.bio LIKE ?)";
        $params[] = "%{$searchQuery}%";
        $params[] = "%{$searchQuery}%";
    }
    if (!empty($deptFilter)) {
        $sql .= " AND u.department = ?";
        $params[] = $deptFilter;
    }
    if (!empty($yearFilter)) {
        $sql .= " AND u.academic_year = ?";
        $params[] = $yearFilter;
    }
    if (!empty($availFilter)) {
        $sql .= " AND u.availability_status = ?";
        $params[] = $availFilter;
    }
    $sql .= " GROUP BY u.id";
}

$sql .= " ORDER BY u.availability_status ASC, project_count DESC, u.full_name ASC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

// Fetch all skills for dropdown
$skillsCatalog = $db->query("SELECT id, name FROM skills ORDER BY name ASC")->fetchAll();

$pageTitle = "Student Directory & Manual Team Builder — " . APP_NAME;
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-0">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <h2 class="fw-extrabold text-navy mb-1" style="font-weight: 800;">Student Directory & Team Builder</h2>
            <p class="text-muted small mb-0">Search and filter peers across departments, inspect skill proficiencies, and build manual team rosters.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-white border text-dark shadow-sm p-2">
                <i class="bi bi-people-fill text-primary me-1"></i> <?= count($students) ?> Students Listed
            </span>
        </div>
    </div>

    <!-- Search & Multi-Filter Bar -->
    <div class="card-tf p-3 mb-4 bg-white shadow-sm">
        <form method="GET" action="<?= BASE_URL ?>/students/index.php" class="row g-2">
            <div class="col-md-3">
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" class="form-control border-start-0" placeholder="Search by name, roll no..." value="<?= e($searchQuery) ?>">
                </div>
            </div>
            <div class="col-md-3">
                <select name="dept" class="form-select">
                    <option value="">All Departments</option>
                    <option value="Computer Science & Engineering" <?= $deptFilter === 'Computer Science & Engineering' ? 'selected' : '' ?>>Computer Science</option>
                    <option value="Information Technology" <?= $deptFilter === 'Information Technology' ? 'selected' : '' ?>>Information Tech</option>
                    <option value="Artificial Intelligence & Data Science" <?= $deptFilter === 'Artificial Intelligence & Data Science' ? 'selected' : '' ?>>AI & Data Science</option>
                    <option value="Electronics & Communication" <?= $deptFilter === 'Electronics & Communication' ? 'selected' : '' ?>>Electronics (ECE)</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="skill" class="form-select">
                    <option value="0">Any Skill</option>
                    <?php foreach ($skillsCatalog as $sc): ?>
                        <option value="<?= $sc['id'] ?>" <?= $skillFilter === (int)$sc['id'] ? 'selected' : '' ?>><?= e($sc['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="avail" class="form-select">
                    <option value="">Any Availability</option>
                    <option value="available" <?= $availFilter === 'available' ? 'selected' : '' ?>>Available</option>
                    <option value="busy" <?= $availFilter === 'busy' ? 'selected' : '' ?>>Busy</option>
                    <option value="in_team" <?= $availFilter === 'in_team' ? 'selected' : '' ?>>Already In Team</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-tf-primary flex-grow-1">Search</button>
                <a href="<?= BASE_URL ?>/students/index.php" class="btn btn-light border text-muted" title="Reset Filters"><i class="bi bi-arrow-counterclockwise"></i></a>
            </div>
        </form>
    </div>

    <!-- Student Cards Grid -->
    <div class="row g-4 mb-5">
        <?php if (empty($students)): ?>
            <div class="col-12">
                <div class="card-tf p-5 text-center">
                    <div class="empty-state">
                        <div class="empty-state-icon"><i class="bi bi-person-x"></i></div>
                        <div class="empty-state-title">No Students Match Your Criteria</div>
                        <div class="empty-state-text">Try relaxing your department or skill filters to discover more candidates.</div>
                        <a href="<?= BASE_URL ?>/students/index.php" class="btn btn-tf-outline btn-sm">Reset Filters</a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($students as $stu): 
                $skillsArr = [];
                if (!empty($stu['skills_data'])) {
                    foreach (explode('|', $stu['skills_data']) as $sItem) {
                        $parts = explode(':', $sItem);
                        if (count($parts) >= 3) {
                            $skillsArr[] = ['id' => $parts[0], 'name' => $parts[1], 'prof' => (int)$parts[2]];
                        }
                    }
                }
            ?>
                <div class="col-md-6 col-xl-4">
                    <div class="card-tf h-100 p-4 d-flex flex-column justify-content-between card-tf-interactive">
                        <div>
                            <!-- Header with Avatar and Select Checkbox -->
                            <div class="d-flex align-items-start justify-content-between mb-3">
                                <div class="d-flex align-items-center gap-3">
                                    <?= getAvatar($stu, 48) ?>
                                    <div>
                                        <h6 class="fw-bold text-dark mb-0 d-flex align-items-center gap-1">
                                            <?= e($stu['full_name']) ?>
                                            <?php if ($stu['is_verified']): ?>
                                                <i class="bi bi-patch-check-fill text-success" title="Verified Profile" style="font-size:13px;"></i>
                                            <?php endif; ?>
                                        </h6>
                                        <div class="text-muted" style="font-size:11px;">
                                            <?= e($stu['degree']) ?> &bull; <?= e($stu['academic_year']) ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input candidate-select-chk" type="checkbox" 
                                           data-id="<?= $stu['id'] ?>" 
                                           data-name="<?= e($stu['full_name']) ?>"
                                           data-skills="<?= e(json_encode(array_column($skillsArr, 'name'))) ?>"
                                           title="Select student for manual team builder">
                                </div>
                            </div>

                            <div class="mb-2">
                                <span class="badge bg-light text-secondary border small mb-2"><?= e($stu['department']) ?></span>
                                <span class="status-pill status-<?= e($stu['availability_status']) ?>" style="font-size: 10px;">
                                    <?= ucfirst(str_replace('_', ' ', $stu['availability_status'])) ?>
                                </span>
                            </div>

                            <p class="text-muted small mb-3" style="font-size:12px; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                <?= !empty($stu['bio']) ? e($stu['bio']) : '<em>No bio available.</em>' ?>
                            </p>

                            <!-- Skills Tags -->
                            <div class="mb-3">
                                <div class="small fw-semibold text-muted mb-1" style="font-size:11px;">Top Skills & Proficiencies:</div>
                                <div class="d-flex flex-wrap gap-1">
                                    <?php if (empty($skillsArr)): ?>
                                        <span class="text-muted small" style="font-size:11px;">No skills added yet</span>
                                    <?php else: ?>
                                        <?php foreach (array_slice($skillsArr, 0, 4) as $sk): ?>
                                            <span class="badge-skill" style="font-size:11px;">
                                                <?= e($sk['name']) ?>
                                                <span class="text-warning" style="font-size:9px;">★<?= $sk['prof'] ?></span>
                                            </span>
                                        <?php endforeach; ?>
                                        <?php if (count($skillsArr) > 4): ?>
                                            <span class="badge bg-light text-muted border" style="font-size:10px;">+<?= count($skillsArr) - 4 ?> more</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Card Footer Links -->
                        <div class="pt-3 border-top d-flex align-items-center justify-content-between">
                            <span class="small text-muted" style="font-size:11px;">
                                <i class="bi bi-folder-check me-1"></i> <?= (int)$stu['project_count'] ?> Projects
                            </span>
                            <div class="d-flex gap-2">
                                <a href="<?= BASE_URL ?>/profile.php?id=<?= $stu['id'] ?>" class="btn btn-sm btn-light border text-primary" style="font-size:11px;">
                                    View Profile
                                </a>
                                <?php if (!empty($myLedTeams)): ?>
                                    <button type="button" class="btn btn-sm btn-tf-primary" style="font-size:11px;" onclick="openInviteModal(<?= $stu['id'] ?>, '<?= e($stu['full_name']) ?>')">
                                        <i class="bi bi-send me-1"></i> Invite
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Manual Team Builder Floating Drawer / Tray -->
<div id="teamBuilderTray" class="position-fixed bottom-0 start-50 translate-middle-x bg-white shadow-lg border rounded-top-4 p-3 d-none" style="width: 90%; max-width: 960px; z-index: 1050; border-bottom: none;">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="p-2 bg-primary-subtle text-primary rounded-3">
                <i class="bi bi-people-fill fs-5"></i>
            </div>
            <div>
                <h6 class="fw-bold text-dark mb-0">Manual Team Builder Tray (<span id="trayCount">0</span> Selected)</h6>
                <div class="text-muted small" style="font-size:11px;" id="traySkillsSummary">Combined Skills Covered: None</div>
            </div>
        </div>

        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-sm btn-light border" onclick="clearSelectedCandidates()">Clear Selection</button>
            <?php if (!empty($myLedTeams)): ?>
                <button type="button" class="btn btn-sm btn-tf-primary" onclick="inviteSelectedCandidates()">
                    <i class="bi bi-envelope-plus me-1"></i> Send Team Invites
                </button>
            <?php else: ?>
                <a href="<?= BASE_URL ?>/teams/create.php" class="btn btn-sm btn-tf-primary">
                    <i class="bi bi-plus-lg me-1"></i> Create Team With Candidates
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal: Send Team Invite -->
<div class="modal fade" id="sendInviteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow-lg">
            <form method="POST" action="<?= BASE_URL ?>/teams/send_invite.php">
                <?= csrf_field() ?>
                <input type="hidden" name="recipient_ids" id="inviteRecipientIds">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold">Send Team Invitation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Candidate(s):</label>
                        <div id="inviteRecipientNames" class="fw-bold text-dark small p-2 bg-light rounded-3"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Select Your Team *</label>
                        <select class="form-select" name="team_id" required>
                            <?php foreach ($myLedTeams as $mlt): ?>
                                <option value="<?= $mlt['id'] ?>"><?= e($mlt['name']) ?> (Code: <?= e($mlt['team_code']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Designated Role *</label>
                        <select class="form-select" name="invited_role">
                            <?php foreach (AVAILABLE_ROLES as $ar): ?>
                                <option value="<?= e($ar) ?>"><?= e($ar) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Custom Invitation Message (Optional)</label>
                        <textarea class="form-control" name="message" rows="2" placeholder="Hi! We saw your profile and your skill in PHP/Algorithms would be a great fit for our hackathon project..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-tf-primary">Send Official Invitation</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let selectedCandidates = [];

document.querySelectorAll('.candidate-select-chk').forEach(chk => {
    chk.addEventListener('change', () => {
        const id = chk.getAttribute('data-id');
        const name = chk.getAttribute('data-name');
        const skills = JSON.parse(chk.getAttribute('data-skills') || '[]');

        if (chk.checked) {
            selectedCandidates.push({ id, name, skills });
        } else {
            selectedCandidates = selectedCandidates.filter(c => c.id !== id);
        }
        updateTray();
    });
});

function updateTray() {
    const tray = document.getElementById('teamBuilderTray');
    const trayCount = document.getElementById('trayCount');
    const traySkills = document.getElementById('traySkillsSummary');

    trayCount.textContent = selectedCandidates.length;

    if (selectedCandidates.length > 0) {
        tray.classList.remove('d-none');
        // Aggregate skills
        const allSkills = new Set();
        selectedCandidates.forEach(c => c.skills.forEach(s => allSkills.add(s)));
        const skillList = Array.from(allSkills);
        traySkills.textContent = skillList.length > 0 ? "Combined Skills: " + skillList.slice(0, 6).join(', ') + (skillList.length > 6 ? ` (+${skillList.length - 6} more)` : '') : "No skills recorded";
    } else {
        tray.classList.add('d-none');
    }
}

function clearSelectedCandidates() {
    document.querySelectorAll('.candidate-select-chk').forEach(chk => chk.checked = false);
    selectedCandidates = [];
    updateTray();
}

function openInviteModal(id, name) {
    document.getElementById('inviteRecipientIds').value = id;
    document.getElementById('inviteRecipientNames').textContent = name;
    new bootstrap.Modal(document.getElementById('sendInviteModal')).show();
}

function inviteSelectedCandidates() {
    if (selectedCandidates.length === 0) return;
    const ids = selectedCandidates.map(c => c.id).join(',');
    const names = selectedCandidates.map(c => c.name).join(', ');
    document.getElementById('inviteRecipientIds').value = ids;
    document.getElementById('inviteRecipientNames').textContent = names;
    new bootstrap.Modal(document.getElementById('sendInviteModal')).show();
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
