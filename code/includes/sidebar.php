<?php
/**
 * TeamForge Dashboard Sidebar
 */
$currentUser = currentUser();
$currentScript = basename($_SERVER['PHP_SELF']);
$currentDir = basename(dirname($_SERVER['PHP_SELF']));

function isActiveLink(string $targetScript, ?string $targetDir = null): string {
    global $currentScript, $currentDir;
    if ($targetDir !== null && $currentDir === $targetDir && $currentScript === $targetScript) {
        return 'active';
    }
    if ($targetDir === null && $currentScript === $targetScript) {
        return 'active';
    }
    return '';
}
?>
<aside class="app-sidebar">
    <!-- Brand Header -->
    <div class="sidebar-header">
        <a href="<?= BASE_URL ?>/dashboard.php" class="sidebar-brand">
            <div class="brand-icon">
                <i class="bi bi-diagram-3-fill"></i>
            </div>
            <div>
                <span>Team<span style="color:#818cf8;">Forge</span></span>
            </div>
        </a>
    </div>

    <!-- Navigation Links -->
    <div class="sidebar-menu">
        <div class="menu-category">Overview</div>
        <a href="<?= BASE_URL ?>/dashboard.php" class="nav-link-custom <?= isActiveLink('dashboard.php') ?>">
            <i class="bi bi-grid-1x2-fill"></i>
            <span>Dashboard</span>
        </a>

        <div class="menu-category">Collaboration & Events</div>
        <a href="<?= BASE_URL ?>/students/index.php" class="nav-link-custom <?= isActiveLink('index.php', 'students') ?>">
            <i class="bi bi-people-fill"></i>
            <span>Student Directory</span>
        </a>
        <a href="<?= BASE_URL ?>/events/index.php" class="nav-link-custom <?= isActiveLink('index.php', 'events') ?>">
            <i class="bi bi-calendar-event-fill"></i>
            <span>College Events</span>
        </a>
        <a href="<?= BASE_URL ?>/teams/my_teams.php" class="nav-link-custom <?= isActiveLink('my_teams.php', 'teams') ?>">
            <i class="bi bi-folder-fill"></i>
            <span>My Teams & Projects</span>
        </a>
        <a href="<?= BASE_URL ?>/teams/create.php" class="nav-link-custom <?= isActiveLink('create.php', 'teams') ?>">
            <i class="bi bi-plus-circle-fill"></i>
            <span>Create Team</span>
        </a>

        <div class="menu-category">Classical AI Engine</div>
        <a href="<?= BASE_URL ?>/ai/generator.php" class="nav-link-custom <?= isActiveLink('generator.php', 'ai') ?>">
            <i class="bi bi-cpu-fill"></i>
            <span>AI Team Generator</span>
        </a>
        <a href="<?= BASE_URL ?>/ai/optimization_lab.php" class="nav-link-custom <?= isActiveLink('optimization_lab.php', 'ai') ?>">
            <i class="bi bi-terminal-fill"></i>
            <span>Optimization Lab</span>
        </a>
        <a href="<?= BASE_URL ?>/ai/simulator.php" class="nav-link-custom <?= isActiveLink('simulator.php', 'ai') ?>">
            <i class="bi bi-sliders2-vertical"></i>
            <span>What-If Simulator</span>
        </a>

        <div class="menu-category">Team Operations</div>
        <a href="<?= BASE_URL ?>/tasks/index.php" class="nav-link-custom <?= isActiveLink('index.php', 'tasks') ?>">
            <i class="bi bi-kanban-fill"></i>
            <span>Task Board</span>
        </a>
        <a href="<?= BASE_URL ?>/chat/index.php" class="nav-link-custom <?= isActiveLink('index.php', 'chat') ?>">
            <i class="bi bi-chat-dots-fill"></i>
            <span>Team Group Chat</span>
        </a>
        <a href="<?= BASE_URL ?>/sessions/index.php" class="nav-link-custom <?= isActiveLink('index.php', 'sessions') ?>">
            <i class="bi bi-clock-history"></i>
            <span>Scheduled Sessions</span>
        </a>

        <?php if ($currentUser && $currentUser['role'] === ROLE_FACULTY_ADMIN): ?>
        <div class="menu-category">Faculty & Admin</div>
        <a href="<?= BASE_URL ?>/admin/index.php" class="nav-link-custom <?= isActiveLink('index.php', 'admin') ?>">
            <i class="bi bi-shield-lock-fill"></i>
            <span>Admin Center</span>
        </a>
        <a href="<?= BASE_URL ?>/admin/audit_logs.php" class="nav-link-custom <?= isActiveLink('audit_logs.php', 'admin') ?>">
            <i class="bi bi-journal-text"></i>
            <span>Audit Trail</span>
        </a>
        <?php endif; ?>

        <div class="menu-category">Database</div>
        <a href="<?= BASE_URL ?>/database/view_tables.php" target="_blank" class="nav-link-custom <?= isActiveLink('view_tables.php', 'database') ?>">
            <i class="bi bi-database-fill"></i>
            <span>View DB Tables</span>
        </a>
    </div>

    <!-- Sidebar Bottom Profile Card -->
    <div class="sidebar-footer">
        <div class="d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2 overflow-hidden">
                <?= getAvatar($currentUser, 32) ?>
                <div class="text-truncate" style="line-height:1.2;">
                    <div class="text-white small fw-bold text-truncate"><?= e($currentUser['full_name']) ?></div>
                    <div class="text-secondary text-truncate" style="font-size:0.72rem;"><?= e($currentUser['email']) ?></div>
                </div>
            </div>
            <a href="<?= BASE_URL ?>/logout.php" class="text-secondary hover-white p-1" title="Sign Out">
                <i class="bi bi-box-arrow-right fs-5"></i>
            </a>
        </div>
    </div>
</aside>
