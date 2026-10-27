<?php
/**
 * TeamForge Registration
 */
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$error = null;
$fields = [
    'full_name' => '',
    'email' => '',
    'student_id_number' => '',
    'department' => 'Computer Science & Engineering',
    'degree' => 'B.Tech',
    'academic_year' => '3rd Year',
    'role' => 'student',
    'preferred_team_size' => 4,
    'bio' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Security session expired. Please refresh the page and try again.';
    } else {
        $fields['full_name'] = sanitize($_POST['full_name'] ?? '');
        $fields['email'] = sanitize($_POST['email'] ?? '');
        $fields['student_id_number'] = sanitize($_POST['student_id_number'] ?? '');
        $fields['department'] = sanitize($_POST['department'] ?? '');
        $fields['degree'] = sanitize($_POST['degree'] ?? '');
        $fields['academic_year'] = sanitize($_POST['academic_year'] ?? '');
        $fields['role'] = in_array($_POST['role'] ?? '', ['student', 'team_leader']) ? $_POST['role'] : 'student';
        $fields['preferred_team_size'] = (int)($_POST['preferred_team_size'] ?? 4);
        $fields['bio'] = sanitize($_POST['bio'] ?? '');

        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($fields['full_name']) || empty($fields['email']) || empty($password)) {
            $error = 'Please fill out all required fields.';
        } elseif (!filter_var($fields['email'], FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters long.';
        } elseif ($password !== $confirmPassword) {
            $error = 'Passwords do not match.';
        } else {
            $db = getDB();
            // Check if email is already taken
            $checkStmt = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $checkStmt->execute([$fields['email']]);
            if ($checkStmt->fetch()) {
                $error = 'An account with this email address already exists. Please sign in instead.';
            } else {
                try {
                    $passwordHash = password_hash($password, PASSWORD_BCRYPT);
                    $insertStmt = $db->prepare("
                        INSERT INTO users (
                            full_name, email, password_hash, role, student_id_number,
                            department, degree, academic_year, preferred_team_size, bio, availability_status
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'available')
                    ");
                    $insertStmt->execute([
                        $fields['full_name'],
                        $fields['email'],
                        $passwordHash,
                        $fields['role'],
                        $fields['student_id_number'],
                        $fields['department'],
                        $fields['degree'],
                        $fields['academic_year'],
                        $fields['preferred_team_size'],
                        $fields['bio']
                    ]);
                    $newUserId = (int)$db->lastInsertId();

                    // Send welcome notification
                    notifyUser($newUserId, 'Welcome to TeamForge!', 'Complete your profile and technical skills to be matched into optimal project teams.', 'profile.php', 'system');
                    logAudit($newUserId, 'USER_REGISTERED', 'user', $newUserId, ['role' => $fields['role']]);

                    // Log in immediately
                    $userStmt = $db->prepare("SELECT * FROM users WHERE id = ?");
                    $userStmt->execute([$newUserId]);
                    $newUser = $userStmt->fetch();
                    loginUser($newUser);

                    setFlash('success', 'Account created successfully! Welcome to TeamForge.');
                    header('Location: ' . BASE_URL . '/profile.php');
                    exit;
                } catch (Exception $e) {
                    $error = 'Registration failed due to a system error: ' . $e->getMessage();
                }
            }
        }
    }
}

$pageTitle = "Create an Account — " . APP_NAME;
include __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8 col-xl-7">
            <div class="text-center mb-4">
                <a href="<?= BASE_URL ?>/index.php" class="d-inline-flex align-items-center gap-2 text-decoration-none text-dark fs-3 fw-bold mb-2">
                    <div class="brand-icon">
                        <i class="bi bi-diagram-3-fill"></i>
                    </div>
                    <span>Team<span style="color:#4f46e5;">Forge</span></span>
                </a>
                <p class="text-muted small">Join the university student team formation network</p>
            </div>

            <div class="card-tf shadow-lg border p-4 p-md-5">
                <h4 class="fw-bold text-dark mb-1">Create Student Profile</h4>
                <p class="text-muted small mb-4">Fill in your academic details so our classical AI algorithms can match you with balanced teams.</p>

                <?php if ($error): ?>
                    <div class="alert alert-danger d-flex align-items-center p-3 mb-4 rounded-3" role="alert">
                        <i class="bi bi-exclamation-triangle-fill fs-5 me-2 flex-shrink-0"></i>
                        <div class="small fw-medium"><?= e($error) ?></div>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?= BASE_URL ?>/register.php">
                    <?= csrf_field() ?>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="full_name" class="form-label small fw-semibold text-secondary">Full Name *</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" value="<?= e($fields['full_name']) ?>" placeholder="e.g. Maya Chen" required>
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label small fw-semibold text-secondary">College Email *</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?= e($fields['email']) ?>" placeholder="mchen@student.university.edu" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label for="student_id_number" class="form-label small fw-semibold text-secondary">Student ID / Roll No.</label>
                            <input type="text" class="form-control" id="student_id_number" name="student_id_number" value="<?= e($fields['student_id_number']) ?>" placeholder="CS24-1088">
                        </div>
                        <div class="col-md-8">
                            <label for="department" class="form-label small fw-semibold text-secondary">Department</label>
                            <select class="form-select" id="department" name="department">
                                <option value="Computer Science & Engineering" <?= $fields['department'] === 'Computer Science & Engineering' ? 'selected' : '' ?>>Computer Science & Engineering</option>
                                <option value="Information Technology" <?= $fields['department'] === 'Information Technology' ? 'selected' : '' ?>>Information Technology</option>
                                <option value="Artificial Intelligence & Data Science" <?= $fields['department'] === 'Artificial Intelligence & Data Science' ? 'selected' : '' ?>>Artificial Intelligence & Data Science</option>
                                <option value="Electronics & Communication" <?= $fields['department'] === 'Electronics & Communication' ? 'selected' : '' ?>>Electronics & Communication</option>
                                <option value="Electrical & Electronics" <?= $fields['department'] === 'Electrical & Electronics' ? 'selected' : '' ?>>Electrical & Electronics</option>
                                <option value="Mechanical Engineering" <?= $fields['department'] === 'Mechanical Engineering' ? 'selected' : '' ?>>Mechanical Engineering</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label for="degree" class="form-label small fw-semibold text-secondary">Degree</label>
                            <input type="text" class="form-control" id="degree" name="degree" value="<?= e($fields['degree']) ?>" placeholder="B.Tech, B.E., M.Tech">
                        </div>
                        <div class="col-md-4">
                            <label for="academic_year" class="form-label small fw-semibold text-secondary">Academic Year</label>
                            <select class="form-select" id="academic_year" name="academic_year">
                                <option value="1st Year" <?= $fields['academic_year'] === '1st Year' ? 'selected' : '' ?>>1st Year</option>
                                <option value="2nd Year" <?= $fields['academic_year'] === '2nd Year' ? 'selected' : '' ?>>2nd Year</option>
                                <option value="3rd Year" <?= $fields['academic_year'] === '3rd Year' ? 'selected' : '' ?>>3rd Year</option>
                                <option value="4th Year" <?= $fields['academic_year'] === '4th Year' ? 'selected' : '' ?>>4th Year</option>
                                <option value="Postgraduate" <?= $fields['academic_year'] === 'Postgraduate' ? 'selected' : '' ?>>Postgraduate</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="role" class="form-label small fw-semibold text-secondary">Primary Role</label>
                            <select class="form-select" id="role" name="role">
                                <option value="student" <?= $fields['role'] === 'student' ? 'selected' : '' ?>>Student Member</option>
                                <option value="team_leader" <?= $fields['role'] === 'team_leader' ? 'selected' : '' ?>>Aspiring Team Leader</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="bio" class="form-label small fw-semibold text-secondary">Short Bio / Technical Interests</label>
                        <textarea class="form-control" id="bio" name="bio" rows="2" placeholder="Tell potential teammates about your interests, favorite tech, or what you want to build..."><?= e($fields['bio']) ?></textarea>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label for="password" class="form-label small fw-semibold text-secondary">Password (Min 8 characters) *</label>
                            <input type="password" class="form-control" id="password" name="password" required autocomplete="new-password">
                        </div>
                        <div class="col-md-6">
                            <label for="confirm_password" class="form-label small fw-semibold text-secondary">Confirm Password *</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required autocomplete="new-password">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-tf-primary w-100 py-2 fs-6 mb-3">
                        Complete Registration & Build Profile <i class="bi bi-arrow-right ms-1"></i>
                    </button>

                    <div class="text-center text-muted small">
                        Already have an account? <a href="<?= BASE_URL ?>/login.php" class="text-primary fw-semibold text-decoration-none">Sign in here</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
