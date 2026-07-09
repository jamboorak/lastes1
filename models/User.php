<?php
/**
 * User Model
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class User {
    private $db;
    
    public function __construct($database = null) {
        if ($database) {
            $this->db = $database;
        } else {
            global $db;
            $this->db = $db;
        }
    }
    
    /**
     * Register a new user
     */
    public function register($fullname, $email, $phone, $password) {
        // Validate input
        if (empty($fullname) || empty($email) || empty($phone) || empty($password)) {
            return ['success' => false, 'message' => 'All fields are required'];
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid email format'];
        }
        
        if (strlen($password) < MIN_PASSWORD_LENGTH) {
            return ['success' => false, 'message' => 'Password must be at least ' . MIN_PASSWORD_LENGTH . ' characters'];
        }
        
        // Check if user already exists
        $sql = "SELECT id FROM users WHERE email = ? OR phone = ?";
        $existing = $this->db->getRow($sql, [$email, $phone]);
        
        if ($existing) {
            return ['success' => false, 'message' => 'Email or phone number already registered'];
        }
        
        // Hash password
        $hashedPassword = password_hash($password, HASH_ALGO);
        
        // Insert new user
        $sql = "INSERT INTO users (fullname, email, phone, password) VALUES (?, ?, ?, ?)";
        $userId = $this->db->insert($sql, [$fullname, $email, $phone, $hashedPassword]);
        
        if ($userId) {
            return ['success' => true, 'message' => 'Registration successful', 'user_id' => $userId];
        } else {
            return ['success' => false, 'message' => 'Registration failed'];
        }
    }
    
    /**
     * Authenticate user login
     */
    public function login($emailOrPhone, $password) {
        if (empty($emailOrPhone) || empty($password)) {
            return ['success' => false, 'message' => 'Email/phone and password are required'];
        }
        
        // Get user by email or phone
        $sql = "SELECT id, fullname, email, phone, password FROM users WHERE email = ? OR phone = ?";
        $user = $this->db->getRow($sql, [$emailOrPhone, $emailOrPhone]);
        
        if (!$user) {
            return ['success' => false, 'message' => 'User not found'];
        }
        
        // Verify password
        if (!password_verify($password, $user['password'])) {
            return ['success' => false, 'message' => 'Invalid password'];
        }
        
        // Set session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['fullname'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();
        
        return ['success' => true, 'message' => 'Login successful', 'user' => $user];
    }
    
    /**
     * Logout user
     */
    public function logout() {
        // Unset all session variables
        $_SESSION = [];
        
        // Destroy session
        if (session_destroy()) {
            return ['success' => true, 'message' => 'Logout successful'];
        } else {
            return ['success' => false, 'message' => 'Logout failed'];
        }
    }
    
    /**
     * Check if user is logged in
     */
    public function isLoggedIn() {
        return (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true)
            || !empty($_SESSION['user_id']);
    }
    
    /**
     * Get current user data
     */
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        $userId = $_SESSION['user_id'];
        $sql = "SELECT id, fullname, email, phone, created_at FROM users WHERE id = ?";
        return $this->db->getRow($sql, [$userId]);
    }
    
    /**
     * Update user profile
     */
    public function updateProfile($userId, $fullname, $email, $phone) {
        if (empty($fullname) || empty($email) || empty($phone)) {
            return ['success' => false, 'message' => 'All fields are required'];
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid email format'];
        }
        
        // Check if email/phone is already used by another user
        $sql = "SELECT id FROM users WHERE (email = ? OR phone = ?) AND id != ?";
        $existing = $this->db->getRow($sql, [$email, $phone, $userId]);
        
        if ($existing) {
            return ['success' => false, 'message' => 'Email or phone number already used by another user'];
        }
        
        // Update user
        $sql = "UPDATE users SET fullname = ?, email = ?, phone = ? WHERE id = ?";
        $affected = $this->db->update($sql, [$fullname, $email, $phone, $userId]);
        
        if ($affected > 0) {
            // Update session
            $_SESSION['user_name'] = $fullname;
            $_SESSION['user_email'] = $email;
            return ['success' => true, 'message' => 'Profile updated successfully'];
        } else {
            return ['success' => false, 'message' => 'No changes made'];
        }
    }
    
    /**
     * Change password
     */
    public function changePassword($userId, $currentPassword, $newPassword) {
        if (empty($currentPassword) || empty($newPassword)) {
            return ['success' => false, 'message' => 'Current password and new password are required'];
        }
        
        if (strlen($newPassword) < MIN_PASSWORD_LENGTH) {
            return ['success' => false, 'message' => 'New password must be at least ' . MIN_PASSWORD_LENGTH . ' characters'];
        }
        
        // Get current password hash
        $sql = "SELECT password FROM users WHERE id = ?";
        $user = $this->db->getRow($sql, [$userId]);
        
        if (!$user) {
            return ['success' => false, 'message' => 'User not found'];
        }
        
        // Verify current password
        if (!password_verify($currentPassword, $user['password'])) {
            return ['success' => false, 'message' => 'Current password is incorrect'];
        }
        
        // Hash new password
        $newPasswordHash = password_hash($newPassword, HASH_ALGO);
        
        // Update password
        $sql = "UPDATE users SET password = ? WHERE id = ?";
        $affected = $this->db->update($sql, [$newPasswordHash, $userId]);
        
        if ($affected > 0) {
            return ['success' => true, 'message' => 'Password changed successfully'];
        } else {
            return ['success' => false, 'message' => 'Password change failed'];
        }
    }
    
    /**
     * Get user by ID
     */
    public function getUserById($userId) {
        $sql = "SELECT id, fullname, email, phone, created_at FROM users WHERE id = ?";
        return $this->db->getRow($sql, [$userId]);
    }
    
    /**
     * Get all users (for admin)
     */
    public function getAllUsers($page = 1, $limit = ITEMS_PER_PAGE) {
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT id, fullname, email, phone, created_at FROM users ORDER BY created_at DESC LIMIT ? OFFSET ?";
        return $this->db->getRows($sql, [$limit, $offset]);
    }
    
    /**
     * Get total users count
     */
    public function getTotalUsers() {
        $sql = "SELECT COUNT(*) as total FROM users";
        $result = $this->db->getRow($sql);
        return $result['total'];
    }
    
    /**
     * Get user by social account
     */
    public function getUserBySocialAccount($provider, $socialId) {
        if ($provider === 'google') {
            $sql = "SELECT id, fullname, email, phone, role, created_at FROM users WHERE google_id = ?";
            return $this->db->getRow($sql, [$socialId]);
        } elseif ($provider === 'facebook') {
            $sql = "SELECT id, fullname, email, phone, role, created_at FROM users WHERE facebook_id = ?";
            return $this->db->getRow($sql, [$socialId]);
        }
        return null;
    }
    
    /**
     * Get user by email
     */
    public function getUserByEmail($email) {
        $sql = "SELECT id, fullname, email, phone, role, google_id, facebook_id, created_at FROM users WHERE email = ?";
        return $this->db->getRow($sql, [$email]);
    }
    
    /**
     * Link social account to existing user
     */
    public function linkSocialAccount($userId, $provider, $socialId, $avatar = '') {
        if ($provider === 'google') {
            $sql = "UPDATE users SET google_id = ?, avatar = COALESCE(?, avatar) WHERE id = ?";
            return $this->db->update($sql, [$socialId, $avatar, $userId]);
        } elseif ($provider === 'facebook') {
            $sql = "UPDATE users SET facebook_id = ?, avatar = COALESCE(?, avatar) WHERE id = ?";
            return $this->db->update($sql, [$socialId, $avatar, $userId]);
        }
        return false;
    }
    
    /**
     * Create new user from social login
     */
    public function createSocialUser($userData) {
        $name = $userData['name'] ?? '';
        $email = $userData['email'] ?? '';
        $googleId = $userData['google_id'] ?? null;
        $facebookId = $userData['facebook_id'] ?? null;
        $avatar = $userData['avatar'] ?? '';
        $emailVerified = $userData['email_verified'] ?? 0;
        $registrationMethod = $userData['registration_method'] ?? 'unknown';
        
        if (empty($name) || empty($email) || (empty($googleId) && empty($facebookId))) {
            return false;
        }
        
        // Check if user already exists
        $sql = "SELECT id FROM users WHERE email = ?";
        $existing = $this->db->getRow($sql, [$email]);
        
        if ($existing) {
            return false; // User already exists
        }
        
        // Insert new user
        $sql = "INSERT INTO users (fullname, email, google_id, facebook_id, avatar, email_verified, registration_method, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $params = [$name, $email, $googleId, $facebookId, $avatar, $emailVerified, $registrationMethod];
        
        return $this->db->insert($sql, $params);
    }
    
    /**
     * Update last login timestamp
     */
    public function updateLastLogin($userId) {
        $sql = "UPDATE users SET last_login = NOW() WHERE id = ?";
        return $this->db->update($sql, [$userId]);
    }
    
    /**
     * Get user with role information
     */
    public function getUserWithRole($userId) {
        $sql = "SELECT id, fullname, email, phone, role, google_id, facebook_id, avatar, created_at, last_login FROM users WHERE id = ?";
        return $this->db->getRow($sql, [$userId]);
    }
    
    /**
     * Check if user has linked social accounts
     */
    public function getLinkedSocialAccounts($userId) {
        $sql = "SELECT google_id, facebook_id FROM users WHERE id = ?";
        $user = $this->db->getRow($sql, [$userId]);
        
        $linked = [];
        if ($user) {
            if (!empty($user['google_id'])) {
                $linked[] = 'google';
            }
            if (!empty($user['facebook_id'])) {
                $linked[] = 'facebook';
            }
        }
        
        return $linked;
    }
    
    /**
     * Unlink social account
     */
    public function unlinkSocialAccount($userId, $provider) {
        if ($provider === 'google') {
            $sql = "UPDATE users SET google_id = NULL WHERE id = ?";
            return $this->db->update($sql, [$userId]);
        } elseif ($provider === 'facebook') {
            $sql = "UPDATE users SET facebook_id = NULL WHERE id = ?";
            return $this->db->update($sql, [$userId]);
        }
        return false;
    }
}
