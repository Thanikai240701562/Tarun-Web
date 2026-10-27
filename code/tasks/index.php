<?php
/**
 * TeamForge Task Management & Automatic AI Task Allocation
 * Section 12: Classical algorithmic task matching based on skill proficiency and existing workload.
 */
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

requireAuth();
$currentUser = currentUser();
$db = getDB();

$teamId = (int)($_GET['team_id'] ?? 0);

// If no team_id specified, pick the user's first active team
if ($teamId <= 0) {
    $myTeams = getUserActiveTeams($currentUser['id']);
    if (!empty($myTeams)) {
        $teamId = (int)$myTeams[0]['id'];
    }
}

$team = null;
$members = [];
$tasks = [];

if ($teamId > 0) {
    $tStmt = $db->prepare("SELECT * FROM teams WHERE id = ?");
    $tStmt->execute([$teamId]);
    $team = $tStmt->fetch();

    if ($team) {
        // Fetch active members
        $mStmt = $db->prepare("
            SELECT tm.*, u.full_name, u.email, u.avatar_url,
                   (SELECT COUNT(*) FROM task_assignments ta JOIN project_tasks pt ON ta.task_id = pt.id WHERE ta.user_id = tm.user_id AND pt.team_id = ? AND pt.status != 'completed') as active_workload
            FROM team_members tm
            JOIN users u ON tm.user_id = u.id
            WHERE tm.team_id = ? AND tm.status = 'active'
        ");
        $mStmt->execute([$teamId, $teamId]);
        $members = $mStmt->fetchAll();

        // Attach skills to each member for AI task recommendation
        $memIds = array_column($members, 'user_id');
        if (!empty($memIds)) {
            $inIds = implode(',', array_map('intval', $memIds));
            $sQuery = $db->query("
                SELECT ss.user_id, s.name, ss.proficiency_level 
                FROM student_skills ss 
                JOIN skills s ON ss.skill_id = s.id 
                WHERE ss.user_id IN ({$inIds})
            ")->fetchAll();
            $memberSkills = [];
            foreach ($sQuery as $sq) {
                $memberSkills[$sq['user_id']][] = strtolower($sq['name']);
            }
            foreach ($members as &$m) {
                $m['skills_list'] = $memberSkills[$m['user_id']] ?? [];
            }
            unset($m);
        }

        // Fetch tasks
        $taskStmt = $db->prepare("
            SELECT pt.*, u.full_name as creator_name,
                   GROUP_CONCAT(CONCAT(assigned_u.id, ':', assigned_u.full_name) SEPARATOR '|') as assignees_data
            FROM project_tasks pt
            JOIN users u ON pt.created_by = u.id
            LEFT JOIN task_assignments ta ON pt.id = ta.task_id
            LEFT JOIN users assigned_u ON ta.user_id = assigned_u.id
            WHERE pt.team_id = ?
            GROUP BY pt.id
            ORDER BY FIELD(pt.status, 'not_started', 'in_progress', 'completed'), pt.deadline ASC
        ");
        $taskStmt->execute([$teamId]);
        $tasks = $taskStmt->fetchAll();
    }
}

$isLeader = ($team && (int)$team['leader_id'] === (int)$currentUser['id']);
$isMember = ($team && isUserMemberOf($currentUser['id'], $teamId));

// Handle Task Actions (Create, Update Status, Assign)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        setFlash('danger', 'Security validation failed.');
        header('Location: ' . BASE_URL . '/tasks/index.php?team_id=' . $teamId);
        exit;
    }

    $action = $_POST['action'] ?? '';

    // 1. Create Task
    if ($action === 'create_task' && ($isLeader || $isMember)) {
        $title = sanitize($_POST['title'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $category = sanitize($_POST['category'] ?? 'general');
        $priority = in_array($_POST['priority'] ?? '', ['low', 'medium', 'high', 'urgent']) ? $_POST['priority'] : 'medium';
        $deadline = !empty($_POST['deadline']) ? sanitize($_POST['deadline']) : null;
        $assigneeId = (int)($_POST['assignee_id'] ?? 0);

        if (!empty($title)) {
            $insTask = $db->prepare("
                INSERT INTO project_tasks (team_id, title, description, category, priority, deadline, created_by, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'not_started')
            ");
            $insTask->execute([$teamId, $title, $description, $category, $priority, $deadline, $currentUser['id']]);
            $newTaskId = (int)$db->lastInsertId();

            if ($assigneeId > 0) {
                $db->prepare("INSERT INTO task_assignments (task_id, user_id) VALUES (?, ?)")->execute([$newTaskId, $assigneeId]);
                notifyUser($assigneeId, 'New Task Assigned', 'You were assigned to "' . $title . '" in team ' . $team['name'] . '.', 'tasks/index.php?team_id=' . $teamId, 'task');
            }

            setFlash('success', 'Task added to board.');
        } else {
            setFlash('danger', 'Task title is required.');
        }
        header('Location: ' . BASE_URL . '/tasks/index.php?team_id=' . $teamId);
        exit;
    }

    // 2. Update Task Status
    if ($action === 'update_status' && $isMember) {
        $tId = (int)($_POST['task_id'] ?? 0);
        $newStatus = in_array($_POST['status'] ?? '', ['not_started', 'in_progress', 'completed']) ? $_POST['status'] : 'not_started';
        $db->prepare("UPDATE project_tasks SET status = ? WHERE id = ? AND team_id = ?")->execute([$newStatus, $tId, $teamId]);
        setFlash('info', 'Task status updated to ' . str_replace('_', ' ', $newStatus) . '.');
        header('Location: ' . BASE_URL . '/tasks/index.php?team_id=' . $teamId);
        exit;
    }

    // 3. Assign / Reassign Task
    if ($action === 'assign_task' && ($isLeader || $isMember)) {
        $tId = (int)($_POST['task_id'] ?? 0);
        $uId = (int)($_POST['user_id'] ?? 0);
        if ($tId > 0 && $uId > 0) {
            $db->prepare("DELETE FROM task_assignments WHERE task_id = ?")->execute([$tId]);
            $db->prepare("INSERT INTO task_assignments (task_id, user_id) VALUES (?, ?)")->execute([$tId, $uId]);
            notifyUser($uId, 'Task Assigned', 'You were assigned a project task.', 'tasks/index.php?team_id=' . $teamId, 'task');
            setFlash('success', 'Task assigned successfully.');
        }
        header('Location: ' . BASE_URL . '/tasks/index.php?team_id=' . $teamId);
        exit;
    }
}

/**
 * Classical AI Task Assignment Recommendation Heuristic (Section 12)
 * Scores each member: Score = SkillMatchScore * 10 - ExistingWorkload * 4
 */
function recommendTaskAssignee(string $category, array $members): ?array {
    if (empty($members)) return null;

    $catKeywords = match($category) {
        'frontend' => ['javascript', 'html & css', 'bootstrap 5', 'react.js', 'tailwind css'],
        'backend' => ['php', 'node.js', 'python', 'restful apis', 'mysql'],
        'database' => ['mysql', 'postgresql', 'mongodb'],
        'ui_ux' => ['ui/ux design & figma', 'html & css'],
        'testing' => ['software testing & qa', 'technical documentation'],
        'documentation' => ['technical documentation'],
        default => ['general']
    };

    $bestMember = null;
    $bestScore = -999;

    foreach ($members as $m) {
        $matchScore = 0;
        $skills = $m['skills_list'] ?? [];
        foreach ($catKeywords as $kw) {
            if (in_array($kw, $skills, true)) {
                $matchScore += 3;
            }
        }
        // Workload penalty to balance distribution
        $workloadPenalty = ((int)$m['active_workload']) * 2;
        $totalHeuristicScore = $matchScore - $workloadPenalty;

        if ($totalHeuristicScore > $bestScore) {
            $bestScore = $totalHeuristicScore;
            $bestMember = $m;
        }
    }
    return $bestMember;
}

$pageTitle = "Task Board & AI Allocation — " . APP_NAME;
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-0">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <h2 class="fw-extrabold text-navy mb-1" style="font-weight: 800;">Project Task Board</h2>
            <p class="text-muted small mb-0">Track project milestones, deadlines, and view classical AI task allocation recommendations.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <?php if ($team): ?>
                <button class="btn btn-tf-primary d-inline-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#addTaskModal">
                    <i class="bi bi-plus-lg"></i>
                    <span>Create Task</span>
                </button>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$team): ?>
        <div class="card-tf p-5 text-center">
            <div class="empty-state">
                <div class="empty-state-icon"><i class="bi bi-kanban"></i></div>
                <div class="empty-state-title">No Active Team Selected</div>
                <div class="empty-state-text">Join or create a project team first to manage sprint tasks and automatic allocations.</div>
                <a href="<?= BASE_URL ?>/teams/create.php" class="btn btn-tf-primary btn-sm">Create a Team</a>
            </div>
        </div>
    <?php else: ?>
        <!-- Team Selector & Workload Header -->
        <div class="card-tf p-3 mb-4 bg-white shadow-sm d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-3">
                <span class="p-2 bg-primary-subtle text-primary rounded-3"><i class="bi bi-folder-fill fs-5"></i></span>
                <div>
                    <h5 class="fw-bold text-dark mb-0"><?= e($team['name']) ?></h5>
                    <div class="text-muted small" style="font-size:11px;"><?= e($team['project_title']) ?> &bull; <?= count($members) ?> Members</div>
                </div>
            </div>

            <!-- Member Workload Badges -->
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <span class="small fw-semibold text-muted me-1">Workload:</span>
                <?php foreach ($members as $mem): ?>
                    <span class="badge bg-light text-dark border p-2 d-inline-flex align-items-center gap-1" style="font-size:11px;">
                        <?= getAvatar($mem, 18) ?>
                        <span><?= e(explode(' ', $mem['full_name'])[0]) ?>:</span>
                        <strong class="text-primary"><?= (int)$mem['active_workload'] ?> tasks</strong>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Kanban Columns Grid: Not Started, In Progress, Completed -->
        <div class="row g-4">
            <?php
            $statuses = [
                'not_started' => ['title' => 'To Do / Backlog', 'badge' => 'secondary', 'icon' => 'bi-circle'],
                'in_progress' => ['title' => 'In Progress', 'badge' => 'warning', 'icon' => 'bi-hourglass-split'],
                'completed'   => ['title' => 'Completed', 'badge' => 'success', 'icon' => 'bi-check2-circle']
            ];

            foreach ($statuses as $sKey => $sMeta):
                $filteredTasks = array_filter($tasks, fn($t) => $t['status'] === $sKey);
            ?>
                <div class="col-md-4">
                    <div class="card-tf h-100 bg-light border p-3">
                        <div class="d-flex align-items-center justify-content-between pb-2 mb-3 border-bottom">
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi <?= $sMeta['icon'] ?> text-<?= $sMeta['badge'] ?>"></i>
                                <h6 class="fw-bold text-dark mb-0"><?= $sMeta['title'] ?></h6>
                            </div>
                            <span class="badge bg-<?= $sMeta['badge'] ?> rounded-pill"><?= count($filteredTasks) ?></span>
                        </div>

                        <div class="d-flex flex-column gap-3" style="min-height: 250px;">
                            <?php if (empty($filteredTasks)): ?>
                                <div class="text-center py-4 text-muted small">
                                    No tasks in this lane.
                                </div>
                            <?php else: ?>
                                <?php foreach ($filteredTasks as $task): 
                                    $assignees = [];
                                    if (!empty($task['assignees_data'])) {
                                        foreach (explode('|', $task['assignees_data']) as $aItem) {
                                            $parts = explode(':', $aItem);
                                            if (count($parts) >= 2) $assignees[] = ['id' => $parts[0], 'name' => $parts[1]];
                                        }
                                    }
                                    $recMember = recommendTaskAssignee($task['category'], $members);
                                ?>
                                    <div class="card p-3 border rounded-3 bg-white shadow-sm">
                                        <div class="d-flex align-items-start justify-content-between mb-2">
                                            <span class="badge bg-light text-secondary border small text-uppercase" style="font-size:10px;">
                                                <?= e($task['category']) ?>
                                            </span>
                                            <span class="badge bg-<?= $task['priority'] === 'urgent' ? 'danger' : ($task['priority'] === 'high' ? 'warning' : 'info') ?>-subtle text-<?= $task['priority'] === 'urgent' ? 'danger' : ($task['priority'] === 'high' ? 'warning' : 'info') ?>" style="font-size:10px;">
                                                <?= ucfirst($task['priority']) ?>
                                            </span>
                                        </div>

                                        <h6 class="fw-bold text-dark mb-1" style="font-size: 14px;"><?= e($task['title']) ?></h6>
                                        <?php if (!empty($task['description'])): ?>
                                            <p class="text-muted small mb-2" style="font-size: 11px; line-height: 1.4;"><?= nl2br(e($task['description'])) ?></p>
                                        <?php endif; ?>

                                        <!-- Deadline -->
                                        <?php if ($task['deadline']): ?>
                                            <div class="text-muted small mb-2" style="font-size: 11px;">
                                                <i class="bi bi-clock me-1 text-primary"></i> Due: <?= formatDate($task['deadline']) ?>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Assignees / AI Recommendation -->
                                        <div class="p-2 rounded-2 bg-light border small mb-2" style="font-size: 11px;">
                                            <?php if (!empty($assignees)): ?>
                                                <div class="d-flex align-items-center gap-1">
                                                    <i class="bi bi-person-check-fill text-success"></i>
                                                    <strong>Assigned:</strong> <?= e($assignees[0]['name']) ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="text-muted">
                                                    <span class="text-warning"><i class="bi bi-cpu-fill me-1"></i> AI Recommendation:</span>
                                                    <strong><?= e($recMember ? $recMember['full_name'] : 'Any available member') ?></strong>
                                                    <?php if ($recMember && $isMember): ?>
                                                        <form method="POST" action="<?= BASE_URL ?>/tasks/index.php?team_id=<?= $teamId ?>" class="d-inline ms-1">
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="action" value="assign_task">
                                                            <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                                                            <input type="hidden" name="user_id" value="<?= $recMember['user_id'] ?>">
                                                            <button type="submit" class="btn btn-sm btn-link p-0 text-primary text-decoration-none" style="font-size:10px;">(Auto-Assign)</button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Status Mover Dropdown -->
                                        <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                                            <form method="POST" action="<?= BASE_URL ?>/tasks/index.php?team_id=<?= $teamId ?>" class="d-flex align-items-center gap-1">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                                                <select name="status" class="form-select form-select-sm" style="font-size: 11px; width: 130px;" onchange="this.form.submit()">
                                                    <option value="not_started" <?= $task['status'] === 'not_started' ? 'selected' : '' ?>>To Do</option>
                                                    <option value="in_progress" <?= $task['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                                                    <option value="completed" <?= $task['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                                                </select>
                                            </form>

                                            <?php if ($isLeader): ?>
                                                <!-- Reassign dropdown -->
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-link text-muted p-0 border-0" type="button" data-bs-toggle="dropdown">
                                                        <i class="bi bi-person-plus"></i>
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 p-2" style="font-size: 12px;">
                                                        <li class="dropdown-header small">Assign Member</li>
                                                        <?php foreach ($members as $m): ?>
                                                            <li>
                                                                <form method="POST" action="<?= BASE_URL ?>/tasks/index.php?team_id=<?= $teamId ?>">
                                                                    <?= csrf_field() ?>
                                                                    <input type="hidden" name="action" value="assign_task">
                                                                    <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                                                                    <input type="hidden" name="user_id" value="<?= $m['user_id'] ?>">
                                                                    <button type="submit" class="dropdown-item py-1 rounded-2">
                                                                        <?= e($m['full_name']) ?>
                                                                    </button>
                                                                </form>
                                                            </li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Modal: Create Task -->
<?php if ($team): ?>
<div class="modal fade" id="addTaskModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow-lg">
            <form method="POST" action="<?= BASE_URL ?>/tasks/index.php?team_id=<?= $teamId ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create_task">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold">Create Team Task</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Task Title *</label>
                        <input type="text" class="form-control" name="title" required placeholder="e.g. Implement MySQL Foreign Keys & Transactions">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Category</label>
                            <select class="form-select" name="category">
                                <option value="frontend">Frontend</option>
                                <option value="backend">Backend</option>
                                <option value="database">Database</option>
                                <option value="ui_ux">UI/UX Design</option>
                                <option value="testing">Testing / QA</option>
                                <option value="documentation">Documentation</option>
                                <option value="presentation">Presentation</option>
                                <option value="general">General</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Priority</label>
                            <select class="form-select" name="priority">
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Due Date</label>
                            <input type="date" class="form-control" name="deadline" min="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Assign Directly (Optional)</label>
                            <select class="form-select" name="assignee_id">
                                <option value="0">-- Let AI Recommend --</option>
                                <?php foreach ($members as $m): ?>
                                    <option value="<?= $m['user_id'] ?>"><?= e($m['full_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Description</label>
                        <textarea class="form-control" name="description" rows="2" placeholder="Brief technical details or acceptance criteria..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-tf-primary">Add Task</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
