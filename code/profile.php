<?php
/**
 * TeamForge Student Profile (View & Edit)
 */
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';

requireAuth();
$currentUser = currentUser();
$db = getDB();

// Target profile ID (can view another student if passed, default to current user)
$viewId = isset($_GET['id']) ? (int)$_GET['id'] : $currentUser['id'];
$isOwnProfile = ($viewId === (int)$currentUser['id']);
$isFaculty = ($currentUser['role'] === ROLE_FACULTY_ADMIN);

// Fetch profile user
$stmt = $db->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$viewId]);
$profileUser = $stmt->fetch();

if (!$profileUser) {
    setFlash('danger', 'Student profile not found.');
    header('Location: ' . BASE_URL . '/students/index.php');
    exit;
}

// Handle Form Submissions (Profile Update, Add Skill, Remove Skill, Add Project, Verify Project)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        setFlash('danger', 'Security token mismatch. Please try again.');
        header('Location: ' . BASE_URL . '/profile.php?id=' . $viewId);
        exit;
    }

    $action = $_POST['action'] ?? '';

    // 1. Update basic info (Own profile only)
    if ($action === 'update_profile' && $isOwnProfile) {
        $fullName = sanitize($_POST['full_name'] ?? '');
        $department = sanitize($_POST['department'] ?? '');
        $degree = sanitize($_POST['degree'] ?? '');
        $academicYear = sanitize($_POST['academic_year'] ?? '');
        $availability = in_array($_POST['availability_status'] ?? '', ['available', 'busy', 'in_team']) ? $_POST['availability_status'] : 'available';
        $prefTeamSize = max(2, min(8, (int)($_POST['preferred_team_size'] ?? 4)));
        $bio = sanitize($_POST['bio'] ?? '');
        $portfolioUrl = filter_var($_POST['portfolio_url'] ?? '', FILTER_VALIDATE_URL) ? sanitize($_POST['portfolio_url']) : null;
        $githubUrl = filter_var($_POST['github_url'] ?? '', FILTER_VALIDATE_URL) ? sanitize($_POST['github_url']) : null;

        $updateStmt = $db->prepare("
            UPDATE users SET
                full_name = ?, department = ?, degree = ?, academic_year = ?,
                availability_status = ?, preferred_team_size = ?, bio = ?,
                portfolio_url = ?, github_url = ?
            WHERE id = ?
        ");
        $updateStmt->execute([
            $fullName, $department, $degree, $academicYear,
            $availability, $prefTeamSize, $bio,
            $portfolioUrl, $githubUrl, $currentUser['id']
        ]);
        
        logAudit($currentUser['id'], 'PROFILE_UPDATED', 'user', $currentUser['id']);
        setFlash('success', 'Profile details updated successfully.');
        header('Location: ' . BASE_URL . '/profile.php');
        exit;
    }

    // 2. Add or Update Skill (Own profile only)
    if ($action === 'add_skill' && $isOwnProfile) {
        $skillId = (int)($_POST['skill_id'] ?? 0);
        $customSkill = sanitize($_POST['custom_skill'] ?? '');
        $proficiency = max(1, min(5, (int)($_POST['proficiency_level'] ?? 3)));
        $yearsExp = (float)($_POST['years_experience'] ?? 1.0);

        if ($skillId === 0 && !empty($customSkill)) {
            // Check if skill exists in master table or insert it
            $chkSkill = $db->prepare("SELECT id FROM skills WHERE LOWER(name) = LOWER(?) LIMIT 1");
            $chkSkill->execute([$customSkill]);
            $existing = $chkSkill->fetch();
            if ($existing) {
                $skillId = (int)$existing['id'];
            } else {
                $insSkill = $db->prepare("INSERT INTO skills (name, category) VALUES (?, 'general')");
                $insSkill->execute([$customSkill]);
                $skillId = (int)$db->lastInsertId();
            }
        }

        if ($skillId > 0) {
            $skillStmt = $db->prepare("
                INSERT INTO student_skills (user_id, skill_id, proficiency_level, years_experience)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE proficiency_level = VALUES(proficiency_level), years_experience = VALUES(years_experience)
            ");
            $skillStmt->execute([$currentUser['id'], $skillId, $proficiency, $yearsExp]);
            setFlash('success', 'Skill added to your profile.');
        } else {
            setFlash('warning', 'Please select or type a valid skill.');
        }
        header('Location: ' . BASE_URL . '/profile.php');
        exit;
    }

    // 3. Remove Skill (Own profile only)
    if ($action === 'remove_skill' && $isOwnProfile) {
        $skillId = (int)($_POST['skill_id'] ?? 0);
        $delStmt = $db->prepare("DELETE FROM student_skills WHERE user_id = ? AND skill_id = ?");
        $delStmt->execute([$currentUser['id'], $skillId]);
        setFlash('info', 'Skill removed from profile.');
        header('Location: ' . BASE_URL . '/profile.php');
        exit;
    }

    // 4. Add Project (Own profile only)
    if ($action === 'add_project' && $isOwnProfile) {
        $title = sanitize($_POST['title'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $rolePlayed = sanitize($_POST['role_played'] ?? 'General Developer');
        $technologies = sanitize($_POST['technologies'] ?? '');
        $projectUrl = filter_var($_POST['project_url'] ?? '', FILTER_VALIDATE_URL) ? sanitize($_POST['project_url']) : null;

        if (!empty($title) && !empty($description)) {
            $projStmt = $db->prepare("
                INSERT INTO student_projects (user_id, title, description, role_played, technologies, project_url, is_verified)
                VALUES (?, ?, ?, ?, ?, ?, 0)
            ");
            $projStmt->execute([$currentUser['id'], $title, $description, $rolePlayed, $technologies, $projectUrl]);
            setFlash('success', 'Project added. Faculty can verify your record.');
        } else {
            setFlash('danger', 'Project title and description are required.');
        }
        header('Location: ' . BASE_URL . '/profile.php');
        exit;
    }

    // 5. Verify Project (Faculty/Admin only)
    if ($action === 'verify_project' && $isFaculty) {
        $projectId = (int)($_POST['project_id'] ?? 0);
        $vStmt = $db->prepare("UPDATE student_projects SET is_verified = 1, verified_by = ? WHERE id = ?");
        $vStmt->execute([$currentUser['id'], $projectId]);

        // Notify student
        $pOwner = $db->prepare("SELECT user_id, title FROM student_projects WHERE id = ?");
        $pOwner->execute([$projectId]);
        $pData = $pOwner->fetch();
        if ($pData) {
            notifyUser($pData['user_id'], 'Project Record Verified', 'Your project "' . $pData['title'] . '" was verified by faculty.', 'profile.php', 'system');
        }

        setFlash('success', 'Project record verified successfully.');
        header('Location: ' . BASE_URL . '/profile.php?id=' . $viewId);
        exit;
    }
}

// Fetch skills with details
$skillsStmt = $db->prepare("
    SELECT s.id, s.name, s.category, ss.proficiency_level, ss.years_experience
    FROM student_skills ss
    JOIN skills s ON ss.skill_id = s.id
    WHERE ss.user_id = ?
    ORDER BY ss.proficiency_level DESC, s.name ASC
");
$skillsStmt->execute([$viewId]);
$userSkills = $skillsStmt->fetchAll();

// Fetch all available skills for dropdown
$allSkillsStmt = $db->query("SELECT id, name, category FROM skills ORDER BY category, name ASC");
$allSkills = $allSkillsStmt->fetchAll();

// Fetch projects
$projectsStmt = $db->prepare("
    SELECT sp.*, u.full_name as verifier_name
    FROM student_projects sp
    LEFT JOIN users u ON sp.verified_by = u.id
    WHERE sp.user_id = ?
    ORDER BY sp.created_at DESC
");
$projectsStmt->execute([$viewId]);
$userProjects = $projectsStmt->fetchAll();

// Calculate Profile Completion Score
$completionScore = 20; // base for registration
if (!empty($profileUser['bio'])) $completionScore += 15;
if (!empty($profileUser['github_url']) || !empty($profileUser['portfolio_url'])) $completionScore += 15;
if (count($userSkills) >= 3) $completionScore += 25;
if (count($userProjects) >= 1) $completionScore += 25;

$pageTitle = e($profileUser['full_name']) . " — Student Profile";
include __DIR__ . '/includes/header.php';
?>

<div class="container-fluid px-0">
    <!-- Profile Header Banner -->
    <div class="card-tf shadow-sm mb-4">
        <div class="p-4" style="background: linear-gradient(135deg, #1e1b4b 0%, #312e81 100%); color: white; border-radius: 12px 12px 0 0;">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div class="d-flex align-items-center gap-3">
                    <?= getAvatar($profileUser, 76) ?>
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <h3 class="fw-bold mb-0 text-white"><?= e($profileUser['full_name']) ?></h3>
                            <?php if ($profileUser['is_verified']): ?>
                                <span class="badge bg-success" title="Verified College Student"><i class="bi bi-patch-check-fill me-1"></i>Verified</span>
                            <?php endif; ?>
                        </div>
                        <div class="text-light opacity-75 small">
                            <span><?= e($profileUser['student_id_number'] ?? 'Student') ?></span> &bull; 
                            <span><?= e($profileUser['degree']) ?> (<?= e($profileUser['academic_year']) ?>)</span> &bull; 
                            <span><?= e($profileUser['department']) ?></span>
                        </div>
                    </div>
                </div>

                <div class="d-flex align-items-center gap-2">
                    <span class="status-pill status-<?= e($profileUser['availability_status']) ?>">
                        <i class="bi bi-circle-fill" style="font-size: 8px;"></i>
                        <?= ucfirst(str_replace('_', ' ', $profileUser['availability_status'])) ?>
                    </span>
                    <?= getRoleBadge($profileUser['role']) ?>
                </div>
            </div>
        </div>

        <div class="p-3 bg-white d-flex flex-wrap align-items-center justify-content-between gap-3 border-top">
            <div class="d-flex align-items-center gap-4 text-muted small">
                <div><i class="bi bi-people me-1 text-primary"></i> Preferred Team Size: <strong class="text-dark"><?= (int)$profileUser['preferred_team_size'] ?> students</strong></div>
                <?php if (!empty($profileUser['github_url'])): ?>
                    <a href="<?= e($profileUser['github_url']) ?>" target="_blank" rel="noopener noreferrer" class="text-decoration-none text-dark fw-semibold">
                        <i class="bi bi-github me-1"></i> GitHub
                    </a>
                <?php endif; ?>
                <?php if (!empty($profileUser['portfolio_url'])): ?>
                    <a href="<?= e($profileUser['portfolio_url']) ?>" target="_blank" rel="noopener noreferrer" class="text-decoration-none text-dark fw-semibold">
                        <i class="bi bi-link-45deg me-1"></i> Portfolio
                    </a>
                <?php endif; ?>
            </div>

            <div class="d-flex align-items-center gap-2" style="min-width: 200px;">
                <div class="small fw-semibold text-muted">Profile Strength:</div>
                <div class="progress flex-grow-1" style="height: 8px;">
                    <div class="progress-bar <?= $completionScore >= 80 ? 'bg-success' : ($completionScore >= 50 ? 'bg-primary' : 'bg-warning') ?>" role="progressbar" style="width: <?= $completionScore ?>%"></div>
                </div>
                <span class="small fw-bold text-dark"><?= $completionScore ?>%</span>
            </div>
        </div>
    </div>

    <!-- Main Profile Content Grid -->
    <div class="row g-4">
        <!-- Left Column: Bio & Skills -->
        <div class="col-lg-5">
            <!-- Bio Card -->
            <div class="card-tf mb-4">
                <div class="card-tf-header">
                    <h5 class="card-tf-title"><i class="bi bi-person-lines-fill me-2 text-primary"></i>About & Focus</h5>
                    <?php if ($isOwnProfile): ?>
                        <button class="btn btn-sm btn-tf-outline" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                            <i class="bi bi-pencil me-1"></i> Edit Profile
                        </button>
                    <?php endif; ?>
                </div>
                <div class="card-tf-body">
                    <p class="text-muted mb-3" style="line-height: 1.6;">
                        <?= !empty($profileUser['bio']) ? nl2br(e($profileUser['bio'])) : '<em>No personal biography provided yet.</em>' ?>
                    </p>
                    <div class="border-top pt-3 text-muted small">
                        <div class="mb-1"><strong>Email:</strong> <?= e($profileUser['email']) ?></div>
                        <div class="mb-1"><strong>Department:</strong> <?= e($profileUser['department']) ?></div>
                        <div><strong>Enrolled Since:</strong> <?= formatDate($profileUser['created_at']) ?></div>
                    </div>
                </div>
            </div>

            <!-- Skills & Proficiencies Card -->
            <div class="card-tf mb-4">
                <div class="card-tf-header">
                    <h5 class="card-tf-title"><i class="bi bi-lightning-charge-fill me-2 text-warning"></i>Technical Skills</h5>
                    <?php if ($isOwnProfile): ?>
                        <button class="btn btn-sm btn-tf-primary" data-bs-toggle="modal" data-bs-target="#addSkillModal">
                            <i class="bi bi-plus me-1"></i> Add Skill
                        </button>
                    <?php endif; ?>
                </div>
                <div class="card-tf-body">
                    <?php if (empty($userSkills)): ?>
                        <div class="text-center py-4 text-muted small">
                            <i class="bi bi-tools fs-3 d-block mb-2 text-secondary opacity-50"></i>
                            No skills listed yet. Add technical skills to help the AI Team Generator balance projects.
                        </div>
                    <?php else: ?>
                        <div class="d-flex flex-column gap-3">
                            <?php foreach ($userSkills as $skill): ?>
                                <div class="d-flex align-items-center justify-content-between p-2 rounded-3 border bg-light">
                                    <div>
                                        <div class="fw-bold small text-dark d-flex align-items-center gap-2">
                                            <?= e($skill['name']) ?>
                                            <span class="badge bg-secondary-subtle text-secondary" style="font-size: 10px;"><?= e(ucfirst($skill['category'])) ?></span>
                                        </div>
                                        <div class="d-flex align-items-center gap-2 mt-1">
                                            <!-- 5 Star visual indicator -->
                                            <div class="text-warning small" style="font-size: 11px;">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="bi bi-star<?= $i <= $skill['proficiency_level'] ? '-fill' : '' ?>"></i>
                                                <?php endfor; ?>
                                            </div>
                                            <span class="text-muted" style="font-size: 11px;">Level <?= $skill['proficiency_level'] ?>/5 &bull; <?= $skill['years_experience'] ?> yrs exp</span>
                                        </div>
                                    </div>
                                    <?php if ($isOwnProfile): ?>
                                        <form method="POST" action="<?= BASE_URL ?>/profile.php" onsubmit="return confirm('Remove skill <?= e($skill['name']) ?>?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="remove_skill">
                                            <input type="hidden" name="skill_id" value="<?= $skill['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-link text-danger p-0 border-0" title="Remove Skill">
                                                <i class="bi bi-x-circle fs-6"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right Column: Completed Projects -->
        <div class="col-lg-7">
            <div class="card-tf">
                <div class="card-tf-header">
                    <h5 class="card-tf-title"><i class="bi bi-folder-check me-2 text-primary"></i>Completed Projects & Experience</h5>
                    <?php if ($isOwnProfile): ?>
                        <button class="btn btn-sm btn-tf-primary" data-bs-toggle="modal" data-bs-target="#addProjectModal">
                            <i class="bi bi-plus me-1"></i> Add Project
                        </button>
                    <?php endif; ?>
                </div>
                <div class="card-tf-body">
                    <?php if (empty($userProjects)): ?>
                        <div class="empty-state py-4">
                            <div class="empty-state-icon"><i class="bi bi-folder-x"></i></div>
                            <div class="empty-state-title">No Projects Showcased</div>
                            <div class="empty-state-text">Add your previous coursework, hackathon submissions, or personal open source work to demonstrate your capability to team leaders.</div>
                            <?php if ($isOwnProfile): ?>
                                <button class="btn btn-tf-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addProjectModal">
                                    <i class="bi bi-plus-lg me-1"></i> Add First Project
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="d-flex flex-column gap-3">
                            <?php foreach ($userProjects as $proj): ?>
                                <div class="card border p-3 rounded-3 bg-white">
                                    <div class="d-flex align-items-start justify-content-between mb-2">
                                        <div>
                                            <h6 class="fw-bold text-dark mb-1"><?= e($proj['title']) ?></h6>
                                            <div class="badge bg-primary-subtle text-primary border border-primary-subtle small mb-2">
                                                <i class="bi bi-person-workspace me-1"></i> Role: <?= e($proj['role_played']) ?>
                                            </div>
                                        </div>
                                        <div>
                                            <?php if ($proj['is_verified']): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle" title="Verified by <?= e($proj['verifier_name']) ?>">
                                                    <i class="bi bi-patch-check-fill me-1"></i> Verified Record
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle">
                                                    <i class="bi bi-hourglass-split me-1"></i> Unverified
                                                </span>
                                                <?php if ($isFaculty): ?>
                                                    <form method="POST" action="<?= BASE_URL ?>/profile.php?id=<?= $viewId ?>" class="d-inline ms-1">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="action" value="verify_project">
                                                        <input type="hidden" name="project_id" value="<?= $proj['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-success py-0 px-2" style="font-size:11px;">
                                                            <i class="bi bi-check2"></i> Verify Now
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <p class="text-muted small mb-2" style="line-height: 1.5;"><?= nl2br(e($proj['description'])) ?></p>

                                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 border-top pt-2">
                                        <div class="small text-secondary">
                                            <strong>Tech Stack:</strong> <span class="badge bg-light text-dark border"><?= e($proj['technologies']) ?></span>
                                        </div>
                                        <?php if (!empty($proj['project_url'])): ?>
                                            <a href="<?= e($proj['project_url']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-light border text-primary" style="font-size: 11px;">
                                                <i class="bi bi-box-arrow-up-right me-1"></i> View Repository / Demo
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($isOwnProfile): ?>
<!-- Modal: Edit Profile -->
<div class="modal fade" id="editProfileModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <form method="POST" action="<?= BASE_URL ?>/profile.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_profile">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold">Edit Profile Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Full Name *</label>
                            <input type="text" class="form-control" name="full_name" value="<?= e($profileUser['full_name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Department</label>
                            <select class="form-select" name="department">
                                <option value="Computer Science & Engineering" <?= $profileUser['department'] === 'Computer Science & Engineering' ? 'selected' : '' ?>>Computer Science & Engineering</option>
                                <option value="Information Technology" <?= $profileUser['department'] === 'Information Technology' ? 'selected' : '' ?>>Information Technology</option>
                                <option value="Artificial Intelligence & Data Science" <?= $profileUser['department'] === 'Artificial Intelligence & Data Science' ? 'selected' : '' ?>>Artificial Intelligence & Data Science</option>
                                <option value="Electronics & Communication" <?= $profileUser['department'] === 'Electronics & Communication' ? 'selected' : '' ?>>Electronics & Communication</option>
                                <option value="Mechanical Engineering" <?= $profileUser['department'] === 'Mechanical Engineering' ? 'selected' : '' ?>>Mechanical Engineering</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Degree</label>
                            <input type="text" class="form-control" name="degree" value="<?= e($profileUser['degree']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Academic Year</label>
                            <select class="form-select" name="academic_year">
                                <option value="1st Year" <?= $profileUser['academic_year'] === '1st Year' ? 'selected' : '' ?>>1st Year</option>
                                <option value="2nd Year" <?= $profileUser['academic_year'] === '2nd Year' ? 'selected' : '' ?>>2nd Year</option>
                                <option value="3rd Year" <?= $profileUser['academic_year'] === '3rd Year' ? 'selected' : '' ?>>3rd Year</option>
                                <option value="4th Year" <?= $profileUser['academic_year'] === '4th Year' ? 'selected' : '' ?>>4th Year</option>
                                <option value="Postgraduate" <?= $profileUser['academic_year'] === 'Postgraduate' ? 'selected' : '' ?>>Postgraduate</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Availability Status</label>
                            <select class="form-select" name="availability_status">
                                <option value="available" <?= $profileUser['availability_status'] === 'available' ? 'selected' : '' ?>>Available for teams</option>
                                <option value="busy" <?= $profileUser['availability_status'] === 'busy' ? 'selected' : '' ?>>Busy / Limited bandwidth</option>
                                <option value="in_team" <?= $profileUser['availability_status'] === 'in_team' ? 'selected' : '' ?>>Already In a Team</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Preferred Team Size</label>
                            <input type="number" class="form-control" name="preferred_team_size" min="2" max="8" value="<?= (int)$profileUser['preferred_team_size'] ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">GitHub URL</label>
                            <input type="url" class="form-control" name="github_url" value="<?= e($profileUser['github_url']) ?>" placeholder="https://github.com/username">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Portfolio Website</label>
                            <input type="url" class="form-control" name="portfolio_url" value="<?= e($profileUser['portfolio_url']) ?>" placeholder="https://mywebsite.com">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Introduction / Bio</label>
                        <textarea class="form-control" name="bio" rows="3"><?= e($profileUser['bio']) ?></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-tf-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Add Skill -->
<div class="modal fade" id="addSkillModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow-lg">
            <form method="POST" action="<?= BASE_URL ?>/profile.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_skill">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold">Add Technical Skill</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Choose Existing Skill</label>
                        <select class="form-select" name="skill_id" id="skillSelect">
                            <option value="0">-- Select from Catalog or Type Custom Below --</option>
                            <?php foreach ($allSkills as $s): ?>
                                <option value="<?= $s['id'] ?>"><?= e($s['name']) ?> (<?= e(ucfirst($s['category'])) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Or Add New Skill Name</label>
                        <input type="text" class="form-control" name="custom_skill" placeholder="e.g. GraphQL, Tailwind, Solidity">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Proficiency Level (1 to 5)</label>
                        <select class="form-select" name="proficiency_level">
                            <option value="1">1 — Novice / Beginner</option>
                            <option value="2">2 — Advanced Beginner</option>
                            <option value="3" selected>3 — Competent</option>
                            <option value="4">4 — Highly Proficient</option>
                            <option value="5">5 — Expert Specialist</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Years of Experience</label>
                        <input type="number" step="0.5" class="form-control" name="years_experience" value="1.0" min="0" max="10">
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-tf-primary">Save Skill</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Add Project -->
<div class="modal fade" id="addProjectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <form method="POST" action="<?= BASE_URL ?>/profile.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_project">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold">Add Completed Project</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-md-8">
                            <label class="form-label small fw-semibold">Project Title *</label>
                            <input type="text" class="form-control" name="title" required placeholder="e.g. Distributed Task Scheduler">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Role Played *</label>
                            <select class="form-select" name="role_played">
                                <?php foreach (AVAILABLE_ROLES as $roleOption): ?>
                                    <option value="<?= e($roleOption) ?>"><?= e($roleOption) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Technologies Used *</label>
                        <input type="text" class="form-control" name="technologies" required placeholder="e.g. PHP 8, MySQL, Bootstrap 5, Redis">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Project URL (GitHub / Demo)</label>
                        <input type="url" class="form-control" name="project_url" placeholder="https://github.com/myaccount/myproject">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Project Description & Your Contributions *</label>
                        <textarea class="form-control" name="description" rows="3" required placeholder="Describe what the project solved, architecture decisions, and your specific implementation responsibilities..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-tf-primary">Add Project Record</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
