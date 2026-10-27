<?php
/**
 * TeamForge Smart Member Replacement Engine (Section 13)
 * Analyzes team's exact skill deficits and searches eligible students across campus
 * to recommend pinpoint replacements without breaking the existing team core.
 */
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

requireAuth();
$currentUser = currentUser();
$db = getDB();

$teamId = (int)($_GET['team_id'] ?? 0);
if ($teamId <= 0) {
    setFlash('danger', 'Please specify a valid team.');
    header('Location: ' . BASE_URL . '/teams/my_teams.php');
    exit;
}

$teamStmt = $db->prepare("SELECT * FROM teams WHERE id = ?");
$teamStmt->execute([$teamId]);
$team = $teamStmt->fetch();

if (!$team || !isUserTeamLeaderOf($currentUser['id'], $teamId)) {
    setFlash('danger', 'Unauthorized. Only the team leader can access replacement recommendations.');
    header('Location: ' . BASE_URL . '/teams/view.php?id=' . $teamId);
    exit;
}

// 1. Identify missing required skills
$skillsStmt = $db->prepare("
    SELECT s.id, s.name, s.category
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
$skillsStmt->execute([$teamId, $teamId]);
$missingSkills = $skillsStmt->fetchAll();
$missingSkillIds = array_column($missingSkills, 'id');

// 2. Fetch active member IDs so we don't recommend existing members
$curMembers = $db->query("SELECT user_id FROM team_members WHERE team_id = {$teamId} AND status = 'active'")->fetchAll(PDO::FETCH_COLUMN);

// 3. Search eligible replacement candidates
// Filter by event participants if team is tied to an event
if (!empty($team['event_id'])) {
    $candStmt = $db->prepare("
        SELECT u.*,
               (SELECT COUNT(*) FROM student_projects WHERE user_id = u.id) as project_count
        FROM users u
        JOIN event_registrations er ON u.id = er.user_id
        WHERE er.event_id = ? AND er.status != 'cancelled' AND u.id NOT IN (" . implode(',', array_map('intval', $curMembers)) . ")
    ");
    $candStmt->execute([$team['event_id']]);
} else {
    $candStmt = $db->query("
        SELECT u.*,
               (SELECT COUNT(*) FROM student_projects WHERE user_id = u.id) as project_count
        FROM users u
        WHERE u.id NOT IN (" . implode(',', array_map('intval', $curMembers)) . ")
    ");
}
$candidatesRaw = $candStmt->fetchAll();

// Rank candidates based on how many missing skills they cover + proficiency + availability
$rankedCandidates = [];

if (!empty($candidatesRaw)) {
    $cIds = implode(',', array_map('intval', array_column($candidatesRaw, 'id')));
    $skillsData = $db->query("
        SELECT ss.user_id, ss.skill_id, ss.proficiency_level, s.name
        FROM student_skills ss
        JOIN skills s ON ss.skill_id = s.id
        WHERE ss.user_id IN ({$cIds})
    ")->fetchAll();

    $candSkills = [];
    foreach ($skillsData as $sd) {
        $candSkills[$sd['user_id']][$sd['skill_id']] = [
            'name' => $sd['name'],
            'prof' => (int)$sd['proficiency_level']
        ];
    }

    foreach ($candidatesRaw as $cand) {
        $cSkills = $candSkills[$cand['id']] ?? [];
        $coveredDeficits = [];
        $deficitScore = 0;

        foreach ($missingSkills as $ms) {
            if (isset($cSkills[$ms['id']])) {
                $coveredDeficits[] = $ms['name'] . ' (★' . $cSkills[$ms['id']]['prof'] . ')';
                $deficitScore += ($cSkills[$ms['id']]['prof'] * 20);
            }
        }

        // Availability bonus
        if ($cand['availability_status'] === 'available') $deficitScore += 30;
        elseif ($cand['availability_status'] === 'busy') $deficitScore += 10;

        // Project bonus
        $deficitScore += min(20, $cand['project_count'] * 4);

        $cand['covered_deficits'] = $coveredDeficits;
        $cand['deficit_score'] = $deficitScore;
        $cand['skills_map'] = $cSkills;

        $rankedCandidates[] = $cand;
    }

    usort($rankedCandidates, fn($a, $b) => $b['deficit_score'] <=> $a['deficit_score']);
}

$pageTitle = "Smart Member Replacement — " . APP_NAME;
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-0">
    <div class="mb-3">
        <a href="<?= BASE_URL ?>/teams/view.php?id=<?= $teamId ?>" class="text-decoration-none text-muted small">
            &larr; Back to <?= e($team['name']) ?> Dashboard
        </a>
    </div>

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <div class="badge bg-warning-subtle text-warning border border-warning-subtle px-3 py-1 rounded-pill fw-bold mb-1">
                <i class="bi bi-arrow-repeat me-1"></i> Surgical Heuristic Search
            </div>
            <h2 class="fw-extrabold text-navy mb-1" style="font-weight: 800;">Smart Member Replacement</h2>
            <p class="text-muted small mb-0">Replaces withdrawn teammates by optimizing only the missing skill gap without disbanding the team.</p>
        </div>
    </div>

    <!-- Missing Skills Deficit Report -->
    <div class="card-tf p-4 mb-4 bg-white shadow-sm border">
        <h5 class="fw-bold text-dark mb-2"><i class="bi bi-shield-exclamation text-warning me-2"></i>Current Missing Skill Deficits</h5>
        <?php if (empty($missingSkills)): ?>
            <div class="alert alert-success d-flex align-items-center gap-2 mb-0 rounded-3">
                <i class="bi bi-check-circle-fill fs-5"></i>
                <div>
                    <strong>Full Coverage:</strong> Your team currently has all declared required skills covered by existing members!
                </div>
            </div>
        <?php else: ?>
            <p class="text-muted small mb-3">The following required competencies were vacated and require replacement:</p>
            <div class="d-flex flex-wrap gap-2 mb-2">
                <?php foreach ($missingSkills as $ms): ?>
                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle p-2 fs-6">
                        <i class="bi bi-x-circle me-1"></i> Missing: <?= e($ms['name']) ?>
                    </span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Recommended Candidates Ranked by Deficit Fill -->
    <h5 class="fw-bold text-dark mb-3">Top Recommended Replacement Candidates</h5>

    <?php if (empty($rankedCandidates)): ?>
        <div class="card-tf p-5 text-center">
            <div class="empty-state">
                <div class="empty-state-icon"><i class="bi bi-person-x"></i></div>
                <div class="empty-state-title">No Eligible Candidates Found</div>
                <div class="empty-state-text">No available candidates currently match the event or campus pool. Try recruiting through the student directory.</div>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach (array_slice($rankedCandidates, 0, 6) as $idx => $cand): 
                $hasMatch = !empty($cand['covered_deficits']);
            ?>
                <div class="col-md-6 col-xl-4">
                    <div class="card-tf h-100 p-4 border d-flex flex-column justify-content-between bg-white card-tf-interactive">
                        <div>
                            <div class="d-flex align-items-start justify-content-between mb-3">
                                <div class="d-flex align-items-center gap-3">
                                    <?= getAvatar($cand, 44) ?>
                                    <div>
                                        <h6 class="fw-bold text-dark mb-0"><?= e($cand['full_name']) ?></h6>
                                        <div class="text-muted small" style="font-size:11px;">
                                            <?= e($cand['department']) ?> &bull; <?= e($cand['academic_year']) ?>
                                        </div>
                                    </div>
                                </div>
                                <span class="badge <?= $hasMatch ? 'bg-success' : 'bg-secondary' ?> font-mono">
                                    Fit: <?= $cand['deficit_score'] ?> pts
                                </span>
                            </div>

                            <!-- Skills Deficit Match Box -->
                            <div class="p-2 rounded-2 <?= $hasMatch ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-light text-muted' ?> small mb-3" style="font-size: 11px;">
                                <?php if ($hasMatch): ?>
                                    <strong><i class="bi bi-check2-all"></i> Solves Deficit:</strong> <?= implode(', ', $cand['covered_deficits']) ?>
                                <?php else: ?>
                                    <em>General capacity contributor (does not cover exact missing skill)</em>
                                <?php endif; ?>
                            </div>

                            <p class="text-muted small mb-3" style="font-size:12px; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                <?= !empty($cand['bio']) ? e($cand['bio']) : '<em>No personal bio provided.</em>' ?>
                            </p>
                        </div>

                        <div class="pt-3 border-top d-flex align-items-center justify-content-between">
                            <span class="status-pill status-<?= e($cand['availability_status']) ?>" style="font-size:10px;">
                                <?= ucfirst($cand['availability_status']) ?>
                            </span>

                            <form method="POST" action="<?= BASE_URL ?>/teams/send_invite.php">
                                <?= csrf_field() ?>
                                <input type="hidden" name="team_id" value="<?= $teamId ?>">
                                <input type="hidden" name="recipient_ids" value="<?= $cand['id'] ?>">
                                <input type="hidden" name="invited_role" value="Replacement Specialist">
                                <button type="submit" class="btn btn-sm btn-tf-primary" style="font-size:11px;">
                                    <i class="bi bi-send me-1"></i> Send Invite
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
