<?php
/**
 * TeamForge User Settings & Security Center
 * Section 3 & 21
 */
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';

requireAuth();
$currentUser = currentUser();
$db = getDB();

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Security session expired. Please refresh and try again.';
    } else {
        $action = $_POST['action'] ?? '';

        // 1. Change Password
        if ($action === 'change_password') {
            $currentPwd = $_POST['current_password'] ?? '';
            $newPwd = $_POST['new_password'] ?? '';
            $confirmPwd = $_POST['confirm_password'] ?? '';

            if (empty($currentPwd) || empty($newPwd)) {
                $error = 'Please fill out all password fields.';
            } elseif (!password_verify($currentPwd, $currentUser['password_hash'])) {
                $error = 'Incorrect current password.';
            } elseif (strlen($newPwd) < 8) {
                $error = 'New password must be at least 8 characters long.';
            } elseif ($newPwd !== $confirmPwd) {
                $error = 'New passwords do not match.';
            } else {
                $newHash = password_hash($newPwd, PASSWORD_BCRYPT);
                $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$newHash, $currentUser['id']]);
                logAudit($currentUser['id'], 'PASSWORD_CHANGED', 'user', $currentUser['id']);
                setFlash('success', 'Your password was updated successfully.');
                header('Location: ' . BASE_URL . '/settings.php');
                exit;
            }
        }

        // 2. Update Availability & Preferences
        if ($action === 'update_preferences') {
            $avail = in_array($_POST['availability_status'] ?? '', ['available', 'busy', 'in_team']) ? $_POST['availability_status'] : 'available';
            $teamSize = max(2, min(8, (int)($_POST['preferred_team_size'] ?? 4)));

            $db->prepare("UPDATE users SET availability_status = ?, preferred_team_size = ? WHERE id = ?")->execute([$avail, $teamSize, $currentUser['id']]);
            logAudit($currentUser['id'], 'PREFERENCES_UPDATED', 'user', $currentUser['id']);
            setFlash('success', 'Preferences updated successfully.');
            header('Location: ' . BASE_URL . '/settings.php');
            exit;
        }
    }
}

$pageTitle = "Settings & Security — " . APP_NAME;
include __DIR__ . '/includes/header.php';
?>

<div class="container-fluid px-0">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <h2 class="fw-extrabold text-navy mb-1" style="font-weight: 800;">Account Settings & Security</h2>
            <p class="text-muted small mb-0">Manage your credentials, availability status, and privacy preferences.</p>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger d-flex align-items-center p-3 mb-4 rounded-3" role="alert">
            <i class="bi bi-exclamation-triangle-fill fs-5 me-2 flex-shrink-0"></i>
            <div class="small fw-medium"><?= e($error) ?></div>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Left: Password & Security -->
        <div class="col-lg-6">
            <div class="card-tf p-4 shadow-sm mb-4">
                <h5 class="fw-bold text-dark mb-3"><i class="bi bi-shield-lock-fill me-2 text-primary"></i>Change Password</h5>

                <form method="POST" action="<?= BASE_URL ?>/settings.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="change_password">

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Current Password *</label>
                        <input type="password" class="form-control" name="current_password" required autocomplete="current-password">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">New Password (Min 8 Characters) *</label>
                        <input type="password" class="form-control" name="new_password" required autocomplete="new-password">
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-semibold">Confirm New Password *</label>
                        <input type="password" class="form-control" name="confirm_password" required autocomplete="new-password">
                    </div>

                    <button type="submit" class="btn btn-tf-primary px-4">Update Password</button>
                </form>
            </div>
        </div>

        <!-- Right: Team Preferences & Privacy -->
        <div class="col-lg-6">
            <div class="card-tf p-4 shadow-sm mb-4">
                <h5 class="fw-bold text-dark mb-3"><i class="bi bi-sliders me-2 text-primary"></i>Team Formation Preferences</h5>

                <form method="POST" action="<?= BASE_URL ?>/settings.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update_preferences">

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Availability Status</label>
                        <select class="form-select" name="availability_status">
                            <option value="available" <?= $currentUser['availability_status'] === 'available' ? 'selected' : '' ?>>Available (Open to invitations & AI matching)</option>
                            <option value="busy" <?= $currentUser['availability_status'] === 'busy' ? 'selected' : '' ?>>Busy (Limited bandwidth / Exams)</option>
                            <option value="in_team" <?= $currentUser['availability_status'] === 'in_team' ? 'selected' : '' ?>>In Team (Already committed)</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-semibold">Preferred Team Size</label>
                        <input type="number" class="form-control" name="preferred_team_size" value="<?= (int)$currentUser['preferred_team_size'] ?>" min="2" max="8">
                        <div class="form-text small">Helps the AI algorithm align you with similar sized squads.</div>
                    </div>

                    <button type="submit" class="btn btn-tf-primary px-4">Save Preferences</button>
                </form>
            </div>

            <!-- Privacy Policy Notice Card -->
            <div class="card-tf p-4 shadow-sm bg-light border">
                <h6 class="fw-bold text-dark mb-2"><i class="bi bi-shield-check text-success me-1"></i> Privacy & Data Policy</h6>
                <p class="text-muted small mb-0" style="line-height: 1.5; font-size:12px;">
                    TeamForge is engineered for student privacy. Ephemeral chat messages are relayed in memory and are strictly never stored permanently in the database. Personal student identifiers are shielded from unauthorized users.
                </p>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
