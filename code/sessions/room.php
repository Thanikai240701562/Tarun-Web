<?php
/**
 * TeamForge Timed Session Room (Section 16)
 * Real-time text discussion with live attendance tracking and countdown timer.
 */
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

requireAuth();
$currentUser = currentUser();
$db = getDB();

$sessionId = (int)($_GET['id'] ?? 0);
if ($sessionId <= 0) {
    setFlash('danger', 'Invalid session ID.');
    header('Location: ' . BASE_URL . '/sessions/index.php');
    exit;
}

// Fetch session and team info
$sessStmt = $db->prepare("
    SELECT ts.*, t.name as team_name, t.leader_id, t.team_code
    FROM team_sessions ts
    JOIN teams t ON ts.team_id = t.id
    WHERE ts.id = ?
    LIMIT 1
");
$sessStmt->execute([$sessionId]);
$session = $sessStmt->fetch();

if (!$session) {
    setFlash('danger', 'Session not found.');
    header('Location: ' . BASE_URL . '/sessions/index.php');
    exit;
}

// Server-Side Membership Check
if (!isUserMemberOf($currentUser['id'], (int)$session['team_id']) && $currentUser['role'] !== ROLE_FACULTY_ADMIN) {
    setFlash('danger', 'Access denied. You are not a member of this team.');
    header('Location: ' . BASE_URL . '/sessions/index.php');
    exit;
}

$isLeader = ((int)$session['leader_id'] === (int)$currentUser['id']);

// Handle End Session (Leader only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'end_session' && $isLeader) {
    if (verify_csrf()) {
        $db->prepare("UPDATE team_sessions SET status = 'completed' WHERE id = ?")->execute([$sessionId]);
        setFlash('info', 'Session ended by team leader.');
    }
    header('Location: ' . BASE_URL . '/sessions/index.php?team_id=' . $session['team_id']);
    exit;
}

// 1. Record Attendance on join
$attStmt = $db->prepare("
    INSERT INTO session_attendance (session_id, user_id, joined_at)
    VALUES (?, ?, CURRENT_TIMESTAMP)
    ON DUPLICATE KEY UPDATE joined_at = joined_at
");
$attStmt->execute([$sessionId, $currentUser['id']]);

// 2. Fetch Attendees vs Absentees
$allMembersStmt = $db->prepare("
    SELECT tm.user_id, u.full_name, u.avatar_url, tm.assigned_role, sa.joined_at
    FROM team_members tm
    JOIN users u ON tm.user_id = u.id
    LEFT JOIN session_attendance sa ON (sa.session_id = ? AND sa.user_id = tm.user_id)
    WHERE tm.team_id = ? AND tm.status = 'active'
");
$allMembersStmt->execute([$sessionId, $session['team_id']]);
$roster = $allMembersStmt->fetchAll();

$present = [];
$absent = [];
foreach ($roster as $r) {
    if (!empty($r['joined_at'])) {
        $present[] = $r;
    } else {
        $absent[] = $r;
    }
}

// Calculate countdown
$endDateTime = $session['session_date'] . ' ' . $session['end_time'];

$pageTitle = e($session['title']) . " — Live Session Room";
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-0">
    <!-- Session Room Banner -->
    <div class="card-tf shadow-sm mb-4">
        <div class="p-4" style="background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%); color: white; border-radius: 12px 12px 0 0;">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-2">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-danger animate-pulse"><i class="bi bi-broadcast me-1"></i> Live Timed Session</span>
                    <span class="badge bg-light text-dark font-mono"><?= e($session['team_name']) ?></span>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <div class="text-end">
                        <div class="small text-light opacity-75">Session Time Left:</div>
                        <div class="fs-5 fw-extrabold text-warning font-mono" id="sessionCountdown">--:--:--</div>
                    </div>
                    <?php if ($isLeader): ?>
                        <form method="POST" action="<?= BASE_URL ?>/sessions/room.php?id=<?= $sessionId ?>" onsubmit="return confirm('End this live session now?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="end_session">
                            <button type="submit" class="btn btn-sm btn-outline-danger text-white">End Session</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <h3 class="fw-bold text-white mb-1"><?= e($session['title']) ?></h3>
            <div class="text-light opacity-75 small">
                <i class="bi bi-card-text me-1"></i> Agenda: <?= e($session['agenda']) ?>
            </div>
        </div>

        <div class="p-2 bg-white border-top d-flex justify-content-between align-items-center px-4">
            <span class="text-muted small" style="font-size:11px;">
                <i class="bi bi-shield-check text-success me-1"></i> Ephemeral text mode active &bull; Attendance recorded
            </span>
            <a href="<?= BASE_URL ?>/sessions/index.php?team_id=<?= $session['team_id'] ?>" class="text-muted small text-decoration-none">
                &larr; Exit Room
            </a>
        </div>
    </div>

    <!-- Main Room Grid: Attendance on Left, Live Chat on Right -->
    <div class="row g-3">
        <!-- Attendance Side Panel -->
        <div class="col-lg-4 col-xl-3">
            <div class="card-tf p-3 bg-white shadow-sm mb-3">
                <h6 class="fw-bold text-dark mb-2 d-flex align-items-center justify-content-between" style="font-size:13px;">
                    <span><i class="bi bi-person-check-fill text-success me-1"></i> Present (<?= count($present) ?>)</span>
                    <span class="badge bg-success-subtle text-success">Attending</span>
                </h6>
                <div class="d-flex flex-column gap-2 mb-3">
                    <?php foreach ($present as $p): ?>
                        <div class="d-flex align-items-center gap-2 p-1">
                            <?= getAvatar($p, 28) ?>
                            <div class="overflow-hidden">
                                <div class="fw-bold text-dark text-truncate" style="font-size:12px;"><?= e($p['full_name']) ?></div>
                                <div class="text-muted" style="font-size:10px;"><?= e($p['assigned_role']) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if (!empty($absent)): ?>
                    <h6 class="fw-bold text-dark mb-2 border-top pt-2" style="font-size:13px;">
                        <span><i class="bi bi-person-x-fill text-danger me-1"></i> Absent / Not Joined (<?= count($absent) ?>)</span>
                    </h6>
                    <div class="d-flex flex-column gap-2">
                        <?php foreach ($absent as $ab): ?>
                            <div class="d-flex align-items-center gap-2 p-1 opacity-75">
                                <?= getAvatar($ab, 28) ?>
                                <div class="overflow-hidden">
                                    <div class="text-secondary text-truncate" style="font-size:12px;"><?= e($ab['full_name']) ?></div>
                                    <div class="text-muted" style="font-size:10px;"><?= e($ab['assigned_role']) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Live Discussion Stream -->
        <div class="col-lg-8 col-xl-9">
            <div class="card-tf d-flex flex-column shadow-sm bg-white" style="height: 600px;">
                <div class="p-3 border-bottom d-flex align-items-center justify-content-between">
                    <span class="fw-bold small text-dark"><i class="bi bi-chat-text-fill text-primary me-2"></i>Live Session Discussion</span>
                    <span class="badge bg-light text-dark border font-mono small"><?= count($present) ?> Active in Room</span>
                </div>

                <!-- Chat Stream -->
                <div class="p-3 flex-grow-1 overflow-auto bg-light" id="sessionChatStream" style="scroll-behavior: smooth;">
                    <div class="text-center py-4 text-muted small" id="sessionPlaceholder">
                        <i class="bi bi-chat-quote fs-3 d-block mb-1 text-secondary opacity-50"></i>
                        Session room is open. Exchange ideas with your team.
                    </div>
                </div>

                <!-- Chat Input -->
                <div class="p-3 border-top bg-white">
                    <form id="sessionChatForm" class="d-flex align-items-center gap-2">
                        <input type="text" id="sessionChatInput" class="form-control" placeholder="Share your update on the agenda..." maxlength="1000" autocomplete="off" required>
                        <button type="submit" class="btn btn-tf-primary px-4">
                            Send <i class="bi bi-send-fill ms-1"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const sessionEnd = new Date("<?= date('Y-m-d H:i:s', strtotime($endDateTime)) ?>").getTime();
const currentUserId = <?= (int)$currentUser['id'] ?>;
const teamId = <?= (int)$session['team_id'] ?>;
const chatStream = document.getElementById('sessionChatStream');
const chatInput = document.getElementById('sessionChatInput');
const chatForm = document.getElementById('sessionChatForm');
let lastMessageId = 0;

// Countdown Timer
function updateCountdown() {
    const now = new Date().getTime();
    const distance = sessionEnd - now;
    if (distance <= 0) {
        document.getElementById('sessionCountdown').textContent = "CLOSED";
        return;
    }
    const hours = Math.floor(distance / (1000 * 60 * 60));
    const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
    const seconds = Math.floor((distance % (1000 * 60)) / 1000);
    document.getElementById('sessionCountdown').textContent = 
        String(hours).padStart(2, '0') + ":" + 
        String(minutes).padStart(2, '0') + ":" + 
        String(seconds).padStart(2, '0');
}
setInterval(updateCountdown, 1000);
updateCountdown();

// Render message bubble
function appendMessage(msg) {
    const isMe = (msg.user_id === currentUserId);
    const placeholder = document.getElementById('sessionPlaceholder');
    if (placeholder) placeholder.remove();

    const bubbleHtml = `
        <div class="d-flex mb-3 ${isMe ? 'justify-content-end' : 'justify-content-start'}">
            <div style="max-width: 75%;">
                <div class="d-flex align-items-center gap-2 mb-1 ${isMe ? 'justify-content-end' : ''}">
                    <span class="small fw-bold text-dark" style="font-size: 11px;">${isMe ? 'You' : msg.sender_name}</span>
                    <span class="text-muted" style="font-size: 10px;">${msg.time_str || ''}</span>
                </div>
                <div class="p-3 rounded-3 shadow-sm ${isMe ? 'bg-primary text-white' : 'bg-white border text-dark'}" style="word-break: break-word; font-size: 13.5px; line-height: 1.45;">
                    ${msg.content}
                </div>
            </div>
        </div>
    `;
    chatStream.insertAdjacentHTML('beforeend', bubbleHtml);
    chatStream.scrollTop = chatStream.scrollHeight;
}

// Poll session messages
async function pollSessionChat() {
    try {
        const res = await fetch(`<?= BASE_URL ?>/chat/relay.php?team_id=${teamId}&since=${lastMessageId}`);
        if (!res.ok) return;
        const data = await res.json();
        if (data.status === 'success' && data.messages.length > 0) {
            data.messages.forEach(m => {
                if (m.id > lastMessageId) {
                    lastMessageId = m.id;
                    appendMessage(m);
                }
            });
        }
    } catch (err) {
        console.warn('Session chat poll error:', err);
    }
}

chatForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const content = chatInput.value.trim();
    if (!content) return;
    chatInput.value = '';

    try {
        const res = await fetch(`<?= BASE_URL ?>/chat/relay.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ team_id: teamId, content: content })
        });
        const data = await res.json();
        if (data.status === 'success') {
            lastMessageId = Math.max(lastMessageId, data.message.id);
            appendMessage(data.message);
        }
    } catch (err) {
        window.tfToast('Failed to send message: ' + err.message, 'error');
    }
});

setInterval(pollSessionChat, 1500);
pollSessionChat();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
