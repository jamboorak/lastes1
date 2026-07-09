<?php
require_once '../config/database.php';
require_once '../config/config.php';
require_once '../includes/EmailService.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['reservation_id']) || !isset($data['status'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$reservationId = (int)$data['reservation_id'];
$status = trim($data['status']);

// Validate status
$validStatuses = ['pending', 'approved', 'cancelled', 'completed'];
if (!in_array($status, $validStatuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Get reservation details and user email
    $query = "SELECT r.id, r.user_id, r.check_in, r.check_out, r.total_amount, r.status,
                     u.email, u.fullname, 
                     GROUP_CONCAT(CONCAT(ri.item_name, ' (', ri.item_type, ')') SEPARATOR ', ') as items
              FROM reservations r
              LEFT JOIN users u ON r.user_id = u.id
              LEFT JOIN reservation_items ri ON r.id = ri.reservation_id
              WHERE r.id = ?
              GROUP BY r.id";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        exit;
    }
    
    $stmt->bind_param('i', $reservationId);
    $stmt->execute();
    $result = $stmt->get_result();
    $reservation = $result->fetch_assoc();
    
    if (!$reservation) {
        echo json_encode(['success' => false, 'message' => 'Reservation not found']);
        exit;
    }
    
    // Update reservation status
    $updateQuery = "UPDATE reservations SET status = ?, updated_at = NOW() WHERE id = ?";
    $updateStmt = $conn->prepare($updateQuery);
    if (!$updateStmt) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        exit;
    }
    
    $updateStmt->bind_param('si', $status, $reservationId);
    if (!$updateStmt->execute()) {
        echo json_encode(['success' => false, 'message' => 'Failed to update reservation']);
        exit;
    }
    
    // Send email notification based on status change
    if ($status === 'approved' && !empty($reservation['email'])) {
        sendApprovalEmail($reservation);
    } elseif ($status === 'cancelled' && !empty($reservation['email'])) {
        sendCancellationEmail($reservation);
    }
    
    echo json_encode(['success' => true, 'message' => 'Reservation status updated successfully']);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

/**
 * Send approval notification email to the guest
 */
function sendApprovalEmail($reservation) {
    $guestName = htmlspecialchars($reservation['fullname'] ?? 'Guest');
    $guestEmail = htmlspecialchars($reservation['email']);
    $reservationId = $reservation['id'];
    
    error_log("📧 Attempting to send approval email for Reservation #$reservationId to: $guestEmail");
    $checkIn = date('F d, Y', strtotime($reservation['check_in']));
    $checkOut = date('F d, Y', strtotime($reservation['check_out']));
    $items = htmlspecialchars($reservation['items'] ?? 'N/A');
    $totalAmount = number_format((float)$reservation['total_amount'], 2);
    
    $subject = "Reservation Approved - Villa Soledad Garden Resort #" . $reservationId;
    
    $message = "<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #f9f9f9; border-radius: 8px; }
        .header { background: linear-gradient(135deg, #1e3a8a 0%, #ff8a3d 100%); color: white; padding: 20px; border-radius: 8px 8px 0 0; text-align: center; }
        .content { background: white; padding: 20px; border-radius: 0 0 8px 8px; }
        .reservation-details { background: #f0f4ff; padding: 15px; border-radius: 6px; margin: 15px 0; border-left: 4px solid #1e3a8a; }
        .detail-row { display: flex; justify-content: space-between; margin: 10px 0; }
        .detail-label { font-weight: bold; color: #1e3a8a; }
        .detail-value { color: #333; }
        .amount { font-size: 1.3em; font-weight: bold; color: #ff8a3d; }
        .button { display: inline-block; background: #1e3a8a; color: white; padding: 12px 25px; border-radius: 5px; text-decoration: none; margin-top: 15px; font-weight: bold; }
        .footer { text-align: center; color: #999; font-size: 0.9em; margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>Reservation Approved! ✓</h1>
        </div>
        
        <div class='content'>
            <p>Dear <strong>{$guestName}</strong>,</p>
            
            <p>Great news! Your reservation at <strong>Villa Soledad Garden Resort</strong> has been <strong style='color: #28a745;'>approved</strong>.</p>
            
            <div class='reservation-details'>
                <h3 style='color: #1e3a8a; margin-top: 0;'>Reservation Details</h3>
                
                <div class='detail-row'>
                    <span class='detail-label'>Reservation ID:</span>
                    <span class='detail-value'>#" . $reservationId . "</span>
                </div>
                
                <div class='detail-row'>
                    <span class='detail-label'>Check-in Date:</span>
                    <span class='detail-value'>" . $checkIn . "</span>
                </div>
                
                <div class='detail-row'>
                    <span class='detail-label'>Check-out Date:</span>
                    <span class='detail-value'>" . $checkOut . "</span>
                </div>
                
                <div class='detail-row'>
                    <span class='detail-label'>Items Booked:</span>
                    <span class='detail-value'>" . $items . "</span>
                </div>
                
                <div class='detail-row'>
                    <span class='detail-label'>Total Amount:</span>
                    <span class='detail-value amount'>₱" . $totalAmount . "</span>
                </div>
            </div>
            
            <p>Your reservation is now confirmed and you're all set for your stay. Please arrive at least 30 minutes before your check-in time.</p>
            
            <p>If you have any questions or need to make changes to your reservation, please don't hesitate to contact us:</p>
            
            <p>
                <strong>Phone:</strong> " . SITE_PHONE . "<br>
                <strong>Email:</strong> " . SITE_EMAIL . "<br>
                <strong>Website:</strong> " . SITE_URL . "
            </p>
            
            <p>We look forward to welcoming you at Villa Soledad Garden Resort!</p>
            
            <p>Best regards,<br><strong>Villa Soledad Garden Resort Management</strong></p>
        </div>
        
        <div class='footer'>
            <p>This is an automated email. Please do not reply to this message.</p>
            <p>&copy; " . date('Y') . " Villa Soledad Garden Resort. All rights reserved.</p>
        </div>
    </div>
</body>
</html>";
    
    $emailResult = sendHtmlEmail($guestEmail, $subject, $message, SITE_EMAIL, SITE_NAME);
    if ($emailResult['success']) {
        error_log("✅ Approval email SENT via SMTP for Reservation #$reservationId to: $guestEmail");
    } else {
        error_log("⚠️  Approval email saved to file for Reservation #$reservationId - " . $emailResult['message']);
    }
}

/**
 * Send cancellation notification email to the guest
 */
function sendCancellationEmail($reservation) {
    $guestName = htmlspecialchars($reservation['fullname'] ?? 'Guest');
    $guestEmail = htmlspecialchars($reservation['email']);
    $reservationId = $reservation['id'];
    
    error_log("📧 Attempting to send cancellation email for Reservation #$reservationId to: $guestEmail");
    $checkIn = date('F d, Y', strtotime($reservation['check_in']));
    $checkOut = date('F d, Y', strtotime($reservation['check_out']));
    $items = htmlspecialchars($reservation['items'] ?? 'N/A');
    $totalAmount = number_format((float)$reservation['total_amount'], 2);
    
$subject = "Reservation Cancelled - Villa Soledad Garden Resort #" . $reservationId;
    
    $message = "<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #f9f9f9; border-radius: 8px; }
        .header { background: linear-gradient(135deg, #d32f2f 0%, #ff6b6b 100%); color: white; padding: 20px; border-radius: 8px 8px 0 0; text-align: center; }
        .content { background: white; padding: 20px; border-radius: 0 0 8px 8px; }
        .reservation-details { background: #fff5f5; padding: 15px; border-radius: 6px; margin: 15px 0; border-left: 4px solid #d32f2f; }
        .detail-row { display: flex; justify-content: space-between; margin: 10px 0; }
        .detail-label { font-weight: bold; color: #d32f2f; }
        .detail-value { color: #333; }
        .amount { font-size: 1.3em; font-weight: bold; color: #d32f2f; }
        .button { display: inline-block; background: #1e3a8a; color: white; padding: 12px 25px; border-radius: 5px; text-decoration: none; margin-top: 15px; font-weight: bold; }
        .footer { text-align: center; color: #999; font-size: 0.9em; margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>Reservation Cancelled ✕</h1>
        </div>
        
        <div class='content'>
            <p>Dear <strong>{$guestName}</strong>,</p>
        
            <p>Your reservation at <strong>Villa Soledad Garden Resort</strong> has been <strong style='color: #d32f2f;'>cancelled</strong>.</p>
            
            <div class='reservation-details'>
                <h3 style='color: #d32f2f; margin-top: 0;'>Cancelled Reservation Details</h3>
                
                <div class='detail-row'>
                    <span class='detail-label'>Reservation ID:</span>
                    <span class='detail-value'>#" . $reservationId . "</span>
                </div>
                
                <div class='detail-row'>
                    <span class='detail-label'>Check-in Date:</span>
                    <span class='detail-value'>" . $checkIn . "</span>
                </div>
                
                <div class='detail-row'>
                    <span class='detail-label'>Check-out Date:</span>
                    <span class='detail-value'>" . $checkOut . "</span>
                </div>
                
                <div class='detail-row'>
                    <span class='detail-label'>Items Booked:</span>
                    <span class='detail-value'>" . $items . "</span>
                </div>
                
                <div class='detail-row'>
                    <span class='detail-label'>Reservation Amount:</span>
                    <span class='detail-value amount'>₱" . $totalAmount . "</span>
                </div>
            </div>
            
            <p>If you have any questions regarding this cancellation or would like to make a new reservation, please feel free to contact us:</p>
            
            <p>
                <strong>Phone:</strong> " . SITE_PHONE . "<br>
                <strong>Email:</strong> " . SITE_EMAIL . "<br>
                <strong>Website:</strong> " . SITE_URL . "
            </p>
            
            <p>We hope to welcome you back to Villa Soledad Garden Resort in the future!</p>
            
            <p>Best regards,<br><strong>Villa Soledad Garden Resort Management</strong></p>
        </div>
        
        <div class='footer'>
            <p>This is an automated email. Please do not reply to this message.</p>
            <p>&copy; " . date('Y') . " Villa Soledad Garden Resort. All rights reserved.</p>
        </div>
    </div>
</body>
</html>";
    
    $emailResult = sendHtmlEmail($guestEmail, $subject, $message, SITE_EMAIL, SITE_NAME);
    if ($emailResult['success']) {
        error_log("✅ Cancellation email SENT via SMTP for Reservation #$reservationId to: $guestEmail");
    } else {
        error_log("⚠️  Cancellation email saved to file for Reservation #$reservationId - " . $emailResult['message']);
    }
}
?>
