<?php
require_once 'config/database.php';
require_once 'config/RoomConfig.php';

$db = new Database();
$conn = $db->getConnection();

// Create the table if it doesn't exist
$conn->query("CREATE TABLE IF NOT EXISTS reservation_limits (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    item_type VARCHAR(50) NOT NULL,
    item_name VARCHAR(150) NOT NULL,
    daily_limit INT(11) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Check if data exists
$checkResult = $conn->query("SELECT COUNT(*) as count FROM reservation_limits");
$row = $checkResult->fetch_assoc();
$totalCount = (int)$row['count'];

if ($totalCount === 0) {
    // Build data from centralized config
    $allData = [];
    
    // Add all rooms from config
    foreach (DEFAULT_ROOMS as $room) {
        $allData[] = ['room', $room['name'], getReservationLimit($room['name'])];
    }
    
    // Add all cottages from config
    foreach (DEFAULT_COTTAGES as $cottage) {
        $allData[] = ['cottage', $cottage['name'], getReservationLimit($cottage['name'])];
    }
    
    $stmt = $conn->prepare("INSERT INTO reservation_limits (item_type, item_name, daily_limit) VALUES (?, ?, ?)");
    if ($stmt) {
        foreach ($allData as $data) {
            $stmt->bind_param('ssi', $data[0], $data[1], $data[2]);
            if (!$stmt->execute()) {
                echo "Error inserting " . htmlspecialchars($data[1]) . ": " . $stmt->error . "<br>";
            }
        }
        $stmt->close();
        echo "<div style='color:green; padding:1rem; background:#f0f9ff; border-radius:4px; margin-bottom:1rem;'><strong>✓ All reservation limits inserted successfully!</strong></div>";
    } else {
        echo "<div style='color:red; padding:1rem; background:#fef2f2; border-radius:4px;'>Error: " . $conn->error . "</div>";
    }
} else {
    echo "<div style='color:blue; padding:1rem; background:#f0f4f8; border-radius:4px; margin-bottom:1rem;'>Reservation limits already exist (" . $totalCount . " records found).</div>";
}

// Display current data
$result = $conn->query("SELECT * FROM reservation_limits ORDER BY item_type, item_name");
echo "<h3 style='color:#1f2937; margin-top:1.5rem;'>Current Reservation Limits:</h3>";
echo "<table style='border-collapse:collapse; width:100%; max-width:600px;'>";
echo "<tr style='background:#f3f4f6;'><th style='border:1px solid #d1d5db; padding:0.75rem; text-align:left;'>Type</th><th style='border:1px solid #d1d5db; padding:0.75rem; text-align:left;'>Name</th><th style='border:1px solid #d1d5db; padding:0.75rem; text-align:left;'>Daily Limit</th></tr>";
while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td style='border:1px solid #d1d5db; padding:0.75rem;'>" . htmlspecialchars($row['item_type']) . "</td>";
    echo "<td style='border:1px solid #d1d5db; padding:0.75rem;'>" . htmlspecialchars($row['item_name']) . "</td>";
    echo "<td style='border:1px solid #d1d5db; padding:0.75rem;'>" . (int)$row['daily_limit'] . "</td>";
    echo "</tr>";
}
echo "</table>";
?>
