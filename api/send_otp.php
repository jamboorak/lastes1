<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Check if user data exists from Google login
if (!isset($_SESSION['google_user_data'])) {
    echo json_encode(['success' => false, 'message' => 'Session expired. Please login again.']);
    exit;
}

$userData = $_SESSION['google_user_data'];
$email = $userData['email'];

// Get request data
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? 'send';

try {
    // Initialize database connection
    $database = new Database();
    $db = $database->getConnection();

    // Generate 6-digit OTP
    $otpCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    
    // Set expiry time (5 minutes from now)
    $expiresAt = date('Y-m-d H:i:s', strtotime('+5 minutes'));
    
    // Store user data as JSON
    $userDataJson = json_encode($userData);
    
    // Delete any existing unused OTP for this email
    $deleteSql = "DELETE FROM otp_codes WHERE email = ? AND is_used = 0";
    $database->delete($deleteSql, [$email]);
    
    // Insert new OTP
    $insertSql = "INSERT INTO otp_codes (email, otp_code, user_data, expires_at, is_used) VALUES (?, ?, ?, ?, 0)";
    $database->insert($insertSql, [$email, $otpCode, $userDataJson, $expiresAt]);
    
    // Send OTP via email
    $emailSent = sendOTPEmail($email, $otpCode, $userData['name'] ?? 'User');
    
    if ($emailSent) {
        echo json_encode([
            'success' => true,
            'message' => 'OTP sent successfully to ' . $email
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to send OTP email. Please check your SMTP configuration.'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

/**
 * Send OTP email using Gmail SMTP
 */
function sendOTPEmail($toEmail, $otpCode, $userName) {
    try {
        // Use PHPMailer for sending emails
        require_once __DIR__ . '/../vendor/autoload.php';
        
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port = SMTP_PORT;
        
        // Recipients
        $mail->setFrom(SMTP_USERNAME, SMTP_FROM_NAME);
        $mail->addAddress($toEmail, $userName);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Your OTP Verification Code - ' . SITE_NAME;
        
        $emailBody = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>OTP Verification</title>
        </head>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; border-radius: 10px 10px 0 0; text-align: center;'>
                    <h1 style='color: white; margin: 0; font-size: 28px;'>OTP Verification</h1>
                </div>
                <div style='background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px;'>
                    <p style='font-size: 16px; margin-bottom: 20px;'>Dear <strong>{$userName}</strong>,</p>
                    <p style='font-size: 16px; margin-bottom: 20px;'>Thank you for choosing <strong>" . SITE_NAME . "</strong>. Your One-Time Password (OTP) for account verification is:</p>
                    <div style='background: white; border: 2px solid #667eea; border-radius: 10px; padding: 20px; text-align: center; margin: 20px 0;'>
                        <span style='font-size: 36px; font-weight: bold; color: #667eea; letter-spacing: 5px;'>{$otpCode}</span>
                    </div>
                    <p style='font-size: 14px; color: #666; margin-bottom: 10px;'>This OTP will expire in <strong>5 minutes</strong>.</p>
                    <p style='font-size: 14px; color: #666; margin-bottom: 20px;'>If you did not request this verification, please ignore this email.</p>
                    <hr style='border: none; border-top: 1px solid #ddd; margin: 20px 0;'>
                    <p style='font-size: 12px; color: #999; text-align: center;'>This is an automated email. Please do not reply.</p>
                    <p style='font-size: 12px; color: #999; text-align: center; margin-top: 10px;'>&copy; " . date('Y') . " " . SITE_NAME . ". All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $mail->Body = $emailBody;
        $mail->AltBody = "Your OTP verification code is: {$otpCode}\n\nThis code will expire in 5 minutes.\n\nIf you did not request this verification, please ignore this email.";
        
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("Email sending failed: " . $e->getMessage());
        return false;
    }
}
?>
