<?php
/**
 * Email Log Viewer
 * Shows recent email attempts from the PHP error log
 */

echo "<h2>📨 Email Activity Log</h2>";
echo "<p>Last 20 email-related log entries:</p>";
echo "<hr>";

// Try to read php error log
$logFile = ini_get('error_log');

if ($logFile && file_exists($logFile)) {
    echo "<p><strong>Log File:</strong> <code>" . htmlspecialchars($logFile) . "</code></p>";
    echo "<hr>";
    
    // Read all lines and filter for email entries
    $lines = file($logFile);
    $emailLines = array_filter($lines, function($line) {
        return stripos($line, '📧') !== false || stripos($line, 'email') !== false || 
               stripos($line, 'approval') !== false || stripos($line, 'cancellation') !== false ||
               stripos($line, '✅') !== false || stripos($line, '❌') !== false;
    });
    
    $emailLines = array_reverse($emailLines);
    $emailLines = array_slice($emailLines, 0, 20);
    
    if (count($emailLines) > 0) {
        echo "<h3>Email Activity Found:</h3>";
        echo "<pre style='background: #f0f0f0; padding: 10px; border-radius: 5px; overflow-x: auto;'>";
        foreach ($emailLines as $line) {
            echo htmlspecialchars($line);
        }
        echo "</pre>";
    } else {
        echo "<p>❌ No email activity found in logs yet.</p>";
    }
} else {
    echo "<p>❌ Error log not accessible. Check PHP configuration.</p>";
    echo "<p><strong>Expected location:</strong> <code>" . ($logFile ?: 'Not configured') . "</code></p>";
}

echo "<hr>";
echo "<p><a href='reservations.php'>← Back to Reservations</a></p>";
?>
