<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database configuration
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'resort_db';

// Create connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Check connection
if ($conn->connect_error) {
    die(json_encode(['success' => false, 'message' => 'Database connection failed']));
}

header('Content-Type: application/json');

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
$code = $input['code'] ?? '';

if (empty($code)) {
    echo json_encode(['success' => false, 'message' => 'Verification code is required']);
    exit();
}

if (!isset($_SESSION['temp_user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Session expired. Please login again']);
    exit();
}

$user_id = $_SESSION['temp_user_id'];

// Verify the code
$stmt = $conn->prepare("SELECT id, verification_expiry FROM users WHERE id = ? AND verification_code = ?");
$stmt->bind_param("is", $user_id, $code);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid verification code']);
    $stmt->close();
    $conn->close();
    exit();
}

$user = $result->fetch_assoc();
$current_time = new DateTime();
$expiry_time = new DateTime($user['verification_expiry']);

if ($current_time > $expiry_time) {
    echo json_encode(['success' => false, 'message' => 'Verification code expired. Please try logging in again']);
    session_destroy();
    $stmt->close();
    $conn->close();
    exit();
}

// Code is valid, set permanent session
$_SESSION['user_id'] = $user_id;
$_SESSION['user_name'] = $_SESSION['temp_user_name'];
$_SESSION['user_email'] = $_SESSION['temp_user_email'];

// Clear temporary data and reset verification code
$clear_code = $conn->prepare("UPDATE users SET verification_code = NULL, verification_expiry = NULL WHERE id = ?");
$clear_code->bind_param("i", $user_id);
$clear_code->execute();
$clear_code->close();

unset($_SESSION['temp_user_id']);
unset($_SESSION['temp_user_name']);
unset($_SESSION['temp_user_email']);
unset($_SESSION['verification_pending']);

echo json_encode([
    'success' => true,
    'message' => 'Email verified successfully',
    'redirect' => 'index.php'
]);

$stmt->close();
$conn->close();
?>
