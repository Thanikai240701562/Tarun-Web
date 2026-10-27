<?php
/**
 * TeamForge — Standalone Database Table Viewer
 * For project review / demonstration purposes only.
 * Access: http://localhost/teamforge/database/view_tables.php
 */

// ── Direct DB connection (no app bootstrap needed) ──────────────────────────
define('DB_HOST', '127.0.0.1');
define('DB_PORT', 3306);
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'teamforge_db');

$error   = null;
$tables  = [];
$tableData = [];
$rowCounts = [];
$columns   = [];

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    // Get all table names
    $stmt   = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // For each table: count rows, get column info & up to 50 rows
    foreach ($tables as $tbl) {
        $countStmt      = $pdo->query("SELECT COUNT(*) FROM `{$tbl}`");
        $rowCounts[$tbl] = (int)$countStmt->fetchColumn();

        $colStmt        = $pdo->query("SHOW COLUMNS FROM `{$tbl}`");
        $columns[$tbl]  = $colStmt->fetchAll();

        $dataStmt         = $pdo->query("SELECT * FROM `{$tbl}` LIMIT 50");
        $tableData[$tbl]  = $dataStmt->fetchAll();
    }
} catch (PDOException $e) {
    $error = $e->getMessage();
}

// Active table from query param
$activeTable = $_GET['table'] ?? ($tables[0] ?? null);
if (!in_array($activeTable, $tables)) {
    $activeTable = $tables[0] ?? null;
}

$totalTables = count($tables);
$totalRows   = array_sum($rowCounts);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TeamForge — Database Viewer</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg:          #0d1117;
            --bg2:         #161b22;
            --bg3:         #1c2130;
            --border:      rgba(255,255,255,0.08);
            --border2:     rgba(255,255,255,0.04);
            --text:        #e6edf3;
            --text-muted:  #7d8590;
            --accent:      #7c3aed;
            --accent2:     #a855f7;
            --accent-glow: rgba(124,58,237,0.25);
            --green:       #3fb950;
            --blue:        #58a6ff;
            --orange:      #d29922;
            --red:         #f85149;
            --sidebar-w:   270px;
        }

        html, body { height: 100%; }

        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: var(--bg);
            color: var(--text);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* Top bar */
        .topbar {
            background: var(--bg2);
            border-bottom: 1px solid var(--border);
            padding: 0 28px;
            height: 58px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-shrink: 0;
            position: sticky;
            top: 0;
            z-index: 100;
            backdrop-filter: blur(12px);
        }
        .topbar-brand { display: flex; align-items: center; gap: 10px; text-decoration: none; }
        .brand-logo { width: 34px; height: 34px; background: linear-gradient(135deg, var(--accent), var(--accent2)); border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 17px; }
        .brand-name { font-size: 16px; font-weight: 700; color: var(--text); letter-spacing: -0.3px; }
        .brand-name span { color: var(--accent2); }
        .topbar-badge { font-size: 11px; font-family: 'JetBrains Mono', monospace; background: var(--accent-glow); border: 1px solid rgba(124,58,237,0.4); color: var(--accent2); padding: 3px 10px; border-radius: 20px; font-weight: 500; }
        .topbar-meta { display: flex; align-items: center; gap: 16px; }
        .stat-chip { display: flex; align-items: center; gap: 6px; font-size: 12px; color: var(--text-muted); }
        .stat-chip strong { color: var(--text); font-weight: 600; }
        .stat-chip .dot { width: 6px; height: 6px; border-radius: 50%; background: var(--green); }

        /* Layout */
        .layout { display: flex; flex: 1; overflow: hidden; }

        /* Sidebar */
        .sidebar { width: var(--sidebar-w); background: var(--bg2); border-right: 1px solid var(--border); display: flex; flex-direction: column; overflow: hidden; flex-shrink: 0; }
        .sidebar-head { padding: 16px 18px 12px; border-bottom: 1px solid var(--border); font-size: 11px; font-weight: 600; color: var(--text-muted); letter-spacing: 0.8px; text-transform: uppercase; display: flex; align-items: center; justify-content: space-between; }
        .sidebar-head .count-bubble { background: var(--bg3); border: 1px solid var(--border); color: var(--text-muted); font-size: 11px; padding: 1px 7px; border-radius: 10px; }
        .sidebar-search { padding: 10px 14px; border-bottom: 1px solid var(--border); }
        .sidebar-search input { width: 100%; background: var(--bg3); border: 1px solid var(--border); border-radius: 6px; color: var(--text); font-family: 'Inter', sans-serif; font-size: 12.5px; padding: 6px 10px 6px 30px; outline: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%237d8590' stroke-width='2'%3E%3Ccircle cx='11' cy='11' r='8'/%3E%3Cpath d='M21 21l-4.35-4.35'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-size: 14px; background-position: 9px center; transition: border-color .2s; }
        .sidebar-search input:focus { border-color: var(--accent); }
        .sidebar-search input::placeholder { color: var(--text-muted); }
        .table-list { overflow-y: auto; flex: 1; padding: 6px 8px 16px; }
        .table-list::-webkit-scrollbar { width: 5px; }
        .table-list::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 10px; }
        .table-link { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 8px 10px; border-radius: 7px; text-decoration: none; color: var(--text-muted); font-size: 13px; margin-bottom: 2px; transition: background .15s, color .15s; border: 1px solid transparent; }
        .table-link:hover { background: var(--bg3); color: var(--text); }
        .table-link.active { background: rgba(124,58,237,0.15); border-color: rgba(124,58,237,0.3); color: var(--accent2); }
        .table-link .tname { display: flex; align-items: center; gap: 7px; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .table-link .tname svg { flex-shrink: 0; opacity: .6; }
        .table-link.active .tname svg { opacity: 1; }
        .row-badge { font-size: 10.5px; font-family: 'JetBrains Mono', monospace; background: var(--bg3); border: 1px solid var(--border); color: var(--text-muted); padding: 1px 7px; border-radius: 10px; flex-shrink: 0; }
        .table-link.active .row-badge { background: rgba(124,58,237,0.15); border-color: rgba(124,58,237,0.3); color: var(--accent2); }

        /* Main */
        .main { flex: 1; overflow-y: auto; display: flex; flex-direction: column; }
        .main::-webkit-scrollbar { width: 6px; }
        .main::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 10px; }

        /* Error */
        .error-card { margin: 40px auto; max-width: 540px; background: rgba(248,81,73,0.08); border: 1px solid rgba(248,81,73,0.3); border-radius: 12px; padding: 32px; text-align: center; }
        .error-card h2 { color: var(--red); font-size: 18px; margin-bottom: 10px; }
        .error-card p { color: var(--text-muted); font-size: 13.5px; line-height: 1.6; }
        .error-card code { font-family: 'JetBrains Mono', monospace; font-size: 12px; background: var(--bg3); padding: 10px 14px; display: block; border-radius: 6px; text-align: left; margin-top: 14px; color: var(--red); word-break: break-all; }

        /* Table header */
        .table-header { padding: 22px 28px 0; border-bottom: 1px solid var(--border); background: var(--bg2); flex-shrink: 0; }
        .table-header-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 16px; }
        .table-header h1 { font-size: 20px; font-weight: 700; letter-spacing: -0.5px; display: flex; align-items: center; gap: 10px; }
        .table-header h1 .tbl-icon { width: 36px; height: 36px; background: linear-gradient(135deg, var(--accent), var(--accent2)); border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0; }
        .meta-pills { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .meta-pill { display: flex; align-items: center; gap: 5px; font-size: 12px; color: var(--text-muted); background: var(--bg3); border: 1px solid var(--border); padding: 4px 10px; border-radius: 20px; }
        .meta-pill.green { color: var(--green); border-color: rgba(63,185,80,.3); background: rgba(63,185,80,.08); }
        .meta-pill.blue  { color: var(--blue);  border-color: rgba(88,166,255,.3); background: rgba(88,166,255,.08); }

        /* Schema strip */
        .schema-strip { display: flex; overflow-x: auto; padding-bottom: 0; }
        .schema-strip::-webkit-scrollbar { height: 3px; }
        .schema-strip::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 10px; }
        .col-pill { display: flex; flex-direction: column; padding: 8px 14px 10px; border-right: 1px solid var(--border); white-space: nowrap; min-width: fit-content; }
        .col-pill:last-child { border-right: none; }
        .col-pill .cname { font-size: 11.5px; font-family: 'JetBrains Mono', monospace; font-weight: 500; color: var(--blue); }
        .col-pill .ctype { font-size: 10px; color: var(--text-muted); margin-top: 2px; }
        .col-pill .cbadge { font-size: 9px; margin-top: 3px; display: inline-flex; align-items: center; gap: 3px; }
        .cbadge.pk  { color: var(--orange); }
        .cbadge.nn  { color: var(--text-muted); }
        .cbadge.uni { color: var(--accent2); }

        /* Data section */
        .data-section { padding: 22px 28px 40px; flex: 1; }
        .data-section-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; }
        .data-section-head h2 { font-size: 13px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.7px; }
        .limit-note { font-size: 11.5px; color: var(--text-muted); font-family: 'JetBrains Mono', monospace; }

        /* Data table */
        .table-wrap { overflow-x: auto; border-radius: 10px; border: 1px solid var(--border); }
        .table-wrap::-webkit-scrollbar { height: 6px; }
        .table-wrap::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.12); border-radius: 10px; }
        table { width: 100%; border-collapse: collapse; font-size: 12.5px; }
        thead th { background: var(--bg3); color: var(--text-muted); font-weight: 600; padding: 10px 14px; text-align: left; white-space: nowrap; font-family: 'JetBrains Mono', monospace; font-size: 11px; border-bottom: 1px solid var(--border); letter-spacing: 0.3px; }
        tbody tr { border-bottom: 1px solid var(--border2); transition: background .12s; }
        tbody tr:hover { background: rgba(124,58,237,0.05); }
        tbody tr:last-child { border-bottom: none; }
        tbody td { padding: 9px 14px; color: var(--text); vertical-align: top; max-width: 280px; }
        .cell-null { color: var(--text-muted); font-style: italic; font-size: 11.5px; font-family: 'JetBrains Mono', monospace; }
        .cell-id { font-family: 'JetBrains Mono', monospace; font-size: 11.5px; color: var(--orange); font-weight: 600; }
        .cell-num { font-family: 'JetBrains Mono', monospace; font-size: 12px; color: var(--blue); }
        .cell-bool-true  { color: var(--green); font-weight: 600; }
        .cell-bool-false { color: var(--red);   font-weight: 600; }
        .cell-enum { display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 20px; font-size: 11px; font-weight: 500; }
        .cell-truncate { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 240px; display: block; }
        .empty-rows { text-align: center; padding: 60px 20px; color: var(--text-muted); }

        /* Enum colour classes */
        .tag-student        { background: rgba(88,166,255,.12); color: #58a6ff; }
        .tag-team_leader    { background: rgba(168,85,247,.12); color: #a855f7; }
        .tag-faculty_admin  { background: rgba(248,81,73,.12);  color: #f85149; }
        .tag-available      { background: rgba(63,185,80,.12);  color: #3fb950; }
        .tag-busy           { background: rgba(210,153,34,.12); color: #d29922; }
        .tag-in_team        { background: rgba(88,166,255,.12); color: #58a6ff; }
        .tag-active         { background: rgba(63,185,80,.12);  color: #3fb950; }
        .tag-inactive       { background: rgba(248,81,73,.12);  color: #f85149; }
        .tag-scheduled      { background: rgba(88,166,255,.12); color: #58a6ff; }
        .tag-completed      { background: rgba(63,185,80,.12);  color: #3fb950; }
        .tag-pending        { background: rgba(210,153,34,.12); color: #d29922; }
        .tag-accepted       { background: rgba(63,185,80,.12);  color: #3fb950; }
        .tag-rejected       { background: rgba(248,81,73,.12);  color: #f85149; }
        .tag-open           { background: rgba(88,166,255,.12); color: #58a6ff; }
        .tag-cancelled      { background: rgba(248,81,73,.12);  color: #f85149; }
        .tag-in_progress    { background: rgba(210,153,34,.12); color: #d29922; }
        .tag-todo           { background: rgba(88,166,255,.12); color: #58a6ff; }
        .tag-done           { background: rgba(63,185,80,.12);  color: #3fb950; }
        .tag-default        { background: #1c2130; color: #7d8590; }

        /* Footer */
        .db-footer { padding: 12px 28px; border-top: 1px solid var(--border); background: var(--bg2); font-size: 11.5px; color: var(--text-muted); display: flex; align-items: center; justify-content: space-between; flex-shrink: 0; }
        .db-footer code { font-family: 'JetBrains Mono', monospace; color: #a855f7; }

        /* Empty db */
        .empty-db { margin: 60px auto; max-width: 400px; text-align: center; color: #7d8590; }
        .empty-db h2 { color: #e6edf3; font-size: 18px; margin-bottom: 8px; }
    </style>
</head>
<body>

<!-- Top Bar -->
<div class="topbar">
    <div class="topbar-brand">
        <div class="brand-logo">🗄️</div>
        <span class="brand-name">Team<span>Forge</span></span>
    </div>
    <span class="topbar-badge">📋 Database Viewer</span>
    <?php if (!$error): ?>
    <div class="topbar-meta">
        <div class="stat-chip"><div class="dot"></div><strong><?= htmlspecialchars(DB_NAME) ?></strong></div>
        <div class="stat-chip">🗂 <strong><?= $totalTables ?></strong>&nbsp;tables</div>
        <div class="stat-chip">📦 <strong><?= number_format($totalRows) ?></strong>&nbsp;total rows</div>
    </div>
    <?php endif; ?>
</div>

<?php if ($error): ?>
<div style="flex:1; display:flex; align-items:flex-start; justify-content:center; padding:40px 20px;">
    <div class="error-card">
        <div style="font-size:40px; margin-bottom:14px;">❌</div>
        <h2>Database Connection Failed</h2>
        <p>Could not connect to <strong><?= htmlspecialchars(DB_NAME) ?></strong> on <strong><?= htmlspecialchars(DB_HOST) ?>:<?= DB_PORT ?></strong>.<br>Make sure XAMPP MySQL is running and the database exists.</p>
        <code><?= htmlspecialchars($error) ?></code>
    </div>
</div>

<?php elseif (empty($tables)): ?>
<div style="flex:1; display:flex; align-items:center; justify-content:center;">
    <div class="empty-db">
        <div style="font-size:48px; margin-bottom:16px;">📭</div>
        <h2>No Tables Found</h2>
        <p>The database <strong><?= htmlspecialchars(DB_NAME) ?></strong> is empty.</p>
    </div>
</div>

<?php else: ?>
<div class="layout">

    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-head">
            Tables <span class="count-bubble"><?= $totalTables ?></span>
        </div>
        <div class="sidebar-search">
            <input type="text" id="tableSearch" placeholder="Filter tables…" autocomplete="off">
        </div>
        <nav class="table-list" id="tableList">
            <?php foreach ($tables as $tbl): ?>
                <a href="?table=<?= urlencode($tbl) ?>"
                   class="table-link <?= ($tbl === $activeTable) ? 'active' : '' ?>"
                   data-name="<?= htmlspecialchars($tbl) ?>">
                    <span class="tname">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/>
                            <line x1="3" y1="15" x2="21" y2="15"/><line x1="9" y1="9" x2="9" y2="21"/>
                        </svg>
                        <?= htmlspecialchars($tbl) ?>
                    </span>
                    <span class="row-badge"><?= number_format($rowCounts[$tbl]) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
    </aside>

    <!-- Main panel -->
    <div class="main">
        <?php if ($activeTable): ?>
            <?php
            $cols = $columns[$activeTable] ?? [];
            $rows = $tableData[$activeTable] ?? [];
            $rc   = $rowCounts[$activeTable] ?? 0;
            ?>

            <div class="table-header">
                <div class="table-header-top">
                    <h1>
                        <div class="tbl-icon">🗃️</div>
                        <?= htmlspecialchars($activeTable) ?>
                    </h1>
                    <div class="meta-pills">
                        <div class="meta-pill green">✅ <?= number_format($rc) ?> row<?= $rc !== 1 ? 's' : '' ?></div>
                        <div class="meta-pill blue">📐 <?= count($cols) ?> column<?= count($cols) !== 1 ? 's' : '' ?></div>
                        <div class="meta-pill">🏎 InnoDB · utf8mb4</div>
                    </div>
                </div>

                <!-- Column schema strip -->
                <div class="schema-strip">
                    <?php foreach ($cols as $col): ?>
                        <div class="col-pill">
                            <span class="cname"><?= htmlspecialchars($col['Field']) ?></span>
                            <span class="ctype"><?= htmlspecialchars($col['Type']) ?></span>
                            <?php if ($col['Key'] === 'PRI'): ?>
                                <span class="cbadge pk">🔑 PK</span>
                            <?php elseif ($col['Key'] === 'UNI'): ?>
                                <span class="cbadge uni">⚡ UNI</span>
                            <?php elseif ($col['Null'] === 'NO'): ?>
                                <span class="cbadge nn">NOT NULL</span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="data-section">
                <div class="data-section-head">
                    <h2>📄 Row Data</h2>
                    <?php if ($rc > 50): ?>
                        <span class="limit-note">Showing 50 / <?= number_format($rc) ?> rows</span>
                    <?php endif; ?>
                </div>

                <div class="table-wrap">
                    <?php if (empty($rows)): ?>
                        <div class="empty-rows"><p>This table is empty — no rows yet.</p></div>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <?php foreach ($cols as $col): ?>
                                        <th><?= htmlspecialchars($col['Field']) ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rows as $row): ?>
                                    <tr>
                                        <?php foreach ($cols as $col):
                                            $field = $col['Field'];
                                            $val   = isset($row[$field]) ? $row[$field] : null;
                                            $type  = strtolower($col['Type']);
                                            $key   = $col['Key'];
                                        ?>
                                            <td>
                                                <?php if ($val === null): ?>
                                                    <span class="cell-null">NULL</span>
                                                <?php elseif ($key === 'PRI' || $field === 'id'): ?>
                                                    <span class="cell-id"><?= htmlspecialchars($val) ?></span>
                                                <?php elseif ($field === 'password_hash'): ?>
                                                    <span class="cell-null" title="<?= htmlspecialchars($val) ?>">••••[hashed]</span>
                                                <?php elseif ($type === 'tinyint(1)'): ?>
                                                    <?php if ($val == 1): ?>
                                                        <span class="cell-bool-true">✔ true</span>
                                                    <?php else: ?>
                                                        <span class="cell-bool-false">✘ false</span>
                                                    <?php endif; ?>
                                                <?php elseif (strpos($type, 'int') !== false || strpos($type, 'decimal') !== false || strpos($type, 'float') !== false): ?>
                                                    <span class="cell-num"><?= htmlspecialchars($val) ?></span>
                                                <?php elseif (strpos($type, 'enum') !== false || (strpos($type, 'varchar') !== false && strlen($val) < 30)): ?>
                                                    <?php
                                                        $slug = 'tag-' . preg_replace('/[^a-z0-9_]/', '', strtolower($val));
                                                        $known = ['tag-student','tag-team_leader','tag-faculty_admin','tag-available','tag-busy','tag-in_team','tag-active','tag-inactive','tag-scheduled','tag-completed','tag-pending','tag-accepted','tag-rejected','tag-open','tag-cancelled','tag-in_progress','tag-todo','tag-done'];
                                                        $cls = in_array($slug, $known) ? $slug : 'tag-default';
                                                    ?>
                                                    <span class="cell-enum <?= $cls ?>"><?= htmlspecialchars($val) ?></span>
                                                <?php elseif (strlen($val) > 100): ?>
                                                    <span class="cell-truncate" title="<?= htmlspecialchars($val) ?>"><?= htmlspecialchars(mb_substr($val, 0, 100)) ?>…</span>
                                                <?php else: ?>
                                                    <?= htmlspecialchars($val) ?>
                                                <?php endif; ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="db-footer">
    <span>🗄️ <code><?= htmlspecialchars(DB_NAME) ?></code> &middot; <?= htmlspecialchars(DB_HOST) ?>:<?= DB_PORT ?> &middot; TeamForge DB Viewer</span>
    <span>For project review only &middot; <?= date('Y') ?></span>
</div>
<?php endif; ?>

<script>
const searchInput = document.getElementById('tableSearch');
if (searchInput) {
    searchInput.addEventListener('input', function() {
        const q = this.value.toLowerCase();
        document.querySelectorAll('#tableList .table-link').forEach(function(link) {
            link.style.display = link.dataset.name.toLowerCase().includes(q) ? '' : 'none';
        });
    });
}
</script>
</body>
</html>
