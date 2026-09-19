<?php
// Database configuration
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'resort_db';

// Create connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "Adding daily_slots column to rooms and cottages tables...<br>";

// Add daily_slots column to rooms table
$checkRoomsColumn = "SHOW COLUMNS FROM rooms LIKE 'daily_slots'";
$result = $conn->query($checkRoomsColumn);

if ($result->num_rows == 0) {
    $sql = "ALTER TABLE rooms ADD COLUMN daily_slots INT(11) DEFAULT 1 AFTER available";
    if ($conn->query($sql) === TRUE) {
        echo "✓ Added daily_slots column to rooms table<br>";
    } else {
        echo "✗ Error adding daily_slots to rooms: " . $conn->error . "<br>";
    }
} else {
    echo "✓ daily_slots column already exists in rooms table<br>";
}

// Add daily_slots column to cottages table
$checkCottagesColumn = "SHOW COLUMNS FROM cottages LIKE 'daily_slots'";
$result = $conn->query($checkCottagesColumn);

if ($result->num_rows == 0) {
    $sql = "ALTER TABLE cottages ADD COLUMN daily_slots INT(11) DEFAULT 1 AFTER available";
    if ($conn->query($sql) === TRUE) {
        echo "✓ Added daily_slots column to cottages table<br>";
    } else {
        echo "✗ Error adding daily_slots to cottages: " . $conn->error . "<br>";
    }
} else {
    echo "✓ daily_slots column already exists in cottages table<br>";
}

// Update existing records with default values from RoomConfig if they have daily_slots = 1
require_once __DIR__ . '/../../config/RoomConfig.php';

$limits = getAllReservationLimits();
foreach ($limits as $itemName => $limit) {
    // Update rooms
    $updateRooms = "UPDATE rooms SET daily_slots = ? WHERE name = ? AND daily_slots = 1";
    $stmt = $conn->prepare($updateRooms);
    $stmt->bind_param('is', $limit, $itemName);
    $stmt->execute();
    
    // Update cottages
    $updateCottages = "UPDATE cottages SET daily_slots = ? WHERE name = ? AND daily_slots = 1";
    $stmt = $conn->prepare($updateCottages);
    $stmt->bind_param('is', $limit, $itemName);
    $stmt->execute();
}

echo "✓ Updated existing records with default values from RoomConfig<br>";

$conn->close();

echo "<br><strong>Migration completed successfully!</strong><br>";
echo "<a href='../../admin/dashboard.php'>Go to Admin Dashboard</a>";
?>
