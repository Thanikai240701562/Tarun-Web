<?php
/**
 * TeamForge Landing Page
 */
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';

// If already logged in, redirect directly to dashboard
if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$pageTitle = APP_NAME . " — Intelligent Student Team Formation Platform";
include __DIR__ . '/includes/header.php';
?>

<!-- Public Top Navigation -->
<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom sticky-top py-3">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2 fw-bold text-dark fs-4" href="<?= BASE_URL ?>/index.php">
            <div class="brand-icon">
                <i class="bi bi-diagram-3-fill"></i>
            </div>
            <span>Team<span style="color:#4f46e5;">Forge</span></span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navContent">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navContent">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-4">
                <li class="nav-item"><a class="nav-link fw-semibold text-secondary" href="#features">Features</a></li>
                <li class="nav-item"><a class="nav-link fw-semibold text-secondary" href="#ai-engine">Classical AI Engine</a></li>
                <li class="nav-item"><a class="nav-link fw-semibold text-secondary" href="#events">College Events</a></li>
                <li class="nav-item"><a class="nav-link fw-semibold text-secondary" href="#architecture">Architecture</a></li>
            </ul>
            <div class="d-flex align-items-center gap-2">
                <a href="<?= BASE_URL ?>/login.php" class="btn btn-tf-outline">Sign In</a>
                <a href="<?= BASE_URL ?>/register.php" class="btn btn-tf-primary">Join TeamForge</a>
            </div>
        </div>
    </div>
</nav>

<!-- Hero Section -->
<section class="py-5" style="background: linear-gradient(180deg, #f8fafc 0%, #eef2ff 100%); border-bottom: 1px solid #e2e8f0;">
    <div class="container py-lg-5">
        <div class="row align-items-center g-5">
            <div class="col-lg-7">
                <div class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 rounded-pill fw-bold mb-3">
                    <i class="bi bi-stars me-1"></i> Powered by Classical AI Heuristics & CSP
                </div>
                <h1 class="display-4 fw-extrabold text-navy tracking-tight mb-3" style="font-weight: 800; color: #0f172a; line-height: 1.15;">
                    Form High-Performing Student Teams with <span style="background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Mathematical Precision</span>
                </h1>
                <p class="lead text-muted mb-4" style="font-size: 1.15rem; max-width: 620px;">
                    Eliminate chaotic group projects and unbalanced hackathon teams. TeamForge matches complementary skill sets, balances workload, and enforces event rules using verified Constraint Satisfaction Algorithms.
                </p>
                <div class="d-flex flex-wrap gap-3 mb-4">
                    <a href="<?= BASE_URL ?>/register.php" class="btn btn-lg btn-tf-primary px-4">
                        Get Started Free <i class="bi bi-arrow-right ms-2"></i>
                    </a>
                    <a href="<?= BASE_URL ?>/login.php" class="btn btn-lg btn-white border px-4 shadow-sm text-dark fw-semibold">
                        <i class="bi bi-box-arrow-in-right me-2 text-primary"></i> Explore Demo
                    </a>
                </div>
                
                <!-- Quick Credentials Callout for Evaluators -->
                <div class="p-3 bg-white border rounded-3 shadow-sm" style="max-width: 580px;">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="small fw-bold text-uppercase text-secondary"><i class="bi bi-key-fill text-warning me-1"></i> Academic Evaluator Quick Access</span>
                        <span class="badge bg-success-subtle text-success">Password: Password123!</span>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="<?= BASE_URL ?>/login.php?quick=faculty" class="badge bg-purple-subtle text-purple border p-2 text-decoration-none">
                            <i class="bi bi-shield-check me-1"></i> Admin / Faculty (Dr. Sarah)
                        </a>
                        <a href="<?= BASE_URL ?>/login.php?quick=tl" class="badge bg-primary-subtle text-primary border p-2 text-decoration-none">
                            <i class="bi bi-award-fill me-1"></i> Team Leader (Aarav)
                        </a>
                        <a href="<?= BASE_URL ?>/login.php?quick=student" class="badge bg-secondary-subtle text-secondary border p-2 text-decoration-none">
                            <i class="bi bi-person-fill me-1"></i> Student (Ananya - AI)
                        </a>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <!-- AI Formation Interactive Mockup Card -->
                <div class="card-tf shadow-lg border-0 p-4" style="border-radius: 20px; background: #ffffff;">
                    <div class="d-flex align-items-center justify-content-between pb-3 border-bottom mb-3">
                        <div class="d-flex align-items-center gap-2">
                            <span class="p-2 rounded-2 bg-primary text-white"><i class="bi bi-cpu"></i></span>
                            <div>
                                <div class="fw-bold small text-dark">Optimization Engine</div>
                                <div class="text-muted" style="font-size:0.75rem;">CSP + Backtracking + Hill Climbing</div>
                            </div>
                        </div>
                        <span class="badge bg-success-subtle text-success fw-bold px-2 py-1">Score: 94.5%</span>
                    </div>

                    <!-- Mini Team Composition Preview -->
                    <div class="small fw-bold text-muted mb-2 text-uppercase" style="font-size: 0.72rem;">Optimal Assignment Output</div>
                    <div class="d-flex flex-column gap-2 mb-3">
                        <div class="d-flex align-items-center justify-content-between p-2 bg-light rounded-3">
                            <div class="d-flex align-items-center gap-2">
                                <div class="avatar-initials rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width:28px;height:28px;font-size:11px;">AS</div>
                                <div>
                                    <div class="fw-bold small text-dark">Aarav Sharma</div>
                                    <div class="text-muted" style="font-size:11px;">Team Leader & Full Stack</div>
                                </div>
                            </div>
                            <span class="badge bg-info-subtle text-info" style="font-size: 10px;">MRV Var 1</span>
                        </div>
                        <div class="d-flex align-items-center justify-content-between p-2 bg-light rounded-3">
                            <div class="d-flex align-items-center gap-2">
                                <div class="avatar-initials rounded-circle bg-info text-white d-flex align-items-center justify-content-center" style="width:28px;height:28px;font-size:11px;">RV</div>
                                <div>
                                    <div class="fw-bold small text-dark">Rohan Verma</div>
                                    <div class="text-muted" style="font-size:11px;">Frontend & Bootstrap Specialist</div>
                                </div>
                            </div>
                            <span class="badge bg-success-subtle text-success" style="font-size: 10px;">Fit: 98%</span>
                        </div>
                        <div class="d-flex align-items-center justify-content-between p-2 bg-light rounded-3">
                            <div class="d-flex align-items-center gap-2">
                                <div class="avatar-initials rounded-circle bg-warning text-white d-flex align-items-center justify-content-center" style="width:28px;height:28px;font-size:11px;">VN</div>
                                <div>
                                    <div class="fw-bold small text-dark">Vikram Nair</div>
                                    <div class="text-muted" style="font-size:11px;">Database & SQL Performance</div>
                                </div>
                            </div>
                            <span class="badge bg-success-subtle text-success" style="font-size: 10px;">Fit: 95%</span>
                        </div>
                        <div class="d-flex align-items-center justify-content-between p-2 bg-light rounded-3">
                            <div class="d-flex align-items-center gap-2">
                                <div class="avatar-initials rounded-circle bg-purple text-white d-flex align-items-center justify-content-center" style="width:28px;height:28px;font-size:11px;background:#7c3aed;">SK</div>
                                <div>
                                    <div class="fw-bold small text-dark">Sneha Kulkarni</div>
                                    <div class="text-muted" style="font-size:11px;">UI/UX & Product Design</div>
                                </div>
                            </div>
                            <span class="badge bg-success-subtle text-success" style="font-size: 10px;">Fit: 92%</span>
                        </div>
                    </div>

                    <div class="p-3 rounded-3" style="background:#f0fdf4; border: 1px solid #bbf7d0;">
                        <div class="d-flex align-items-center gap-2 text-success small fw-bold mb-1">
                            <i class="bi bi-check-all fs-5"></i> Hard Constraints Satisfied
                        </div>
                        <div class="text-muted" style="font-size:0.75rem;">
                            100% Required Skills Covered &bull; Exactly 4 Members &bull; No Duplicate Candidates &bull; Availability Verified
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Features Grid -->
<section class="py-5 bg-white" id="features">
    <div class="container py-4">
        <div class="text-center mb-5">
            <h2 class="fw-bold text-dark mb-2">Engineered for Academic Excellence</h2>
            <p class="text-muted" style="max-width: 540px; margin: 0 auto;">Everything students, team leaders, and university faculty need to collaborate seamlessly.</p>
        </div>

        <div class="row g-4">
            <div class="col-md-4">
                <div class="card-tf h-100 p-4 border card-tf-interactive">
                    <div class="stat-icon bg-primary-subtle text-primary mb-3">
                        <i class="bi bi-cpu-fill"></i>
                    </div>
                    <h5 class="fw-bold text-dark">Classical AI Team Generator</h5>
                    <p class="text-muted small">
                        Combines Backtracking Search, MRV heuristics, Forward Checking, and Hill Climbing member-swap optimization directly in PHP with zero external API dependencies.
                    </p>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card-tf h-100 p-4 border card-tf-interactive">
                    <div class="stat-icon bg-info-subtle text-info mb-3">
                        <i class="bi bi-award-fill"></i>
                    </div>
                    <h5 class="fw-bold text-dark">Fair Team Leader Allocation</h5>
                    <p class="text-muted small">
                        Configurable leadership policies for open projects or official college hackathons. Ensures 1 leader per team with transparent verification and transfer protocols.
                    </p>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card-tf h-100 p-4 border card-tf-interactive">
                    <div class="stat-icon bg-success-subtle text-success mb-3">
                        <i class="bi bi-shield-lock-fill"></i>
                    </div>
                    <h5 class="fw-bold text-dark">Ephemeral Real-Time Chat</h5>
                    <p class="text-muted small">
                        Private team text discussions and scheduled timed sessions delivered via WebSockets with zero persistent message storage in MySQL for privacy.
                    </p>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card-tf h-100 p-4 border card-tf-interactive">
                    <div class="stat-icon bg-warning-subtle text-warning mb-3">
                        <i class="bi bi-calendar-check-fill"></i>
                    </div>
                    <h5 class="fw-bold text-dark">College Events & Enrollment</h5>
                    <p class="text-muted capacity">
                        Hackathons, coding symposiums, and project expos with participant limits, registration deadlines, and event-bound team rosters.
                    </p>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card-tf h-100 p-4 border card-tf-interactive">
                    <div class="stat-icon bg-purple-subtle text-purple mb-3" style="background:#f3e8ff; color:#7c3aed;">
                        <i class="bi bi-sliders2-vertical"></i>
                    </div>
                    <h5 class="fw-bold text-dark">Explainable AI Lab & Simulator</h5>
                    <p class="text-muted small">
                        Step-by-step visual execution trace displaying variable orderings, forward-checking prunings, and what-if parameter sensitivity analysis.
                    </p>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card-tf h-100 p-4 border card-tf-interactive">
                    <div class="stat-icon bg-danger-subtle text-danger mb-3">
                        <i class="bi bi-arrow-repeat"></i>
                    </div>
                    <h5 class="fw-bold text-dark">Smart Member Replacement</h5>
                    <p class="text-muted small">
                        When a teammate withdraws, the engine identifies exact missing skills and calculates surgical replacements without disrupting existing members.
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Call to Action Banner -->
<section class="py-5" style="background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%); color: white;">
    <div class="container py-4 text-center">
        <h2 class="display-6 fw-bold mb-3">Ready to Forge Your Winning Team?</h2>
        <p class="text-light opacity-75 mb-4" style="max-width: 580px; margin: 0 auto;">
            Join your fellow students, discover teammates with complementary skills, and build breakthrough projects.
        </p>
        <div class="d-flex justify-content-center gap-3">
            <a href="<?= BASE_URL ?>/register.php" class="btn btn-lg btn-tf-primary px-4">Create Student Account</a>
            <a href="<?= BASE_URL ?>/login.php" class="btn btn-lg btn-outline-light px-4">Sign In Now</a>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
