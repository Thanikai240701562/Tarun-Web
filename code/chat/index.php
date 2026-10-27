<?php
/**
 * TeamForge Ephemeral Team Group Chat (Section 15)
 * Real-time text discussion with zero permanent MySQL message persistence.
 */
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

requireAuth();
$currentUser = currentUser();
$db = getDB();

// Fetch teams user belongs to
$myTeams = getUserActiveTeams($currentUser['id']);

$teamId = (int)($_GET['team_id'] ?? 0);
if ($teamId <= 0 && !empty($myTeams)) {
    $teamId = (int)$myTeams[0]['id'];
}

$activeTeam = null;
$teamMembers = [];

if ($teamId > 0) {
    // Check membership authorization
    if (!isUserMemberOf($currentUser['id'], $teamId) && $currentUser['role'] !== ROLE_FACULTY_ADMIN) {
        setFlash('danger', 'Access denied. You are not an accepted member of this team.');
        header('Location: ' . BASE_URL . '/teams/my_teams.php');
        exit;
    }

    $tStmt = $db->prepare("SELECT * FROM teams WHERE id = ?");
    $tStmt->execute([$teamId]);
    $activeTeam = $tStmt->fetch();

    if ($activeTeam) {
        $mStmt = $db->prepare("
            SELECT tm.*, u.full_name, u.avatar_url, u.availability_status
            FROM team_members tm
            JOIN users u ON tm.user_id = u.id
            WHERE tm.team_id = ? AND tm.status = 'active'
        ");
        $mStmt->execute([$teamId]);
        $teamMembers = $mStmt->fetchAll();
    }
}

$pageTitle = "Team Group Chat — " . APP_NAME;
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-0">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
        <div>
            <h2 class="fw-extrabold text-navy mb-1" style="font-weight: 800;">Team Group Chat</h2>
            <p class="text-muted small mb-0">Collaborate with your teammates in real-time.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-success-subtle text-success border border-success-subtle p-2">
                <i class="bi bi-shield-check me-1"></i> Ephemeral Mode: Zero Messages Persisted in MySQL
            </span>
        </div>
    </div>

    <?php if (empty($myTeams)): ?>
        <div class="card-tf p-5 text-center">
            <div class="empty-state">
                <div class="empty-state-icon"><i class="bi bi-chat-square-dots"></i></div>
                <div class="empty-state-title">No Active Team Found</div>
                <div class="empty-state-text">You must be an accepted member of a team to access private real-time team channels.</div>
                <a href="<?= BASE_URL ?>/teams/create.php" class="btn btn-tf-primary btn-sm">Create or Join a Team</a>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-3">
            <!-- Left: Team Selector & Member Roster -->
            <div class="col-lg-4 col-xl-3">
                <!-- Team Switcher Card -->
                <div class="card-tf p-3 mb-3 bg-white shadow-sm">
                    <label class="form-label small fw-semibold text-secondary mb-2">Select Team Channel</label>
                    <div class="d-flex flex-column gap-1">
                        <?php foreach ($myTeams as $mt): ?>
                            <a href="<?= BASE_URL ?>/chat/index.php?team_id=<?= $mt['id'] ?>" class="btn btn-sm text-start p-2 rounded-3 d-flex align-items-center justify-content-between <?= (int)$mt['id'] === $teamId ? 'btn-tf-primary' : 'btn-light' ?>">
                                <div class="text-truncate fw-semibold" style="font-size: 13px;"><?= e($mt['name']) ?></div>
                                <span class="badge bg-white text-dark font-mono" style="font-size: 10px;"><?= e($mt['team_code']) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Team Members Presence Card -->
                <div class="card-tf p-3 bg-white shadow-sm">
                    <h6 class="fw-bold text-dark mb-2" style="font-size: 13px;">Team Members (<?= count($teamMembers) ?>)</h6>
                    <div class="d-flex flex-column gap-2" style="max-height: 380px; overflow-y: auto;">
                        <?php foreach ($teamMembers as $m): ?>
                            <div class="d-flex align-items-center gap-2 p-1">
                                <?= getAvatar($m, 28) ?>
                                <div class="overflow-hidden">
                                    <div class="small fw-semibold text-dark text-truncate" style="font-size:12px;"><?= e($m['full_name']) ?></div>
                                    <div class="text-muted text-truncate" style="font-size:10px;"><?= e($m['assigned_role']) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Right: Chat Box Area -->
            <div class="col-lg-8 col-xl-9">
                <div class="card-tf d-flex flex-column shadow-sm bg-white" style="height: 650px;">
                    <!-- Chat Header -->
                    <div class="p-3 border-bottom d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2">
                            <span class="p-2 bg-primary-subtle text-primary rounded-circle"><i class="bi bi-hash fs-5"></i></span>
                            <div>
                                <h6 class="fw-bold text-dark mb-0"><?= e($activeTeam['name']) ?></h6>
                                <div class="text-muted small" style="font-size:11px;"><?= e($activeTeam['project_title']) ?></div>
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="<?= BASE_URL ?>/sessions/index.php?team_id=<?= $teamId ?>" class="btn btn-sm btn-white border text-primary" style="font-size:11px;">
                                <i class="bi bi-clock-history me-1"></i> Scheduled Sessions
                            </a>
                        </div>
                    </div>

                    <!-- Chat Messages Stream -->
                    <div class="p-3 flex-grow-1 overflow-auto bg-light" id="chatStream" style="scroll-behavior: smooth;">
                        <div class="text-center py-4 text-muted small" id="chatPlaceholder">
                            <i class="bi bi-chat-dots fs-3 d-block mb-1 text-secondary opacity-50"></i>
                            Connecting to ephemeral team relay...
                        </div>
                    </div>

                    <!-- Chat Input Bar -->
                    <div class="p-3 border-top bg-white">
                        <form id="chatForm" class="d-flex align-items-center gap-2">
                            <input type="text" id="chatInput" class="form-control" placeholder="Type a message to <?= e($activeTeam['name']) ?>..." maxlength="1000" autocomplete="off" required>
                            <button type="submit" class="btn btn-tf-primary px-4 d-inline-flex align-items-center gap-2" id="sendBtn">
                                <span>Send</span>
                                <i class="bi bi-send-fill"></i>
                            </button>
                        </form>
                        <div class="d-flex justify-content-between align-items-center mt-1 text-muted" style="font-size:10px;">
                            <span>Messages are transmitted in real-time and not stored permanently in the database.</span>
                            <span id="charCount">0/1000</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php if ($activeTeam): ?>
<script>
const currentUserId = <?= (int)$currentUser['id'] ?>;
const teamId = <?= $teamId ?>;
const chatStream = document.getElementById('chatStream');
const chatInput = document.getElementById('chatInput');
const chatForm = document.getElementById('chatForm');
const charCount = document.getElementById('charCount');
let lastMessageId = 0;
let pollTimer = null;

// Character counter
chatInput.addEventListener('input', () => {
    charCount.textContent = `${chatInput.value.length}/1000`;
});

// Render message bubble
function appendMessage(msg) {
    const isMe = (msg.user_id === currentUserId);
    const placeholder = document.getElementById('chatPlaceholder');
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

// Fetch messages from relay
async function pollMessages() {
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
        console.warn('Chat poll error:', err);
    }
}

// Send Message
chatForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const content = chatInput.value.trim();
    if (!content) return;

    chatInput.value = '';
    charCount.textContent = '0/1000';

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

// Start polling every 1.5 seconds
pollMessages();
pollTimer = setInterval(pollMessages, 1500);

window.addEventListener('beforeunload', () => {
    if (pollTimer) clearInterval(pollTimer);
});
</script>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
