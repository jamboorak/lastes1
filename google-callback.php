<?php
/**
 * Google OAuth Configuration
 * You need to set these values from Google Cloud Console
 */

// Include configuration first to define SITE_URL
require_once 'config/config.php';

// Load Google OAuth settings from environment variables or config constants.
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

/**
 * Handle Google OAuth Callback
 */

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Verify CSRF state to prevent attacks
if (!isset($_GET['state']) || $_GET['state'] !== $_SESSION['oauth_state']) {
    $_SESSION['login_error'] = 'Google login failed. Please try again.';
    header('Location: ' . SITE_URL);
    exit;
}

$redirect_url = $_SESSION['redirect_after_login'] ?? SITE_URL;
unset($_SESSION['redirect_after_login']);

// Handle user cancellation or missing authorization code
if (isset($_GET['error'])) {
    if ($_GET['error'] === 'access_denied') {
        // If the user cancels the Google login, return them to account selection again
        header('Location: ' . SITE_URL . 'google-auth.php?action=login&retry=1');
        exit;
    }
    $_SESSION['login_error'] = 'Google login failed: ' . htmlspecialchars($_GET['error']);
    header('Location: ' . $redirect_url);
    exit;
}

if (!isset($_GET['code'])) {
    $_SESSION['login_error'] = 'Authorization code not received. Please try again.';
    header('Location: ' . $redirect_url);
    exit;
}

$code = $_GET['code'];

// Debug: Log the redirect URI being used
error_log("DEBUG - Using redirect URI: " . GOOGLE_REDIRECT_URI);
error_log("DEBUG - Client ID: " . GOOGLE_CLIENT_ID);

// Exchange authorization code for access token
$token_url = 'https://oauth2.googleapis.com/token';
$token_payload = [
    'client_id' => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'code' => $code,
    'grant_type' => 'authorization_code',
    'redirect_uri' => GOOGLE_REDIRECT_URI
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $token_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($token_payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/x-www-form-urlencoded',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$token_response = curl_exec($ch);
$curl_error = curl_error($ch);
curl_close($ch);

$token_data = json_decode($token_response, true);

// Debug logging
error_log("DEBUG - Token Response: " . print_r($token_data, true));
error_log("DEBUG - Curl Error: " . $curl_error);
error_log("DEBUG - Full Response: " . $token_response);

if ($token_response === false || !empty($curl_error)) {
    $_SESSION['login_error'] = 'Google login failed: ' . htmlspecialchars($curl_error);
    header('Location: ' . $redirect_url);
    exit;
}

if (!empty($token_data['error'])) {
    $_SESSION['login_error'] = 'Google login failed: ' . htmlspecialchars($token_data['error_description'] ?? $token_data['error']);
    header('Location: ' . $redirect_url);
    exit;
}

if (!isset($token_data['access_token'])) {
    $_SESSION['login_error'] = 'Google login failed: Unable to retrieve an access token from Google.';
    header('Location: ' . $redirect_url);
    exit;
}

$access_token = $token_data['access_token'];

// Get user information from Google
$user_info_url = 'https://www.googleapis.com/oauth2/v2/userinfo';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $user_info_url);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $access_token
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$user_response = curl_exec($ch);
curl_close($ch);

$user_data = json_decode($user_response, true);

if (!isset($user_data['email'])) {
    die('Failed to get user information.');
}

// Store user data in session for OTP verification
$_SESSION['google_user_data'] = $user_data;
$_SESSION['google_user_data']['google_id'] = $user_data['id'];
$_SESSION['google_user_data']['avatar'] = $user_data['picture'] ?? null;
$_SESSION['google_user_data']['email_verified'] = 1;
$_SESSION['google_user_data']['registration_method'] = 'google';

// Clear OAuth state
unset($_SESSION['oauth_state']);

// Redirect to OTP verification page
header('Location: otp-verification.php');
exit;

?>
<!DOCTYPE html>
<html>
<head>
    <title>Completing authentication...</title>
    <script>
        // Replace current history state to prevent back button from working
        window.history.replaceState(null, null, window.location.href);
        
        // Override back button behavior
        window.addEventListener('popstate', function(event) {
            // When back button is pressed, refresh the page instead of going back
            window.location.reload();
        });
        
        // Redirect after authentication after a brief delay
        setTimeout(function() {
            window.location.href = '<?php echo $redirect_url; ?>';
        }, 100);
    </script>
</head>
<body>
    <p>Completing authentication...</p>
</body>
</html>
<?php
exit;
?>