<?php
/**
 * TeamForge Faculty & Admin API
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/mock_data.php';

$action = $_GET['action'] ?? 'stats';

switch ($action) {
    case 'audit_logs':
        if (isDbAvailable()) {
            $db = getDB();
            $logs = $db->query("
                SELECT al.*, u.full_name, u.email 
                FROM audit_logs al
                LEFT JOIN users u ON al.user_id = u.id
                ORDER BY al.created_at DESC LIMIT 50
            ")->fetchAll();
            jsonResponse(['success' => true, 'audit_logs' => $logs]);
        } else {
            $logs = [
                ['id' => 1, 'action' => 'LOGIN_SUCCESS', 'full_name' => 'Dr. Sarah Mitchell', 'email' => 'faculty@university.edu', 'created_at' => date('Y-m-d H:i:s', strtotime('-15 mins'))],
                ['id' => 2, 'action' => 'AI_TEAM_GENERATED', 'full_name' => 'Aarav Sharma', 'email' => 'aarav.sharma@student.edu', 'created_at' => date('Y-m-d H:i:s', strtotime('-45 mins'))],
                ['id' => 3, 'action' => 'TEAM_CREATED', 'full_name' => 'Priya Patel', 'email' => 'priya.patel@student.edu', 'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours'))],
                ['id' => 4, 'action' => 'EVENT_PUBLISHED', 'full_name' => 'Dr. Sarah Mitchell', 'email' => 'faculty@university.edu', 'created_at' => date('Y-m-d H:i:s', strtotime('-1 day'))]
            ];
            jsonResponse(['success' => true, 'is_mock' => true, 'audit_logs' => $logs]);
        }
        break;

    case 'stats':
    default:
        if (isDbAvailable()) {
            $db = getDB();
            $studentsCount = (int)$db->query("SELECT COUNT(*) FROM users WHERE role != 'faculty_admin'")->fetchColumn();
            $teamsCount = (int)$db->query("SELECT COUNT(*) FROM teams")->fetchColumn();
            $eventsCount = (int)$db->query("SELECT COUNT(*) FROM events")->fetchColumn();
            $aiRunsCount = (int)$db->query("SELECT COUNT(*) FROM optimization_runs")->fetchColumn();

            jsonResponse([
                'success' => true,
                'stats' => [
                    'students_count' => $studentsCount,
                    'teams_count' => $teamsCount,
                    'events_count' => $eventsCount,
                    'ai_runs_count' => $aiRunsCount
                ]
            ]);
        } else {
            jsonResponse([
                'success' => true,
                'is_mock' => true,
                'stats' => [
                    'students_count' => 128,
                    'teams_count' => 34,
                    'events_count' => 3,
                    'ai_runs_count' => 89
                ]
            ]);
        }
        break;
}
