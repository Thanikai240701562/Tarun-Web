<?php
/**
 * TeamForge Teams API
 * Handles team listing, details, creation, invitations response, and roster updates
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/mock_data.php';

$input = getJsonInput();
$action = $input['action'] ?? $_GET['action'] ?? 'list';
$user = currentUser();
$userId = $user['id'] ?? ($_SESSION['user_id'] ?? 2);

switch ($action) {
    case 'create':
        $name = sanitize($input['name'] ?? '');
        $projectTitle = sanitize($input['project_title'] ?? '');
        $description = sanitize($input['description'] ?? '');
        $eventId = !empty($input['event_id']) ? (int)$input['event_id'] : null;
        $minSize = (int)($input['min_size'] ?? 3);
        $maxSize = (int)($input['max_size'] ?? 4);
        $skillIds = is_array($input['skills'] ?? null) ? $input['skills'] : [];

        if (empty($name) || empty($projectTitle)) {
            jsonError('Team name and project title are required.');
        }

        if (isDbAvailable()) {
            $db = getDB();
            $stmt = $db->prepare("
                INSERT INTO teams (name, project_title, description, leader_id, event_id, min_size, max_size, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'forming')
            ");
            $stmt->execute([$name, $projectTitle, $description, $userId, $eventId, $minSize, $maxSize]);
            $teamId = (int)$db->lastInsertId();

            // Add leader to team_members
            $mStmt = $db->prepare("
                INSERT INTO team_members (team_id, user_id, assigned_role, status)
                VALUES (?, ?, 'Team Leader', 'active')
            ");
            $mStmt->execute([$teamId, $userId]);

            // Add team required skills
            if (!empty($skillIds)) {
                $skStmt = $db->prepare("INSERT INTO team_skills (team_id, skill_id, is_required) VALUES (?, ?, 1)");
                foreach ($skillIds as $sId) {
                    $skStmt->execute([$teamId, (int)$sId]);
                }
            }

            jsonResponse(['success' => true, 'team_id' => $teamId, 'message' => 'Team formed successfully!']);
        } else {
            jsonResponse(['success' => true, 'team_id' => 99, 'message' => 'Team formed successfully (Demo Mode)!']);
        }
        break;

    case 'respond_invite':
        $inviteId = (int)($input['invite_id'] ?? 0);
        $decision = sanitize($input['decision'] ?? ''); // 'accepted' or 'declined'

        if ($inviteId <= 0 || !in_array($decision, ['accepted', 'declined'])) {
            jsonError('Invalid invitation response parameters.');
        }

        if (isDbAvailable()) {
            $db = getDB();
            $invStmt = $db->prepare("SELECT * FROM team_invitations WHERE id = ? AND recipient_id = ? AND status = 'pending'");
            $invStmt->execute([$inviteId, $userId]);
            $inv = $invStmt->fetch();

            if (!$inv) jsonError('Invitation not found or already processed.');

            $upStmt = $db->prepare("UPDATE team_invitations SET status = ?, responded_at = NOW() WHERE id = ?");
            $upStmt->execute([$decision, $inviteId]);

            if ($decision === 'accepted') {
                $addStmt = $db->prepare("
                    INSERT INTO team_members (team_id, user_id, assigned_role, status)
                    VALUES (?, ?, ?, 'active')
                ");
                $addStmt->execute([$inv['team_id'], $userId, $inv['role_offered']]);
            }

            jsonResponse(['success' => true, 'message' => "Invitation has been $decision."]);
        } else {
            jsonResponse(['success' => true, 'message' => "Invitation has been $decision (Demo Mode)."]);
        }
        break;

    case 'view':
    case 'details':
        $teamId = (int)($_GET['id'] ?? $input['id'] ?? 0);
        if ($teamId <= 0) jsonError('Invalid team ID.');

        if (isDbAvailable()) {
            $db = getDB();
            $stmt = $db->prepare("
                SELECT t.*, u.full_name as leader_name, e.title as event_title
                FROM teams t
                JOIN users u ON t.leader_id = u.id
                LEFT JOIN events e ON t.event_id = e.id
                WHERE t.id = ?
            ");
            $stmt->execute([$teamId]);
            $team = $stmt->fetch();
            if (!$team) jsonError('Team not found.', 404);

            // Members
            $mStmt = $db->prepare("
                SELECT tm.*, u.full_name, u.email, u.department, u.avatar_url
                FROM team_members tm
                JOIN users u ON tm.user_id = u.id
                WHERE tm.team_id = ? AND tm.status = 'active'
            ");
            $mStmt->execute([$teamId]);
            $team['members'] = $mStmt->fetchAll();

            // Missing skills
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
            $skStmt->execute([$teamId, $teamId]);
            $team['missing_skills'] = $skStmt->fetchAll(PDO::FETCH_COLUMN);

            jsonResponse(['success' => true, 'team' => $team]);
        } else {
            $mockTeams = getMockTeams();
            $team = null;
            foreach ($mockTeams as $t) {
                if ($t['id'] === $teamId) {
                    $team = $t;
                    break;
                }
            }
            if (!$team) $team = $mockTeams[0];
            jsonResponse(['success' => true, 'is_mock' => true, 'team' => $team]);
        }
        break;

    case 'list':
    default:
        if (isDbAvailable()) {
            $db = getDB();
            $teams = getUserActiveTeams($userId);
            foreach ($teams as &$t) {
                $mStmt = $db->prepare("
                    SELECT tm.*, u.full_name, u.avatar_url, u.department
                    FROM team_members tm
                    JOIN users u ON tm.user_id = u.id
                    WHERE tm.team_id = ? AND tm.status = 'active'
                ");
                $mStmt->execute([$t['id']]);
                $t['members'] = $mStmt->fetchAll();
            }
            jsonResponse(['success' => true, 'teams' => $teams]);
        } else {
            jsonResponse(['success' => true, 'is_mock' => true, 'teams' => getMockTeams()]);
        }
        break;
}
