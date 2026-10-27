<?php
/**
 * TeamForge 404 & Access Error Handler
 */
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';

$pageTitle = "Page Not Found — " . APP_NAME;
include __DIR__ . '/includes/header.php';
?>

<div class="container py-5 text-center">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card-tf p-5 shadow-lg border">
                <div class="empty-state py-3">
                    <div class="empty-state-icon" style="background:#fef2f2; color:#ef4444;"><i class="bi bi-exclamation-octagon"></i></div>
                    <h2 class="fw-bold text-dark mb-2">404 — Page Not Found</h2>
                    <p class="text-muted small mb-4">The page or resource you requested could not be located or you may not have sufficient access permissions.</p>
                    <div class="d-flex justify-content-center gap-2">
                        <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-tf-primary">Return to Dashboard</a>
                        <a href="<?= BASE_URL ?>/index.php" class="btn btn-light border">Home</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
