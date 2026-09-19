<?php
require_once __DIR__ . '/config/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!empty($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . 'profile.php');
    exit;
}

header('Location: ' . SITE_URL . 'google-auth.php?action=login');
exit;
