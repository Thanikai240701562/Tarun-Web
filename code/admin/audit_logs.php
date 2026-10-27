<?php
/**
 * TeamForge Security & Administrative Audit Trail
 * Section 18, 20
 */
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole(ROLE_FACULTY_ADMIN);
$currentUser = currentUser();
$db = getDB();

$actionFilter = sanitize($_GET['action_filter'] ?? '');

$sql = "
    SELECT al.*, u.full_name, u.email, u.role
    FROM audit_logs al
    LEFT JOIN users u ON al.user_id = u.id
    WHERE 1=1
";
$params = [];

if (!empty($actionFilter)) {
    $sql .= " AND al.action LIKE ?";
    $params[] = "%{$actionFilter}%";
}

$sql .= " ORDER BY al.created_at DESC LIMIT 100";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

$pageTitle = "Audit Trail & Security Logs — " . APP_NAME;
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-0">
    <div class="mb-3">
        <a href="<?= BASE_URL ?>/admin/index.php" class="text-decoration-none text-muted small">
            &larr; Back to Admin Center
        </a>
    </div>

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <h2 class="fw-extrabold text-navy mb-1" style="font-weight: 800;">Audit Trail & Security Logs</h2>
            <p class="text-muted small mb-0">Immutable system activity logs for administrative oversight and access accountability.</p>
        </div>
        <form method="GET" action="<?= BASE_URL ?>/admin/audit_logs.php" class="d-flex gap-2">
            <input type="text" name="action_filter" class="form-control form-control-sm" placeholder="Filter action..." value="<?= e($actionFilter) ?>">
            <button type="submit" class="btn btn-sm btn-tf-primary">Filter</button>
            <a href="<?= BASE_URL ?>/admin/audit_logs.php" class="btn btn-sm btn-light border text-muted"><i class="bi bi-arrow-counterclockwise"></i></a>
        </form>
    </div>

    <div class="card-tf shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size:12px;">
                <thead class="table-light">
                    <tr>
                        <th>Timestamp</th>
                        <th>Actor / User</th>
                        <th>Action Performed</th>
                        <th>Entity</th>
                        <th>IP Address</th>
                        <th>Details (JSON Payload)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr><td colspan="6" class="text-center py-4 text-muted">No audit logs recorded yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td class="font-mono text-muted text-nowrap"><?= $log['created_at'] ?></td>
                                <td>
                                    <?php if ($log['user_id']): ?>
                                        <strong><?= e($log['full_name']) ?></strong>
                                        <div class="text-muted" style="font-size:10px;"><?= e($log['email']) ?></div>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary">System / Guest</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border font-mono fw-bold">
                                        <?= e($log['action']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?= e($log['entity_type']) ?> #<?= (int)$log['entity_id'] ?>
                                </td>
                                <td class="font-mono text-muted"><?= e($log['ip_address'] ?? '127.0.0.1') ?></td>
                                <td class="font-mono small text-muted text-truncate" style="max-width: 250px;">
                                    <?= e($log['details_json'] ?? '{}') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
