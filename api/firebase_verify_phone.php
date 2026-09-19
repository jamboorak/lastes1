<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../includes/ActivityLogger.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (!FIREBASE_PHONE_AUTH_ENABLED || empty($_SESSION['google_user_data']['email'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Firebase phone verification is not enabled.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$idToken = trim((string)($input['id_token'] ?? ''));

if ($idToken === '' || strlen($idToken) > 10000) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'A valid Firebase token is required.']);
    exit;
}

try {
    $lookupUrl = 'https://identitytoolkit.googleapis.com/v1/accounts:lookup?key=' . rawurlencode(FIREBASE_API_KEY);
    $curl = curl_init($lookupUrl);
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['idToken' => $idToken]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT => 15
    ]);
    $lookupResponse = curl_exec($curl);
    $curlError = curl_error($curl);
    $httpStatus = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($lookupResponse === false || $curlError !== '' || $httpStatus !== 200) {
        throw new RuntimeException('Firebase token verification failed.');
    }

    $firebaseData = json_decode($lookupResponse, true);
    $firebaseUser = $firebaseData['users'][0] ?? null;
    $firebasePhone = trim((string)($firebaseUser['phoneNumber'] ?? ''));
    $firebaseUid = trim((string)($firebaseUser['localId'] ?? ''));

    if ($firebasePhone === '' || $firebaseUid === '') {
        throw new RuntimeException('The Firebase account has no verified phone number.');
    }

    $database = new Database();
    $db = $database->getConnection();
    $userModel = new User($database);
    $userEmail = $_SESSION['google_user_data']['email'];
    $user = $userModel->getUserByEmail($userEmail);

    if (!$user) {
        $userId = $userModel->createSocialUser([
            'name' => $_SESSION['google_user_data']['name'] ?? 'User',
            'email' => $userEmail,
            'google_id' => $_SESSION['google_user_data']['google_id'] ?? null,
            'avatar' => $_SESSION['google_user_data']['avatar'] ?? null,
            'email_verified' => 1,
            'registration_method' => 'google'
        ]);

        if (!$userId) {
            throw new RuntimeException('Unable to create the local user account.');
        }
        $user = $userModel->getUserByEmail($userEmail);
    }

    $update = $db->prepare('UPDATE users SET phone = ?, phone_verified = 1 WHERE id = ?');
    if (!$update) {
        throw new RuntimeException('Phone verification database field is not configured.');
    }
    $update->bind_param('si', $firebasePhone, $user['id']);
    $update->execute();
    $update->close();

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_name'] = $user['fullname'];
    $_SESSION['logged_in'] = true;
    $_SESSION['login_time'] = time();
    unset($_SESSION['google_user_data']);

    $userModel->updateLastLogin($user['id']);
    logUserActivity($db, $user['id'], 'login', 'User logged in after Firebase phone verification');

    $redirectUrl = $_SESSION['redirect_after_login'] ?? 'index.php';
    unset($_SESSION['redirect_after_login']);

    echo json_encode(['success' => true, 'redirect_url' => $redirectUrl]);
} catch (Throwable $exception) {
    error_log('Firebase phone verification error: ' . $exception->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
}
