<?php
/**
 * TeamForge Scheduled Sessions API
 * Standups, study sessions, and faculty milestone reviews
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/mock_data.php';

$input = getJsonInput();
$action = $input['action'] ?? $_GET['action'] ?? 'list';
$user = currentUser();
$userId = $user['id'] ?? ($_SESSION['user_id'] ?? 2);

switch ($action) {
    case 'create':
        $teamId = (int)($input['team_id'] ?? 1);
        $title = sanitize($input['title'] ?? '');
        $description = sanitize($input['description'] ?? '');
        $startTime = sanitize($input['start_time'] ?? '');
        $duration = (int)($input['duration_minutes'] ?? 45);
        $meetingUrl = sanitize($input['meeting_url'] ?? 'https://meet.google.com/tf-standup-demo');

        if (empty($title) || empty($startTime)) {
            jsonError('Session title and start time are required.');
        }

        if (isDbAvailable()) {
            $db = getDB();
            $stmt = $db->prepare("
                INSERT INTO team_sessions (team_id, title, description, start_time, duration_minutes, meeting_url, created_by, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'scheduled')
            ");
            $stmt->execute([$teamId, $title, $description, $startTime, $duration, $meetingUrl, $userId]);
            jsonResponse(['success' => true, 'message' => 'Session scheduled successfully.']);
        } else {
            jsonResponse(['success' => true, 'message' => 'Session scheduled successfully (Demo Mode).']);
        }
        break;

    case 'list':
    default:
        if (isDbAvailable()) {
            $db = getDB();
            $stmt = $db->query("
                SELECT ts.*, t.name as team_name, u.full_name as creator_name
                FROM team_sessions ts
                JOIN teams t ON ts.team_id = t.id
                JOIN users u ON ts.created_by = u.id
                ORDER BY ts.start_time ASC
            ");
            $sessions = $stmt->fetchAll();
            jsonResponse(['success' => true, 'sessions' => $sessions]);
        } else {
            $sessions = [
                [
                    'id' => 1,
                    'team_id' => 1,
                    'team_name' => 'ByteCrafters',
                    'title' => 'Sprint 1 Standup & CSP Constraint Verification',
                    'description' => 'Review hard constraints, team size bounds, and MRV branch pruning.',
                    'start_time' => date('Y-m-d 14:00:00', strtotime('+1 day')),
                    'duration_minutes' => 45,
                    'meeting_url' => 'https://meet.google.com/tf-demo-sprint1',
                    'creator_name' => 'Aarav Sharma',
                    'status' => 'scheduled'
                ],
                [
                    'id' => 2,
                    'team_id' => 1,
                    'team_name' => 'ByteCrafters',
                    'title' => 'Faculty Mentor Checkpoint with Dr. Sarah',
                    'description' => 'Milestone 2 deliverable review and algorithm complexity benchmarking.',
                    'start_time' => date('Y-m-d 16:30:00', strtotime('+3 days')),
                    'duration_minutes' => 60,
                    'meeting_url' => 'https://meet.google.com/tf-demo-checkpoint',
                    'creator_name' => 'Dr. Sarah Mitchell',
                    'status' => 'scheduled'
                ]
            ];
            jsonResponse(['success' => true, 'is_mock' => true, 'sessions' => $sessions]);
        }
        break;
}
