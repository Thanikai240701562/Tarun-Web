<?php
/**
 * TeamForge College Events Directory
 */
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

requireAuth();
$currentUser = currentUser();
$db = getDB();

// Filters
$typeFilter = sanitize($_GET['type'] ?? '');
$statusFilter = sanitize($_GET['status'] ?? '');
$searchQuery = sanitize($_GET['q'] ?? '');

$sql = "
    SELECT e.*, u.full_name as organizer_name,
           (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.id AND status != 'cancelled') as current_participants,
           (SELECT status FROM event_registrations WHERE event_id = e.id AND user_id = ?) as user_registration_status
    FROM events e
    JOIN users u ON e.organizer_id = u.id
    WHERE 1=1
";
$params = [$currentUser['id']];

if (!empty($typeFilter)) {
    $sql .= " AND e.event_type = ?";
    $params[] = $typeFilter;
}
if (!empty($statusFilter)) {
    $sql .= " AND e.status = ?";
    $params[] = $statusFilter;
}
if (!empty($searchQuery)) {
    $sql .= " AND (e.title LIKE ? OR e.description LIKE ? OR e.venue LIKE ?)";
    $like = "%{$searchQuery}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$sql .= " ORDER BY e.event_date ASC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$events = $stmt->fetchAll();

$pageTitle = "College Events & Hackathons — " . APP_NAME;
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-0">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <h2 class="fw-extrabold text-navy mb-1" style="font-weight: 800;">College Events & Hackathons</h2>
            <p class="text-muted small mb-0">Browse university competitions, register, and form teams with eligible participants.</p>
        </div>
        <?php if ($currentUser['role'] === ROLE_FACULTY_ADMIN): ?>
            <a href="<?= BASE_URL ?>/events/create.php" class="btn btn-tf-primary d-inline-flex align-items-center gap-2">
                <i class="bi bi-plus-circle-fill"></i>
                <span>Publish New Event</span>
            </a>
        <?php endif; ?>
    </div>

    <!-- Search & Filter Card -->
    <div class="card-tf p-3 mb-4 bg-white shadow-sm">
        <form method="GET" action="<?= BASE_URL ?>/events/index.php" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" class="form-control border-start-0" placeholder="Search event title, venue, or theme..." value="<?= e($searchQuery) ?>">
                </div>
            </div>
            <div class="col-md-3">
                <select name="type" class="form-select">
                    <option value="">All Event Types</option>
                    <option value="hackathon" <?= $typeFilter === 'hackathon' ? 'selected' : '' ?>>Hackathons</option>
                    <option value="coding_competition" <?= $typeFilter === 'coding_competition' ? 'selected' : '' ?>>Coding Competitions</option>
                    <option value="project_expo" <?= $typeFilter === 'project_expo' ? 'selected' : '' ?>>Project Expos</option>
                    <option value="symposium" <?= $typeFilter === 'symposium' ? 'selected' : '' ?>>Symposiums</option>
                    <option value="workshop" <?= $typeFilter === 'workshop' ? 'selected' : '' ?>>Workshops</option>
                    <option value="innovation_challenge" <?= $typeFilter === 'innovation_challenge' ? 'selected' : '' ?>>Innovation Challenges</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active Registration</option>
                    <option value="upcoming" <?= $statusFilter === 'upcoming' ? 'selected' : '' ?>>Upcoming</option>
                    <option value="registration_closed" <?= $statusFilter === 'registration_closed' ? 'selected' : '' ?>>Closed</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-tf-primary flex-grow-1">Filter</button>
                <a href="<?= BASE_URL ?>/events/index.php" class="btn btn-light border text-muted" title="Reset Filters"><i class="bi bi-arrow-counterclockwise"></i></a>
            </div>
        </form>
    </div>

    <!-- Events Grid -->
    <?php if (empty($events)): ?>
        <div class="card-tf p-5 text-center">
            <div class="empty-state">
                <div class="empty-state-icon"><i class="bi bi-calendar-x"></i></div>
                <div class="empty-state-title">No Events Found</div>
                <div class="empty-state-text">No college events match your current filter parameters. Try clearing the search query.</div>
                <a href="<?= BASE_URL ?>/events/index.php" class="btn btn-tf-outline btn-sm">Clear All Filters</a>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($events as $event): 
                $pct = min(100, round(($event['current_participants'] / max(1, $event['max_participants'])) * 100));
                $isRegOpen = ($event['status'] === 'active' && strtotime($event['reg_end_date']) >= strtotime(date('Y-m-d')));
            ?>
                <div class="col-lg-6 col-xl-4">
                    <div class="card-tf h-100 d-flex flex-column justify-content-between card-tf-interactive">
                        <div>
                            <div class="p-4 border-bottom" style="background: linear-gradient(135deg, #f8fafc 0%, #eef2ff 100%);">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <span class="badge bg-primary text-white text-uppercase" style="font-size:10px;">
                                        <?= e(str_replace('_', ' ', $event['event_type'])) ?>
                                    </span>
                                    <?php if ($event['user_registration_status']): ?>
                                        <span class="badge bg-success" style="font-size:11px;">
                                            <i class="bi bi-check-circle-fill me-1"></i> Enrolled
                                        </span>
                                    <?php elseif ($isRegOpen): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size:11px;">
                                            Open
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle" style="font-size:11px;">
                                            Closed
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <h5 class="fw-bold text-dark mb-1"><?= e($event['title']) ?></h5>
                                <div class="text-muted small">
                                    <i class="bi bi-geo-alt-fill text-danger me-1"></i> <?= e($event['venue']) ?>
                                </div>
                            </div>

                            <div class="p-4">
                                <p class="text-muted small mb-3" style="line-height:1.5; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;">
                                    <?= e($event['description']) ?>
                                </p>

                                <div class="d-flex flex-column gap-2 mb-3 text-muted small">
                                    <div class="d-flex justify-content-between">
                                        <span><i class="bi bi-calendar3 me-1 text-primary"></i> Event Date:</span>
                                        <strong class="text-dark"><?= formatDate($event['event_date']) ?></strong>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span><i class="bi bi-people me-1 text-primary"></i> Team Size Limit:</span>
                                        <strong class="text-dark"><?= $event['min_team_size'] ?> &ndash; <?= $event['max_team_size'] ?> members</strong>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span><i class="bi bi-clock me-1 text-primary"></i> Reg Deadline:</span>
                                        <strong class="<?= strtotime($event['reg_end_date']) < time() ? 'text-danger' : 'text-dark' ?>">
                                            <?= formatDate($event['reg_end_date']) ?>
                                        </strong>
                                    </div>
                                </div>

                                <!-- Capacity Progress Bar -->
                                <div class="mb-2">
                                    <div class="d-flex justify-content-between small text-muted mb-1" style="font-size:11px;">
                                        <span>Capacity: <?= (int)$event['current_participants'] ?> / <?= (int)$event['max_participants'] ?></span>
                                        <span><?= $pct ?>%</span>
                                    </div>
                                    <div class="progress" style="height: 6px;">
                                        <div class="progress-bar <?= $pct >= 90 ? 'bg-danger' : ($pct >= 70 ? 'bg-warning' : 'bg-primary') ?>" style="width: <?= $pct ?>%;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="p-3 bg-light border-top d-flex align-items-center justify-content-between">
                            <span class="text-muted small" style="font-size:11px;">
                                By: <?= e($event['organizer_name']) ?>
                            </span>
                            <a href="<?= BASE_URL ?>/events/view.php?id=<?= $event['id'] ?>" class="btn btn-sm btn-tf-primary">
                                Details & Registration &rarr;
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
