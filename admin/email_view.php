<?php
/**
 * Email View Helper
 */

require_once '../config/config.php';

$file = isset($_GET['file']) ? basename($_GET['file']) : null;
$emailDir = __DIR__ . '/../tmp/emails';
$filePath = $emailDir . '/' . $file;

if (!$file || !file_exists($filePath)) {
    echo "Email not found";
    exit;
}

$content = file_get_contents($filePath);
// Extract only the HTML body (after the double newline that separates headers)
$parts = explode("\r\n\r\n", $content, 2);
if (count($parts) == 2) {
    echo $parts[1];
} else {
    echo htmlspecialchars($content);
}
?>
