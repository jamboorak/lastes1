<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set headers first
header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/RoomConfig.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Debug: Log session data
error_log("Session data: " . print_r($_SESSION, true));

// Initialize database connection
$db = new Database();
$conn = $db->getConnection();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // No session - user not logged in
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please log in to make a reservation']);
    exit();
}

$sessionUserId = $_SESSION['user_id'];
error_log("Session user ID: $sessionUserId");

// Verify the user exists in either users or user_accounts table
$userExists = false;
$verifyUserSql = "SELECT id FROM users WHERE id = ? UNION SELECT id FROM user_accounts WHERE id = ?";
$verifyStmt = $conn->prepare($verifyUserSql);
$verifyStmt->bind_param("ii", $sessionUserId, $sessionUserId);
$verifyStmt->execute();
$userCheckResult = $verifyStmt->get_result();

if ($userCheckResult->num_rows === 0) {
    // Session user ID doesn't exist in either table
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User not found. Please log in again.']);
    exit();
}

$userId = $sessionUserId;
error_log("Session user ID $sessionUserId verified and exists in database");

error_log("Final user ID being used: $userId");

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit();
}

$selectedDate = $data['selectedDate'] ?? '';
$checkIn = $data['checkIn'] ?? $selectedDate;
$checkOut = $data['checkOut'] ?? $selectedDate;
$adults = $data['adults'] ?? 0;
$children = $data['children'] ?? 0;
$seniors = $data['seniors'] ?? 0;
$items = $data['items'] ?? [];
$totalAmount = $data['totalAmount'] ?? 0;
$tourType = $data['tourType'] ?? 'day';

// Validate required fields - items and guests are required, dates are now optional
if (empty($items) || ($adults + $children + $seniors) === 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields: Please select items and specify number of guests']);
    exit();
}

// Use selectedDate or current date if checkIn/checkOut are empty
if (empty($checkIn)) {
    $checkIn = date('Y-m-d');
}
if (empty($checkOut)) {
    $checkOut = date('Y-m-d', strtotime('+1 day'));
}

function getItemLimit($conn, $itemName) {
    $sql = "SELECT daily_limit FROM reservation_limits WHERE item_name = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('s', $itemName);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        if (!empty($res) && isset($res['daily_limit'])) {
            return (int)$res['daily_limit'];
        }
    }

    // Fallback to centralized config if DB entry not found
    return getReservationLimit($itemName);
}

function getBookingDates($checkIn, $checkOut) {
    $dates = [];
    $current = new DateTime($checkIn);
    $endDate = new DateTime($checkOut);

    while ($current < $endDate) {
        $dates[] = $current->format('Y-m-d');
        $current->modify('+1 day');
    }

    return $dates;
}

function getReservedCount($conn, $itemName, $date) {
    $sql = "SELECT COUNT(*) as reserved_count
            FROM reservation_items ri
            JOIN reservations r ON ri.reservation_id = r.id
            WHERE r.status IN ('pending', 'approved')
              AND ri.item_name = ?
              AND ? >= r.check_in
              AND ? < r.check_out";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $itemName, $date, $date);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    return (int) ($result['reserved_count'] ?? 0);
}

$bookingDates = getBookingDates($checkIn, $checkOut);
if (empty($bookingDates)) {
    $bookingDates = [$checkIn];
}

foreach ($items as $item) {
    $limit = getItemLimit($conn, $item['name']);

    foreach ($bookingDates as $date) {
        $reservedCount = getReservedCount($conn, $item['name'], $date);
        
        // Count how many of this item are being booked in the current request
        $currentBookingCount = 0;
        foreach ($items as $checkItem) {
            if ($checkItem['name'] === $item['name']) {
                $currentBookingCount++;
            }
        }

        if (($reservedCount + $currentBookingCount) > $limit) {
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'message' => 'The selected ' . $item['name'] . ' has limited availability. You can book up to ' . ($limit - $reservedCount) . ' more on ' . $date . '. Please adjust your booking.'
            ]);
            exit();
        }
    }
}

try {
    // Start transaction
    $conn->begin_transaction();
    
    // Insert main reservation record
    $reservationSql = "INSERT INTO reservations (user_id, check_in, check_out, adults, children, seniors, total_amount, tour_type, status, created_at) 
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())";
    
    $stmt = $conn->prepare($reservationSql);
    $stmt->bind_param("issiiids", $userId, $checkIn, $checkOut, $adults, $children, $seniors, $totalAmount, $tourType);
    $stmt->execute();
    $reservationId = $stmt->insert_id;
    
    // Insert reservation items (rooms/cottages)
    $itemSql = "INSERT INTO reservation_items (reservation_id, item_type, item_id, item_name, price, capacity, nights, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $itemStmt = $conn->prepare($itemSql);
    
    foreach ($items as $item) {
        $nights = $item['nights'] ?? 1;
        $itemStmt->bind_param("isisidi", $reservationId, $item['type'], $item['id'], $item['name'], $item['price'], $item['capacity'], $nights);
        $itemStmt->execute();
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode(['success' => true, 'message' => 'Reservation submitted successfully and is pending approval', 'reservation_id' => $reservationId]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($conn)) {
        $conn->rollback();
    }
    
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
