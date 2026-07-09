<?php
require_once '../config/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['error' => 'unauthorized']);
    exit();
}

require_once '../config/database.php';
$db = new Database();
$conn = $db->getConnection();

$year = date('Y');
$stmt = $conn->prepare("SELECT MONTH(check_in) AS month, COUNT(*) AS count FROM reservations WHERE YEAR(check_in) = ? AND COALESCE(NULLIF(status, ''), 'pending') IN ('approved','completed') GROUP BY MONTH(check_in) ORDER BY month");
if ($stmt) {
    $stmt->bind_param('i', $year);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = $res->fetch_all(MYSQLI_ASSOC);
} else {
    $rows = [];
}

$months = array_fill(1,12,0);
foreach ($rows as $r) {
    $m = (int)$r['month'];
    $months[$m] = (int)$r['count'];
}

header('Content-Type: application/json');
echo json_encode(['year' => (int)$year, 'months' => $months]);
