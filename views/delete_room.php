<?php
require_once __DIR__ . '/../config/database.php';

$roomId = $_GET['id'] ?? 0;

if ($roomId > 0) {
    $database = new Database();
    $conn = $database->getConnection();
    
    $sql = "DELETE FROM rooms WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $roomId);
    
    if ($stmt->execute()) {
        echo "Room ID $roomId deleted successfully.";
    } else {
        echo "Error deleting room: " . $conn->error;
    }
    
    $stmt->close();
    $conn->close();
    
    echo "<br><a href='check_rooms.php'>Back to Room List</a>";
} else {
    echo "Invalid room ID.";
    echo "<br><a href='check_rooms.php'>Back to Room List</a>";
}
?>
