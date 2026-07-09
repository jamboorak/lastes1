<?php
require_once '../config/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Clear only admin session variables (don't destroy main user session)
unset($_SESSION['admin_id']);
unset($_SESSION['admin_name']);
unset($_SESSION['admin_email']);
unset($_SESSION['admin_role']);
unset($_SESSION['admin_logged_in']);

// Redirect to admin login page
header("Location: login.php");
exit();
?>