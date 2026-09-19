<?php
require_once '../config/config.php';
require_once '../config/database.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    $feeType = trim($_POST['fee_type'] ?? '');
    $facilityType = trim($_POST['facility_type'] ?? '');
    $facilityName = trim($_POST['facility_name'] ?? '');
    $amount = (float)($_POST['amount'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $dateIncurred = trim($_POST['date_incurred'] ?? '');

    if ($feeType && $facilityType && $facilityName && $amount > 0 && $dateIncurred) {
        $stmt = $conn->prepare('INSERT INTO maintenance_fees (fee_type, facility_type, facility_name, amount, description, date_incurred) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('ssssds', $feeType, $facilityType, $facilityName, $amount, $description, $dateIncurred);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Maintenance/repair fee saved successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Unable to save fee.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'All required fields must be filled.']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
