<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set headers first
header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/ActivityLogger.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
$cancellationReason = trim((string)($data['cancellation_reason'] ?? ''));

if (strlen($cancellationReason) < 5) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please provide a cancellation reason of at least 5 characters.']);
    exit();
}

try {
    // Keep this endpoint usable on databases created before the cancellation field was added.
    $columnCheck = $conn->query("SHOW COLUMNS FROM reservations LIKE 'cancellation_reason'");
    if ($columnCheck && $columnCheck->num_rows === 0) {
        $conn->query("ALTER TABLE reservations ADD COLUMN cancellation_reason TEXT NULL AFTER status");
    }

    // First verify the reservation belongs to this user
    $verifySql = "SELECT id, status, check_in, tour_type FROM reservations WHERE id = ? AND user_id = ?";
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

    if (!in_array($reservation['status'], ['pending', 'approved'], true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'This reservation cannot be cancelled.']);
        exit();
    }

    $arrival = new DateTime($reservation['check_in']);
    $arrival->setTime($reservation['tour_type'] === 'night' ? 20 : 8, 0, 0);
    $cancellationDeadline = clone $arrival;
    $cancellationDeadline->modify('-24 hours');
    if (new DateTime() >= $cancellationDeadline) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'The cancellation period has ended. Reservations must be cancelled at least 24 hours before arrival.']);
        exit();
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    // Update reservation status to cancelled
    $cancelSql = "UPDATE reservations SET status = 'cancelled', cancellation_reason = ?, updated_at = NOW() WHERE id = ?";
    $cancelStmt = $conn->prepare($cancelSql);
    $cancelStmt->bind_param("si", $cancellationReason, $reservationId);
    $cancelStmt->execute();
    
    // Commit transaction
    $conn->commit();
    logUserActivity($conn, $userId, 'reservation_cancelled', 'Cancelled reservation #' . $reservationId);
    
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