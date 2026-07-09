<?php
/**
 * Email Verification Page
 * Handles email verification for user accounts
 */

require_once 'config/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'models/UserAccount.php';
require_once 'config/database.php';

// Initialize database and user model
$database = new Database();
$db = $database->getConnection();
$userAccount = new UserAccount($db);

// Get verification token from URL
$token = $_GET['token'] ?? '';

$pageTitle = "Email Verification";
$message = "";
$success = false;

if (!empty($token)) {
    // Verify the email token
    $result = $userAccount->verifyEmail($token);
    
    if ($result['success']) {
        $success = true;
        $message = $result['message'];
        $pageTitle = "Email Verified!";
    } else {
        $message = $result['message'];
        $pageTitle = "Verification Failed";
    }
} else {
    $message = "Invalid verification link. Please check your email and try again.";
    $pageTitle = "Invalid Link";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Resort</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <div class="logo">
                    <i class="fas fa-umbrella-beach"></i>
                </div>
                <h2><?php echo $pageTitle; ?></h2>
            </div>
            
            <div class="verification-result">
                <?php if ($success): ?>
                    <div class="success-message">
                        <i class="fas fa-check-circle"></i>
                        <h3><?php echo $message; ?></h3>
                        <p>Your account has been successfully verified and is now active.</p>
                    </div>
                    
                    <div class="verification-actions">
                        <a href="google-auth.php?action=login" class="btn-primary btn-full">
                            <i class="fas fa-sign-in-alt"></i>
                            Proceed to Login
                        </a>
                    </div>
                    
                <?php else: ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-triangle"></i>
                        <h3>Verification Failed</h3>
                        <p><?php echo $message; ?></p>
                    </div>
                    
                    <div class="verification-actions">
                        <a href="google-auth.php?action=login" class="btn-secondary btn-full">
                            <i class="fas fa-arrow-left"></i>
                            Back to Login
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="help-section">
                <h4>Need Help?</h4>
                <p>If you're having trouble verifying your email:</p>
                <ul>
                    <li>Make sure you clicked the most recent verification email</li>
                    <li>Check if the verification link has expired (links expire after 24 hours)</li>
                    <li>Try requesting a new verification email</li>
                    <li>Contact support if problems persist</li>
                </ul>
                
                <div class="help-actions">
                    <button onclick="requestNewVerification()" class="btn-secondary">
                        <i class="fas fa-envelope"></i>
                        Request New Verification
                    </button>
                    <a href="contact.php" class="btn-outline">
                        <i class="fas fa-headset"></i>
                        Contact Support
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function requestNewVerification() {
            // Show loading
            const button = event.target;
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
            button.disabled = true;
            
            // Get email from user (would need to implement email input or session)
            const email = prompt('Please enter your email address:');
            
            if (email) {
                // Send request to server
                fetch('resend_verification.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'email=' + encodeURIComponent(email)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Verification email sent! Please check your inbox.');
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Network error. Please try again.');
                })
                .finally(() => {
                    button.innerHTML = originalText;
                    button.disabled = false;
                });
            } else {
                button.innerHTML = originalText;
                button.disabled = false;
            }
        }
    </script>
    
    <style>
        .verification-result {
            margin: 2rem 0;
        }
        
        .success-message {
            text-align: center;
            padding: 2rem;
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            color: white;
            border-radius: 15px;
            margin-bottom: 2rem;
        }
        
        .success-message i {
            font-size: 3rem;
            margin-bottom: 1rem;
            display: block;
        }
        
        .success-message h3 {
            margin: 0 0 1rem 0;
            font-size: 1.5rem;
        }
        
        .error-message {
            text-align: center;
            padding: 2rem;
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
            border-radius: 15px;
            margin-bottom: 2rem;
        }
        
        .error-message i {
            font-size: 3rem;
            margin-bottom: 1rem;
            display: block;
        }
        
        .error-message h3 {
            margin: 0 0 1rem 0;
            font-size: 1.5rem;
        }
        
        .verification-actions {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .help-section {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 10px;
            margin-top: 2rem;
        }
        
        .help-section h4 {
            color: #333;
            margin-bottom: 1rem;
        }
        
        .help-section p {
            color: #666;
            margin-bottom: 1rem;
        }
        
        .help-section ul {
            margin: 1rem 0;
            padding-left: 1.5rem;
        }
        
        .help-section li {
            color: #666;
            margin: 0.5rem 0;
        }
        
        .help-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }
        
        .btn-outline {
            background: transparent;
            color: #667eea;
            border: 2px solid #667eea;
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-outline:hover {
            background: #667eea;
            color: white;
        }
        
        @media (max-width: 480px) {
            .help-actions {
                flex-direction: column;
            }
            
            .btn-outline {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</body>
</html>
