<?php
/**
 * User Account Model with Verification System
 * Handles all user account operations including verification and security
 */

require_once __DIR__ . '/../config/config.php';

class UserAccount {
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
     * Create new user account with verification
     */
    public function createAccount($userData) {
        try {
            // Validate required fields
            $required = ['fullname', 'email'];
            foreach ($required as $field) {
                if (empty($userData[$field])) {
                    return ['success' => false, 'message' => "Field '$field' is required"];
                }
            }
            
            // Check if email already exists
            if ($this->emailExists($userData['email'])) {
                return ['success' => false, 'message' => 'Email already registered'];
            }
            
            // Hash password if provided
            $hashedPassword = !empty($userData['password']) ? password_hash($userData['password'], PASSWORD_DEFAULT) : null;
            
            // Generate verification token
            $verificationToken = $this->generateVerificationToken();
            $verificationExpires = date('Y-m-d H:i:s', strtotime('+24 hours'));
            
            // Get user IP and user agent
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            
            $sql = "INSERT INTO user_accounts (
                fullname, email, phone, password, google_id, facebook_id, avatar,
                email_verified, phone_verified, account_status, verification_token, verification_expires,
                registration_method, registration_ip, user_agent, role
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $params = [
                $userData['fullname'],
                $userData['email'],
                $userData['phone'] ?? null,
                $hashedPassword,
                $userData['google_id'] ?? null,
                $userData['facebook_id'] ?? null,
                $userData['avatar'] ?? null,
                $userData['email_verified'] ?? 0,
                $userData['phone_verified'] ?? 0,
                $userData['account_status'] ?? 'pending',
                $verificationToken,
                $verificationExpires,
                $userData['registration_method'] ?? 'email',
                $ipAddress,
                $userAgent,
                $userData['role'] ?? 'user'
            ];
            
            $userId = $this->db->insert($sql, $params);
            
            if ($userId) {
                // Send verification email
                $this->sendVerificationEmail($userData['email'], $verificationToken);
                
                return [
                    'success' => true, 
                    'message' => 'Account created successfully. Please check your email for verification.',
                    'user_id' => $userId,
                    'verification_sent' => true
                ];
            } else {
                return ['success' => false, 'message' => 'Failed to create account'];
            }
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Authenticate user login with verification
     */
    public function authenticate($email, $password, $method = 'email') {
        try {
            // Check if account is locked
            $lockCheck = $this->isAccountLocked($email);
            if ($lockCheck['locked']) {
                return ['success' => false, 'message' => $lockCheck['message']];
            }
            
            // Get user by email
            $sql = "SELECT * FROM user_accounts WHERE email = ? AND account_status != 'deleted'";
            $user = $this->db->getRow($sql, [$email]);
            
            if (!$user) {
                $this->recordLoginAttempt(null, $email, 'failed', 'user_not_found');
                return ['success' => false, 'message' => 'Invalid email or password'];
            }
            
            // Verify password for email login
            if ($method === 'email' && !password_verify($password, $user['password'])) {
                $this->recordLoginAttempt($user['id'], $email, 'failed', 'invalid_password');
                $this->incrementLoginAttempts($user['id']);
                return ['success' => false, 'message' => 'Invalid email or password'];
            }
            
            // Check if account is verified
            if ($user['email_verified'] == 0) {
                return ['success' => false, 'message' => 'Please verify your email before logging in', 'requires_verification' => true];
            }
            
            // Check account status
            if ($user['account_status'] !== 'active') {
                $statusMessage = $this->getAccountStatusMessage($user['account_status']);
                return ['success' => false, 'message' => $statusMessage];
            }
            
            // Successful login
            $this->recordLoginAttempt($user['id'], $email, 'success');
            $this->updateLastLogin($user['id']);
            $this->resetLoginAttempts($user['id']);
            $this->createUserSession($user['id']);
            
            return [
                'success' => true,
                'message' => 'Login successful',
                'user' => [
                    'id' => $user['id'],
                    'fullname' => $user['fullname'],
                    'email' => $user['email'],
                    'role' => $user['role'],
                    'avatar' => $user['avatar']
                ]
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Login error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Authenticate social login
     */
    public function authenticateSocial($provider, $socialId, $userData) {
        try {
            $field = $provider === 'google' ? 'google_id' : 'facebook_id';
            $sql = "SELECT * FROM user_accounts WHERE $field = ? AND account_status != 'deleted'";
            $user = $this->db->getRow($sql, [$socialId]);
            
            if ($user) {
                // Existing user - update info and login
                $this->updateSocialUserInfo($user['id'], $userData);
                $this->recordLoginAttempt($user['id'], $user['email'], 'success', null, $provider);
                $this->updateLastLogin($user['id']);
                $this->createUserSession($user['id']);
                
                return [
                    'success' => true,
                    'message' => 'Login successful',
                    'user' => [
                        'id' => $user['id'],
                        'fullname' => $user['fullname'],
                        'email' => $user['email'],
                        'role' => $user['role'],
                        'avatar' => $user['avatar']
                    ]
                ];
            } else {
                // New user - create account
                $userData['registration_method'] = $provider;
                $userData['email_verified'] = 1; // Social accounts are pre-verified
                $userData['account_status'] = 'active';
                $userData[$field] = $socialId;
                
                return $this->createAccount($userData);
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Social login error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Verify email account
     */
    public function verifyEmail($token) {
        try {
            $sql = "SELECT * FROM user_accounts WHERE verification_token = ? AND email_verified = 0 AND verification_expires > NOW()";
            $user = $this->db->getRow($sql, [$token]);
            
            if (!$user) {
                return ['success' => false, 'message' => 'Invalid or expired verification token'];
            }
            
            // Activate account
            $sql = "UPDATE user_accounts SET 
                email_verified = 1, 
                account_status = 'active',
                verification_token = NULL,
                verification_expires = NULL,
                updated_at = NOW()
                WHERE id = ?";
            
            $result = $this->db->update($sql, [$user['id']]);
            
            if ($result) {
                return ['success' => true, 'message' => 'Email verified successfully. You can now login.'];
            } else {
                return ['success' => false, 'message' => 'Failed to verify email'];
            }
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Verification error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Check if email exists
     */
    public function emailExists($email) {
        $sql = "SELECT id FROM user_accounts WHERE email = ?";
        $result = $this->db->getRow($sql, [$email]);
        return $result !== false;
    }
    
    /**
     * Generate verification token
     */
    private function generateVerificationToken() {
        return bin2hex(random_bytes(32));
    }
    
    /**
     * Record login attempt
     */
    private function recordLoginAttempt($userId, $email, $status, $reason = null, $method = 'email') {
        $sql = "INSERT INTO login_attempts (
            user_id, email, ip_address, user_agent, login_method, attempt_status, failure_reason
        ) VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $userId,
            $email,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $method,
            $status,
            $reason
        ];
        
        return $this->db->insert($sql, $params);
    }
    
    /**
     * Update last login
     */
    private function updateLastLogin($userId) {
        $sql = "UPDATE user_accounts SET last_login = NOW() WHERE id = ?";
        return $this->db->update($sql, [$userId]);
    }
    
    /**
     * Increment login attempts
     */
    private function incrementLoginAttempts($userId) {
        $sql = "UPDATE user_accounts SET 
            login_attempts = login_attempts + 1,
            last_login_attempt = NOW()
            WHERE id = ?";
        return $this->db->update($sql, [$userId]);
    }
    
    /**
     * Reset login attempts
     */
    private function resetLoginAttempts($userId) {
        $sql = "UPDATE user_accounts SET 
            login_attempts = 0,
            last_login_attempt = NULL,
            account_locked_until = NULL
            WHERE id = ?";
        return $this->db->update($sql, [$userId]);
    }
    
    /**
     * Check if account is locked
     */
    private function isAccountLocked($email) {
        $sql = "SELECT login_attempts, account_locked_until FROM user_accounts WHERE email = ?";
        $user = $this->db->getRow($sql, [$email]);
        
        if (!$user) return ['locked' => false];
        
        // Check if locked
        if ($user['account_locked_until'] && $user['account_locked_until'] > date('Y-m-d H:i:s')) {
            return [
                'locked' => true,
                'message' => 'Account is locked until ' . $user['account_locked_until']
            ];
        }
        
        // Lock account after 5 failed attempts
        if ($user['login_attempts'] >= 5) {
            $lockUntil = date('Y-m-d H:i:s', strtotime('+30 minutes'));
            $this->db->update("UPDATE user_accounts SET account_locked_until = ? WHERE email = ?", [$lockUntil, $email]);
            
            return [
                'locked' => true,
                'message' => 'Account locked due to too many failed attempts. Try again in 30 minutes.'
            ];
        }
        
        return ['locked' => false];
    }
    
    /**
     * Create user session
     */
    private function createUserSession($userId) {
        $sessionId = session_id();
        $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        $sql = "INSERT INTO user_sessions (
            user_id, session_id, ip_address, user_agent, expires_at
        ) VALUES (?, ?, ?, ?, ?)";
        
        $params = [
            $userId,
            $sessionId,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $expiresAt
        ];
        
        return $this->db->insert($sql, $params);
    }
    
    /**
     * Update social user info
     */
    private function updateSocialUserInfo($userId, $userData) {
        $sql = "UPDATE user_accounts SET 
            fullname = COALESCE(?, fullname),
            avatar = COALESCE(?, avatar),
            updated_at = NOW()
            WHERE id = ?";
        
        return $this->db->update($sql, [$userData['name'], $userData['avatar'], $userId]);
    }
    
    /**
     * Get account status message
     */
    private function getAccountStatusMessage($status) {
        $messages = [
            'pending' => 'Account is pending approval',
            'active' => 'Account is active',
            'suspended' => 'Account is suspended',
            'deleted' => 'Account has been deleted'
        ];
        
        return $messages[$status] ?? 'Unknown account status';
    }
    
    /**
     * Send verification email (placeholder - implement actual email sending)
     */
    private function sendVerificationEmail($email, $token) {
        // In production, implement actual email sending
        $verificationUrl = "http://localhost/restorts/verify_email.php?token=" . $token;
        
        // For now, just log the verification URL
        error_log("Verification URL for $email: $verificationUrl");
        
        return true;
    }
    
    /**
     * Get user by ID
     */
    public function getUserById($userId) {
        $sql = "SELECT * FROM user_accounts WHERE id = ? AND account_status != 'deleted'";
        return $this->db->getRow($sql, [$userId]);
    }
    
    /**
     * Get user by email
     */
    public function getUserByEmail($email) {
        $sql = "SELECT * FROM user_accounts WHERE email = ? AND account_status != 'deleted'";
        return $this->db->getRow($sql, [$email]);
    }
}
?>
