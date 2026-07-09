<?php
require_once 'config/database.php';

// Check what rooms are currently in the database
$database = new Database();
$conn = $database->getConnection();

echo "<h2>Current Rooms in Database:</h2>";
$sql = "SELECT * FROM rooms ORDER BY id";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Name</th><th>Description</th><th>Price</th><th>Action</th></tr>";
    
    while($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . htmlspecialchars($row['name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['description']) . "</td>";
        echo "<td>₱" . number_format($row['price_per_night'], 2) . "</td>";
        echo "<td><button onclick='deleteRoom(" . $row['id'] . ")' style='background: #dc2626; color: white; border: none; padding: 0.5rem; cursor: pointer;'>Delete</button></td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "No rooms found in database.";
}

$conn->close();
?>

<script>
function deleteRoom(roomId) {
    if (confirm('Are you sure you want to delete this room?')) {
        window.location.href = 'delete_room.php?id=' + roomId;
    }
}
</script>

<br><a href='booking.php'>Return to Booking Page</a>
