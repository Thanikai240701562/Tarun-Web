<?php
/**
 * TeamForge Database Installer & Seeder
 * Connects to MySQL, creates database, executes schema.sql and seed_data.sql.
 */

define('DB_HOST', '127.0.0.1');
define('DB_PORT', 3306);
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'teamforge_db');

header('Content-Type: text/html; charset=utf-8');

$isCli = (php_sapi_name() === 'cli');

function outputMsg($msg, $type = 'info') {
    global $isCli;
    if ($isCli) {
        $prefix = match($type) {
            'success' => '[SUCCESS] ',
            'error'   => '[ERROR] ',
            'warning' => '[WARN] ',
            default   => '[INFO] '
        };
        echo $prefix . strip_tags($msg) . PHP_EOL;
    } else {
        $color = match($type) {
            'success' => '#10b981',
            'error'   => '#ef4444',
            'warning' => '#f59e0b',
            default   => '#3b82f6'
        };
        echo "<div style='font-family:Segoe UI, sans-serif; padding:10px 14px; margin:6px 0; border-radius:6px; background:#f8fafc; border-left:4px solid {$color}; color:#1e293b;'>{$msg}</div>";
    }
}

try {
    outputMsg("Connecting to MySQL on " . DB_HOST . ":" . DB_PORT . "...", 'info');
    $pdo = new PDO("mysql:host=" . DB_HOST . ";port=" . DB_PORT, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    outputMsg("Connected to MySQL server successfully.", 'success');

    // 1. Run Schema
    $schemaFile = __DIR__ . '/schema.sql';
    if (!file_exists($schemaFile)) {
        throw new Exception("schema.sql not found at " . $schemaFile);
    }
    outputMsg("Importing schema from database/schema.sql...", 'info');
    $schemaSql = file_get_contents($schemaFile);
    $pdo->exec($schemaSql);
    outputMsg("Schema imported successfully! Database 'teamforge_db' created.", 'success');

    // Switch to database
    $pdo->exec("USE `" . DB_NAME . "`");

    // 2. Run Seed Data
    $seedFile = __DIR__ . '/seed_data.sql';
    if (file_exists($seedFile)) {
        outputMsg("Importing seed data from database/seed_data.sql...", 'info');
        $seedSql = file_get_contents($seedFile);
        $pdo->exec($seedSql);
        outputMsg("Demo seed data imported successfully!", 'success');
    }

    // 3. Verify Table Counts
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    outputMsg("Verified: " . count($tables) . " tables active in " . DB_NAME . ".", 'success');

    if ($isCli) {
        echo PHP_EOL . "TeamForge database is ready! Access at http://localhost/teamforge/" . PHP_EOL;
    } else {
        echo "<div style='margin-top:20px;'><a href='../index.php' style='display:inline-block; padding:10px 20px; background:#4f46e5; color:white; text-decoration:none; border-radius:6px; font-weight:600;'>Go to TeamForge Homepage &rarr;</a></div>";
    }
} catch (PDOException $e) {
    outputMsg("Database error: " . $e->getMessage(), 'error');
} catch (Exception $e) {
    outputMsg("General error: " . $e->getMessage(), 'error');
}
