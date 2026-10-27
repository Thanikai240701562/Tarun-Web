-- =====================================================================
-- TeamForge Seed Data
-- Comprehensive realistic college demo dataset
-- Password for all accounts: Password123!
-- =====================================================================

USE `teamforge_db`;

-- 1. Insert Master Skills
INSERT INTO `skills` (`id`, `name`, `category`) VALUES
(1, 'PHP', 'backend'),
(2, 'MySQL', 'database'),
(3, 'JavaScript', 'frontend'),
(4, 'HTML & CSS', 'frontend'),
(5, 'Bootstrap 5', 'frontend'),
(6, 'React.js', 'frontend'),
(7, 'Node.js', 'backend'),
(8, 'Python', 'ai_algo'),
(9, 'Data Structures & Algorithms', 'ai_algo'),
(10, 'Graph Algorithms & CSP', 'ai_algo'),
(11, 'UI/UX Design & Figma', 'ui_ux'),
(12, 'RESTful APIs', 'backend'),
(13, 'Docker', 'devops'),
(14, 'Git & GitHub', 'general'),
(15, 'PostgreSQL', 'database'),
(16, 'MongoDB', 'database'),
(17, 'Software Testing & QA', 'testing'),
(18, 'Technical Documentation', 'general'),
(19, 'Flutter', 'mobile'),
(20, 'Tailwind CSS', 'frontend');

-- 2. Insert Users (Admin, Team Leaders, and Students)
-- Hash corresponds to 'Password123!'
SET @pwd = '$2y$10$vyvhPljT34fufBqjLQVZ6Okhwg8pQ7F8Zr8B/1aSPgyNTiE4eAFgu';

INSERT INTO `users` (`id`, `full_name`, `email`, `password_hash`, `role`, `student_id_number`, `department`, `degree`, `academic_year`, `availability_status`, `preferred_team_size`, `bio`, `portfolio_url`, `github_url`, `is_verified`) VALUES
-- Faculty / Event Organizer / Admin
(1, 'Dr. Sarah Mitchell', 'faculty@university.edu', @pwd, 'faculty_admin', 'FAC-2024-001', 'Computer Science & Engineering', 'Ph.D in AI & Algorithms', 'Postgraduate', 'available', 4, 'Department Chair and Hackathon Lead Faculty Mentor. Passionate about AI research and student innovation.', 'https://cs.university.edu/~smitchell', 'https://github.com/drsarahmitchell', 1),

-- Student / Team Leader 1
(2, 'Aarav Sharma', 'aarav.sharma@student.edu', @pwd, 'team_leader', 'CS23-0101', 'Computer Science & Engineering', 'B.Tech CSE', '3rd Year', 'in_team', 4, 'Full-stack developer with 2+ years experience in PHP, MySQL, and modern JavaScript. Love leading agile hackathon teams.', 'https://aaravsharma.dev', 'https://github.com/aaravsharma', 1),

-- Student / Team Leader 2
(3, 'Priya Patel', 'priya.patel@student.edu', @pwd, 'team_leader', 'IT23-0204', 'Information Technology', 'B.Tech IT', '4th Year', 'in_team', 4, 'AI and backend specialist. Experienced in discrete optimization, CSP, and distributed database architecture.', 'https://priyapatel.me', 'https://github.com/priyapatel-tech', 1),

-- Students (Diverse skills & roles)
(4, 'Rohan Verma', 'rohan.verma@student.edu', @pwd, 'student', 'CS23-0105', 'Computer Science & Engineering', 'B.Tech CSE', '3rd Year', 'available', 4, 'Frontend fanatic specializing in responsive UI, Bootstrap, and interactive charts. Looking for a high-energy hackathon team.', 'https://rohanv.design', 'https://github.com/rohanverma-dev', 1),
(5, 'anaya', 'ananya.iyer@student.edu', @pwd, 'student', 'AI24-0312', 'Artificial Intelligence & Data Science', 'B.Tech AI&DS', '2nd Year', 'available', 3, 'Passionate about algorithmic problem solving, search algorithms, and Python data pipelines.', 'https://ananyaiyer.com', 'https://github.com/ananya-ai', 1),
(6, 'Vikram Nair', 'vikram.nair@student.edu', @pwd, 'student', 'CS22-0044', 'Computer Science & Engineering', 'B.Tech CSE', '4th Year', 'available', 4, 'Database architect and query optimization enthusiast. Expert in MySQL, normalization, and relational integrity.', 'https://vikramnair.net', 'https://github.com/vikramnair-sql', 1),
(7, 'Sneha Kulkarni', 'sneha.kulkarni@student.edu', @pwd, 'student', 'IT23-0219', 'Information Technology', 'B.Tech IT', '3rd Year', 'available', 4, 'Product designer and UI/UX researcher. Turning complex algorithms into effortless user interfaces using Figma and CSS.', 'https://snehadesigns.co', 'https://github.com/snehakulkarni', 1),
(8, 'Kabir Mehta', 'kabir.mehta@student.edu', @pwd, 'student', 'CS24-0155', 'Computer Science & Engineering', 'B.Tech CSE', '2nd Year', 'available', 4, 'Backend developer who loves building robust REST APIs and WebSocket relay architectures.', 'https://kabirmehta.io', 'https://github.com/kabirmehta', 0),
(9, 'Neha Gupta', 'neha.gupta@student.edu', @pwd, 'student', 'ECE23-0402', 'Electronics & Communication', 'B.Tech ECE', '3rd Year', 'available', 5, 'Embedded systems and algorithmic computing. Looking to pair up with web developers for IoT hackathons.', 'https://nehagupta.dev', 'https://github.com/nehagupta-ece', 1),
(10, 'Devansh Joshi', 'devansh.joshi@student.edu', @pwd, 'student', 'CS23-0188', 'Computer Science & Engineering', 'B.Tech CSE', '3rd Year', 'available', 4, 'QA engineer and test automation specialist. Committed to bug-free codebases, edge case tests, and API validation.', 'https://devanshtesting.net', 'https://github.com/devansh-joshi', 1),
(11, 'Kavya Raman', 'kavya.raman@student.edu', @pwd, 'student', 'AI23-0305', 'Artificial Intelligence & Data Science', 'B.Tech AI&DS', '3rd Year', 'available', 4, 'Classical heuristic search, constraint programming, and technical documentation lead.', 'https://kavyaraman.tech', 'https://github.com/kavyaraman-ai', 1),
(12, 'Aditya Rao', 'aditya.rao@student.edu', @pwd, 'student', 'CS22-0012', 'Computer Science & Engineering', 'B.Tech CSE', '4th Year', 'available', 4, 'Senior web engineer with extensive full-stack experience in PHP/MySQL and client performance optimization.', 'https://adityarao.dev', 'https://github.com/adityarao-code', 1);

-- 3. Student Skills Mapping
INSERT INTO `student_skills` (`user_id`, `skill_id`, `proficiency_level`, `years_experience`) VALUES
-- Aarav (TL1 - Fullstack)
(2, 1, 5, 2.5), (2, 2, 4, 2.0), (2, 3, 5, 3.0), (2, 4, 5, 3.0), (2, 5, 4, 2.0), (2, 14, 4, 2.0),
-- Priya (TL2 - AI/Backend)
(3, 1, 4, 2.0), (3, 2, 5, 3.0), (3, 8, 5, 3.5), (3, 9, 5, 3.0), (3, 10, 5, 2.5), (3, 15, 4, 2.0),
-- Rohan (Frontend)
(4, 3, 5, 2.5), (4, 4, 5, 3.0), (4, 5, 5, 2.5), (4, 11, 4, 2.0), (4, 6, 4, 1.5),
-- Ananya (AI / Algorithms)
(5, 8, 5, 2.0), (5, 9, 5, 2.5), (5, 10, 4, 2.0), (5, 14, 3, 1.5),
-- Vikram (Database Specialist)
(6, 2, 5, 3.5), (6, 15, 5, 3.0), (6, 16, 4, 2.0), (6, 1, 3, 1.5), (6, 12, 4, 2.0),
-- Sneha (UI/UX)
(7, 11, 5, 3.0), (7, 4, 4, 2.0), (7, 5, 4, 2.0), (7, 20, 4, 1.5),
-- Kabir (Backend / APIs)
(8, 1, 4, 2.0), (8, 2, 4, 2.0), (8, 7, 4, 2.0), (8, 12, 5, 2.5), (8, 14, 4, 2.0),
-- Neha (General / Algorithms)
(9, 8, 4, 2.0), (9, 9, 4, 2.0), (9, 3, 3, 1.5), (9, 14, 3, 1.5),
-- Devansh (Testing / QA)
(10, 17, 5, 2.5), (10, 18, 5, 3.0), (10, 3, 3, 1.5), (10, 12, 4, 2.0),
-- Kavya (AI / Docs)
(11, 8, 4, 2.0), (11, 9, 5, 2.5), (11, 10, 4, 2.0), (11, 18, 5, 3.0),
-- Aditya (Fullstack Senior)
(12, 1, 5, 4.0), (12, 2, 5, 3.5), (12, 3, 5, 4.0), (12, 4, 5, 4.0), (12, 13, 4, 2.0);

-- 4. Student Projects
INSERT INTO `student_projects` (`user_id`, `title`, `description`, `role_played`, `technologies`, `project_url`, `is_verified`, `verified_by`) VALUES
(2, 'Campus Event Hub', 'Centralized portal for student club registration, automated ticketing, and attendance QR tracking.', 'Full-Stack Lead', 'PHP 8, MySQL, Bootstrap, Vanilla JS', 'https://github.com/aaravsharma/campus-hub', 1, 1),
(3, 'Constraint-Based Exam Timetable Generator', 'Implemented CSP with MRV and degree heuristic in Python to eliminate room and faculty scheduling conflicts.', 'Algorithm Architect', 'Python, CSP, MySQL, Flask', 'https://github.com/priyapatel-tech/exam-csp', 1, 1),
(4, 'SaaS Analytics Dashboard UI', 'Custom high-performance data dashboard featuring dark mode, accessible widgets, and SVG data visualizers.', 'Lead UI Developer', 'HTML5, CSS3, Chart.js, Bootstrap 5', 'https://github.com/rohanverma-dev/analytics-ui', 1, 1),
(6, 'High-Throughput Library Database', 'Normalized schema up to BCNF with partitioned transaction logs handling 50k borrow records.', 'Database Administrator', 'MySQL 8, Stored Procedures, Indexes', 'https://github.com/vikramnair-sql/library-db', 1, 1),
(7, 'Student Mentorship Mobile Redesign', 'Complete Figma design system with 40+ responsive screens, usability tested with 50 college students.', 'UI/UX Researcher', 'Figma, Adobe XD, Design Systems', 'https://figma.com/@snehadesigns', 1, 1);

-- 5. College Events
INSERT INTO `events` (`id`, `title`, `description`, `organizer_id`, `event_type`, `venue`, `event_date`, `reg_start_date`, `reg_end_date`, `max_participants`, `min_team_size`, `max_team_size`, `required_skills`, `eligibility_criteria`, `team_formation_policy`, `status`) VALUES
(1, 'HackNex 2026: 36-Hour AI & Web Hackathon', 'Premier national college hackathon focusing on practical AI tools, web platforms, and civic tech solutions. Teams will build fully functional prototypes evaluated by industry judges.', 1, 'hackathon', 'Main Campus Auditorium & Tech Innovation Labs', DATE_ADD(CURRENT_DATE, INTERVAL 20 DAY), DATE_SUB(CURRENT_DATE, INTERVAL 5 DAY), DATE_ADD(CURRENT_DATE, INTERVAL 10 DAY), 120, 3, 5, 'PHP, MySQL, JavaScript, UI/UX, AI/Algorithms', 'Open to all enrolled undergraduate engineering students.', 'open', 'active'),

(2, 'CodeFest 2026: Algorithmic Challenge & Symposium', 'Intensive collegiate problem solving tournament featuring classical algorithm benchmarks, search problems, and discrete optimization problems.', 1, 'coding_competition', 'Computing Center Lab 3', DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY), CURRENT_DATE, DATE_ADD(CURRENT_DATE, INTERVAL 15 DAY), 80, 2, 4, 'Data Structures & Algorithms, Python, Graph Algorithms', 'Open to 2nd, 3rd, and 4th year students.', 'open', 'active'),

(3, 'InnovateX: Annual Senior Capstone Expo', 'Grand exhibition of final-year engineering projects with external venture capital reviewers and university patent advisors.', 1, 'project_expo', 'Convention Pavilion Hall A', DATE_ADD(CURRENT_DATE, INTERVAL 45 DAY), DATE_SUB(CURRENT_DATE, INTERVAL 10 DAY), DATE_ADD(CURRENT_DATE, INTERVAL 25 DAY), 200, 3, 5, 'Full Stack, Database, Testing, Documentation', 'Senior undergraduate and postgraduate teams only.', 'faculty_approval', 'upcoming');

-- 6. Event Registrations
INSERT INTO `event_registrations` (`event_id`, `user_id`, `status`) VALUES
(1, 2, 'approved'),
(1, 4, 'approved'),
(1, 6, 'approved'),
(1, 7, 'approved'),
(1, 8, 'registered'),
(1, 10, 'registered'),
(2, 3, 'approved'),
(2, 5, 'approved'),
(2, 9, 'approved'),
(2, 11, 'registered');

-- 7. Teams
INSERT INTO `teams` (`id`, `name`, `project_title`, `project_description`, `project_category`, `event_id`, `leader_id`, `team_code`, `required_team_size`, `preferred_experience`, `project_deadline`, `visibility`, `formation_method`, `status`) VALUES
(1, 'ByteCrafters', 'Campus Resource Exchange', 'A peer-to-peer textbook, laboratory gear, and component exchange platform for students with real-time request handling and verification.', 'Web Application', 1, 2, 'BYTE-7842', 4, 'mixed', DATE_ADD(CURRENT_DATE, INTERVAL 20 DAY), 'public', 'hybrid', 'recruiting'),

(2, 'AlgoKnights', 'EcoTransit Route Optimization', 'Classical graph search and heuristic scheduling engine for campus green shuttle vans, minimizing idle fuel burn.', 'AI & Algorithms', 2, 3, 'ALGO-9311', 3, 'advanced', DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY), 'public', 'ai_generated', 'recruiting');

-- 8. Team Members
INSERT INTO `team_members` (`team_id`, `user_id`, `assigned_role`, `status`) VALUES
(1, 2, 'Team Leader & Full-Stack Developer', 'active'),
(1, 4, 'Frontend Developer & UI Specialist', 'active'),
(2, 3, 'Team Leader & Algorithm Developer', 'active'),
(2, 5, 'AI/Algorithm Developer', 'active');

-- 9. Team Skills Needed
INSERT INTO `team_skills` (`team_id`, `skill_id`, `is_required`) VALUES
(1, 1, 1), -- PHP
(1, 2, 1), -- MySQL
(1, 3, 1), -- JavaScript
(1, 11, 1), -- UI/UX
(2, 8, 1), -- Python
(2, 9, 1), -- Algorithms
(2, 10, 1); -- Graph/CSP

-- 10. Notifications
INSERT INTO `notifications` (`user_id`, `title`, `message`, `link`, `type`, `is_read`) VALUES
(2, 'Welcome to TeamForge', 'Your team ByteCrafters has been created! Use the AI Team Generator or Student Directory to recruit members.', 'teams/view.php?id=1', 'team', 0),
(4, 'Team Invitation Accepted', 'You are now an active member of ByteCrafters for HackNex 2026.', 'teams/view.php?id=1', 'invitation', 1),
(1, 'New Event Registrations', '10 students have registered for HackNex 2026. Review participant rosters.', 'events/view.php?id=1', 'event', 0);

-- 11. Project Tasks
INSERT INTO `project_tasks` (`id`, `team_id`, `title`, `description`, `category`, `status`, `priority`, `deadline`, `created_by`) VALUES
(1, 1, 'Architecture & Schema Blueprint', 'Finalize MySQL normalized tables for item listings, transactions, and user claims.', 'database', 'completed', 'high', DATE_ADD(CURRENT_DATE, INTERVAL 5 DAY), 2),
(2, 1, 'Bootstrap Responsive Layout', 'Build modular views for listing catalog, item cards, and search filters with accessible cards.', 'frontend', 'in_progress', 'high', DATE_ADD(CURRENT_DATE, INTERVAL 9 DAY), 2),
(3, 1, 'REST Authentication & Sessions', 'Implement secure registration, login, and CSRF protection handlers.', 'backend', 'not_started', 'urgent', DATE_ADD(CURRENT_DATE, INTERVAL 12 DAY), 2),
(4, 2, 'Graph Representation of Campus Grid', 'Formulate university nodes, intersections, and time-dependent edge cost weights.', 'ai_algo', 'in_progress', 'high', DATE_ADD(CURRENT_DATE, INTERVAL 8 DAY), 3);

-- 12. Task Assignments
INSERT INTO `task_assignments` (`task_id`, `user_id`) VALUES
(1, 2),
(2, 4),
(3, 2),
(4, 5);

-- 13. Audit Log
INSERT INTO `audit_logs` (`user_id`, `action`, `entity_type`, `entity_id`, `details_json`, `ip_address`) VALUES
(1, 'EVENT_CREATED', 'event', 1, '{"event":"HackNex 2026","max_participants":120}', '127.0.0.1'),
(2, 'TEAM_CREATED', 'team', 1, '{"name":"ByteCrafters","team_code":"BYTE-7842"}', '127.0.0.1');
