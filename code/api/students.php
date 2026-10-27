<?php
/**
 * TeamForge Student Directory API
 * Searching, filtering, and sending team invitations
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/mock_data.php';

$input = getJsonInput();
$action = $input['action'] ?? $_GET['action'] ?? 'list';

switch ($action) {
    case 'invite':
        $user = currentUser();
        $senderId = $user['id'] ?? ($_SESSION['user_id'] ?? 2);
        $teamId = (int)($input['team_id'] ?? 0);
        $recipientId = (int)($input['recipient_id'] ?? 0);
        $roleOffered = sanitize($input['role_offered'] ?? 'Developer');
        $message = sanitize($input['message'] ?? 'Would love to collaborate in our team!');

        if ($teamId <= 0 || $recipientId <= 0) {
            jsonError('Invalid team or recipient ID.');
        }

        if (isDbAvailable()) {
            $db = getDB();
            // Check if already invited or member
            $checkStmt = $db->prepare("SELECT id FROM team_invitations WHERE team_id = ? AND recipient_id = ? AND status = 'pending'");
            $checkStmt->execute([$teamId, $recipientId]);
            if ($checkStmt->fetch()) {
                jsonError('An active invitation has already been sent to this student.');
            }

            $stmt = $db->prepare("
                INSERT INTO team_invitations (team_id, sender_id, recipient_id, role_offered, message, status)
                VALUES (?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->execute([$teamId, $senderId, $recipientId, $roleOffered, $message]);

            // Add notification
            $notifStmt = $db->prepare("
                INSERT INTO notifications (user_id, title, message, link)
                VALUES (?, 'Team Invitation Received', 'You received an invitation to join a team.', 'teams/my_teams.php')
            ");
            $notifStmt->execute([$recipientId]);

            jsonResponse(['success' => true, 'message' => 'Invitation sent successfully.']);
        } else {
            jsonResponse(['success' => true, 'message' => 'Invitation sent successfully (Demo Mode).']);
        }
        break;

    case 'list':
    default:
        $query = sanitize($_GET['q'] ?? '');
        $department = sanitize($_GET['dept'] ?? '');
        $availability = sanitize($_GET['availability'] ?? '');

        if (isDbAvailable()) {
            $db = getDB();
            $sql = "
                SELECT u.id, u.full_name, u.email, u.department, u.degree, u.academic_year, 
                       u.availability_status, u.bio, u.avatar_url, u.github_url, u.portfolio_url
                FROM users u
                WHERE u.role != 'faculty_admin'
            ";
            $params = [];

            if (!empty($query)) {
                $sql .= " AND (u.full_name LIKE ? OR u.department LIKE ? OR u.bio LIKE ?)";
                $qParam = "%$query%";
                $params[] = $qParam;
                $params[] = $qParam;
                $params[] = $qParam;
            }

            if (!empty($department)) {
                $sql .= " AND u.department = ?";
                $params[] = $department;
            }

            if (!empty($availability)) {
                $sql .= " AND u.availability_status = ?";
                $params[] = $availability;
            }

            $sql .= " ORDER BY u.full_name ASC";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $students = $stmt->fetchAll();

            // Fetch skills for these students
            foreach ($students as &$student) {
                $skStmt = $db->prepare("
                    SELECT s.name, s.category, ss.proficiency_level, ss.years_experience
                    FROM student_skills ss
                    JOIN skills s ON ss.skill_id = s.id
                    WHERE ss.user_id = ?
                    ORDER BY ss.proficiency_level DESC
                ");
                $skStmt->execute([$student['id']]);
                $student['skills'] = $skStmt->fetchAll();
            }

            jsonResponse(['success' => true, 'students' => $students]);
        } else {
            $mock = getMockUsers();
            $filtered = [];
            foreach ($mock as $s) {
                if ($s['role'] === 'faculty_admin') continue;
                if (!empty($department) && $s['department'] !== $department) continue;
                if (!empty($availability) && $s['availability_status'] !== $availability) continue;
                if (!empty($query)) {
                    $haystack = strtolower($s['full_name'] . ' ' . $s['department'] . ' ' . $s['bio'] . ' ' . implode(' ', $s['skills']));
                    if (strpos($haystack, strtolower($query)) === false) continue;
                }
                $filtered[] = $s;
            }
            jsonResponse(['success' => true, 'is_mock' => true, 'students' => $filtered]);
        }
        break;
}
