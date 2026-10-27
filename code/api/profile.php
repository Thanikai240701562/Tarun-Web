<?php
/**
 * TeamForge User Profile & Skills API
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/mock_data.php';

$input = getJsonInput();
$action = $input['action'] ?? $_GET['action'] ?? 'get';
$user = currentUser();
$userId = $user['id'] ?? ($_SESSION['user_id'] ?? 2);

switch ($action) {
    case 'update':
        $fullName = sanitize($input['full_name'] ?? '');
        $bio = sanitize($input['bio'] ?? '');
        $department = sanitize($input['department'] ?? '');
        $availability = sanitize($input['availability_status'] ?? 'available');
        $githubUrl = sanitize($input['github_url'] ?? '');
        $portfolioUrl = sanitize($input['portfolio_url'] ?? '');

        if (empty($fullName)) jsonError('Name cannot be empty.');

        if (isDbAvailable()) {
            $db = getDB();
            $stmt = $db->prepare("
                UPDATE users SET 
                    full_name = ?, bio = ?, department = ?, 
                    availability_status = ?, github_url = ?, portfolio_url = ?
                WHERE id = ?
            ");
            $stmt->execute([$fullName, $bio, $department, $availability, $githubUrl, $portfolioUrl, $userId]);
            $_SESSION['user_name'] = $fullName;
            jsonResponse(['success' => true, 'message' => 'Profile updated successfully!']);
        } else {
            $_SESSION['user_name'] = $fullName;
            jsonResponse(['success' => true, 'message' => 'Profile updated (Demo Mode)!']);
        }
        break;

    case 'update_skills':
        $skills = is_array($input['skills'] ?? null) ? $input['skills'] : [];

        if (isDbAvailable()) {
            $db = getDB();
            $db->prepare("DELETE FROM student_skills WHERE user_id = ?")->execute([$userId]);
            $stmt = $db->prepare("
                INSERT INTO student_skills (user_id, skill_id, proficiency_level, years_experience)
                VALUES (?, ?, ?, ?)
            ");
            foreach ($skills as $s) {
                $sId = (int)($s['skill_id'] ?? 0);
                $prof = max(1, min(5, (int)($s['proficiency'] ?? 3)));
                $years = (float)($s['years'] ?? 1.0);
                if ($sId > 0) {
                    $stmt->execute([$userId, $sId, $prof, $years]);
                }
            }
            jsonResponse(['success' => true, 'message' => 'Skills updated successfully!']);
        } else {
            jsonResponse(['success' => true, 'message' => 'Skills updated (Demo Mode)!']);
        }
        break;

    case 'get':
    default:
        $targetId = (int)($_GET['id'] ?? $userId);
        if (isDbAvailable()) {
            $db = getDB();
            $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$targetId]);
            $u = $stmt->fetch();
            if (!$u) jsonError('User not found.', 404);

            $skStmt = $db->prepare("
                SELECT s.id as skill_id, s.name, s.category, ss.proficiency_level, ss.years_experience
                FROM student_skills ss
                JOIN skills s ON ss.skill_id = s.id
                WHERE ss.user_id = ?
            ");
            $skStmt->execute([$targetId]);
            $u['skills'] = $skStmt->fetchAll();

            $allSkills = $db->query("SELECT id, name, category FROM skills ORDER BY name ASC")->fetchAll();
            jsonResponse(['success' => true, 'user' => $u, 'available_skills' => $allSkills]);
        } else {
            $mock = getMockUsers();
            $u = $mock[1]; // Aarav Sharma
            $availableSkills = [
                ['id' => 1, 'name' => 'PHP'],
                ['id' => 2, 'name' => 'MySQL'],
                ['id' => 3, 'name' => 'JavaScript'],
                ['id' => 4, 'name' => 'HTML & CSS'],
                ['id' => 5, 'name' => 'Bootstrap 5'],
                ['id' => 8, 'name' => 'Python'],
                ['id' => 11, 'name' => 'UI/UX Design & Figma']
            ];
            $u['skills'] = [
                ['skill_id' => 1, 'name' => 'PHP', 'proficiency_level' => 5, 'years_experience' => 2.5],
                ['skill_id' => 2, 'name' => 'MySQL', 'proficiency_level' => 4, 'years_experience' => 2.0],
                ['skill_id' => 3, 'name' => 'JavaScript', 'proficiency_level' => 5, 'years_experience' => 3.0],
                ['skill_id' => 4, 'name' => 'HTML & CSS', 'proficiency_level' => 5, 'years_experience' => 3.0]
            ];
            jsonResponse(['success' => true, 'is_mock' => true, 'user' => $u, 'available_skills' => $availableSkills]);
        }
        break;
}
