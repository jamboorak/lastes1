<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set headers first
header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Debug: Log session data
error_log("Cancel reservation - Session data: " . print_r($_SESSION, true));

// Initialize database connection
$db = new Database();
$conn = $db->getConnection();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please log in to cancel a reservation']);
    exit();
}

$userId = $_SESSION['user_id'];

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || empty($data['reservation_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit();
}

$reservationId = (int)$data['reservation_id'];

try {
    // First verify the reservation belongs to this user
    $verifySql = "SELECT id, status FROM reservations WHERE id = ? AND user_id = ?";
    $verifyStmt = $conn->prepare($verifySql);
    $verifyStmt->bind_param("ii", $reservationId, $userId);
    $verifyStmt->execute();
    $verifyResult = $verifyStmt->get_result();
    
    if ($verifyResult->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Reservation not found']);
        exit();
    }
    
    $reservation = $verifyResult->fetch_assoc();
    
    // Check if reservation can be cancelled
    if ($reservation['status'] === 'cancelled') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'This reservation is already cancelled']);
        exit();
    }
    
    if ($reservation['status'] === 'completed') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Cannot cancel a completed reservation']);
        exit();
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    // Update reservation status to cancelled
    $cancelSql = "UPDATE reservations SET status = 'cancelled', updated_at = NOW() WHERE id = ?";
    $cancelStmt = $conn->prepare($cancelSql);
    $cancelStmt->bind_param("i", $reservationId);
    $cancelStmt->execute();
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode(['success' => true, 'message' => 'Reservation cancelled successfully']);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($conn)) {
        $conn->rollback();
    }
    
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>