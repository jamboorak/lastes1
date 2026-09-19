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
            COALESCE(SUM(CASE WHEN status IN ('approved', 'completed') THEN total_amount ELSE 0 END), 0) as gross_revenue,
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
        // Get maintenance fees for this month
        $monthStart = $row['month'] . '-01';
        $monthEnd = $row['month'] . '-31';
        
        $feeQuery = "SELECT COALESCE(SUM(amount), 0) as total_fees FROM maintenance_fees WHERE date_incurred BETWEEN ? AND ?";
        $feeStmt = $conn->prepare($feeQuery);
        $feeStmt->bind_param('ss', $monthStart, $monthEnd);
        $feeStmt->execute();
        $feeResult = $feeStmt->get_result();
        $feeRow = $feeResult->fetch_assoc();
        $totalFees = (float)$feeRow['total_fees'];
        
        $grossRevenue = (float)$row['gross_revenue'];
        $netRevenue = $grossRevenue - $totalFees;
        
        $monthlyData[] = [
            'month' => $row['month'],
            'month_name' => $row['month_name'],
            'total_bookings' => (int)$row['total_bookings'],
            'gross_revenue' => $grossRevenue,
            'maintenance_fees' => $totalFees,
            'net_revenue' => $netRevenue,
            'approved_bookings' => (int)$row['approved_bookings'],
            'completed_bookings' => (int)$row['completed_bookings'],
            'cancelled_bookings' => (int)$row['cancelled_bookings']
        ];
    }

    // Get overall summary
    $summaryQuery = "
        SELECT
            COUNT(*) as total_bookings,
            COALESCE(SUM(CASE WHEN status IN ('approved', 'completed') THEN total_amount ELSE 0 END), 0) as gross_revenue,
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

    // Get detailed maintenance fees for the period
    $feeQuery = "SELECT id, fee_type, facility_type, facility_name, amount, description, date_incurred FROM maintenance_fees WHERE date_incurred BETWEEN ? AND ? ORDER BY date_incurred DESC";
    $feeStmt = $conn->prepare($feeQuery);
    $feeStmt->bind_param('ss', $startDate, $endDate);
    $feeStmt->execute();
    $feeResult = $feeStmt->get_result();

    $feeDetails = [];
    $totalFees = 0;
    while ($feeRow = $feeResult->fetch_assoc()) {
        $feeDetails[] = [
            'id' => (int)$feeRow['id'],
            'fee_type' => $feeRow['fee_type'],
            'facility_type' => $feeRow['facility_type'],
            'facility_name' => $feeRow['facility_name'],
            'amount' => (float)$feeRow['amount'],
            'description' => $feeRow['description'],
            'date_incurred' => $feeRow['date_incurred']
        ];
        $totalFees += (float)$feeRow['amount'];
    }

    $grossRevenue = (float)$summary['gross_revenue'];
    $netRevenue = $grossRevenue - $totalFees;

    $summaryData = [
        'total_bookings' => (int)$summary['total_bookings'],
        'gross_revenue' => $grossRevenue,
        'maintenance_fees' => $totalFees,
        'fee_details' => $feeDetails,
        'net_revenue' => $netRevenue,
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
