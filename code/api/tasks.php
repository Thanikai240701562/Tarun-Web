<?php
/**
 * TeamForge Tasks Kanban API
 * Handles Kanban task board operations
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/mock_data.php';

$input = getJsonInput();
$action = $input['action'] ?? $_GET['action'] ?? 'list';
$user = currentUser();
$userId = $user['id'] ?? ($_SESSION['user_id'] ?? 2);

switch ($action) {
    case 'create':
        $teamId = (int)($input['team_id'] ?? 0);
        $title = sanitize($input['title'] ?? '');
        $description = sanitize($input['description'] ?? '');
        $priority = sanitize($input['priority'] ?? 'medium');
        $status = sanitize($input['status'] ?? 'todo');
        $dueDate = sanitize($input['due_date'] ?? date('Y-m-d', strtotime('+7 days')));
        $assigneeId = !empty($input['assignee_id']) ? (int)$input['assignee_id'] : null;

        if ($teamId <= 0 || empty($title)) {
            jsonError('Team ID and task title are required.');
        }

        if (isDbAvailable()) {
            $db = getDB();
            $stmt = $db->prepare("
                INSERT INTO project_tasks (team_id, title, description, priority, status, due_date, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$teamId, $title, $description, $priority, $status, $dueDate, $userId]);
            $taskId = (int)$db->lastInsertId();

            if ($assigneeId) {
                $asStmt = $db->prepare("INSERT INTO task_assignments (task_id, user_id) VALUES (?, ?)");
                $asStmt->execute([$taskId, $assigneeId]);
            }

            jsonResponse(['success' => true, 'task_id' => $taskId, 'message' => 'Task created successfully.']);
        } else {
            jsonResponse(['success' => true, 'task_id' => rand(100, 999), 'message' => 'Task created (Demo Mode).']);
        }
        break;

    case 'update_status':
        $taskId = (int)($input['task_id'] ?? 0);
        $status = sanitize($input['status'] ?? 'todo');

        if ($taskId <= 0 || !in_array($status, ['todo', 'in_progress', 'review', 'completed'])) {
            jsonError('Invalid task ID or status value.');
        }

        if (isDbAvailable()) {
            $db = getDB();
            $stmt = $db->prepare("UPDATE project_tasks SET status = ? WHERE id = ?");
            $stmt->execute([$status, $taskId]);
            jsonResponse(['success' => true, 'message' => 'Task status updated.']);
        } else {
            jsonResponse(['success' => true, 'message' => 'Task status updated (Demo Mode).']);
        }
        break;

    case 'list':
    default:
        $teamId = (int)($_GET['team_id'] ?? 1);

        if (isDbAvailable()) {
            $db = getDB();
            $stmt = $db->prepare("
                SELECT pt.*, u.full_name as creator_name,
                    (SELECT GROUP_CONCAT(u2.full_name SEPARATOR ', ') 
                     FROM task_assignments ta 
                     JOIN users u2 ON ta.user_id = u2.id 
                     WHERE ta.task_id = pt.id) as assignees
                FROM project_tasks pt
                JOIN users u ON pt.created_by = u.id
                WHERE pt.team_id = ?
                ORDER BY pt.created_at DESC
            ");
            $stmt->execute([$teamId]);
            $tasks = $stmt->fetchAll();
            jsonResponse(['success' => true, 'tasks' => $tasks]);
        } else {
            // Mock Kanban tasks
            $tasks = [
                ['id' => 1, 'team_id' => $teamId, 'title' => 'Formulate CSP Domain Constraints', 'description' => 'Draft the variable role slots and candidate domain pruning checks.', 'priority' => 'high', 'status' => 'completed', 'assignees' => 'Aarav Sharma', 'due_date' => '2026-10-15'],
                ['id' => 2, 'team_id' => $teamId, 'title' => 'Build Bootstrap Responsive Cards', 'description' => 'Design accessible team proposal and candidate detail modals.', 'priority' => 'medium', 'status' => 'in_progress', 'assignees' => 'Rohan Verma', 'due_date' => '2026-10-18'],
                ['id' => 3, 'team_id' => $teamId, 'title' => 'Optimize MySQL 3NF Foreign Keys', 'description' => 'Add composite indexes on candidate skill search tables.', 'priority' => 'high', 'status' => 'review', 'assignees' => 'Vikram Nair', 'due_date' => '2026-10-19'],
                ['id' => 4, 'team_id' => $teamId, 'title' => 'Ephemeral Chat Sliding Memory Buffer', 'description' => 'Verify zero persistent database writes on Apache restart.', 'priority' => 'low', 'status' => 'todo', 'assignees' => 'Aarav Sharma', 'due_date' => '2026-10-22']
            ];
            jsonResponse(['success' => true, 'is_mock' => true, 'tasks' => $tasks]);
        }
        break;
}
