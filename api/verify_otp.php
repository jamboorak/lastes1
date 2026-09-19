<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../includes/ActivityLogger.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Check if user data exists from Google login
if (!isset($_SESSION['google_user_data'])) {
    echo json_encode(['success' => false, 'message' => 'Session expired. Please login again.']);
    exit;
}

// Get request data
$input = json_decode(file_get_contents('php://input'), true);
$otpCode = $input['otp'] ?? '';

if (empty($otpCode) || strlen($otpCode) !== 6) {
    echo json_encode(['success' => false, 'message' => 'Invalid OTP format']);
    exit;
}

try {
    // Initialize database connection
    $database = new Database();
    $db = $database->getConnection();
    
    $userEmail = $_SESSION['google_user_data']['email'];
    
    // Debug logging
    error_log("DEBUG - Verifying OTP for email: " . $userEmail);
    error_log("DEBUG - Received OTP: " . $otpCode);
    
    // Get the OTP record (without OTP code in WHERE clause first to see what's stored)
    $sql = "SELECT * FROM otp_codes WHERE email = ? AND is_used = 0 ORDER BY created_at DESC LIMIT 1";
    $otpRecord = $database->getRow($sql, [$userEmail]);
    
    error_log("DEBUG - OTP Record: " . print_r($otpRecord, true));
    
    if (!$otpRecord) {
        echo json_encode(['success' => false, 'message' => 'No OTP found for this email']);
        exit;
    }
    
    // Compare OTP codes
    if ($otpRecord['otp_code'] !== $otpCode) {
        error_log("DEBUG - OTP mismatch. Expected: " . $otpRecord['otp_code'] . ", Got: " . $otpCode);
        echo json_encode(['success' => false, 'message' => 'Invalid OTP code']);
        exit;
    }
    
    // Check if OTP is expired
    $expiresAt = strtotime($otpRecord['expires_at']);
    $currentTime = time();
    
    if ($currentTime > $expiresAt) {
        echo json_encode(['success' => false, 'message' => 'OTP has expired. Please request a new one.']);
        exit;
    }
    
    // Mark OTP as used
    $updateSql = "UPDATE otp_codes SET is_used = 1 WHERE id = ?";
    $database->update($updateSql, [$otpRecord['id']]);
    
    // Process user login/registration
    $userModel = new User($database);
    
    // Get user data from OTP record or session
    $userData = !empty($otpRecord['user_data']) ? json_decode($otpRecord['user_data'], true) : $_SESSION['google_user_data'];
    
    // Check if user exists
    $existingUser = $userModel->getUserByEmail($userEmail);
    
    if ($existingUser) {
        // User exists, log them in
        $_SESSION['user_id'] = $existingUser['id'];
        $_SESSION['user_email'] = $existingUser['email'];
        $_SESSION['user_name'] = $existingUser['fullname'];
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();
        
        // Update last login
        $userModel->updateLastLogin($existingUser['id']);
        logUserActivity($db, $existingUser['id'], 'login', 'User logged in after OTP verification');
        
        // Clear Google user data from session
        unset($_SESSION['google_user_data']);
        
        $redirectUrl = $_SESSION['redirect_after_login'] ?? 'index.php';
        unset($_SESSION['redirect_after_login']);
        
        echo json_encode([
            'success' => true,
            'message' => 'Login successful!',
            'redirect_url' => $redirectUrl
        ]);
    } else {
        // Create a new social-login user account
        $userId = $userModel->createSocialUser([
            'name' => $userData['name'] ?? 'User',
            'email' => $userData['email'],
            'google_id' => $userData['google_id'] ?? null,
            'avatar' => $userData['avatar'] ?? null,
            'email_verified' => 1,
            'registration_method' => 'google'
        ]);

        if ($userId) {
            $_SESSION['user_id'] = $userId;
            $_SESSION['user_email'] = $userData['email'];
            $_SESSION['user_name'] = $userData['name'] ?? 'User';
            $_SESSION['logged_in'] = true;
            $_SESSION['login_time'] = time();
            
            // Update last login
            $userModel->updateLastLogin($userId);
            logUserActivity($db, $userId, 'login', 'User created an account and logged in after OTP verification');
            
            // Clear Google user data from session
            unset($_SESSION['google_user_data']);
            
            $redirectUrl = $_SESSION['redirect_after_login'] ?? 'index.php';
            unset($_SESSION['redirect_after_login']);
            
            echo json_encode([
                'success' => true,
                'message' => 'Account created and logged in successfully!',
                'redirect_url' => $redirectUrl
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to create your account. Please try again.'
            ]);
        }
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
