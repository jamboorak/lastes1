<?php
/**
 * Authentication Controller
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../models/User.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class AuthController {
    private $user;
    
    public function __construct() {
        $this->user = new User();
    }
    
    /**
     * Handle user registration
     */
    public function register() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('google-auth.php?action=login');
            return;
        }
        
        $fullname = trim($_POST['fullname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = trim($_POST['password'] ?? '');
        
        $result = $this->user->register($fullname, $email, $phone, $password);
        
        if ($result['success']) {
            $_SESSION['success'] = $result['message'];
            $this->redirect('google-auth.php?action=login');
        } else {
            $_SESSION['error'] = $result['message'];
            $_SESSION['form_data'] = $_POST;
            $this->redirect('google-auth.php?action=login');
        }
    }
    
    /**
     * Handle user login
     */
    public function login() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('google-auth.php?action=login');
            return;
        }
        
        $emailOrPhone = trim($_POST['email_number'] ?? '');
        $password = trim($_POST['password'] ?? '');
        
        $result = $this->user->login($emailOrPhone, $password);
        
        if ($result['success']) {
            $_SESSION['success'] = $result['message'];
            $this->redirect('index.php');
        } else {
            $_SESSION['error'] = $result['message'];
            $_SESSION['form_data'] = $_POST;
            $this->redirect('google-auth.php?action=login');
        }
    }
    
    /**
     * Handle user logout
     */
    public function logout() {
        $result = $this->user->logout();
        
        if ($result['success']) {
            $_SESSION['success'] = $result['message'];
        } else {
            $_SESSION['error'] = $result['message'];
        }
        
        $this->redirect('index.php');
    }
    
    /**
     * Update user profile
     */
    public function updateProfile() {
        if (!$this->user->isLoggedIn()) {
            $_SESSION['error'] = 'Please login to update your profile';
            $this->redirect('google-auth.php?action=login');
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('profile.php');
            return;
        }
        
        $userId = $_SESSION['user_id'];
        $fullname = trim($_POST['fullname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        
        $result = $this->user->updateProfile($userId, $fullname, $email, $phone);
        
        if ($result['success']) {
            $_SESSION['success'] = $result['message'];
        } else {
            $_SESSION['error'] = $result['message'];
            $_SESSION['form_data'] = $_POST;
        }
        
        $this->redirect('profile.php');
    }
    
    /**
     * Change password
     */
    public function changePassword() {
        if (!$this->user->isLoggedIn()) {
            $_SESSION['error'] = 'Please login to change your password';
            $this->redirect('google-auth.php?action=login');
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('profile.php');
            return;
        }
        
        $userId = $_SESSION['user_id'];
        $currentPassword = trim($_POST['current_password'] ?? '');
        $newPassword = trim($_POST['new_password'] ?? '');
        $confirmPassword = trim($_POST['confirm_password'] ?? '');
        
        if ($newPassword !== $confirmPassword) {
            $_SESSION['error'] = 'New password and confirm password do not match';
            $this->redirect('profile.php');
            return;
        }
        
        $result = $this->user->changePassword($userId, $currentPassword, $newPassword);
        
        if ($result['success']) {
            $_SESSION['success'] = $result['message'];
        } else {
            $_SESSION['error'] = $result['message'];
        }
        
        $this->redirect('profile.php');
    }
    
    /**
     * Require authentication
     */
    public function requireAuth() {
        if (!$this->user->isLoggedIn()) {
            $_SESSION['error'] = 'Please login to continue';
            $this->redirect('google-auth.php?action=login');
            exit();
        }
    }
    
    /**
     * Get current user
     */
    public function getCurrentUser() {
        return $this->user->getCurrentUser();
    }
    
    /**
     * Check if user is logged in
     */
    public function isLoggedIn() {
        return $this->user->isLoggedIn();
    }
    
    /**
     * Redirect to a page using the site base URL
     */
    private function redirect($page) {
        header('Location: ' . SITE_URL . $page);
        exit();
    }
}

// Handle route actions
if (isset($_GET['action'])) {
    $controller = new AuthController();
    
    switch ($_GET['action']) {
        case 'register':
            $controller->register();
            break;
        case 'login':
            $controller->login();
            break;
        case 'logout':
            $controller->logout();
            break;
        case 'update_profile':
            $controller->updateProfile();
            break;
        case 'change_password':
            $controller->changePassword();
            break;
        default:
            header("Location: index.php");
            exit();
    }
}
