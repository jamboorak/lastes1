<?php
require_once __DIR__ . '/config/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Legacy email-link verification was replaced by Google OTP sign-in.
header('Location: ' . SITE_URL . 'index.php');
exit;
