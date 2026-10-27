<?php
/**
 * TeamForge In-App Notification Center
 * Section 14
 */
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';

requireAuth();
$currentUser = currentUser();
$db = getDB();

// Handle Mark All as Read / Single Mark as Read
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        setFlash('danger', 'Security validation failed.');
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'mark_all_read') {
            $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")->execute([$currentUser['id']]);
            setFlash('success', 'All notifications marked as read.');
        } elseif ($action === 'mark_single_read') {
            $nId = (int)($_POST['notification_id'] ?? 0);
            $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?")->execute([$nId, $currentUser['id']]);
        }
    }
    header('Location: ' . BASE_URL . '/notifications.php');
    exit;
}

// Fetch all notifications for user
$notifStmt = $db->prepare("
    SELECT * FROM notifications 
    WHERE user_id = ? 
    ORDER BY is_read ASC, created_at DESC 
    LIMIT 50
");
$notifStmt->execute([$currentUser['id']]);
$notifications = $notifStmt->fetchAll();

$pageTitle = "Notifications — " . APP_NAME;
include __DIR__ . '/includes/header.php';
?>

<div class="container-fluid px-0">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <h2 class="fw-extrabold text-navy mb-1" style="font-weight: 800;">Notifications & Activity</h2>
            <p class="text-muted small mb-0">Stay up to date with invitations, join requests, tasks, and scheduled sessions.</p>
        </div>
        <?php if (!empty($notifications)): ?>
            <form method="POST" action="<?= BASE_URL ?>/notifications.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="mark_all_read">
                <button type="submit" class="btn btn-white border shadow-sm text-dark d-inline-flex align-items-center gap-2">
                    <i class="bi bi-check2-all text-primary"></i>
                    <span>Mark All as Read</span>
                </button>
            </form>
        <?php endif; ?>
    </div>

    <div class="card-tf shadow-sm">
        <div class="card-tf-header">
            <h5 class="card-tf-title"><i class="bi bi-bell-fill me-2 text-primary"></i>Recent In-App Activity</h5>
            <span class="badge bg-light text-dark border"><?= count($notifications) ?> Total</span>
        </div>
        <div class="card-tf-body p-0">
            <?php if (empty($notifications)): ?>
                <div class="empty-state py-5">
                    <div class="empty-state-icon"><i class="bi bi-bell-slash"></i></div>
                    <div class="empty-state-title">No Notifications Yet</div>
                    <div class="empty-state-text">You're all caught up! New team invitations, task assignments, and event updates will appear here.</div>
                </div>
            <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($notifications as $notif): 
                        $iconClass = match($notif['type']) {
                            'invitation' => 'bi-envelope-fill text-primary',
                            'join_request' => 'bi-person-plus-fill text-info',
                            'event' => 'bi-calendar-event-fill text-warning',
                            'team' => 'bi-diagram-3-fill text-success',
                            'task' => 'bi-kanban-fill text-purple',
                            'session' => 'bi-clock-fill text-danger',
                            default => 'bi-info-circle-fill text-secondary'
                        };
                    ?>
                        <div class="list-group-item p-3 d-flex align-items-center justify-content-between gap-3 <?= $notif['is_read'] ? 'bg-white' : 'bg-light' ?>">
                            <div class="d-flex align-items-start gap-3">
                                <span class="fs-4 flex-shrink-0"><i class="bi <?= $iconClass ?>"></i></span>
                                <div>
                                    <div class="d-flex align-items-center gap-2">
                                        <h6 class="fw-bold text-dark mb-0 <?= $notif['is_read'] ? 'opacity-75' : '' ?>"><?= e($notif['title']) ?></h6>
                                        <?php if (!$notif['is_read']): ?>
                                            <span class="badge bg-primary" style="font-size:9px;">New</span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-muted small mb-1" style="font-size:12px;"><?= e($notif['message']) ?></p>
                                    <div class="text-muted" style="font-size: 11px;"><?= timeAgo($notif['created_at']) ?></div>
                                </div>
                            </div>

                            <div class="d-flex align-items-center gap-2">
                                <?php if (!empty($notif['link'])): ?>
                                    <a href="<?= BASE_URL . '/' . e($notif['link']) ?>" class="btn btn-sm btn-tf-primary py-1 px-3" style="font-size:11px;">
                                        View &rarr;
                                    </a>
                                <?php endif; ?>
                                <?php if (!$notif['is_read']): ?>
                                    <form method="POST" action="<?= BASE_URL ?>/notifications.php">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="mark_single_read">
                                        <input type="hidden" name="notification_id" value="<?= $notif['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-light border py-1 px-2" title="Mark as read" style="font-size:11px;">
                                            <i class="bi bi-check"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
