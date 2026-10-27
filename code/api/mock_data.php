<?php
/**
 * TeamForge Mock Data Provider
 * Supplies pre-seeded demo data when MySQL is not running or offline
 */

function getMockUsers(): array {
    return [
        [
            'id' => 1,
            'full_name' => 'Dr. Sarah Mitchell',
            'email' => 'faculty@university.edu',
            'role' => 'faculty_admin',
            'student_id_number' => 'FAC-2024-001',
            'department' => 'Computer Science & Engineering',
            'academic_year' => 'Postgraduate',
            'availability_status' => 'available',
            'bio' => 'Department Chair and Hackathon Lead Faculty Mentor. Passionate about AI research and student innovation.',
            'avatar_url' => null,
            'skills' => ['Python', 'Graph Algorithms & CSP', 'Data Structures & Algorithms']
        ],
        [
            'id' => 2,
            'full_name' => 'Aarav Sharma',
            'email' => 'aarav.sharma@student.edu',
            'role' => 'team_leader',
            'student_id_number' => 'CS23-0101',
            'department' => 'Computer Science & Engineering',
            'academic_year' => '3rd Year',
            'availability_status' => 'in_team',
            'bio' => 'Full-stack developer with 2+ years experience in PHP, MySQL, and modern JavaScript. Love leading agile hackathon teams.',
            'avatar_url' => null,
            'skills' => ['PHP', 'MySQL', 'JavaScript', 'HTML & CSS', 'Bootstrap 5', 'Git & GitHub']
        ],
        [
            'id' => 3,
            'full_name' => 'Priya Patel',
            'email' => 'priya.patel@student.edu',
            'role' => 'team_leader',
            'student_id_number' => 'IT23-0204',
            'department' => 'Information Technology',
            'academic_year' => '4th Year',
            'availability_status' => 'in_team',
            'bio' => 'AI and backend specialist. Experienced in discrete optimization, CSP, and distributed database architecture.',
            'avatar_url' => null,
            'skills' => ['Python', 'Graph Algorithms & CSP', 'MySQL', 'PHP', 'PostgreSQL']
        ],
        [
            'id' => 4,
            'full_name' => 'Rohan Verma',
            'email' => 'rohan.verma@student.edu',
            'role' => 'student',
            'student_id_number' => 'CS23-0105',
            'department' => 'Computer Science & Engineering',
            'academic_year' => '3rd Year',
            'availability_status' => 'available',
            'bio' => 'Frontend fanatic specializing in responsive UI, Bootstrap, and interactive charts. Looking for a high-energy hackathon team.',
            'avatar_url' => null,
            'skills' => ['JavaScript', 'HTML & CSS', 'Bootstrap 5', 'UI/UX Design & Figma', 'React.js']
        ],
        [
            'id' => 5,
            'full_name' => 'anaya',
            'email' => 'ananya.iyer@student.edu',
            'role' => 'student',
            'student_id_number' => 'AI24-0312',
            'department' => 'Artificial Intelligence & Data Science',
            'academic_year' => '2nd Year',
            'availability_status' => 'available',
            'bio' => 'Passionate about algorithmic problem solving, search algorithms, and Python data pipelines.',
            'avatar_url' => null,
            'skills' => ['Python', 'Data Structures & Algorithms', 'Graph Algorithms & CSP', 'Git & GitHub']
        ],
        [
            'id' => 6,
            'full_name' => 'Vikram Nair',
            'email' => 'vikram.nair@student.edu',
            'role' => 'student',
            'student_id_number' => 'CS22-0044',
            'department' => 'Computer Science & Engineering',
            'academic_year' => '4th Year',
            'availability_status' => 'available',
            'bio' => 'Database architect and query optimization enthusiast. Expert in MySQL, normalization, and relational integrity.',
            'avatar_url' => null,
            'skills' => ['MySQL', 'PostgreSQL', 'MongoDB', 'PHP', 'RESTful APIs']
        ],
        [
            'id' => 7,
            'full_name' => 'Sneha Kulkarni',
            'email' => 'sneha.kulkarni@student.edu',
            'role' => 'student',
            'student_id_number' => 'IT23-0219',
            'department' => 'Information Technology',
            'academic_year' => '3rd Year',
            'availability_status' => 'available',
            'bio' => 'Product designer and UI/UX researcher. Turning complex algorithms into effortless user interfaces using Figma and CSS.',
            'avatar_url' => null,
            'skills' => ['UI/UX Design & Figma', 'HTML & CSS', 'Bootstrap 5', 'Tailwind CSS']
        ],
        [
            'id' => 8,
            'full_name' => 'Kabir Mehta',
            'email' => 'kabir.mehta@student.edu',
            'role' => 'student',
            'student_id_number' => 'CS24-0155',
            'department' => 'Computer Science & Engineering',
            'academic_year' => '2nd Year',
            'availability_status' => 'available',
            'bio' => 'Backend developer who loves building robust REST APIs and WebSocket relay architectures.',
            'avatar_url' => null,
            'skills' => ['PHP', 'Node.js', 'RESTful APIs', 'MySQL']
        ]
    ];
}

function getMockEvents(): array {
    return [
        [
            'id' => 1,
            'title' => 'Inter-College AI & Web Hackathon 2026',
            'slug' => 'inter-college-ai-web-hackathon-2026',
            'description' => 'Annual university-wide 36-hour hackathon focused on Classical AI algorithms, intelligent web systems, and collaborative developer platforms. 50+ college teams participating.',
            'department' => 'Computer Science & Engineering',
            'event_date' => '2026-11-15 09:00:00',
            'registration_deadline' => '2026-11-10 23:59:59',
            'min_team_size' => 3,
            'max_team_size' => 5,
            'status' => 'active',
            'participant_count' => 84,
            'tags' => ['AI/ML', 'Web Dev', 'Hackathon']
        ],
        [
            'id' => 2,
            'title' => 'Discrete Optimization & Algorithm Challenge',
            'slug' => 'discrete-optimization-algorithm-challenge',
            'description' => 'Solve NP-hard scheduling, CSP graph coloring, and heuristic routing challenges under strict time benchmarks. Individual and 2-person teams.',
            'department' => 'Artificial Intelligence & Data Science',
            'event_date' => '2026-12-05 10:00:00',
            'registration_deadline' => '2026-11-28 23:59:59',
            'min_team_size' => 2,
            'max_team_size' => 3,
            'status' => 'active',
            'participant_count' => 42,
            'tags' => ['Algorithms', 'CSP', 'Competitive Coding']
        ],
        [
            'id' => 3,
            'title' => 'Annual Capstone Project Showcase 2026',
            'slug' => 'annual-capstone-project-showcase-2026',
            'description' => 'Mandatory academic senior project formation and presentation round. Evaluated by department faculty and invited industry judges.',
            'department' => 'All Engineering Departments',
            'event_date' => '2027-01-20 09:00:00',
            'registration_deadline' => '2026-12-15 23:59:59',
            'min_team_size' => 4,
            'max_team_size' => 5,
            'status' => 'active',
            'participant_count' => 160,
            'tags' => ['Capstone', 'Academic', 'Senior Projects']
        ]
    ];
}

function getMockTeams(): array {
    return [
        [
            'id' => 1,
            'name' => 'ByteCrafters',
            'project_title' => 'TeamForge — Classical AI Multi-Agent Platform',
            'description' => 'Building an intelligent team-formation platform using pure PHP CSP algorithms, responsive modern UI, and ephemeral chat.',
            'leader_id' => 2,
            'leader_name' => 'Aarav Sharma',
            'event_id' => 1,
            'event_name' => 'Inter-College AI & Web Hackathon 2026',
            'status' => 'forming',
            'min_size' => 3,
            'max_size' => 4,
            'members' => [
                ['id' => 2, 'name' => 'Aarav Sharma', 'role' => 'Team Leader / Fullstack', 'department' => 'CSE', 'email' => 'aarav.sharma@student.edu'],
                ['id' => 4, 'name' => 'Rohan Verma', 'role' => 'Frontend Developer', 'department' => 'CSE', 'email' => 'rohan.verma@student.edu'],
                ['id' => 6, 'name' => 'Vikram Nair', 'role' => 'Database Architect', 'department' => 'CSE', 'email' => 'vikram.nair@student.edu']
            ],
            'missing_skills' => ['Data Structures & Algorithms', 'Graph Algorithms & CSP']
        ],
        [
            'id' => 2,
            'name' => 'AlgoKnights',
            'project_title' => 'Autonomous Route Planner using CSP & Backtracking',
            'description' => 'Constrained graph routing with MRV forward-checking search.',
            'leader_id' => 3,
            'leader_name' => 'Priya Patel',
            'event_id' => 2,
            'event_name' => 'Discrete Optimization & Algorithm Challenge',
            'status' => 'forming',
            'min_size' => 2,
            'max_size' => 3,
            'members' => [
                ['id' => 3, 'name' => 'Priya Patel', 'role' => 'Team Leader / AI Specialist', 'department' => 'IT', 'email' => 'priya.patel@student.edu'],
                ['id' => 5, 'name' => 'anaya', 'role' => 'Algorithm Developer', 'department' => 'AI&DS', 'email' => 'ananya.iyer@student.edu']
            ],
            'missing_skills' => ['Technical Documentation']
        ]
    ];
}
