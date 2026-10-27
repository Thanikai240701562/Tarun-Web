<?php
/**
 * TeamForge Dashboard API
 * Provides metrics, active team details, events, and pending invitations
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/mock_data.php';

$user = currentUser();
if (!$user && isset($_SESSION['user_id'])) {
    $user = [
        'id' => $_SESSION['user_id'],
        'full_name' => $_SESSION['user_name'] ?? 'Demo User',
        'email' => $_SESSION['user_email'] ?? 'student@university.edu',
        'role' => $_SESSION['user_role'] ?? 'team_leader'
    ];
}

$userId = $user['id'] ?? 2; // Default to Aarav if unauthenticated for preview

if (isDbAvailable()) {
    $db = getDB();

    // 1. Teams count
    $myTeams = getUserActiveTeams($userId);
    $teamCount = count($myTeams);

    // 2. Events registered
    $evtStmt = $db->prepare("SELECT COUNT(*) FROM event_registrations WHERE user_id = ? AND status != 'cancelled'");
    $evtStmt->execute([$userId]);
    $eventsCount = (int)$evtStmt->fetchColumn();

    // 3. Pending invitations
    $invStmt = $db->prepare("SELECT COUNT(*) FROM team_invitations WHERE recipient_id = ? AND status = 'pending'");
    $invStmt->execute([$userId]);
    $invitesCount = (int)$invStmt->fetchColumn();

    // 4. Pending tasks
    $taskStmt = $db->prepare("
        SELECT COUNT(*) FROM task_assignments ta
        JOIN project_tasks pt ON ta.task_id = pt.id
        WHERE ta.user_id = ? AND pt.status != 'completed'
    ");
    $taskStmt->execute([$userId]);
    $tasksCount = (int)$taskStmt->fetchColumn();

    // 5. Active college events
    $eventsStmt = $db->query("
        SELECT e.*, 
            (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.id AND status != 'cancelled') as participant_count
        FROM events e WHERE e.status = 'active'
        ORDER BY e.event_date ASC LIMIT 3
    ");
    $activeEvents = $eventsStmt->fetchAll();

    // 6. Primary team
    $primaryTeam = !empty($myTeams) ? $myTeams[0] : null;
    $teamMembers = [];
    $missingSkills = [];
    if ($primaryTeam) {
        $mStmt = $db->prepare("
            SELECT tm.*, u.full_name, u.avatar_url, u.email, u.department
            FROM team_members tm
            JOIN users u ON tm.user_id = u.id
            WHERE tm.team_id = ? AND tm.status = 'active'
        ");
        $mStmt->execute([$primaryTeam['id']]);
        $teamMembers = $mStmt->fetchAll();

        $skStmt = $db->prepare("
            SELECT s.name 
            FROM team_skills ts
            JOIN skills s ON ts.skill_id = s.id
            WHERE ts.team_id = ? AND ts.is_required = 1
            AND ts.skill_id NOT IN (
                SELECT ss.skill_id 
                FROM team_members tm 
                JOIN student_skills ss ON tm.user_id = ss.user_id 
                WHERE tm.team_id = ? AND tm.status = 'active'
            )
        ");
        $skStmt->execute([$primaryTeam['id'], $primaryTeam['id']]);
        $missingSkills = $skStmt->fetchAll(PDO::FETCH_COLUMN);
    }

    // 7. Pending Invitations list
    $invListStmt = $db->prepare("
        SELECT ti.*, t.name as team_name, t.project_title, u.full_name as sender_name
        FROM team_invitations ti
        JOIN teams t ON ti.team_id = t.id
        JOIN users u ON ti.sender_id = u.id
        WHERE ti.recipient_id = ? AND ti.status = 'pending'
        ORDER BY ti.created_at DESC
    ");
    $invListStmt->execute([$userId]);
    $pendingInvitations = $invListStmt->fetchAll();

    jsonResponse([
        'success' => true,
        'metrics' => [
            'teams_count' => $teamCount,
            'events_count' => $eventsCount,
            'pending_invites_count' => $invitesCount,
            'pending_tasks_count' => $tasksCount
        ],
        'primary_team' => $primaryTeam ? array_merge($primaryTeam, [
            'members' => $teamMembers,
            'missing_skills' => $missingSkills
        ]) : null,
        'active_events' => $activeEvents,
        'pending_invitations' => $pendingInvitations
    ]);
} else {
    // Fallback Mock Data
    $mockTeams = getMockTeams();
    $mockEvents = getMockEvents();
    $primary = $mockTeams[0];

    jsonResponse([
        'success' => true,
        'is_mock' => true,
        'metrics' => [
            'teams_count' => count($mockTeams),
            'events_count' => 2,
            'pending_invites_count' => 1,
            'pending_tasks_count' => 3
        ],
        'primary_team' => $primary,
        'active_events' => $mockEvents,
        'pending_invitations' => [
            [
                'id' => 101,
                'team_id' => 2,
                'team_name' => 'AlgoKnights',
                'project_title' => 'Autonomous Route Planner using CSP',
                'sender_name' => 'Priya Patel',
                'role_offered' => 'Backend Optimization Lead',
                'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours'))
            ]
        ]
    ]);
}
