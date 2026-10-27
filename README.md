# TeamForge — Intelligent Student Team Formation & Collaboration Platform

> **CS23531 — WEB PROGRAMMING Mini-Project**  
> **Department of Computer Science and Engineering**  
> **Rajalakshmi Engineering College (Autonomous), Thandalam, Chennai 602 105**  
> **Candidate:** Tarun Antony M (Register No. 240701556)  
> **Academic Session:** MAY 2026  

---

## 📌 Project Overview

**TeamForge** is a modern, responsive, full-stack university collaboration web platform engineered to eliminate the friction, skill mismatches, and interpersonal bottlenecks inherent in manual student team formation. 

Adhering to the **Design Thinking** methodology (**Empathize, Define, Ideate, Prototype, Test, Re-Design**), TeamForge combines a modern web frontend with a high-performance PHP/MySQL backend and an intelligent Constraint Satisfaction Problem (CSP) solver engine to automatically construct optimal, cross-functional student teams for collegiate hackathons, capstones, and course projects.

---

## 📂 Project Deliverables

| Deliverable | Path / Link | Description |
|---|---|---|
| **Project Report (PDF)** | [`Tarun_Antony_M_240701556_WEB.pdf`](Tarun_Antony_M_240701556_WEB.pdf) | Formal 43-page Design Thinking Mini-Project Report adhering to institutional formatting (Chapters 1–14, SDGs, 20 Figures, and IEEE References). |
| **Project Report (DOCX)** | [`Tarun_Antony_M_240701556_WEB.docx`](Tarun_Antony_M_240701556_WEB.docx) | Editable Microsoft Word source document with Roman numeral prelims (i–v) and decimal chapter pagination (1–38). |
| **Full-Stack Source Code** | [`code/`](code/) | Complete runnable application (HTML5, CSS3, Vanilla JavaScript, PHP 8.2+ API, MySQL 3NF database schema and seeds). |
| **UI Screenshots & Figures** | [`screenshots/`](screenshots/) | High-resolution UI captures and architectural mind map representing Figures 1 through 20 of the prototype stage. |

---

## 🚀 Key Features

1. **Role-Aware Authentication & Dashboards**
   - Secure session-based authentication with bcrypt password hashing.
   - Separate, dynamic dashboards for **Students**, **Team Leaders**, and **Faculty/Administrators**.
   - One-click pre-seeded demo login helpers for instant evaluation.

2. **Student Discovery & Skill Directory**
   - Searchable, filterable student catalog by department, technical skill, and availability.
   - Skill proficiency verification badges and direct team invitation modal dialogs.

3. **Intelligent Team Formation Engine**
   - Deterministic Constraint Satisfaction Problem (CSP) solver with Minimum Remaining Values (MRV) and Forward Checking.
   - Team balance optimization across four distinct technical domains (Frontend, Backend, Database, QA/Algorithms).
   - Interactive radar chart visualizations comparing candidate team proposals.

4. **Project Kanban Workspace**
   - Interactive task management board with status columns (*Backlog*, *In Progress*, *Review*, *Done*).
   - Task assignment, priority indicators, and real-time completion tracking.

5. **Real-Time Ephemeral Collaboration**
   - Privacy-centric ephemeral team messaging with zero persistent database message retention.
   - Standup and mentor review session scheduler with direct video conferencing links.

6. **Administrative & Audit Controls**
   - Institutional metrics dashboard, hackathon event management, and system audit logging.

---

## 🛠️ Technology Stack & Architecture

```
                    ┌────────────────────────────────────────────────────────┐
                    │               Client-Side Presentation Layer           │
                    │   HTML5 Semantic Layouts  •  Vanilla CSS3 Design System │
                    │   Responsive Bootstrap 5.3 Grid  •  Vanilla JS (ES6+)  │
                    └───────────────────────────┬────────────────────────────┘
                                                │ RESTful Fetch Requests (JSON)
                                                ▼
                    ┌────────────────────────────────────────────────────────┐
                    │             Server-Side Application Layer              │
                    │    PHP 8.2 Modular REST APIs  •  Session Security      │
                    │    Deterministic CSP Team Formation Engine (Backtrack) │
                    └───────────────────────────┬────────────────────────────┘
                                                │ Prepared Statements (PDO)
                                                ▼
                    ┌────────────────────────────────────────────────────────┐
                    │                    Data Storage Layer                  │
                    │       MySQL 8.0 / MariaDB Normalized 3NF Schema        │
                    │       12 Relational Tables with Foreign Key Integrity  │
                    └────────────────────────────────────────────────────────┘
```

- **Frontend**:
  - Semantic HTML5 structure across all modules (`index.html`, `dashboard.html`, `students.html`, `events.html`, `teams.html`, `ai-generator.html`, `tasks.html`, `chat.html`, `sessions.html`, `profile.html`, `admin.html`).
  - Vanilla CSS3 design system (`assets/css/main.css`) with custom CSS custom properties (variables), responsive flexbox/grid, and glassmorphism accents.
  - Asynchronous communication via native `fetch()` in `assets/js/api.js` and `assets/js/main.js`.
- **Backend**:
  - PHP 8.2+ procedural and object-oriented REST endpoints under `code/api/`.
  - Session-based access control, input sanitization, and CSRF mitigation.
  - Deterministic CSP Solver in `code/ai/TeamForgeCspEngine.php`.
- **Database**:
  - Normalized 3NF MySQL schema with relational constraints across users, profiles, skills, teams, memberships, tasks, events, and audit logs.

---

## 🔑 Pre-Seeded Demo Accounts

All pre-seeded demo accounts use the standard password: **`Password123!`**

| Account Role | User Name | Email Address | Focus Area / Responsibilities |
|---|---|---|---|
| **Faculty / Admin** | Dr. Sarah Mitchell | `faculty@university.edu` | Event management, project approvals, audit logs, analytics |
| **Team Leader 1** | Aarav Sharma | `aarav.sharma@student.edu` | Team lead (*ByteCrafters*), task manager, AI solver invoker |
| **Team Leader 2** | Priya Patel | `priya.patel@student.edu` | Team lead (*AlgoKnights*), discrete optimization specialist |
| **Student (Frontend)** | Rohan Verma | `rohan.verma@student.edu` | Responsive UI/UX, CSS architecture |
| **Student (Database)** | Vikram Nair | `vikram.nair@student.edu` | High-throughput MySQL normalization, indexing |
| **Student (AI/Algo)** | Ananya Iyer | `ananya.iyer@student.edu` | Graph search, heuristic solver verification |

---

## 💻 Local Setup & Installation

### Prerequisites
1. **XAMPP for Windows** (Apache + MySQL with PHP 8.2+).
2. Modern Web Browser (Google Chrome, Microsoft Edge, or Mozilla Firefox).

### 1. Web Root Configuration
To serve the project directly via Apache, open an Administrator Command Prompt or PowerShell terminal and create a directory junction pointing from Apache's `htdocs` to `code/`:
```cmd
mklink /J "C:\xampp\htdocs\teamforge" "C:\path\to\tarun-web\code"
```
*Alternatively, simply copy the `code/` directory into `C:\xampp\htdocs\teamforge`.*

### 2. Database Initialization
1. Start **Apache** and **MySQL** services in the XAMPP Control Panel.
2. Open your browser and navigate to **phpMyAdmin** (`http://localhost/phpmyadmin`).
3. Create a new database named `teamforge_db` with collation `utf8mb4_unicode_ci`.
4. Import the following SQL files in sequence:
   - `code/database/schema.sql` (Creates 12 relational 3NF tables)
   - `code/database/seed_data.sql` (Populates pre-seeded users, events, teams, and skills)
   
*Or run the automated setup CLI script:*
```powershell
php "code/database/setup_db.php"
```

### 3. Accessing the Application
Launch your web browser and navigate to:
**`http://localhost/teamforge/`**

---

## 📊 Alignment with UN Sustainable Development Goals (SDGs)

This project directly contributes to the United Nations Sustainable Development Goals:
- **SDG 4 (Quality Education):** Fosters equitable peer learning, eliminating isolation and skill disparity in project-based academic curricula.
- **SDG 8 (Decent Work & Economic Growth):** Equips undergraduate engineering students with collaborative skills, agile workflows, and project execution experience required by modern technology industries.
- **SDG 9 (Industry, Innovation & Infrastructure):** Implements modern web standards, decoupled API architectures, and deterministic optimization algorithms for academic institutions.

---

## 📜 Academic Verification

- **Course:** CS23531 — Web Programming
- **Institution:** Rajalakshmi Engineering College (Autonomous), Thandalam, Chennai
- **Student Name:** Tarun Antony M
- **Register Number:** 240701556
- **Submission Date:** MAY 2026
