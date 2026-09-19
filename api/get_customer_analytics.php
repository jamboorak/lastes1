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

    // Bookings per day
    $dailyBookingsQuery = "
        SELECT 
            DATE(check_in) as date,
            COUNT(*) as bookings
        FROM reservations
        WHERE check_in BETWEEN ? AND ?
        GROUP BY DATE(check_in)
        ORDER BY date ASC
    ";
    $stmt = $conn->prepare($dailyBookingsQuery);
    $stmt->bind_param('ss', $startDate, $endDate);
    $stmt->execute();
    $dailyResult = $stmt->get_result();
    $dailyBookings = [];
    while ($row = $dailyResult->fetch_assoc()) {
        $dailyBookings[] = [
            'date' => $row['date'],
            'bookings' => (int)$row['bookings']
        ];
    }

    // Bookings per month
    $monthlyBookingsQuery = "
        SELECT 
            DATE_FORMAT(check_in, '%Y-%m') as month,
            DATE_FORMAT(check_in, '%M %Y') as month_name,
            COUNT(*) as bookings
        FROM reservations
        WHERE check_in BETWEEN ? AND ?
        GROUP BY DATE_FORMAT(check_in, '%Y-%m'), DATE_FORMAT(check_in, '%M %Y')
        ORDER BY month ASC
    ";
    $stmt = $conn->prepare($monthlyBookingsQuery);
    $stmt->bind_param('ss', $startDate, $endDate);
    $stmt->execute();
    $monthlyResult = $stmt->get_result();
    $monthlyBookings = [];
    while ($row = $monthlyResult->fetch_assoc()) {
        $monthlyBookings[] = [
            'month' => $row['month'],
            'month_name' => $row['month_name'],
            'bookings' => (int)$row['bookings']
        ];
    }

    // Peak booking months
    $peakMonthsQuery = "
        SELECT 
            DATE_FORMAT(check_in, '%M %Y') as month_name,
            COUNT(*) as bookings
        FROM reservations
        WHERE check_in BETWEEN ? AND ?
        GROUP BY DATE_FORMAT(check_in, '%Y-%m'), DATE_FORMAT(check_in, '%M %Y')
        ORDER BY bookings DESC
        LIMIT 5
    ";
    $stmt = $conn->prepare($peakMonthsQuery);
    $stmt->bind_param('ss', $startDate, $endDate);
    $stmt->execute();
    $peakResult = $stmt->get_result();
    $peakMonths = [];
    while ($row = $peakResult->fetch_assoc()) {
        $peakMonths[] = [
            'month_name' => $row['month_name'],
            'bookings' => (int)$row['bookings']
        ];
    }

    // Weekend vs Weekday bookings (always include both categories)
    $weekendWeekdayQuery = "
        SELECT 
            CASE 
                WHEN DAYOFWEEK(check_in) IN (1, 7) THEN 'Weekend'
                ELSE 'Weekday'
            END as day_type,
            COUNT(*) as bookings
        FROM reservations
        WHERE check_in BETWEEN ? AND ?
        GROUP BY day_type
    ";
    $stmt = $conn->prepare($weekendWeekdayQuery);
    $stmt->bind_param('ss', $startDate, $endDate);
    $stmt->execute();
    $wwResult = $stmt->get_result();
    $weekendWeekdayMap = ['Weekday' => 0, 'Weekend' => 0];
    while ($row = $wwResult->fetch_assoc()) {
        $type = $row['day_type'];
        if (isset($weekendWeekdayMap[$type])) {
            $weekendWeekdayMap[$type] = (int)$row['bookings'];
        }
    }
    $weekendWeekday = [
        ['day_type' => 'Weekday', 'bookings' => $weekendWeekdayMap['Weekday']],
        ['day_type' => 'Weekend', 'bookings' => $weekendWeekdayMap['Weekend']],
    ];

    // Day vs Night tour comparison (always include both categories)
    $dayNightQuery = "
        SELECT 
            COALESCE(NULLIF(LOWER(tour_type), ''), 'day') as tour_type,
            COUNT(*) as bookings
        FROM reservations
        WHERE check_in BETWEEN ? AND ?
        GROUP BY COALESCE(NULLIF(LOWER(tour_type), ''), 'day')
    ";
    $stmt = $conn->prepare($dayNightQuery);
    $stmt->bind_param('ss', $startDate, $endDate);
    $stmt->execute();
    $dnResult = $stmt->get_result();
    $dayNightMap = ['day' => 0, 'night' => 0];
    while ($row = $dnResult->fetch_assoc()) {
        $type = strtolower((string)$row['tour_type']);
        if ($type === 'night') {
            $dayNightMap['night'] = (int)$row['bookings'];
        } else {
            $dayNightMap['day'] += (int)$row['bookings'];
        }
    }
    $dayNight = [
        ['tour_type' => 'day', 'bookings' => $dayNightMap['day']],
        ['tour_type' => 'night', 'bookings' => $dayNightMap['night']],
    ];

    // Repeat vs New Guests (new = first-ever booking in range; returning = had prior bookings or multiple in range)
    $repeatGuestQuery = "
        SELECT 
            r.user_id,
            COUNT(*) as booking_count,
            (
                SELECT COUNT(*)
                FROM reservations r2
                WHERE r2.user_id = r.user_id
                  AND r2.check_in < ?
            ) as prior_bookings
        FROM reservations r
        WHERE r.check_in BETWEEN ? AND ?
          AND r.user_id IS NOT NULL
        GROUP BY r.user_id
    ";
    $stmt = $conn->prepare($repeatGuestQuery);
    $stmt->bind_param('sss', $startDate, $startDate, $endDate);
    $stmt->execute();
    $rgResult = $stmt->get_result();
    
    $newGuests = 0;
    $returningGuests = 0;
    $totalGuests = 0;
    
    while ($row = $rgResult->fetch_assoc()) {
        $totalGuests++;
        $bookingCount = (int)$row['booking_count'];
        $priorBookings = (int)$row['prior_bookings'];
        if ($priorBookings > 0 || $bookingCount > 1) {
            $returningGuests++;
        } else {
            $newGuests++;
        }
    }

    $guestLoyalty = [
        'total_guests' => $totalGuests,
        'new_guests' => $newGuests,
        'returning_guests' => $returningGuests,
        'new_guests_percentage' => $totalGuests > 0 ? round(($newGuests / $totalGuests) * 100, 1) : 0,
        'returning_guests_percentage' => $totalGuests > 0 ? round(($returningGuests / $totalGuests) * 100, 1) : 0
    ];

    // Calculate occupancy rates
    $occupancyQuery = "
        SELECT 
            DATE(check_in) as date,
            COUNT(*) as bookings,
            SUM(adults + children + seniors) as total_guests
        FROM reservations
        WHERE check_in BETWEEN ? AND ?
        GROUP BY DATE(check_in)
        ORDER BY date ASC
    ";
    $stmt = $conn->prepare($occupancyQuery);
    $stmt->bind_param('ss', $startDate, $endDate);
    $stmt->execute();
    $occupancyResult = $stmt->get_result();
    $occupancyData = [];
    while ($row = $occupancyResult->fetch_assoc()) {
        $occupancyData[] = [
            'date' => $row['date'],
            'bookings' => (int)$row['bookings'],
            'total_guests' => (int)$row['total_guests']
        ];
    }

    // Calculate facility usage
    $facilityUsageQuery = "
        SELECT 
            ri.item_type,
            ri.item_name,
            COUNT(*) as usage_count,
            SUM(r.adults + r.children + r.seniors) as total_users
        FROM reservation_items ri
        JOIN reservations r ON ri.reservation_id = r.id
        WHERE r.check_in BETWEEN ? AND ?
        GROUP BY ri.item_type, ri.item_name
        ORDER BY usage_count DESC
    ";
    $stmt = $conn->prepare($facilityUsageQuery);
    $stmt->bind_param('ss', $startDate, $endDate);
    $stmt->execute();
    $facilityResult = $stmt->get_result();
    $facilityUsage = [];
    while ($row = $facilityResult->fetch_assoc()) {
        $facilityUsage[] = [
            'item_type' => $row['item_type'],
            'item_name' => $row['item_name'],
            'usage_count' => (int)$row['usage_count'],
            'total_users' => (int)$row['total_users']
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'daily_bookings' => $dailyBookings,
            'monthly_bookings' => $monthlyBookings,
            'peak_months' => $peakMonths,
            'weekend_weekday' => $weekendWeekday,
            'day_night_tours' => $dayNight,
            'guest_loyalty' => $guestLoyalty,
            'occupancy_rates' => $occupancyData,
            'facility_usage' => $facilityUsage
        ],
        'period' => [
            'start_date' => $startDate,
            'end_date' => $endDate
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
