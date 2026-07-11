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

    // Query to get monthly revenue data
    $query = "
        SELECT 
            DATE_FORMAT(check_in, '%Y-%m') as month,
            DATE_FORMAT(check_in, '%M %Y') as month_name,
            COUNT(*) as total_bookings,
            COALESCE(SUM(CASE WHEN status IN ('approved', 'completed') THEN total_amount ELSE 0 END), 0) as total_revenue,
            COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_bookings,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_bookings,
            COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_bookings
        FROM reservations 
        WHERE check_in BETWEEN ? AND ?
        GROUP BY DATE_FORMAT(check_in, '%Y-%m'), DATE_FORMAT(check_in, '%M %Y')
        ORDER BY month ASC
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param('ss', $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();

    $monthlyData = [];
    while ($row = $result->fetch_assoc()) {
        $monthlyData[] = [
            'month' => $row['month'],
            'month_name' => $row['month_name'],
            'total_bookings' => (int)$row['total_bookings'],
            'total_revenue' => (float)$row['total_revenue'],
            'approved_bookings' => (int)$row['approved_bookings'],
            'completed_bookings' => (int)$row['completed_bookings'],
            'cancelled_bookings' => (int)$row['cancelled_bookings']
        ];
    }

    // Get overall summary
    $summaryQuery = "
        SELECT 
            COUNT(*) as total_bookings,
            COALESCE(SUM(CASE WHEN status IN ('approved', 'completed') THEN total_amount ELSE 0 END), 0) as total_revenue,
            COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_bookings,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_bookings,
            COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_bookings
        FROM reservations 
        WHERE check_in BETWEEN ? AND ?
    ";

    $stmt = $conn->prepare($summaryQuery);
    $stmt->bind_param('ss', $startDate, $endDate);
    $stmt->execute();
    $summaryResult = $stmt->get_result();
    $summary = $summaryResult->fetch_assoc();

    $summaryData = [
        'total_bookings' => (int)$summary['total_bookings'],
        'total_revenue' => (float)$summary['total_revenue'],
        'approved_bookings' => (int)$summary['approved_bookings'],
        'completed_bookings' => (int)$summary['completed_bookings'],
        'cancelled_bookings' => (int)$summary['cancelled_bookings']
    ];

    echo json_encode([
        'success' => true,
        'data' => $monthlyData,
        'summary' => $summaryData,
        'period' => [
            'start_date' => $startDate,
            'end_date' => $endDate
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
