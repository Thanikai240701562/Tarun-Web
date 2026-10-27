<?php
/**
 * TeamForge Dashboard Top Navbar
 */
$currentUser = currentUser();
$unreadCount = $currentUser ? getUnreadNotificationCount($currentUser['id']) : 0;
?>
<header class="app-topbar">
    <div class="d-flex align-items-center gap-3">
        <button type="button" class="btn btn-sm btn-light d-lg-none" id="sidebarToggleBtn" aria-label="Toggle Navigation">
            <i class="bi bi-list fs-5"></i>
        </button>
        <div class="topbar-search d-none d-md-block">
            <i class="bi bi-search search-icon"></i>
            <form action="<?= BASE_URL ?>/students/index.php" method="GET">
                <input type="text" name="q" class="form-control" placeholder="Search students, skills, projects...">
            </form>
        </div>
    </div>

    <div class="d-flex align-items-center gap-3">
        <!-- Quick Action: AI Generator -->
        <a href="<?= BASE_URL ?>/ai/generator.php" class="btn btn-sm btn-tf-primary d-none d-sm-inline-flex align-items-center gap-2">
            <i class="bi bi-cpu-fill"></i>
            <span>AI Team Generator</span>
        </a>

        <!-- Notifications Dropdown -->
        <div class="dropdown">
            <button class="btn btn-sm btn-light position-relative p-2 rounded-circle" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Notifications">
                <i class="bi bi-bell fs-5 text-secondary"></i>
                <?php if ($unreadCount > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:0.68rem;">
                        <?= $unreadCount ?>
                    </span>
                <?php endif; ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 p-2" style="width: 320px; border-radius: 12px;">
                <li class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom">
                    <span class="fw-bold small text-uppercase text-muted">Notifications</span>
                    <a href="<?= BASE_URL ?>/notifications.php" class="text-primary text-decoration-none small fw-semibold">View All</a>
                </li>
                <?php
                $db = getDB();
                $notifStmt = $db->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 4");
                $notifStmt->execute([$currentUser['id']]);
                $recentNotifs = $notifStmt->fetchAll();
                
                if (empty($recentNotifs)): ?>
                    <li class="text-center py-4 text-muted small">
                        <i class="bi bi-bell-slash fs-4 d-block mb-1 text-secondary opacity-50"></i>
                        No new notifications
                    </li>
                <?php else: ?>
                    <?php foreach ($recentNotifs as $notif): ?>
                        <li>
                            <a class="dropdown-item p-2 rounded-3 mb-1 <?= $notif['is_read'] ? 'opacity-75' : 'bg-light' ?>" href="<?= !empty($notif['link']) ? BASE_URL . '/' . e($notif['link']) : BASE_URL . '/notifications.php' ?>">
                                <div class="fw-semibold small text-truncate text-dark"><?= e($notif['title']) ?></div>
                                <div class="text-muted small text-truncate" style="font-size:0.8rem;"><?= e($notif['message']) ?></div>
                                <div class="text-muted text-end" style="font-size:0.7rem;"><?= timeAgo($notif['created_at']) ?></div>
                            </a>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>

        <!-- User Profile Dropdown -->
        <div class="dropdown">
            <button class="btn btn-sm p-1 d-flex align-items-center gap-2 border-0 bg-transparent" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <?= getAvatar($currentUser, 36) ?>
                <div class="text-start d-none d-md-block" style="line-height: 1.2;">
                    <div class="fw-bold small text-dark"><?= e($currentUser['full_name']) ?></div>
                    <div class="text-muted" style="font-size: 0.72rem;"><?= ucfirst(str_replace('_', ' ', $currentUser['role'])) ?></div>
                </div>
                <i class="bi bi-chevron-down text-muted small d-none d-md-inline"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 p-2" style="border-radius: 12px; min-width: 200px;">
                <li class="px-3 py-2 border-bottom mb-1">
                    <div class="fw-bold text-dark small"><?= e($currentUser['full_name']) ?></div>
                    <div class="text-muted small" style="font-size: 0.75rem;"><?= e($currentUser['email']) ?></div>
                    <div class="mt-1"><?= getRoleBadge($currentUser['role']) ?></div>
                </li>
                <li><a class="dropdown-item py-2 rounded-2" href="<?= BASE_URL ?>/profile.php"><i class="bi bi-person me-2 text-primary"></i>My Profile</a></li>
                <li><a class="dropdown-item py-2 rounded-2" href="<?= BASE_URL ?>/teams/my_teams.php"><i class="bi bi-people me-2 text-primary"></i>My Teams</a></li>
                <li><a class="dropdown-item py-2 rounded-2" href="<?= BASE_URL ?>/settings.php"><i class="bi bi-gear me-2 text-primary"></i>Settings & Security</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item py-2 rounded-2 text-danger" href="<?= BASE_URL ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Sign Out</a></li>
            </ul>
        </div>
    </div>
</header>
