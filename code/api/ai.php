<?php
/**
 * TeamForge Classical AI API
 * Executes deterministic Constraint Satisfaction Problem (CSP) Engine,
 * MRV Heuristic, Forward Checking, and Hill Climbing Local Search
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/mock_data.php';
require_once __DIR__ . '/../ai/TeamForgeCspEngine.php';

$input = getJsonInput();
$action = $input['action'] ?? $_GET['action'] ?? 'params';
$user = currentUser();
$userId = $user['id'] ?? ($_SESSION['user_id'] ?? 2);

switch ($action) {
    case 'params':
        $dbOk = isDbAvailable();
        if ($dbOk) {
            $db = getDB();
            $skills = $db->query("SELECT id, name, category FROM skills ORDER BY category, name ASC")->fetchAll();
            $events = $db->query("SELECT id, title, min_team_size, max_team_size FROM events WHERE status = 'active' ORDER BY event_date ASC")->fetchAll();
        } else {
            $skills = [
                ['id' => 1, 'name' => 'PHP', 'category' => 'backend'],
                ['id' => 2, 'name' => 'MySQL', 'category' => 'database'],
                ['id' => 3, 'name' => 'JavaScript', 'category' => 'frontend'],
                ['id' => 4, 'name' => 'HTML & CSS', 'category' => 'frontend'],
                ['id' => 5, 'name' => 'Bootstrap 5', 'category' => 'frontend'],
                ['id' => 6, 'name' => 'React.js', 'category' => 'frontend'],
                ['id' => 8, 'name' => 'Python', 'category' => 'ai_algo'],
                ['id' => 9, 'name' => 'Data Structures & Algorithms', 'category' => 'ai_algo'],
                ['id' => 10, 'name' => 'Graph Algorithms & CSP', 'category' => 'ai_algo'],
                ['id' => 11, 'name' => 'UI/UX Design & Figma', 'category' => 'ui_ux'],
                ['id' => 12, 'name' => 'RESTful APIs', 'category' => 'backend'],
                ['id' => 14, 'name' => 'Git & GitHub', 'category' => 'general']
            ];
            $events = getMockEvents();
        }

        jsonResponse([
            'success' => true,
            'skills' => $skills,
            'events' => $events,
            'roles' => AVAILABLE_ROLES,
            'default_weights' => DEFAULT_AI_WEIGHTS
        ]);
        break;

    case 'generate':
        $targetSize = max(2, min(8, (int)($input['target_size'] ?? 4)));
        $eventId = (int)($input['event_id'] ?? 0);
        $selectedSkillIds = array_map('intval', $input['skills'] ?? [1, 2, 3, 11]);
        $weightSkill = (float)($input['weight_skill'] ?? 0.35);
        $weightRole = (float)($input['weight_role'] ?? 0.25);
        $weightExp = (float)($input['weight_exp'] ?? 0.15);
        $weightAvail = (float)($input['weight_avail'] ?? 0.15);

        $weights = [
            'skill_coverage' => $weightSkill,
            'role_coverage' => $weightRole,
            'experience_balance' => $weightExp,
            'availability' => $weightAvail,
            'interest_alignment' => 0.10
        ];

        $targetRoles = array_slice(AVAILABLE_ROLES, 0, $targetSize);

        $dbOk = isDbAvailable();
        $candidates = [];
        $reqSkillMap = [];

        if ($dbOk) {
            $db = getDB();
            // Fetch candidates
            if ($eventId > 0) {
                $candStmt = $db->prepare("
                    SELECT u.*,
                        (SELECT COUNT(*) FROM team_members tm JOIN teams t ON tm.team_id = t.id WHERE tm.user_id = u.id AND tm.status = 'active') as active_team_count,
                        (SELECT COUNT(*) FROM student_projects WHERE user_id = u.id) as project_count
                    FROM users u
                    JOIN event_registrations er ON u.id = er.user_id
                    WHERE er.event_id = ? AND er.status != 'cancelled'
                ");
                $candStmt->execute([$eventId]);
            } else {
                $candStmt = $db->query("
                    SELECT u.*,
                        (SELECT COUNT(*) FROM team_members tm JOIN teams t ON tm.team_id = t.id WHERE tm.user_id = u.id AND tm.status = 'active') as active_team_count,
                        (SELECT COUNT(*) FROM student_projects WHERE user_id = u.id) as project_count
                    FROM users u
                ");
            }
            $candidatesRaw = $candStmt->fetchAll();

            $candIds = array_column($candidatesRaw, 'id');
            if (!empty($candIds)) {
                $inClause = implode(',', array_map('intval', $candIds));
                $skillsQuery = $db->query("
                    SELECT ss.user_id, ss.skill_id, ss.proficiency_level, ss.years_experience, s.name
                    FROM student_skills ss
                    JOIN skills s ON ss.skill_id = s.id
                    WHERE ss.user_id IN ({$inClause})
                ")->fetchAll();

                $skillsByCandidate = [];
                foreach ($skillsQuery as $sq) {
                    $skillsByCandidate[$sq['user_id']][$sq['skill_id']] = [
                        'name' => $sq['name'],
                        'prof' => (int)$sq['proficiency_level'],
                        'years' => (float)$sq['years_experience']
                    ];
                }

                foreach ($candidatesRaw as $cRow) {
                    $cRow['skills'] = $skillsByCandidate[$cRow['id']] ?? [];
                    $candidates[] = $cRow;
                }
            }

            // Skills map
            $allSkills = $db->query("SELECT id, name FROM skills")->fetchAll();
            foreach ($allSkills as $s) {
                if (in_array((int)$s['id'], $selectedSkillIds, true)) {
                    $reqSkillMap[(int)$s['id']] = $s['name'];
                }
            }
        }

        // If candidates are empty (or DB offline), use rich mock candidate pool
        if (empty($candidates)) {
            $mockUsers = getMockUsers();
            $reqSkillMap = [
                1 => 'PHP',
                2 => 'MySQL',
                3 => 'JavaScript',
                4 => 'HTML & CSS',
                8 => 'Python',
                10 => 'Graph Algorithms & CSP',
                11 => 'UI/UX Design & Figma'
            ];
            foreach ($mockUsers as $mU) {
                $cSkills = [];
                foreach ($mU['skills'] as $idx => $sName) {
                    $cSkills[$idx + 1] = ['name' => $sName, 'prof' => 4, 'years' => 2.0];
                }
                $mU['skills'] = $cSkills;
                $candidates[] = $mU;
            }
        }

        $lockedMembers = [];
        if (!empty($user) && $user['role'] !== ROLE_FACULTY_ADMIN) {
            $lockedMembers[$targetRoles[0]] = $user['id'];
        }

        $startTime = microtime(true);
        $aiEngine = new TeamForgeCspEngine(
            $candidates,
            $reqSkillMap,
            $targetRoles,
            $targetSize,
            $weights,
            $lockedMembers,
            [],
            $eventId > 0 ? $eventId : null
        );

        $proposals = $aiEngine->generateMultipleProposals();
        $executionTimeMs = round((microtime(true) - $startTime) * 1000, 2);

        jsonResponse([
            'success' => true,
            'execution_time_ms' => $executionTimeMs,
            'algorithm' => 'CSP_Backtracking_MRV_ForwardChecking_HillClimbing',
            'proposals' => $proposals,
            'execution_trace' => array_slice($aiEngine->executionTrace, 0, 50),
            'target_roles' => $targetRoles,
            'weights' => $weights
        ]);
        break;

    default:
        jsonError('Unknown action.');
        break;
}
