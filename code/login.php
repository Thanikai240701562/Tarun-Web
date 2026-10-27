<?php
/**
 * TeamForge Login
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
$emailInput = '';

// Check quick fill param for testing/demo
$quick = $_GET['quick'] ?? '';
$demoPassword = '';
if ($quick === 'faculty') {
    $emailInput = 'faculty@university.edu';
    $demoPassword = 'Password123!';
} elseif ($quick === 'tl') {
    $emailInput = 'aarav.sharma@student.edu';
    $demoPassword = 'Password123!';
} elseif ($quick === 'student') {
    $emailInput = 'ananya.iyer@student.edu';
    $demoPassword = 'Password123!';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Security session expired. Please refresh the page and try again.';
    } else {
        $email = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $emailInput = $email;

        if (empty($email) || empty($password)) {
            $error = 'Please enter both your college email and password.';
        } else {
            $db = getDB();
            $stmt = $db->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                // Successful authentication
                loginUser($user);
                
                $redirectUrl = $_POST['redirect'] ?? $_GET['redirect'] ?? (BASE_URL . '/dashboard.php');
                // Ensure redirect is local to prevent open-redirect vulnerabilities
                if (!str_starts_with($redirectUrl, BASE_URL) && !str_starts_with($redirectUrl, '/')) {
                    $redirectUrl = BASE_URL . '/dashboard.php';
                }
                
                setFlash('success', 'Welcome back, ' . $user['full_name'] . '!');
                header('Location: ' . $redirectUrl);
                exit;
            } else {
                $error = 'Invalid college email or password.';
                logAudit(null, 'LOGIN_FAILED', 'user', null, ['email' => $email]);
            }
        }
    }
}

$pageTitle = "Sign In — " . APP_NAME;
include __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="text-center mb-4">
                <a href="<?= BASE_URL ?>/index.php" class="d-inline-flex align-items-center gap-2 text-decoration-none text-dark fs-3 fw-bold mb-2">
                    <div class="brand-icon">
                        <i class="bi bi-diagram-3-fill"></i>
                    </div>
                    <span>Team<span style="color:#4f46e5;">Forge</span></span>
                </a>
                <p class="text-muted small">Sign in to access your student dashboard & teams</p>
            </div>

            <!-- Demo Quick Login Helper -->
            <div class="card-tf p-3 mb-4 bg-light border-dashed">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="small fw-bold text-muted text-uppercase" style="font-size:0.7rem;">
                        <i class="bi bi-lightning-charge-fill text-warning me-1"></i> Quick Demo Logins
                    </span>
                    <span class="badge bg-secondary-subtle text-secondary" style="font-size:0.7rem;">Demo Data</span>
                </div>
                <div class="d-flex flex-column gap-1">
                    <button type="button" class="btn btn-sm btn-white border text-start d-flex justify-content-between align-items-center" onclick="fillCreds('faculty@university.edu', 'Password123!')">
                        <span class="small fw-semibold"><i class="bi bi-shield-check text-purple me-1"></i> Dr. Sarah (Faculty/Admin)</span>
                        <span class="text-muted small" style="font-size:0.7rem;">Auto-fill &rarr;</span>
                    </button>
                    <button type="button" class="btn btn-sm btn-white border text-start d-flex justify-content-between align-items-center" onclick="fillCreds('aarav.sharma@student.edu', 'Password123!')">
                        <span class="small fw-semibold"><i class="bi bi-award-fill text-primary me-1"></i> Aarav Sharma (Team Leader)</span>
                        <span class="text-muted small" style="font-size:0.7rem;">Auto-fill &rarr;</span>
                    </button>
                    <button type="button" class="btn btn-sm btn-white border text-start d-flex justify-content-between align-items-center" onclick="fillCreds('ananya.iyer@student.edu', 'Password123!')">
                        <span class="small fw-semibold"><i class="bi bi-person-fill text-info me-1"></i> anaya (Student/AI)</span>
                        <span class="text-muted small" style="font-size:0.7rem;">Auto-fill &rarr;</span>
                    </button>
                </div>
            </div>

            <!-- Login Card -->
            <div class="card-tf shadow-lg border p-4 p-md-5">
                <h4 class="fw-bold text-dark mb-4">Welcome Back</h4>

                <?php if ($error): ?>
                    <div class="alert alert-danger d-flex align-items-center p-3 mb-4 rounded-3" role="alert">
                        <i class="bi bi-exclamation-triangle-fill fs-5 me-2 flex-shrink-0"></i>
                        <div class="small fw-medium"><?= e($error) ?></div>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?= BASE_URL ?>/login.php" id="loginForm">
                    <?= csrf_field() ?>
                    <input type="hidden" name="redirect" value="<?= e($_GET['redirect'] ?? '') ?>">

                    <div class="mb-3">
                        <label for="email" class="form-label small fw-semibold text-secondary">College Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-envelope"></i></span>
                            <input type="email" class="form-control border-start-0" id="email" name="email" value="<?= e($emailInput) ?>" placeholder="student@university.edu" required autocomplete="email">
                        </div>
                    </div>

                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label for="password" class="form-label small fw-semibold text-secondary mb-0">Password</label>
                            <a href="#" class="small text-decoration-none text-muted" onclick="alert('For demo evaluation accounts, the password is Password123!')">Forgot password?</a>
                        </div>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-lock"></i></span>
                            <input type="password" class="form-control border-start-0" id="password" name="password" value="<?= e($demoPassword) ?>" placeholder="Enter password" required autocomplete="current-password">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-tf-primary w-100 py-2 fs-6 mb-3">
                        Sign In to TeamForge <i class="bi bi-arrow-right ms-1"></i>
                    </button>

                    <div class="text-center text-muted small">
                        Don't have an account yet? <a href="<?= BASE_URL ?>/register.php" class="text-primary fw-semibold text-decoration-none">Register here</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function fillCreds(email, pwd) {
    document.getElementById('email').value = email;
    document.getElementById('password').value = pwd;
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
