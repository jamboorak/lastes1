<?php
require_once '../config/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized', 'count' => 0, 'items' => []]);
    exit;
}

require_once '../config/database.php';
require_once '../includes/guest_info_schema.php';

$db = new Database();
$conn = $db->getConnection();
ensureGuestInfoSchema($conn);

$guestNameExpr = guestDisplayNameSql('gi', 'u');
$sql = "SELECT r.id, r.check_in, r.check_out, COALESCE(NULLIF(r.status, ''), 'pending') AS status,
               {$guestNameExpr} AS name,
               GROUP_CONCAT(CONCAT(ri.item_name, ' (', ri.item_type, ')') SEPARATOR ', ') AS items
        FROM reservations r
        LEFT JOIN users u ON r.user_id = u.id
        LEFT JOIN guest_info gi ON r.guest_info_id = gi.id
        LEFT JOIN reservation_items ri ON r.id = ri.reservation_id
        WHERE COALESCE(NULLIF(r.status, ''), 'pending') = 'pending'
        GROUP BY r.id
        ORDER BY r.created_at DESC";

$result = $conn->query($sql);
$items = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $items[] = [
            'id' => (int)$row['id'],
            'name' => trim($row['name'] ?? '') !== '' ? $row['name'] : 'Guest',
            'items' => trim($row['items'] ?? '') !== '' ? $row['items'] : 'Reservation',
            'check_in' => $row['check_in'],
            'check_in_label' => date('M d, Y', strtotime($row['check_in'])),
            'status' => $row['status'] ?? 'pending'
        ];
    }
}

echo json_encode([
    'success' => true,
    'count' => count($items),
    'items' => $items
]);
