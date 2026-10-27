<?php
/**
 * TeamForge Global Constants & Configurations
 */

if (!defined('TEAMFORGE_APP')) {
    define('TEAMFORGE_APP', true);
}

// Environment & Base URL
define('APP_NAME', 'TeamForge');
define('APP_TAGLINE', 'Intelligent Student Team Formation Platform');
define('APP_VERSION', '1.0.0');

// Dynamic base URL detection for XAMPP
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
define('BASE_URL', $protocol . $host . '/teamforge');

// Database Credentials
define('DB_HOST', '127.0.0.1');
define('DB_PORT', 3306);
define('DB_NAME', 'teamforge_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// Session Security
define('SESSION_LIFETIME', 86400); // 24 hours
define('SESSION_COOKIE_NAME', 'TEAMFORGE_SESSID');

// User Roles
define('ROLE_STUDENT', 'student');
define('ROLE_TEAM_LEADER', 'team_leader');
define('ROLE_FACULTY_ADMIN', 'faculty_admin');

// Standard Project Roles
define('AVAILABLE_ROLES', [
    'Frontend Developer',
    'Backend Developer',
    'Database Developer',
    'UI/UX Designer',
    'Tester/QA',
    'Documentation',
    'AI/Algorithm Developer',
    'General Developer'
]);

// Skill Categories
define('SKILL_CATEGORIES', [
    'frontend' => 'Frontend Development',
    'backend' => 'Backend Development',
    'database' => 'Database Engineering',
    'ai_algo' => 'AI & Algorithms',
    'ui_ux' => 'UI / UX Design',
    'devops' => 'DevOps & Cloud',
    'mobile' => 'Mobile Apps',
    'testing' => 'Testing & QA',
    'general' => 'General / Tools'
]);

// Default AI Algorithm Weights for Multi-Objective Scoring
define('DEFAULT_AI_WEIGHTS', [
    'skill_coverage' => 0.35,      // Coverage of required hard skills
    'role_coverage' => 0.25,       // Coverage of target project roles
    'experience_balance' => 0.15,  // Mixture of junior & senior proficiencies
    'availability' => 0.15,        // Current bandwidth & active commitments
    'interest_alignment' => 0.10   // Department/interest match
]);

// WebSocket Server Details for Real-Time Ephemeral Chat
define('WS_HOST', 'localhost');
define('WS_PORT', 8088);
