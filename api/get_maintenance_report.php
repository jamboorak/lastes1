<?php
require_once '../config/config.php';
require_once '../config/database.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    $startDate = $_GET['start_date'] ?? null;
    $endDate = $_GET['end_date'] ?? null;

    if (!$startDate || !$endDate) {
        echo json_encode(['success' => false, 'message' => 'Start date and end date are required']);
        exit();
    }

    // Validate dates
    if (!strtotime($startDate) || !strtotime($endDate)) {
        echo json_encode(['success' => false, 'message' => 'Invalid date format']);
        exit();
    }

    // Query to get maintenance data for the period
    $query = "
        SELECT 
            id,
            fee_type,
            facility_type,
            facility_name,
            amount,
            description,
            date_incurred
        FROM maintenance_fees
        WHERE date_incurred BETWEEN ? AND ?
        ORDER BY date_incurred DESC
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param('ss', $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();

    $maintenanceData = [];
    $totalFees = 0;
    while ($row = $result->fetch_assoc()) {
        $maintenanceData[] = [
            'id' => (int)$row['id'],
            'fee_type' => $row['fee_type'],
            'facility_type' => $row['facility_type'],
            'facility_name' => $row['facility_name'],
            'amount' => (float)$row['amount'],
            'description' => $row['description'],
            'date_incurred' => $row['date_incurred']
        ];
        $totalFees += (float)$row['amount'];
    }

    $summary = [
        'total_fees' => $totalFees,
        'total_count' => count($maintenanceData)
    ];

    echo json_encode([
        'success' => true,
        'data' => $maintenanceData,
        'summary' => $summary,
        'period' => [
            'start_date' => $startDate,
            'end_date' => $endDate
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>