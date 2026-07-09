<?php
require_once 'config/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config/database.php';
require_once 'models/User.php';

// Initialize database connection
$database = new Database();

// Initialize User model
$user = new User($database);

// Redirect direct Google GET requests to the OAuth flow
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['provider']) && $_GET['provider'] === 'google') {
    header('Location: google-auth.php?action=login');
    exit;
}

// Set response header
header('Content-Type: application/json');

// Check if it's a social login request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['social_login'])) {
    try {
        $provider = $_POST['provider'] ?? '';
        $socialId = $_POST['social_id'] ?? '';
        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';
        $avatar = $_POST['avatar'] ?? '';
        
        // Validate required fields
        if (empty($provider) || empty($socialId) || empty($email)) {
            throw new Exception('Missing required social login information');
        }
        
        // Validate provider
        if (!in_array($provider, ['google', 'facebook'])) {
            throw new Exception('Invalid social login provider');
        }
        
        // Check if user already exists with this social account
        $existingUser = $user->getUserBySocialAccount($provider, $socialId);
        
        if ($existingUser) {
            // User exists, log them in
            $_SESSION['user_id'] = $existingUser['id'];
            $_SESSION['user_email'] = $existingUser['email'];
            $_SESSION['user_name'] = $existingUser['name'];
            $_SESSION['user_role'] = $existingUser['role'];
            $_SESSION['login_method'] = $provider;
            $_SESSION['logged_in'] = true;
            
            // Update last login
            $user->updateLastLogin($existingUser['id']);
            
            $redirectUrl = $existingUser['role'] === 'admin' ? 'admin/dashboard.php' : 'index.php';
            
            echo json_encode([
                'success' => true,
                'message' => 'Login successful!',
                'redirect_url' => $redirectUrl,
                'user' => [
                    'id' => $existingUser['id'],
                    'name' => $existingUser['name'],
                    'email' => $existingUser['email'],
                    'role' => $existingUser['role']
                ]
            ]);
            
        } else {
            // Check if user exists with same email
            $emailUser = $user->getUserByEmail($email);
            
            if ($emailUser) {
                // User exists with same email but different social account
                // Link the social account to existing user
                $linkResult = $user->linkSocialAccount($emailUser['id'], $provider, $socialId, $avatar);
                
                if ($linkResult) {
                    $_SESSION['user_id'] = $emailUser['id'];
                    $_SESSION['user_email'] = $emailUser['email'];
                    $_SESSION['user_name'] = $emailUser['name'];
                    $_SESSION['user_role'] = $emailUser['role'];
                    $_SESSION['login_method'] = $provider;
                    $_SESSION['logged_in'] = true;
                    
                    // Update last login
                    $user->updateLastLogin($emailUser['id']);
                    
                    $redirectUrl = $emailUser['role'] === 'admin' ? 'admin/dashboard.php' : 'index.php';
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Social account linked successfully!',
                        'redirect_url' => $redirectUrl,
                        'user' => [
                            'id' => $emailUser['id'],
                            'name' => $emailUser['name'],
                            'email' => $emailUser['email'],
                            'role' => $emailUser['role']
                        ]
                    ]);
                } else {
                    throw new Exception('Failed to link social account');
                }
            } else {
                // Create new user account
                $userData = [
                    'name' => $name,
                    'email' => $email,
                    'password' => '', // No password for social login
                    'phone' => '',
                    'role' => 'user',
                    'google_id' => $provider === 'google' ? $socialId : null,
                    'facebook_id' => $provider === 'facebook' ? $socialId : null,
                    'avatar' => $avatar,
                    'email_verified' => 1, // Social accounts are pre-verified
                    'registration_method' => $provider
                ];
                
                $newUserId = $user->createSocialUser($userData);
                
                if ($newUserId) {
                    $_SESSION['user_id'] = $newUserId;
                    $_SESSION['user_email'] = $email;
                    $_SESSION['user_name'] = $name;
                    $_SESSION['user_role'] = 'user';
                    $_SESSION['login_method'] = $provider;
                    $_SESSION['logged_in'] = true;
                    
                    // Update last login
                    $user->updateLastLogin($newUserId);
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Account created and logged in successfully!',
                        'redirect_url' => 'index.php',
                        'user' => [
                            'id' => $newUserId,
                            'name' => $name,
                            'email' => $email,
                            'role' => 'user'
                        ]
                    ]);
                } else {
                    throw new Exception('Failed to create new user account');
                }
            }
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
}
?>
