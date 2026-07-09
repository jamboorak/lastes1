<?php
require_once 'config/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Clear all user session variables
unset($_SESSION['user_id']);
unset($_SESSION['fullname']);
unset($_SESSION['email']);
unset($_SESSION['role']);
unset($_SESSION['logged_in']);
unset($_SESSION['user_name']);
unset($_SESSION['user_avatar']);

// Clear Google OAuth session variables if they exist
unset($_SESSION['oauth_state']);
unset($_SESSION['google_access_token']);
unset($_SESSION['google_refresh_token']);
unset($_SESSION['google_token_expires']);

// Clear any other session variables that might exist
unset($_SESSION['selected_date']);
unset($_SESSION['form_data']);

// Destroy the session completely
session_destroy();

// Start a new session to prevent session fixation attacks
session_start();
session_regenerate_id(true);

// Redirect to home page
header('Location: ' . SITE_URL);
exit;
