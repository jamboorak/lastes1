<?php
/**
 * Real-Time Availability API
 * Returns availability data for rooms/cottages for a specific date range
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/RoomConfig.php';

// Get parameters
$startDate = $_GET['start_date'] ?? date('Y-m-d');
$endDate = $_GET['end_date'] ?? date('Y-m-d', strtotime('+1 day'));
$items = $_GET['items'] ?? []; // JSON array of item names to check

// Validate dates
if (strtotime($startDate) > strtotime($endDate)) {
    http_response_code(400);
    echo json_encode(['error' => 'Start date cannot be after end date']);
    exit();
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Create reservation_limits table if not exists
    $conn->query("CREATE TABLE IF NOT EXISTS reservation_limits (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        item_type VARCHAR(50) NOT NULL,
        item_name VARCHAR(150) NOT NULL,
        daily_limit INT(11) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Get item limits from database
    $itemLimits = [];
    $limitResult = $conn->query("SELECT item_name, daily_limit FROM reservation_limits");
    if ($limitResult) {
        while ($limRow = $limitResult->fetch_assoc()) {
            $itemLimits[$limRow['item_name']] = (int)$limRow['daily_limit'];
        }
    }
    
    // If no limits in database, seed them with centralized config
    if (empty($itemLimits)) {
        // Build seed data from centralized config
        $seedData = [];
        
        // Add all rooms from config
        foreach (DEFAULT_ROOMS as $room) {
            $seedData[] = ['room', $room['name'], getReservationLimit($room['name'])];
        }
        
        // Add all cottages from config
        foreach (DEFAULT_COTTAGES as $cottage) {
            $seedData[] = ['cottage', $cottage['name'], getReservationLimit($cottage['name'])];
        }
        
        $stmt = $conn->prepare("INSERT INTO reservation_limits (item_type, item_name, daily_limit) VALUES (?, ?, ?)");
        if ($stmt) {
            foreach ($seedData as $data) {
                $stmt->bind_param('ssi', $data[0], $data[1], $data[2]);
                $stmt->execute();
                $itemLimits[$data[1]] = $data[2];
            }
            $stmt->close();
        }
    }
    
    $availability = [];
    
    // Generate all dates in range
    $current = new DateTime($startDate);
    $end = new DateTime($endDate);
    $dates = [];
    while ($current < $end) {
        $dates[] = $current->format('Y-m-d');
        $current->modify('+1 day');
    }
    
    // Check availability for each item
    foreach ($itemLimits as $itemName => $limit) {
        $itemData = [
            'name' => $itemName,
            'limit' => $limit,
            'daily' => []
        ];
        
        // Check each date
        foreach ($dates as $date) {
            // Count reserved items for this date
            $sql = "SELECT COUNT(*) as reserved_count
                    FROM reservation_items ri
                    JOIN reservations r ON ri.reservation_id = r.id
                    WHERE r.status IN ('pending', 'approved')
                      AND ri.item_name = ?
                      AND ? >= r.check_in
                      AND ? < r.check_out";
            
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            
            $stmt->bind_param("sss", $itemName, $date, $date);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            
            $reserved = (int) ($row['reserved_count'] ?? 0);
            $available = max(0, $limit - $reserved);
            
            $itemData['daily'][$date] = [
                'date' => $date,
                'reserved' => $reserved,
                'available' => $available,
                'status' => $available > 0 ? 'available' : 'fully_booked',
                'percentage' => round(($reserved / $limit) * 100, 0)
            ];
        }
        
        $availability[$itemName] = $itemData;
    }
    
    // Return availability data
    echo json_encode([
        'success' => true,
        'start_date' => $startDate,
        'end_date' => $endDate,
        'dates' => $dates,
        'availability' => $availability
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
