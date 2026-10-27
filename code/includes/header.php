<?php
/**
 * TeamForge Common Header
 */
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';

$pageTitle = $pageTitle ?? APP_NAME . ' — ' . APP_TAGLINE;
$user = currentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <meta name="description" content="Intelligent Student Team Formation Platform powered by Classical AI Algorithms">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 CSS & Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- TeamForge Custom Design System -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css">
</head>
<body>
<?php if (isLoggedIn()): ?>
<div class="app-wrapper">
    <div class="sidebar-overlay"></div>
    <?php include __DIR__ . '/sidebar.php'; ?>
    <div class="app-main">
        <?php include __DIR__ . '/navbar.php'; ?>
        <main class="content-body">
            <?= renderFlashes() ?>
<?php else: ?>
    <!-- Non-authenticated pages (Landing, Login, Register) container -->
    <div class="public-wrapper">
<?php endif; ?>
