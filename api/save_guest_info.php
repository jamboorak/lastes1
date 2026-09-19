<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/firebase.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/guest_info_schema.php';

if (!isset($_SESSION['user_id']) || (int)$_SESSION['user_id'] <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please log in first.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request data.']);
    exit;
}

$firstName = trim((string)($data['first_name'] ?? ''));
$lastName = trim((string)($data['last_name'] ?? ''));
$email = trim((string)($data['email'] ?? ''));
$mobileInput = trim((string)($data['mobile_number'] ?? $data['phone'] ?? ''));
$mobileDigits = preg_replace('/\D+/', '', $mobileInput);

// Force uppercase for first name and last name
$firstName = strtoupper($firstName);
$lastName = strtoupper($lastName);

if ($firstName === '' || $lastName === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'First name and last name are required.']);
    exit;
}

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
    exit;
}

if ($mobileInput === '' || strlen($mobileDigits) < 7) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please enter a valid mobile number.']);
    exit;
}

if (FIREBASE_GUEST_PHONE_AUTH_ENABLED) {
    $verifiedPhone = trim((string)($_SESSION['guest_phone_verified_number'] ?? ''));
    $normalizedInput = '+' . ltrim($mobileDigits, '0');
    if (str_starts_with($mobileInput, '0')) {
        $normalizedInput = '+63' . substr($mobileDigits, 1);
    }

    if (empty($_SESSION['guest_phone_verified']) || $verifiedPhone === '' || $normalizedInput !== $verifiedPhone) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Please verify this mobile number before continuing.']);
        exit;
    }
}

// Store full number in guest_info.mobile_number; country code field kept empty for single-input UI
$countryCode = '';
$mobileNumber = $mobileInput;

try {
    $db = new Database();
    $conn = $db->getConnection();
    ensureGuestInfoSchema($conn);

    $userId = (int)$_SESSION['user_id'];
    $stmt = $conn->prepare(
        'INSERT INTO guest_info (user_id, first_name, last_name, email, mobile_country_code, mobile_number)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    if (!$stmt) {
        throw new Exception('Unable to prepare guest save query.');
    }

    $stmt->bind_param('isssss', $userId, $firstName, $lastName, $email, $countryCode, $mobileNumber);
    if (!$stmt->execute()) {
        throw new Exception('Unable to save guest information.');
    }

    $guestInfoId = (int)$stmt->insert_id;
    $stmt->close();

    // Sync mobile number to the logged-in user's profile phone field
    $profileStmt = $conn->prepare('UPDATE users SET phone = ? WHERE id = ?');
    if ($profileStmt) {
        $profileStmt->bind_param('si', $mobileNumber, $userId);
        $profileStmt->execute();
        $profileStmt->close();
    }

    $_SESSION['guest_info_id'] = $guestInfoId;
    $_SESSION['guest_info'] = [
        'id' => $guestInfoId,
        'first_name' => $firstName,
        'last_name' => $lastName,
        'email' => $email,
        'mobile_country_code' => $countryCode,
        'mobile_number' => $mobileNumber,
        'full_name' => trim($firstName . ' ' . $lastName)
    ];

    echo json_encode([
        'success' => true,
        'message' => 'Guest information saved.',
        'guest_info_id' => $guestInfoId,
        'redirect' => SITE_URL . 'booking.php'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
