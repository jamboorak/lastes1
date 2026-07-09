<?php
require_once '../config/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['error' => 'unauthorized']);
    exit();
}

require_once '../config/database.php';

$db = new Database();
$conn = $db->getConnection();

$totalBookings = 0;
$totalRevenue = 0;

$result = $conn->query('SELECT COUNT(*) AS count FROM reservations');
if ($result) {
    $row = $result->fetch_assoc();
    $totalBookings = (int)($row['count'] ?? 0);
}

$result2 = $conn->query("SELECT COALESCE(SUM(total_amount),0) AS total FROM reservations WHERE COALESCE(NULLIF(status, ''), 'pending') IN ('approved','completed')");
if ($result2) {
    $r2 = $result2->fetch_assoc();
    $totalRevenue = (float)($r2['total'] ?? 0);
}

header('Content-Type: application/json');
echo json_encode(['total_bookings' => $totalBookings, 'total_revenue' => $totalRevenue]);
