<?php
/**
 * Google OAuth Configuration
 * You need to set these values from Google Cloud Console
 */

require_once 'config/config.php';

if (!defined('GOOGLE_CLIENT_ID')) {
    define('GOOGLE_CLIENT_ID', getenv('GOOGLE_CLIENT_ID') ?: '');
}
if (!defined('GOOGLE_CLIENT_SECRET')) {
    define('GOOGLE_CLIENT_SECRET', getenv('GOOGLE_CLIENT_SECRET') ?: '');
}
if (!defined('GOOGLE_REDIRECT_URI')) {
    $host = strtolower($_SERVER['HTTP_HOST'] ?? 'localhost');

    if (preg_match('/^(localhost|127\.0\.0\.1|::1)$/i', $host)) {
        define('GOOGLE_REDIRECT_URI', 'http://localhost/restorts/google-callback.php');
    } else {
        $redirectBase = defined('SITE_URL') ? rtrim(SITE_URL, '/') : null;
        if (empty($redirectBase) || !filter_var($redirectBase, FILTER_VALIDATE_URL)) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/');
            $scriptDir = $scriptDir === '.' ? '' : $scriptDir;
            $redirectBase = $protocol . $host . $scriptDir;
        }
        define('GOOGLE_REDIRECT_URI', rtrim($redirectBase, '/') . '/google-callback.php');
    }
}

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$googleLoginEnabled = !empty(GOOGLE_CLIENT_ID)
    && GOOGLE_CLIENT_ID !== 'your_google_client_id_here'
    && !empty(GOOGLE_CLIENT_SECRET)
    && GOOGLE_CLIENT_SECRET !== 'your_google_client_secret_here';

$forceLocalLogin = isset($_GET['mode']) && $_GET['mode'] === 'local';

if (!$googleLoginEnabled) {
    $_SESSION['login_error'] = 'Google sign-in is not configured yet. Please add your Google OAuth client ID and secret.';
    header('Location: ' . SITE_URL);
    exit;
}

$_SESSION['oauth_state'] = bin2hex(random_bytes(16));

$google_oauth_url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
    'client_id' => GOOGLE_CLIENT_ID,
    'redirect_uri' => GOOGLE_REDIRECT_URI,
    'response_type' => 'code',
    'scope' => 'openid email profile',
    'state' => $_SESSION['oauth_state'],
    'access_type' => 'offline',
    'prompt' => 'select_account consent'
]);

header('Location: ' . $google_oauth_url);
exit;
?>