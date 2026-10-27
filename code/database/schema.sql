-- =====================================================================
-- TeamForge Database Schema
-- Classical AI Powered Student Team Formation Platform
-- Engine: InnoDB, Charset: utf8mb4_unicode_ci
-- Note: Ephemeral Chat messages are intentionally NOT stored in MySQL
-- =====================================================================

CREATE DATABASE IF NOT EXISTS `teamforge_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `teamforge_db`;

-- Drop existing tables in reverse dependency order if rebuilding
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `audit_logs`;
DROP TABLE IF EXISTS `session_attendance`;
DROP TABLE IF EXISTS `team_sessions`;
DROP TABLE IF EXISTS `optimization_steps`;
DROP TABLE IF EXISTS `optimization_runs`;
DROP TABLE IF EXISTS `task_assignments`;
DROP TABLE IF EXISTS `project_tasks`;
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `join_requests`;
DROP TABLE IF EXISTS `team_invitations`;
DROP TABLE IF EXISTS `team_skills`;
DROP TABLE IF EXISTS `team_members`;
DROP TABLE IF EXISTS `teams`;
DROP TABLE IF EXISTS `event_registrations`;
DROP TABLE IF EXISTS `events`;
DROP TABLE IF EXISTS `student_projects`;
DROP TABLE IF EXISTS `student_skills`;
DROP TABLE IF EXISTS `skills`;
DROP TABLE IF EXISTS `users`;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. Users Table
CREATE TABLE `users` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `full_name` VARCHAR(120) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('student', 'team_leader', 'faculty_admin') NOT NULL DEFAULT 'student',
  `avatar_url` VARCHAR(255) DEFAULT NULL,
  `student_id_number` VARCHAR(50) DEFAULT NULL,
  `department` VARCHAR(100) DEFAULT 'Computer Science & Engineering',
  `degree` VARCHAR(50) DEFAULT 'B.Tech / B.E.',
  `academic_year` ENUM('1st Year', '2nd Year', '3rd Year', '4th Year', 'Postgraduate') DEFAULT '3rd Year',
  `availability_status` ENUM('available', 'busy', 'in_team') DEFAULT 'available',
  `preferred_team_size` TINYINT UNSIGNED DEFAULT 4,
  `bio` TEXT DEFAULT NULL,
  `portfolio_url` VARCHAR(255) DEFAULT NULL,
  `github_url` VARCHAR(255) DEFAULT NULL,
  `is_verified` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_users_role` (`role`),
  INDEX `idx_users_dept_year` (`department`, `academic_year`),
  INDEX `idx_users_availability` (`availability_status`)
) ENGINE=InnoDB;

-- 2. Master Skills Table
CREATE TABLE `skills` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(80) NOT NULL UNIQUE,
  `category` ENUM('frontend', 'backend', 'database', 'ai_algo', 'ui_ux', 'devops', 'mobile', 'testing', 'general') DEFAULT 'general',
  INDEX `idx_skills_category` (`category`)
) ENGINE=InnoDB;

-- 3. Student Skills (Proficiency 1-5, Experience in Years)
CREATE TABLE `student_skills` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `skill_id` INT UNSIGNED NOT NULL,
  `proficiency_level` TINYINT UNSIGNED NOT NULL DEFAULT 3 COMMENT 'Scale 1-5',
  `years_experience` DECIMAL(3,1) UNSIGNED DEFAULT 1.0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_user_skill` (`user_id`, `skill_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`skill_id`) REFERENCES `skills`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 4. Student Projects
CREATE TABLE `student_projects` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(180) NOT NULL,
  `description` TEXT NOT NULL,
  `role_played` VARCHAR(100) NOT NULL,
  `technologies` VARCHAR(255) NOT NULL,
  `project_url` VARCHAR(255) DEFAULT NULL,
  `is_verified` TINYINT(1) DEFAULT 0,
  `verified_by` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`verified_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_projects_user` (`user_id`)
) ENGINE=InnoDB;

-- 5. College Events
CREATE TABLE `events` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(200) NOT NULL,
  `description` TEXT NOT NULL,
  `organizer_id` INT UNSIGNED NOT NULL,
  `event_type` ENUM('hackathon', 'symposium', 'coding_competition', 'project_expo', 'workshop', 'innovation_challenge') NOT NULL,
  `venue` VARCHAR(150) NOT NULL,
  `event_date` DATE NOT NULL,
  `reg_start_date` DATE NOT NULL,
  `reg_end_date` DATE NOT NULL,
  `max_participants` INT UNSIGNED NOT NULL DEFAULT 100,
  `min_team_size` TINYINT UNSIGNED NOT NULL DEFAULT 2,
  `max_team_size` TINYINT UNSIGNED NOT NULL DEFAULT 5,
  `required_skills` VARCHAR(255) DEFAULT NULL,
  `eligibility_criteria` TEXT DEFAULT NULL,
  `team_formation_policy` ENUM('open', 'faculty_approval', 'strict_leader') DEFAULT 'open',
  `status` ENUM('upcoming', 'active', 'registration_closed', 'completed', 'suspended') DEFAULT 'active',
  `banner_url` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`organizer_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
  INDEX `idx_events_status_date` (`status`, `event_date`)
) ENGINE=InnoDB;

-- 6. Event Registrations
CREATE TABLE `event_registrations` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `event_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `status` ENUM('registered', 'approved', 'waitlist', 'cancelled') DEFAULT 'registered',
  `registered_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_event_user` (`event_id`, `user_id`),
  FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_registrations_event` (`event_id`)
) ENGINE=InnoDB;

-- 7. Teams
CREATE TABLE `teams` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `project_title` VARCHAR(200) NOT NULL,
  `project_description` TEXT NOT NULL,
  `project_category` VARCHAR(100) DEFAULT 'Web & AI Development',
  `event_id` INT UNSIGNED DEFAULT NULL,
  `leader_id` INT UNSIGNED NOT NULL,
  `team_code` VARCHAR(16) NOT NULL UNIQUE,
  `required_team_size` TINYINT UNSIGNED NOT NULL DEFAULT 4,
  `preferred_experience` ENUM('beginner', 'intermediate', 'advanced', 'mixed') DEFAULT 'mixed',
  `project_deadline` DATE DEFAULT NULL,
  `visibility` ENUM('public', 'private') DEFAULT 'public',
  `formation_method` ENUM('manual', 'ai_generated', 'hybrid') DEFAULT 'manual',
  `status` ENUM('recruiting', 'locked', 'completed', 'archived') DEFAULT 'recruiting',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`leader_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
  INDEX `idx_teams_code` (`team_code`),
  INDEX `idx_teams_status` (`status`)
) ENGINE=InnoDB;

-- 8. Team Members
CREATE TABLE `team_members` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `team_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `assigned_role` VARCHAR(80) NOT NULL DEFAULT 'General Developer',
  `joined_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `status` ENUM('active', 'inactive', 'withdrawn') DEFAULT 'active',
  UNIQUE KEY `uk_team_user` (`team_id`, `user_id`),
  FOREIGN KEY (`team_id`) REFERENCES `teams`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_members_team` (`team_id`),
  INDEX `idx_members_user` (`user_id`)
) ENGINE=InnoDB;

-- 9. Team Required Skills
CREATE TABLE `team_skills` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `team_id` INT UNSIGNED NOT NULL,
  `skill_id` INT UNSIGNED NOT NULL,
  `is_required` TINYINT(1) DEFAULT 1,
  UNIQUE KEY `uk_team_skill` (`team_id`, `skill_id`),
  FOREIGN KEY (`team_id`) REFERENCES `teams`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`skill_id`) REFERENCES `skills`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 10. Team Invitations
CREATE TABLE `team_invitations` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `team_id` INT UNSIGNED NOT NULL,
  `sender_id` INT UNSIGNED NOT NULL,
  `recipient_id` INT UNSIGNED NOT NULL,
  `invited_role` VARCHAR(80) DEFAULT 'General Developer',
  `status` ENUM('pending', 'accepted', 'rejected', 'withdrawn', 'expired') DEFAULT 'pending',
  `message` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`team_id`) REFERENCES `teams`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`sender_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`recipient_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_invites_recipient` (`recipient_id`, `status`)
) ENGINE=InnoDB;

-- 11. Join Requests
CREATE TABLE `join_requests` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `team_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `requested_role` VARCHAR(80) DEFAULT 'General Developer',
  `status` ENUM('pending', 'accepted', 'rejected', 'withdrawn') DEFAULT 'pending',
  `message` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_team_user_req` (`team_id`, `user_id`),
  FOREIGN KEY (`team_id`) REFERENCES `teams`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_requests_team` (`team_id`, `status`)
) ENGINE=InnoDB;

-- 12. In-App Notifications
CREATE TABLE `notifications` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(150) NOT NULL,
  `message` VARCHAR(255) NOT NULL,
  `link` VARCHAR(255) DEFAULT NULL,
  `type` ENUM('invitation', 'join_request', 'event', 'team', 'task', 'session', 'system') DEFAULT 'system',
  `is_read` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_notifications_user_read` (`user_id`, `is_read`)
) ENGINE=InnoDB;

-- 13. Project Tasks
CREATE TABLE `project_tasks` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `team_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(180) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `category` ENUM('frontend', 'backend', 'database', 'ui_ux', 'testing', 'documentation', 'presentation', 'general') DEFAULT 'general',
  `status` ENUM('not_started', 'in_progress', 'completed') DEFAULT 'not_started',
  `priority` ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
  `deadline` DATE DEFAULT NULL,
  `created_by` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`team_id`) REFERENCES `teams`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
  INDEX `idx_tasks_team_status` (`team_id`, `status`)
) ENGINE=InnoDB;

-- 14. Task Assignments
CREATE TABLE `task_assignments` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `task_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `assigned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_task_user` (`task_id`, `user_id`),
  FOREIGN KEY (`task_id`) REFERENCES `project_tasks`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 15. Classical AI Optimization Runs
CREATE TABLE `optimization_runs` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `team_id` INT UNSIGNED DEFAULT NULL,
  `run_name` VARCHAR(150) NOT NULL,
  `algorithm_used` VARCHAR(100) NOT NULL DEFAULT 'Backtracking_MRV_HillClimbing',
  `input_params_json` LONGTEXT NOT NULL,
  `best_score` DECIMAL(5,2) DEFAULT 0.00,
  `generated_assignments_json` LONGTEXT NOT NULL,
  `status` ENUM('success', 'conflict_detected', 'failed') DEFAULT 'success',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`team_id`) REFERENCES `teams`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 16. Explainable Optimization Steps (For AI Lab execution trace)
CREATE TABLE `optimization_steps` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `run_id` INT UNSIGNED NOT NULL,
  `step_number` INT UNSIGNED NOT NULL,
  `step_type` ENUM('mrv_select', 'forward_check', 'backtrack', 'greedy_assign', 'hill_climb_swap', 'constraint_violation', 'solution_found') NOT NULL,
  `description` TEXT NOT NULL,
  `current_score` DECIMAL(5,2) DEFAULT 0.00,
  `state_snapshot_json` LONGTEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`run_id`) REFERENCES `optimization_runs`(`id`) ON DELETE CASCADE,
  INDEX `idx_opt_run_step` (`run_id`, `step_number`)
) ENGINE=InnoDB;

-- 17. Scheduled Timed Team Chat Sessions
CREATE TABLE `team_sessions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `team_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(180) NOT NULL,
  `agenda` TEXT NOT NULL,
  `session_date` DATE NOT NULL,
  `start_time` TIME NOT NULL,
  `end_time` TIME NOT NULL,
  `status` ENUM('scheduled', 'open', 'completed', 'cancelled') DEFAULT 'scheduled',
  `created_by` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`team_id`) REFERENCES `teams`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
  INDEX `idx_sessions_team_date` (`team_id`, `session_date`)
) ENGINE=InnoDB;

-- 18. Session Attendance
CREATE TABLE `session_attendance` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `session_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `joined_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `left_at` TIMESTAMP NULL DEFAULT NULL,
  UNIQUE KEY `uk_session_user` (`session_id`, `user_id`),
  FOREIGN KEY (`session_id`) REFERENCES `team_sessions`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 19. Security and Audit Logs
CREATE TABLE `audit_logs` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `action` VARCHAR(100) NOT NULL,
  `entity_type` VARCHAR(50) NOT NULL,
  `entity_id` INT UNSIGNED DEFAULT NULL,
  `details_json` TEXT DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_audit_action` (`action`, `created_at`)
) ENGINE=InnoDB;
