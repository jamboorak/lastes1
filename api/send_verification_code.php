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
$email_number = $input['email_number'] ?? '';
$password = $input['password'] ?? '';

if (empty($email_number) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Email and password are required']);
    exit();
}

// Find user by email or phone
$stmt = $conn->prepare("SELECT id, fullname, email, password FROM users WHERE email = ? OR phone = ?");
$stmt->bind_param("ss", $email_number, $email_number);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'User not found']);
    $stmt->close();
    $conn->close();
    exit();
}

$user = $result->fetch_assoc();

// Verify password
if (!password_verify($password, $user['password'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid password']);
    $stmt->close();
    $conn->close();
    exit();
}

// Generate 6-digit verification code
$verification_code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
$code_expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));

// Store verification code in database
$update_stmt = $conn->prepare("UPDATE users SET verification_code = ?, verification_expiry = ? WHERE id = ?");
$update_stmt->bind_param("ssi", $verification_code, $code_expiry, $user['id']);
$update_stmt->execute();
$update_stmt->close();

// Send email with verification code
$to = $user['email'];
$subject = 'Your Resort Login Verification Code';
$message = "
    <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #f9f9f9; border-radius: 10px; }
                .header { background: orange; color: white; padding: 20px; border-radius: 5px 5px 0 0; text-align: center; }
                .content { padding: 20px; background: white; }
                .code { font-size: 32px; font-weight: bold; color: orange; text-align: center; letter-spacing: 3px; padding: 20px; }
                .footer { font-size: 12px; color: #666; text-align: center; padding: 10px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>Resort - Email Verification</h2>
                </div>
                <div class='content'>
                    <p>Hello " . htmlspecialchars($user['fullname']) . ",</p>
                    <p>Your verification code for Resort login is:</p>
                    <div class='code'>" . $verification_code . "</div>
                    <p>This code will expire in 10 minutes.</p>
                    <p>If you didn't request this code, please ignore this email.</p>
                </div>
                <div class='footer'>
                    <p>&copy; Resort Booking System. All rights reserved.</p>
                </div>
            </div>
        </body>
    </html>
";

// Send email
$headers = "MIME-Version: 1.0" . "\r\n";
$headers .= "Content-type: text/html; charset=UTF-8" . "\r\n";
$headers .= "From: noreply@resort.com" . "\r\n";

if (mail($to, $subject, $message, $headers)) {
    // Store session data temporarily
    $_SESSION['temp_user_id'] = $user['id'];
    $_SESSION['temp_user_name'] = $user['fullname'];
    $_SESSION['temp_user_email'] = $user['email'];
    $_SESSION['verification_pending'] = true;
    
    echo json_encode([
        'success' => true,
        'message' => 'Verification code sent to your email',
        'email' => substr($user['email'], 0, 3) . '****' . substr($user['email'], -3)
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to send verification code']);
}

$stmt->close();
$conn->close();
?>
