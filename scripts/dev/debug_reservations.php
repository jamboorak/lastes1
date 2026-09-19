<?php
require_once __DIR__ . '/../../config/database.php';

$db = new Database();
$conn = $db->getConnection();

// Check ALL reservations and their statuses
$result = $conn->query("SELECT id, status, user_id FROM reservations ORDER BY id DESC");

echo "<h2>All Reservations in Database:</h2>";
echo "<table border='1' cellpadding='10'>";
echo "<tr><th>ID</th><th>Status (raw)</th><th>Status Length</th><th>Status == 'approved'?</th><th>Status == 'pending'?</th></tr>";

while ($row = $result->fetch_assoc()) {
    $status = $row['status'];
    $isApproved = ($status === 'approved');
    $isPending = ($status === 'pending' || $status === '' || $status === NULL);
    
    echo "<tr>";
    echo "<td>{$row['id']}</td>";
    echo "<td>'" . htmlspecialchars($status) . "'</td>";
    echo "<td>" . strlen($status) . "</td>";
    echo "<td>" . ($isApproved ? 'YES' : 'NO') . "</td>";
    echo "<td>" . ($isPending ? 'YES' : 'NO') . "</td>";
    echo "</tr>";
}

echo "</table>";

echo "<h2>Query Result (Pending Only):</h2>";
$pendingResult = $conn->query("SELECT id, status FROM reservations WHERE status = '' OR status IS NULL OR status = 'pending'");
echo "<p>Found: " . $pendingResult->num_rows . " pending records</p>";

while ($row = $pendingResult->fetch_assoc()) {
    echo "ID: {$row['id']}, Status: '{$row['status']}'<br>";
}
?>
