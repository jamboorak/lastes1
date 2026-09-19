<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (!FIREBASE_GUEST_PHONE_AUTH_ENABLED || empty($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Phone verification is not available.']);
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
    $phoneNumber = trim((string)($firebaseUser['phoneNumber'] ?? ''));
    $firebaseUid = trim((string)($firebaseUser['localId'] ?? ''));

    if ($phoneNumber === '' || $firebaseUid === '') {
        throw new RuntimeException('The Firebase account has no verified phone number.');
    }

    $database = new Database();
    $conn = $database->getConnection();
    $userId = (int)$_SESSION['user_id'];
    $userStmt = $conn->prepare('SELECT phone, phone_verified FROM users WHERE id = ? LIMIT 1');
    $userStmt->bind_param('i', $userId);
    $userStmt->execute();
    $user = $userStmt->get_result()->fetch_assoc();
    $userStmt->close();

    if (!$user) {
        throw new RuntimeException('User account not found.');
    }

    if ((int)($user['phone_verified'] ?? 0) === 1 && trim((string)$user['phone']) !== '' && $user['phone'] !== $phoneNumber) {
        throw new RuntimeException('A different mobile number is already verified for this account.');
    }

    $update = $conn->prepare('UPDATE users SET phone = ?, phone_verified = 1 WHERE id = ?');
    if (!$update) {
        throw new RuntimeException('Phone verification database field is not configured.');
    }
    $update->bind_param('si', $phoneNumber, $userId);
    $update->execute();
    $update->close();

    $_SESSION['guest_phone_verified'] = true;
    $_SESSION['guest_phone_verified_number'] = $phoneNumber;

    echo json_encode([
        'success' => true,
        'phone_number' => $phoneNumber,
        'message' => 'Mobile number verified successfully.'
    ]);
} catch (Throwable $exception) {
    error_log('Firebase guest phone verification error: ' . $exception->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
}
