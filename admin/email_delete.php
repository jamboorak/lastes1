<?php
/**
 * Email Delete Helper
 */

require_once '../config/config.php';

header('Content-Type: application/json');

$file = isset($_GET['file']) ? basename($_GET['file']) : null;
$emailDir = __DIR__ . '/../tmp/emails';
$filePath = $emailDir . '/' . $file;

if (!$file || !file_exists($filePath)) {
    echo json_encode(['success' => false, 'message' => 'File not found']);
    exit;
}

if (unlink($filePath)) {
    echo json_encode(['success' => true, 'message' => 'Email deleted']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error deleting file']);
}
?>
