<?php
/**
 * TeamForge Logout Handler
 */
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';

logoutUser();
setFlash('info', 'You have been signed out safely.');
header('Location: ' . BASE_URL . '/login.php');
exit;
