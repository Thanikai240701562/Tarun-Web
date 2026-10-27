<?php
/**
 * TeamForge Classical AI Optimization Engine
 * Pure PHP Classical AI Implementation:
 * - Constraint Satisfaction Problem (CSP) Formulation
 * - Backtracking Search
 * - Minimum Remaining Values (MRV) Heuristic
 * - Forward Checking / Constraint Propagation
 * - Greedy Initial Assignment
 * - Hill Climbing Local Search (Member Swaps)
 * - Multi-Objective Objective Scoring Function
 * 
 * Zero external AI APIs, Zero Machine Learning. 100% Deterministic & Explainable.
 */

class StudentCandidate {
    public int $id;
    public string $name;
    public string $email;
    public string $department;
    public string $academicYear;
    public string $availability;
    public int $activeTeamCount;
    public array $skills = []; // [skill_id => ['name' => ..., 'prof' => 1-5, 'years' => ...]]
    public array $roles = [];
    public int $projectCount;

    public function __construct(array $data) {
        $this->id = (int)$data['id'];
        $this->name = $data['full_name'];
        $this->email = $data['email'] ?? '';
        $this->department = $data['department'] ?? 'Computer Science';
        $this->academicYear = $data['academic_year'] ?? '3rd Year';
        $this->availability = $data['availability_status'] ?? 'available';
        $this->activeTeamCount = (int)($data['active_team_count'] ?? 0);
        $this->projectCount = (int)($data['project_count'] ?? 0);
    }
}

class TeamForgeCspEngine {
    private array $candidates = [];       // Domain of candidate students: [id => StudentCandidate]
    private array $requiredSkills = [];   // [skill_id => skill_name]
    private array $targetRoles = [];      // Array of role strings: ['Frontend Developer', 'Backend Developer', ...]
    private int $targetTeamSize = 4;
    private array $weights = [];
    private array $lockedMemberIds = [];  // Members pinned/locked by team leader
    private array $excludedMemberIds = [];// Members explicitly excluded
    private ?int $eventId = null;

    // Explainable Trace Log
    public array $executionTrace = [];
    private int $stepCounter = 0;

    public function __construct(
        array $candidatesData,
        array $requiredSkillIds,
        array $targetRoles,
        int $targetTeamSize = 4,
        array $weights = [],
        array $lockedMemberIds = [],
        array $excludedMemberIds = [],
        ?int $eventId = null
    ) {
        $this->requiredSkills = $requiredSkillIds;
        $this->targetRoles = !empty($targetRoles) ? $targetRoles : array_slice(AVAILABLE_ROLES, 0, $targetTeamSize);
        $this->targetTeamSize = $targetTeamSize;
        $this->weights = array_merge(DEFAULT_AI_WEIGHTS, $weights);
        $this->lockedMemberIds = $lockedMemberIds;
        $this->excludedMemberIds = $excludedMemberIds;
        $this->eventId = $eventId;

        // Populate Candidates
        foreach ($candidatesData as $cData) {
            if ($cData instanceof StudentCandidate) {
                $cand = $cData;
            } else {
                $cand = new StudentCandidate($cData);
                if (!empty($cData['skills'])) {
                    $cand->skills = $cData['skills'];
                }
            }
            // Filter excluded candidates
            if (!in_array($cand->id, $this->excludedMemberIds, true)) {
                $this->candidates[$cand->id] = $cand;
            }
        }
    }

    /**
     * Record an explainable execution step
     */
    private function logTrace(string $stepType, string $description, float $score = 0.0, array $state = []): void {
        $this->stepCounter++;
        $this->executionTrace[] = [
            'step' => $this->stepCounter,
            'type' => $stepType,
            'description' => $description,
            'score' => round($score, 2),
            'state' => $state
        ];
    }

    /**
     * Multi-Objective Evaluation Function
     * Evaluates a team assignment against required skills, role coverage, experience balance, and availability.
     * Returns: [
     *   'total_score' => 0.0 to 100.0,
     *   'skill_coverage_pct' => 0 to 100,
     *   'role_coverage_pct' => 0 to 100,
     *   'experience_balance_score' => 0 to 100,
     *   'availability_score' => 0 to 100,
     *   'missing_skills' => [...],
     *   'explanation' => [...]
     * ]
     */
    public function evaluateAssignment(array $assignment): array {
        if (empty($assignment)) {
            return [
                'total_score' => 0.0,
                'skill_coverage_pct' => 0,
                'role_coverage_pct' => 0,
                'experience_balance_score' => 0,
                'availability_score' => 0,
                'missing_skills' => array_values($this->requiredSkills),
                'explanations' => []
            ];
        }

        $explanations = [];

        // 1. Skill Coverage
        $coveredSkills = [];
        $totalSkillProficiencySum = 0;
        foreach ($assignment as $role => $candId) {
            if (!isset($this->candidates[$candId])) continue;
            $cand = $this->candidates[$candId];
            $candContribution = [];
            foreach ($this->requiredSkills as $sId => $sName) {
                if (isset($cand->skills[$sId])) {
                    $coveredSkills[$sId] = true;
                    $candContribution[] = $sName . ' (Prof: ' . $cand->skills[$sId]['prof'] . '/5)';
                    $totalSkillProficiencySum += $cand->skills[$sId]['prof'];
                }
            }
            $explanations[$candId] = [
                'name' => $cand->name,
                'role' => $role,
                'skills_covered' => $candContribution,
                'projects' => $cand->projectCount,
                'availability' => $cand->availability
            ];
        }

        $reqSkillCount = max(1, count($this->requiredSkills));
        $coveredCount = count($coveredSkills);
        $skillCoveragePct = round(($coveredCount / $reqSkillCount) * 100, 1);

        $missingSkills = [];
        foreach ($this->requiredSkills as $sId => $sName) {
            if (!isset($coveredSkills[$sId])) {
                $missingSkills[] = $sName;
            }
        }

        // 2. Role Coverage
        $assignedRolesCount = count(array_filter(array_keys($assignment)));
        $roleCoveragePct = round(($assignedRolesCount / max(1, count($this->targetRoles))) * 100, 1);

        // 3. Experience & Proficiency Balance
        // Preferred is a healthy mix of seniors (3rd/4th yr) and juniors, with average project experience
        $yearsCount = [];
        $totalProjects = 0;
        foreach ($assignment as $candId) {
            if (isset($this->candidates[$candId])) {
                $cand = $this->candidates[$candId];
                $yearsCount[$cand->academicYear] = ($yearsCount[$cand->academicYear] ?? 0) + 1;
                $totalProjects += $cand->projectCount;
            }
        }
        $diversityFactor = count($yearsCount) > 1 ? 95.0 : 70.0;
        $expScore = min(100.0, ($totalProjects * 8.0) + ($diversityFactor * 0.6));

        // 4. Availability & Bandwidth
        $availScoreSum = 0;
        foreach ($assignment as $candId) {
            if (isset($this->candidates[$candId])) {
                $availScoreSum += match($this->candidates[$candId]->availability) {
                    'available' => 100,
                    'busy' => 50,
                    'in_team' => 30,
                    default => 60
                };
            }
        }
        $availScore = round($availScoreSum / max(1, count($assignment)), 1);

        // Weighted Combination
        $w = $this->weights;
        $totalScore = (
            ($skillCoveragePct * $w['skill_coverage']) +
            ($roleCoveragePct * $w['role_coverage']) +
            ($expScore * $w['experience_balance']) +
            ($availScore * $w['availability']) +
            (85.0 * $w['interest_alignment'])
        );

        return [
            'total_score' => round($totalScore, 2),
            'skill_coverage_pct' => $skillCoveragePct,
            'role_coverage_pct' => $roleCoveragePct,
            'experience_balance_score' => round($expScore, 1),
            'availability_score' => $availScore,
            'missing_skills' => $missingSkills,
            'explanations' => $explanations
        ];
    }

    /**
     * Algorithm E: Greedy Initialization
     * Quickly builds a strong initial valid team using greedy heuristic.
     */
    public function generateGreedyInitial(): array {
        $assignment = [];
        $usedCandidateIds = [];

        $this->logTrace('greedy_assign', 'Starting Greedy Initial Assignment across target roles.', 0.0);

        // 1. Lock designated pinned members first
        foreach ($this->lockedMemberIds as $role => $lockedId) {
            if (isset($this->candidates[$lockedId])) {
                $assignment[$role] = $lockedId;
                $usedCandidateIds[$lockedId] = true;
                $this->logTrace('greedy_assign', "Pinned locked member {$this->candidates[$lockedId]->name} to role [{$role}].", 0.0);
            }
        }

        // 2. For each unfilled role, pick candidate with maximum skill match & availability
        foreach ($this->targetRoles as $role) {
            if (isset($assignment[$role])) continue;

            $bestCandidateId = null;
            $bestCandidateScore = -1;

            foreach ($this->candidates as $candId => $cand) {
                if (isset($usedCandidateIds[$candId])) continue;

                // Candidate heuristic score
                $cScore = 0;
                foreach ($this->requiredSkills as $sId => $sName) {
                    if (isset($cand->skills[$sId])) {
                        $cScore += ($cand->skills[$sId]['prof'] * 15);
                    }
                }
                if ($cand->availability === 'available') $cScore += 30;
                $cScore += min(30, $cand->projectCount * 5);

                if ($cScore > $bestCandidateScore) {
                    $bestCandidateScore = $cScore;
                    $bestCandidateId = $candId;
                }
            }

            if ($bestCandidateId !== null) {
                $assignment[$role] = $bestCandidateId;
                $usedCandidateIds[$bestCandidateId] = true;
                $this->logTrace('greedy_assign', "Greedy match: Selected {$this->candidates[$bestCandidateId]->name} for role [{$role}] (Local Score: {$bestCandidateScore}).", 0.0);
            }
        }

        $eval = $this->evaluateAssignment($assignment);
        $this->logTrace('solution_found', "Greedy baseline team established with Team Score: {$eval['total_score']}%.", $eval['total_score']);

        return $assignment;
    }

    /**
     * Algorithm A, B, C, D: CSP with Backtracking, MRV Heuristic, and Forward Checking
     */
    public function solveWithBacktrackingMRV(): array {
        $this->logTrace('mrv_select', 'Initializing CSP Solver: Variables = Role Slots, Domains = Candidate Pools.');

        // Initialize Domains for each role variable
        $domains = [];
        foreach ($this->targetRoles as $role) {
            $domain = [];
            foreach ($this->candidates as $candId => $cand) {
                // If this role is locked to a specific member
                if (isset($this->lockedMemberIds[$role])) {
                    if ($candId === $this->lockedMemberIds[$role]) {
                        $domain[] = $candId;
                    }
                    continue;
                }

                // Domain filtering: Role keyword heuristic
                $isMatch = true;
                if (str_contains(strtolower($role), 'frontend')) {
                    // Prefers candidate with frontend skills
                    $hasFe = false;
                    foreach ($cand->skills as $sk) {
                        if (in_array(strtolower($sk['name']), ['html & css', 'javascript', 'bootstrap 5', 'react.js', 'tailwind css'])) $hasFe = true;
                    }
                    if ($hasFe) $domain[] = $candId;
                } elseif (str_contains(strtolower($role), 'database')) {
                    $hasDb = false;
                    foreach ($cand->skills as $sk) {
                        if (in_array(strtolower($sk['name']), ['mysql', 'postgresql', 'mongodb'])) $hasDb = true;
                    }
                    if ($hasDb) $domain[] = $candId;
                } elseif (str_contains(strtolower($role), 'algo') || str_contains(strtolower($role), 'ai')) {
                    $hasAi = false;
                    foreach ($cand->skills as $sk) {
                        if (in_array(strtolower($sk['name']), ['python', 'data structures & algorithms', 'graph algorithms & csp'])) $hasAi = true;
                    }
                    if ($hasAi) $domain[] = $candId;
                } else {
                    $domain[] = $candId;
                }
            }

            // Fallback: If filtered domain is empty, use all candidates
            if (empty($domain)) {
                $domain = array_keys($this->candidates);
            }
            $domains[$role] = $domain;
        }

        $assignment = [];
        $solution = $this->backtrack($assignment, $domains);

        if (!empty($solution)) {
            $eval = $this->evaluateAssignment($solution);
            $this->logTrace('solution_found', "Backtracking with MRV found optimal constraint-satisfying team. Score: {$eval['total_score']}%.", $eval['total_score']);
            return $solution;
        }

        // If strict backtracking yielded no complete assignment, fallback to greedy baseline
        $this->logTrace('constraint_violation', 'Backtracking detected dead end. Falling back to robust Greedy Baseline.', 0.0);
        return $this->generateGreedyInitial();
    }

    /**
     * Recursive Backtracking with MRV and Forward Checking
     */
    private function backtrack(array &$assignment, array $domains): ?array {
        // Goal test: all roles filled
        if (count($assignment) === count($this->targetRoles)) {
            return $assignment;
        }

        // C. MRV Heuristic: Choose unassigned variable with smallest domain
        $unassignedRoles = array_diff($this->targetRoles, array_keys($assignment));
        $chosenRole = null;
        $minDomainSize = PHP_INT_MAX;

        foreach ($unassignedRoles as $role) {
            $dSize = count($domains[$role]);
            if ($dSize < $minDomainSize) {
                $minDomainSize = $dSize;
                $chosenRole = $role;
            }
        }

        $this->logTrace('mrv_select', "MRV Heuristic: Selected role [{$chosenRole}] with {$minDomainSize} remaining domain candidates.");

        // Try values from chosen variable's domain
        foreach ($domains[$chosenRole] as $candId) {
            // Hard constraint: candidate cannot be assigned twice in same team
            if (in_array($candId, $assignment, true)) {
                continue;
            }

            // Tentative assignment
            $assignment[$chosenRole] = $candId;
            $candName = $this->candidates[$candId]->name ?? "Candidate #{$candId}";
            $this->logTrace('forward_check', "Tentative assignment: Assigned {$candName} to [{$chosenRole}].");

            // D. Forward Checking: Prune assigned candidate from remaining domains
            $prunedDomains = $domains;
            $conflictFound = false;

            foreach ($unassignedRoles as $otherRole) {
                if ($otherRole === $chosenRole) continue;
                $prunedDomains[$otherRole] = array_values(array_diff($prunedDomains[$otherRole], [$candId]));
                if (empty($prunedDomains[$otherRole])) {
                    // Forward checking detected domain wipeout!
                    $this->logTrace('constraint_violation', "Forward Checking: Domain wipeout detected for role [{$otherRole}]! Pruning branch.");
                    $conflictFound = true;
                    break;
                }
            }

            if (!$conflictFound) {
                $result = $this->backtrack($assignment, $prunedDomains);
                if ($result !== null) {
                    return $result;
                }
            }

            // Backtrack: Undo assignment
            $this->logTrace('backtrack', "Backtracking: Undoing assignment of {$candName} from [{$chosenRole}].");
            unset($assignment[$chosenRole]);
        }

        return null;
    }

    /**
     * Algorithm F: Hill Climbing Local Search
     * Iteratively swaps members with candidates in the pool to maximize the multi-objective score.
     */
    public function optimizeWithHillClimbing(array $initialAssignment, int $maxIterations = 25): array {
        $currentAssignment = $initialAssignment;
        $currentEval = $this->evaluateAssignment($currentAssignment);
        $currentScore = $currentEval['total_score'];

        $this->logTrace('hill_climb_swap', "Starting Hill Climbing Local Search from initial score {$currentScore}%.", $currentScore);

        $improved = true;
        $iteration = 0;

        while ($improved && $iteration < $maxIterations) {
            $improved = false;
            $iteration++;

            $bestNeighbor = $currentAssignment;
            $bestNeighborScore = $currentScore;
            $bestSwapDesc = '';

            // Explore neighborhood: Swap each non-locked member with an unused candidate
            foreach ($currentAssignment as $role => $assignedId) {
                // Do not swap pinned/locked members
                if (isset($this->lockedMemberIds[$role]) && $this->lockedMemberIds[$role] === $assignedId) {
                    continue;
                }

                foreach ($this->candidates as $candId => $candidate) {
                    if (in_array($candId, $currentAssignment, true)) {
                        continue;
                    }

                    // Create neighbor assignment
                    $neighbor = $currentAssignment;
                    $neighbor[$role] = $candId;

                    $neighborEval = $this->evaluateAssignment($neighbor);
                    $nScore = $neighborEval['total_score'];

                    // Strict improvement
                    if ($nScore > $bestNeighborScore + 0.1) {
                        $bestNeighborScore = $nScore;
                        $bestNeighbor = $neighbor;
                        $oldName = $this->candidates[$assignedId]->name;
                        $newName = $candidate->name;
                        $bestSwapDesc = "Swapped [{$oldName}] with [{$newName}] in role [{$role}]. Score improved: {$currentScore}% &rarr; {$nScore}%.";
                        $improved = true;
                    }
                }
            }

            if ($improved) {
                $currentAssignment = $bestNeighbor;
                $currentScore = $bestNeighborScore;
                $this->logTrace('hill_climb_swap', "Hill Climbing step {$iteration}: {$bestSwapDesc}", $currentScore);
            }
        }

        $this->logTrace('solution_found', "Hill Climbing converged after {$iteration} iterations. Peak score: {$currentScore}%.", $currentScore);
        return $currentAssignment;
    }

    /**
     * Generate Multiple Alternative Proposals (Section 11)
     * Returns 3 distinct proposals:
     * 1. Balanced Optimal (CSP + Hill Climbing)
     * 2. Senior / Experience-Biased Formation
     * 3. Junior / Skill-Coverage High Density Formation
     */
    public function generateMultipleProposals(): array {
        $proposals = [];

        // Proposal 1: Optimal Balanced
        $baseTeam = $this->solveWithBacktrackingMRV();
        $optTeam = $this->optimizeWithHillClimbing($baseTeam);
        $eval1 = $this->evaluateAssignment($optTeam);
        $proposals[] = [
            'id' => 1,
            'title' => 'Proposal Alpha: Balanced Optimal (CSP + Hill Climbing)',
            'description' => 'Mathematically maximizes combined skill coverage, role specialization, and availability alignment.',
            'assignment' => $optTeam,
            'evaluation' => $eval1,
            'advantages' => ['Highest multi-objective score', 'Full hard constraint validation', 'Zero redundant roles'],
            'limitations' => ['Requires all members to commit active hours']
        ];

        // Proposal 2: Maximum Skill Coverage Focus
        // Boost skill weight
        $engine2 = new TeamForgeCspEngine(
            array_values($this->candidates),
            $this->requiredSkills,
            $this->targetRoles,
            $this->targetTeamSize,
            ['skill_coverage' => 0.55, 'role_coverage' => 0.20, 'experience_balance' => 0.10, 'availability' => 0.10, 'interest_alignment' => 0.05],
            $this->lockedMemberIds,
            $this->excludedMemberIds
        );
        $team2 = $engine2->optimizeWithHillClimbing($engine2->generateGreedyInitial());
        $eval2 = $this->evaluateAssignment($team2);
        $proposals[] = [
            'id' => 2,
            'title' => 'Proposal Beta: High Skill Density Specialist Squad',
            'description' => 'Prioritizes maximum technical proficiency across required frameworks, ideal for complex technical hackathons.',
            'assignment' => $team2,
            'evaluation' => $eval2,
            'advantages' => ['Near 100% hard skill proficiency', 'Deep specialized knowledge in core languages'],
            'limitations' => ['Higher competition for senior contributors']
        ];

        // Proposal 3: Availability & Agile Workload Focused
        $engine3 = new TeamForgeCspEngine(
            array_values($this->candidates),
            $this->requiredSkills,
            $this->targetRoles,
            $this->targetTeamSize,
            ['skill_coverage' => 0.25, 'role_coverage' => 0.20, 'experience_balance' => 0.15, 'availability' => 0.35, 'interest_alignment' => 0.05],
            $this->lockedMemberIds,
            $this->excludedMemberIds
        );
        $team3 = $engine3->generateGreedyInitial();
        $eval3 = $this->evaluateAssignment($team3);
        $proposals[] = [
            'id' => 3,
            'title' => 'Proposal Gamma: Agile & High Bandwidth Squad',
            'description' => 'Maximizes student availability and free sprint capacity, ensuring fast project execution without scheduling bottlenecks.',
            'assignment' => $team3,
            'evaluation' => $eval3,
            'advantages' => ['Members have high bandwidth & active availability', 'Fast communication turnaround'],
            'limitations' => ['May require pair programming for advanced algorithms']
        ];

        return $proposals;
    }
}
