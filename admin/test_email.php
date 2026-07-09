<?php
/**
 * Email Sending Test & Diagnostic Script
 * Tests if the mail() function is working properly
 */

require_once '../config/config.php';

echo "<h2>📧 Email System Diagnostic</h2>";
echo "<hr>";

// Test 1: Check if mail() function exists
echo "<h3>1. PHP mail() Function</h3>";
if (function_exists('mail')) {
    echo "✅ mail() function is available<br>";
} else {
    echo "❌ mail() function is NOT available<br>";
}

// Test 2: Check PHP mail settings
echo "<h3>2. PHP Mail Configuration</h3>";
$sendmail_path = ini_get('sendmail_path');
$smtp = ini_get('SMTP');
$smtp_port = ini_get('smtp_port');

echo "sendmail_path: <code>" . ($sendmail_path ?: 'Not configured') . "</code><br>";
echo "SMTP Server: <code>" . ($smtp ?: 'Not configured') . "</code><br>";
echo "SMTP Port: <code>" . ($smtp_port ?: 'Not configured') . "</code><br>";

// Test 3: Check configuration constants
echo "<h3>3. Application Email Configuration</h3>";
echo "SITE_EMAIL: <code>" . SITE_EMAIL . "</code><br>";
echo "SITE_PHONE: <code>" . SITE_PHONE . "</code><br>";
echo "SITE_URL: <code>" . SITE_URL . "</code><br>";

// Test 4: Try sending a test email
echo "<h3>4. Test Email Send</h3>";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_email'])) {
    $testEmail = filter_var($_POST['test_email'], FILTER_VALIDATE_EMAIL);
    
    if (!$testEmail) {
        echo "❌ Invalid email address<br>";
    } else {
        $subject = "Test Email from Villa Soledad Garden Resort";
        $message = "<html><body>";
        $message .= "<h2>Email System Test</h2>";
        $message .= "<p>This is a test email to verify that the email system is working.</p>";
        $message .= "<p><strong>Timestamp:</strong> " . date('Y-m-d H:i:s') . "</p>";
        $message .= "</body></html>";
        
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: " . SITE_EMAIL . "\r\n";
        $headers .= "Reply-To: " . SITE_EMAIL . "\r\n";
        
        // Try sending WITHOUT error suppression to see any errors
        $result = mail($testEmail, $subject, $message, $headers);
        
        if ($result) {
            echo "✅ Email send command accepted (returned TRUE)<br>";
            echo "Note: This doesn't guarantee delivery - check the recipient's inbox<br>";
        } else {
            echo "❌ Email send command FAILED (returned FALSE)<br>";
            echo "This means the mail system is not properly configured on this server.<br>";
        }
        
        // Log the attempt
        error_log("Test email sent to: $testEmail | Result: " . ($result ? 'TRUE' : 'FALSE'));
    }
}

// Form to test sending
echo "<h3>5. Send Test Email</h3>";
echo "<form method='POST'>";
echo "<input type='email' name='test_email' placeholder='Enter your email' value='test@example.com' required>";
echo "<button type='submit'>Send Test Email</button>";
echo "</form>";

// Test 5: Check recent error logs
echo "<h3>6. Recent PHP Errors (Last 10 lines)</h3>";
$logFile = ini_get('error_log');
if ($logFile && file_exists($logFile)) {
    echo "<code>" . htmlspecialchars($logFile) . "</code><br><br>";
    $lines = file($logFile);
    $lastLines = array_slice($lines, -10);
    echo "<pre>";
    foreach ($lastLines as $line) {
        echo htmlspecialchars($line);
    }
    echo "</pre>";
} else {
    echo "No error log found or path not accessible<br>";
}

echo "<hr>";
echo "<h3>⚠️ Common Issues</h3>";
echo "<ul>";
echo "<li><strong>Windows/XAMPP:</strong> mail() requires a mail server (Mailhog, SendGrid, Gmail SMTP, etc.)</li>";
echo "<li><strong>Check:</strong> Make sure a valid test email address is configured</li>";
echo "<li><strong>Solution:</strong> Install Mailhog or configure SMTP in php.ini</li>";
echo "</ul>";
?>
