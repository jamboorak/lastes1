<?php
require_once __DIR__ . '/../config/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/RoomConfig.php';

function resolveImageUrl($imageUrl, $default = '') {
    if (empty($imageUrl)) {
        return $default;
    }
    $imageUrl = trim($imageUrl);
    if (preg_match('/^https?:\/\//i', $imageUrl)) {
        return $imageUrl;
    }
    if (strpos($imageUrl, '/') === 0) {
        return rtrim(SITE_URL, '/') . $imageUrl;
    }
    return SITE_URL . $imageUrl;
}

// Determine if user is logged in (require actual login)
$isLoggedIn = isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0;
$userId = $_SESSION['user_id'] ?? 0;

// Redirect to login if user is not logged in
if (!$isLoggedIn) {
    header('Location: ' . SITE_URL . 'google-auth.php?action=login');
    exit;
}

$roomId = $_GET['id'] ?? null;
$type = $_GET['type'] ?? 'room'; // room, cottage

// Get database connection
$db = new Database();
$conn = $db->getConnection();

// Seed default rooms and cottages if tables are empty
$roomCount = 0;
$cottageCount = 0;
if ($countRes = $conn->query("SELECT COUNT(*) AS total FROM rooms")) {
    $roomCount = (int) $countRes->fetch_assoc()['total'];
}
if ($countRes = $conn->query("SELECT COUNT(*) AS total FROM cottages")) {
    $cottageCount = (int) $countRes->fetch_assoc()['total'];
}

// Use centralized config for room and cottage data
$defaultRooms = DEFAULT_ROOMS;

if ($roomCount === 0) {
    foreach ($defaultRooms as $roomData) {
        $conn->query("INSERT INTO rooms (name, description, capacity, price_per_night, image_url, available) VALUES ('" . $conn->real_escape_string($roomData['name']) . "', '" . $conn->real_escape_string($roomData['description']) . "', " . (int)$roomData['capacity'] . ", " . (float)$roomData['price'] . ", '" . $conn->real_escape_string($roomData['image']) . "', " . (int)$roomData['available'] . ")");
    }
}

// Only refresh cottages if they don't exist
if ($cottageCount == 0) {
    // Use centralized config for cottage data
    $defaultCottages = DEFAULT_COTTAGES;
    foreach ($defaultCottages as $cottage) {
        $conn->query("INSERT INTO cottages (name, description, capacity, price_per_night, image_url, available) VALUES ('" . $conn->real_escape_string($cottage['name']) . "', '" . $conn->real_escape_string($cottage['description']) . "', " . (int)$cottage['capacity'] . ", " . (float)$cottage['price'] . ", '" . $conn->real_escape_string($cottage['image']) . "', " . (int)$cottage['available'] . ")");
    }
}

// Fetch selected room or cottage details
if ($roomId) {
    if ($type === 'cottage') {
        $roomSql = "SELECT * FROM cottages WHERE id = ?";
    } else {
        $roomSql = "SELECT * FROM rooms WHERE id = ?";
    }
    $roomStmt = $conn->prepare($roomSql);
    $roomStmt->bind_param("i", $roomId);
    $roomStmt->execute();
    $roomResult = $roomStmt->get_result();
    $room = $roomResult->fetch_assoc();
} else {
    $room = [];
}

// Fetch all available rooms and cottages for booking tabs
$rooms = [];
$cottages = [];
$roomsSql = "SELECT * FROM rooms WHERE available = 1 ORDER BY id";
if ($roomsResult = $conn->query($roomsSql)) {
    $rooms = $roomsResult->fetch_all(MYSQLI_ASSOC);
    $uniqueRooms = [];
    foreach ($rooms as $roomItem) {
        if (!isset($uniqueRooms[$roomItem['name']])) {
            $uniqueRooms[$roomItem['name']] = $roomItem;
        }
    }
    $rooms = array_values($uniqueRooms);
}

$cottagesSql = "SELECT * FROM cottages WHERE available = 1 ORDER BY id";
if ($cottagesResult = $conn->query($cottagesSql)) {
    $cottages = $cottagesResult->fetch_all(MYSQLI_ASSOC);
    $uniqueCottages = [];
    foreach ($cottages as $cottageItem) {
        if (!isset($uniqueCottages[$cottageItem['name']])) {
            $uniqueCottages[$cottageItem['name']] = $cottageItem;
        }
    }
    $cottages = array_values($uniqueCottages);
}

// Room details by image file name for the booking page
$roomDetailsMap = [
    'standard.jpg' => [
        'summary' => 'Good for 2 pax • Resort access for 2 • Aircon Room',
        'extras' => 'Free breakfast for 2 • Full sized bed • Shower w/heater'
    ],
    'deluxe.jpg' => [
        'summary' => 'Good for 2 pax (max 4) • Resort access for 2 • Aircon Room',
        'extras' => 'Free breakfast for 2 • 2 Full sized beds • Shower w/heater'
    ],
    'family-room.svg' => [
        'summary' => 'Good for 4 pax (max 6) • Resort access for 4 • Aircon Room',
        'extras' => 'Free breakfast for 4 • 1 Full sized bed • 1 Double deck • Shower w/heater'
    ],
    'family-deluxe-room.svg' => [
        'summary' => 'Good for 6 pax (max 8) • Resort access for 6 • Aircon Room',
        'extras' => 'Free breakfast for 6 • 4 Full sized beds • 2 Toilet & Bath • Shower w/heater'
    ]
];

// Initialize booking controller for availability checks
require_once __DIR__ . '/../controllers/BookingController.php';
$bookingController = new BookingController();

// Get selected date from session or request
$selectedDate = $_SESSION['selected_date'] ?? $_GET['date'] ?? '';
$availabilityData = [];

if (!empty($selectedDate)) {
    $availabilityData = $bookingController->checkDailyAvailability($selectedDate);
}

// Handle booking submission
$bookingMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$isLoggedIn) {
        $bookingMessage = '<div style="background-color: #fee2e2; color: #7f1d1d; padding: 1rem; border-radius: 5px; margin-bottom: 1rem;"><i class="fas fa-exclamation-circle"></i> Please log in to make a booking.</div>';
    } else {
        $checkIn = $_POST['check_in'] ?? '';
        $checkOut = $_POST['check_out'] ?? '';
        $guests = $_POST['guests'] ?? '';
        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $notes = $_POST['notes'] ?? '';
        $roomType = $_POST['room_type'] ?? '';

        if (!empty($checkIn) && !empty($checkOut) && !empty($guests) && !empty($name)) {
            // Check room availability before booking
            if (!empty($roomType)) {
                $isAvailable = $bookingController->isRoomAvailable($roomType, $checkIn);
                if (!$isAvailable) {
                    $bookingMessage = '<div style="background-color: #fee2e2; color: #7f1d1d; padding: 1rem; border-radius: 5px; margin-bottom: 1rem;"><i class="fas fa-exclamation-circle"></i> This room is not available on the selected date. Please choose a different room or date.</div>';
                } else {
                    $checkInDate = new DateTime($checkIn);
                    $checkOutDate = new DateTime($checkOut);
                    $interval = $checkInDate->diff($checkOutDate);
                    $days = $interval->days ?: 1;
                    $totalPrice = ($room['price_per_night'] ?? 0) * $days;

                    $bookingSql = "INSERT INTO bookings (user_id, room_id, check_in, check_out, guests, total_price, notes, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
                    $bookingStmt = $conn->prepare($bookingSql);
                    $bookingStmt->bind_param("iissiis", $userId, $roomId, $checkIn, $checkOut, $guests, $totalPrice, $notes);

                    if ($bookingStmt->execute()) {
                        $bookingMessage = '<div style="background-color: #d1fae5; color: #065f46; padding: 1rem; border-radius: 5px; margin-bottom: 1rem;"><i class="fas fa-check-circle"></i> Booking confirmed! Booking ID: ' . $bookingStmt->insert_id . '</div>';
                        $_POST = [];
                    } else {
                        $bookingMessage = '<div style="background-color: #fee2e2; color: #7f1d1d; padding: 1rem; border-radius: 5px; margin-bottom: 1rem;"><i class="fas fa-exclamation-circle"></i> Error creating booking. Please try again.</div>';
                    }
                }
            }
        }
    }
}
?>
<?php
$pageTitle = 'Book Your Stay';
$pageHead = <<<PAGE_HEAD
<style>
        /* Complete CSS reset for this page */
        * {
            box-sizing: border-box;
        }
        
        html, body {
            margin: 0 !important;
            padding: 0 !important;
            height: 100%;
            border: none !important;
            outline: none !important;
            overflow-x: hidden;
        }

        body {
            background: #f3f4f6;
            position: relative;
            margin: 0 !important;
            padding: 0 !important;
            top: 0 !important;
            left: 0 !important;
        }

        /* Remove any space from flash messages or other elements */
        .alert, .flash-message {
            margin-top: 0 !important;
            display: none !important; /* Hide flash messages that might cause space */
        }

        /* Also hide any other elements that might appear before header */
        body > div:not(.header):not(.login-modal) {
            display: none !important;
        }

        /* Remove all possible margins from everything before header */
        body > :not(.header) {
            margin-top: 0 !important;
        }

        /* Specific fix for header spacing */
        body > .header:first-child {
            margin-top: 0 !important;
            padding-top: 0 !important;
            transform: translateY(0) !important;
        }

        /* Ensure header is always at top */
        .header {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            margin: 0 !important;
            padding: 1rem 0 !important;
            z-index: 9999 !important;
        }

        /* Add padding to body to account for fixed header */
        body {
            padding-top: 80px !important;
        }

        /* Remove any space from main and body */
        main {
            margin-top: 0 !important;
            padding-top: 0 !important;
        }

        /* Force no space above header */
        body > :first-child {
            margin-top: 0 !important;
        }

        .booking-page {
            padding: 0 1rem 4rem;
            max-width: 1100px;
            margin: 0 auto;
        }

        .booking-header {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            margin: 1.5rem 0 2rem;
            padding-top: 0.5rem;
        }

        .booking-header h1 {
            font-size: clamp(2rem, 3vw, 3rem);
            color: #102a43;
            margin: 0;
            line-height: 1.05;
        }

        .btn-select-date {
            background: #ffffff;
            border: 2px solid #d9e2ec;
            color: #102a43;
            font-weight: 700;
            padding: 0.9rem 1.5rem;
            border-radius: 999px;
            cursor: pointer;
            transition: all 0.25s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-select-date:hover {
            border-color: #ff7a3d;
            color: #ff7a3d;
        }

        .booking-controls {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 2rem;
        }

        .tab-buttons,
        .toggle-buttons {
            display: inline-flex;
            border-radius: 999px;
            background: #ffffff;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
            overflow: hidden;
        }

        .tab-button,
        .toggle-button {
            border: none;
            padding: 0.85rem 1.5rem;
            background: transparent;
            cursor: pointer;
            font-weight: 700;
            color: #334e68;
            transition: all 0.25s ease;
        }

        .tab-button.active,
        .toggle-button.active {
            background: #ff7a3d;
            color: #ffffff;
        }

        .booking-cards {
            display: grid;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
            grid-template-columns: 1fr;
            align-items: start;
        }

        .section-cards {
            display: grid;
            gap: 1.5rem;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            align-items: start;
        }

        /* Ensure uniform card height within each section */
        .section-cards .booking-card {
            display: flex;
            flex-direction: column;
            min-height: 380px;
            overflow: visible;
        }

        .section-cards .booking-card .booking-card-left {
            flex: 1 1 auto;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            padding: 1.25rem;
        }

        .section-cards .booking-card .booking-card-image {
            width: 100%;
            height: 180px;
            border-radius: 12px;
            overflow: hidden;
            display: block;
            flex-shrink: 0;
        }

        .section-cards .booking-card .booking-card-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .section-cards .booking-card .booking-card-right {
            margin-top: auto;
            padding: 0.75rem 1rem 1rem 1rem;
            display: flex;
            gap: 0.5rem;
            align-items: center;
            justify-content: space-between;
            flex-shrink: 0;
        }

        .booking-card {
            background: #ffffff;
            border-radius: 1.5rem;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
            padding: 1.5rem;
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 1rem;
            align-items: center;
            min-height: unset;
        }

        .booking-card-left {
            display: grid;
            gap: 0.75rem;
            padding: 0;
        }

        .booking-card-image {
            width: 140px;
            height: 110px;
            background: #e2e8f0;
            border-radius: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #94a3b8;
            font-size: 2rem;
        }

        .booking-card-right {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 0.75rem;
            margin-top: 1rem;
        }

        .booking-card {
            background: #ffffff;
            border-radius: 28px;
            box-shadow: 0 28px 60px rgba(15, 23, 42, 0.14);
            padding: 0;
            display: flex;
            flex-direction: column;
            gap: 0;
            overflow: hidden;
            min-height: 380px;
        }

        .booking-card-left {
            display: block;
            padding: 1.5rem;
        }

        .booking-card-image {
            width: 100%;
            height: 220px;
            background: #e2e8f0;
            display: block;
            overflow: hidden;
            position: relative;
        }

        .booking-card-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .booking-card-title {
            font-size: 1.35rem;
            font-weight: 800;
            color: #102a43;
            margin: 0;
        }

        .section-title {
            text-align: center;
            color: #102a43;
            font-size: 2rem;
            margin: 0 0 0.75rem 0;
            font-weight: 700;
        }

        .section-title::after {
            content: '';
            display: block;
            width: 64px;
            height: 6px;
            margin: 8px auto 0 auto;
            border-radius: 8px;
            background: linear-gradient(90deg, #ff7a3d, #3b82f6);
        }

        .booking-card .price {
            color: #102a43;
            font-weight: 700;
            font-size: 1.05rem;
            margin-top: 0.5rem;
        }

        .booking-card-meta {
            color: #64748b;
            font-size: 0.95rem;
            margin: 0.15rem 0;
        }

        .availability-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.45rem 0.75rem;
            border-radius: 999px;
            font-size: 0.85rem;
            font-weight: 700;
            color: #ffffff;
            width: fit-content;
        }

        .availability-badge.available {
            background: #059669;
        }

        .availability-badge.unavailable {
            background: #dc2626;
        }

        .availability-badge.date-required {
            background: #6b7280;
        }

        /* Ensure buttons are always visible */
        .section-cards .booking-card .booking-card-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            justify-content: flex-end;
            width: 100%;
        }

        .section-cards .booking-card .btn-view,
        .section-cards .booking-card .btn-add {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 2px solid #ff7a3d;
            border-radius: 999px;
            padding: 0.6rem 1rem;
            cursor: pointer;
            font-weight: 700;
            transition: all 0.25s ease;
            min-width: 80px;
            font-size: 0.9rem;
        }

        .section-cards .booking-card .availability-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.4rem 0.75rem;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 700;
            color: #ffffff;
            width: fit-content;
            margin-top: 0.25rem;
        }

        .booking-card-stars {
            color: #ff7a3d;
            font-size: 0.95rem;
        }

        .booking-card-right {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            justify-content: space-between;
            padding: 0.75rem 1rem 1rem 1rem;
            margin-top: auto;
        }

        .booking-card-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            justify-content: flex-end;
            width: 100%;
        }

        .btn-view,
        .btn-add {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 2px solid #ff7a3d;
            border-radius: 999px;
            padding: 0.85rem 1.4rem;
            cursor: pointer;
            font-weight: 700;
            transition: all 0.25s ease;
            min-width: 100px;
            margin: 0;
        }

        .btn-view {
            background: transparent;
            color: #102a43;
        }

        .btn-view:hover {
            background: #ff7a3d;
            color: #ffffff;
        }

        .btn-add {
            background: #ff7a3d;
            color: #ffffff;
        }

        .btn-add:hover {
            background: #ff9362;
        }

        .booking-footer {
            background: #ffffff;
            border-radius: 1rem;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
            padding: 1rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
        }

        .footer-label {
            color: #64748b;
            font-size: 0.95rem;
        }

        .footer-value {
            font-weight: 700;
            font-size: 1.1rem;
            color: #102a43;
        }

        .btn-reservation {
            text-transform: uppercase;
            font-weight: 700;
            background: transparent;
            border: none;
            color: #102a43;
            cursor: pointer;
            padding: 0.85rem 1.5rem;
            border-radius: 999px;
            transition: all 0.25s ease;
        }

        .btn-reservation:hover {
            background: #f1f5f9;
        }

        .booking-modal {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.75);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            padding: 1rem;
        }

        .booking-modal.open {
            display: flex;
        }

        .booking-modal-content {
            width: min(900px, 100%);
            background: #ffffff;
            border-radius: 1.5rem;
            padding: 2rem;
            box-shadow: 0 28px 80px rgba(15, 23, 42, 0.2);
            position: relative;
        }

        .booking-modal-close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            border: none;
            background: transparent;
            font-size: 1.8rem;
            color: #334e68;
            cursor: pointer;
        }

        .booking-modal-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-top: 1.5rem;
        }

        .booking-modal-large {
            background: #e2e8f0;
            min-height: 320px;
            border-radius: 1.25rem;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #64748b;
            font-size: 2.5rem;
        }

        .booking-modal-details {
            display: grid;
            gap: 1rem;
        }

        .booking-modal-status {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #f8fafc;
            border-radius: 999px;
            padding: 1rem 1.5rem;
            font-weight: 700;
            color: #102a43;
        }

        .booking-modal-meta {
            display: grid;
            gap: 0.75rem;
            padding: 1rem;
            background: #f8fafc;
            border-radius: 1rem;
        }

        .booking-modal-meta span {
            display: inline-flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
        }

        
        .guests-entrance-fees {
            background: #ffffff;
            border-radius: 1.5rem;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
            padding: 2rem;
            display: none;
        }

        .guests-entrance-fees.show {
            display: block;
        }

        .entrance-fees-title {
            font-size: 1.8rem;
            font-weight: 800;
            color: #102a43;
            margin: 0 0 2rem 0;
        }

        .fees-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }

        .fee-card {
            background: #f8fafc;
            border-radius: 1.25rem;
            padding: 1.5rem;
            border: 2px solid #e2e8f0;
        }

        .fee-card-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: #ff7a3d;
            margin: 0 0 1rem 0;
        }

        .fee-items {
            display: grid;
            gap: 0.75rem;
        }

        .fee-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .fee-item:last-child {
            border-bottom: none;
        }

        .fee-item-label {
            color: #475569;
            font-weight: 500;
        }

        .fee-item-price {
            color: #102a43;
            font-weight: 700;
            font-size: 1rem;
        }

        @media (max-width: 900px) {
            .booking-card {
                grid-template-columns: 1fr;
                height: auto !important;
            }

            .booking-card-right {
                align-items: flex-start;
            }

            .booking-footer {
                flex-direction: column;
                align-items: stretch;
            }

            .fees-container {
                grid-template-columns: 1fr;
            }
        }

        /* Reservation Modal Styles */
        .reservation-modal {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.75);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            padding: 1rem;
        }

        .reservation-modal.open {
            display: flex;
        }

        .reservation-modal-content {
            width: min(700px, 100%);
            max-height: 90vh;
            overflow-y: auto;
            background: #ffffff;
            border-radius: 1.5rem;
            padding: 2rem;
            box-shadow: 0 28px 80px rgba(15, 23, 42, 0.2);
            position: relative;
        }

        .reservation-modal-close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            border: none;
            background: transparent;
            font-size: 1.8rem;
            color: #334e68;
            cursor: pointer;
        }

        .reservation-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: #f8fafc;
            border-radius: 0.75rem;
            margin-bottom: 0.75rem;
        }

        .reservation-item-info h4 {
            margin: 0 0 0.25rem 0;
            color: #102a43;
            font-size: 1rem;
        }

        .reservation-item-info p {
            margin: 0;
            color: #64748b;
            font-size: 0.9rem;
        }

        .reservation-item-price {
            color: #102a43;
            font-weight: 700;
            font-size: 1.1rem;
        }

        .reservation-item-remove {
            background: none;
            border: none;
            color: #ef4444;
            cursor: pointer;
            padding: 0.5rem;
            font-size: 1.2rem;
        }

        .bill-line-item {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .bill-line-item:last-child {
            border-bottom: none;
        }

        .bill-label {
            color: #64748b;
        }

        .bill-value {
            color: #102a43;
            font-weight: 600;
        }

        .bill-subtotal {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 2px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            font-size: 1.1rem;
            font-weight: 700;
            color: #102a43;
        }
    </style>
PAGE_HEAD;
?>
    <?php require_once __DIR__ . '/../includes/header.php'; ?>

    <!-- Date Picker Modal with Real-Time Availability Calendar -->
    <div id="datePickerModal" class="date-picker-modal" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.75); align-items:center; justify-content:center; z-index:9999; padding:1rem; overflow-y:auto;">
        <div style="background:#fff; border-radius:1.5rem; padding:2rem; width:min(400px,100%); box-shadow:0 28px 80px rgba(15,23,42,0.2); position:relative; margin:1rem 0;">
            <button onclick="closeDatePickerModal()" style="position:absolute; top:1rem; right:1rem; border:none; background:transparent; font-size:1.8rem; color:#334e68; cursor:pointer;">&times;</button>
            
            <h2 style="color:#102a43; margin:0 0 1.5rem 0; font-size:1.5rem;"><i class="fas fa-calendar-day"></i> Select Reservation Date</h2>
            
            <!-- Quick Date Selection -->
            <div style="margin-bottom:1.5rem;">
                <label style="display:block; color:#64748b; font-size:0.9rem; margin-bottom:0.5rem;">Quick Select</label>
                <input type="date" id="selectedReservationDate" style="width:100%; padding:0.75rem; border:2px solid #e2e8f0; border-radius:0.5rem; font-size:1rem;">
            </div>



            <!-- Availability Summary -->
            <div id="availabilitySummary" style="display:none; background:#fef3c7; border-left:4px solid #f59e0b; padding:1rem; border-radius:0.5rem; margin-bottom:1.5rem;">
                <p style="margin:0; color:#92400e; font-size:0.9rem;" id="summaryText"></p>
            </div>

            <button onclick="saveSelectedDate()" style="width:100%; padding:1rem; background:#ff7a3d; color:#fff; border:none; border-radius:0.75rem; font-size:1rem; font-weight:700; cursor:pointer; transition:all 0.3s ease;" onmouseover="this.style.background='#ff9362'" onmouseout="this.style.background='#ff7a3d'">Confirm Date</button>
        </div>
    </div>

    <main class="booking-page" style="margin-top: 0; padding-top: 0;">
        <div class="booking-header">
            <h1>Start your booking now!</h1>
            <button class="btn-select-date" onclick="openDatePickerModal()"><i class="fas fa-calendar-day"></i> Select Date</button>
        </div>

        <!-- Date Selection Notice -->
        <div id="dateNotice" style="background: #fff3cd; border: 2px solid #ffc107; border-radius: 0.75rem; padding: 1rem 1.5rem; margin-bottom: 1.5rem; display: flex; align-items: center;">
            <span style="color: #856404; font-weight: 600;">Please select a reservation date first before adding rooms or cottages to your booking.</span>
        </div>

        <div class="booking-controls">
            <div class="reservation-summary-mini" style="display:flex; align-items:center; gap:1rem;">
                <div style="background:#fff; padding:0.75rem 1rem; border-radius:12px; border:1px solid #e8eef6; display:flex; gap:1rem; align-items:center;">
                    <div>
                        <div style="font-size:0.85rem; color:#64748b;">Total Price:</div>
                        <div style="font-weight:700; color:#102a43;"> <span id="miniTotal">₱#,###,##</span></div>
                    </div>
                    <button class="btn-small btn-view" onclick="openReservationModal()" style="background:transparent; border:none; color:#102a43; font-weight:700; display:flex; align-items:center; gap:0.5rem;"><i class="fas fa-eye"></i> View</button>
                </div>
            </div>
            <div class="toggle-buttons">
                <button class="toggle-button active" data-toggle="day">Day</button>
                <button class="toggle-button" data-toggle="night">Night</button>
            </div>
        </div>

        <div class="booking-cards" id="bookingCards">
            <section class="rooms-section">
                <h2 class="section-title">Rooms</h2>
                <?php $roomCount = max(1, is_array($rooms) ? count($rooms) : 0); ?>
                <div class="section-cards" style="--cols: <?php echo $roomCount; ?>; --gap: 16px;">
                <?php if (!empty($rooms)): ?>
                    <?php foreach ($rooms as $roomItem): ?>
                    <?php $roomImage = basename($roomItem['image_url'] ?? ''); ?>
                    <?php $roomDetail = $roomDetailsMap[$roomImage] ?? null; ?>
                    <?php 
                    $roomName = $roomItem['name'];
                    $isAvailable = !empty($selectedDate) && isset($availabilityData[$roomName]) ? $availabilityData[$roomName]['available'] > 0 : true;
                    $availabilityInfo = isset($availabilityData[$roomName]) ? $availabilityData[$roomName] : ['limit' => 0, 'booked' => 0, 'available' => 0];
                    ?>
                    <div class="booking-card" data-type="rooms" data-room-name="<?php echo htmlspecialchars($roomName); ?>">
                        <div class="booking-card-left">
                            <div class="booking-card-image">
                                <?php if (!empty($roomItem['image_url'])): ?>
                                    <img src="<?php echo htmlspecialchars(resolveImageUrl($roomItem['image_url'])); ?>" alt="<?php echo htmlspecialchars($roomItem['name']); ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: 1rem;">
                                <?php else: ?>
                                    <i class="fas fa-bed"></i>
                                <?php endif; ?>
                            </div>
                            <h2 class="booking-card-title"><?php echo htmlspecialchars($roomItem['name']); ?></h2>
                            <?php if ($roomDetail): ?>
                                <p class="booking-card-meta"><?php echo htmlspecialchars($roomDetail['summary']); ?></p>
                            <?php else: ?>
                                <p class="booking-card-meta"><?php echo htmlspecialchars($roomItem['description']); ?></p>
                            <?php endif; ?>
                            <p class="booking-card-meta">₱<?php echo number_format($roomItem['price_per_night'], 2); ?></p>
                            <span class="availability-badge <?php echo $isAvailable ? 'available' : 'date-required'; ?>">
                                <i class="fas <?php echo $isAvailable ? 'fa-check-circle' : 'fa-calendar-alt'; ?>"></i>
                                <?php echo !empty($selectedDate) ? ($isAvailable ? $availabilityInfo['available'] . ' available' : 'Unavailable') : 'Select date'; ?>
                            </span>
                                                    </div>
                        <div class="booking-card-right">
                            <div class="booking-card-actions">
                                <button type="button" class="btn-view" onclick="openBookingModal(this)" data-name="<?php echo htmlspecialchars($roomItem['name'], ENT_QUOTES); ?>" data-description="<?php echo htmlspecialchars($roomItem['description'], ENT_QUOTES); ?>" data-price="₱<?php echo number_format($roomItem['price_per_night'], 2); ?>" data-capacity="<?php echo htmlspecialchars($roomItem['capacity'], ENT_QUOTES); ?>" data-summary="<?php echo htmlspecialchars($roomDetail['summary'] ?? '', ENT_QUOTES); ?>" data-extras="<?php echo htmlspecialchars($roomDetail['extras'] ?? '', ENT_QUOTES); ?>" data-image="<?php echo htmlspecialchars(resolveImageUrl($roomItem['image_url'] ?? ''), ENT_QUOTES); ?>" data-price-num="<?php echo $roomItem['price_per_night']; ?>">View</button>
                                <button type="button" class="btn-add" 
                                        onclick="addToReservation('room', <?php echo $roomItem['id']; ?>, '<?php echo htmlspecialchars($roomItem['name'], ENT_QUOTES); ?>', <?php echo $roomItem['price_per_night']; ?>, <?php echo $roomItem['capacity']; ?>)">
                                    Add
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="booking-card" data-type="rooms">
                    <div class="booking-card-left">
                        <div class="booking-card-image"><i class="fas fa-bed"></i></div>
                        <h2 class="booking-card-title">No rooms available</h2>
                    </div>
                </div>
                <?php endif; ?>
                </div>
            </section>

            <section class="cottages-section" style="margin-top:1.5rem;">
                <h2 class="section-title">Cottages</h2>
                <?php $cottageCount = max(1, is_array($cottages) ? count($cottages) : 0); ?>
                <div class="section-cards" style="--cols: <?php echo $cottageCount; ?>; --gap: 16px;">
                <?php if (!empty($cottages)): ?>
                    <?php foreach ($cottages as $cottage): ?>
                    <?php 
                    $cottageName = $cottage['name'];
                    $isAvailable = !empty($selectedDate) && isset($availabilityData[$cottageName]) ? $availabilityData[$cottageName]['available'] > 0 : true;
                    $availabilityInfo = isset($availabilityData[$cottageName]) ? $availabilityData[$cottageName] : ['limit' => 0, 'booked' => 0, 'available' => 0];
                    ?>
                    <div class="booking-card" data-type="cottages" data-room-name="<?php echo htmlspecialchars($cottageName); ?>">
                        <div class="booking-card-left">
                            <div class="booking-card-image">
                                <?php if (!empty($cottage['image_url'])): ?>
                                    <img src="<?php echo htmlspecialchars(resolveImageUrl($cottage['image_url'])); ?>" alt="<?php echo htmlspecialchars($cottage['name']); ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: 1rem;">
                                <?php else: ?>
                                    <i class="fas fa-home"></i>
                                <?php endif; ?>
                            </div>
                            <h2 class="booking-card-title"><?php echo htmlspecialchars($cottage['name']); ?></h2>
                            <p class="booking-card-meta">Good for <?php echo htmlspecialchars($cottage['capacity']); ?> people</p>
                            <p class="booking-card-meta">₱<?php echo number_format($cottage['price_per_night'], 2); ?></p>
                            <span class="availability-badge <?php echo $isAvailable ? 'available' : 'date-required'; ?>">
                                <i class="fas <?php echo $isAvailable ? 'fa-check-circle' : 'fa-calendar-alt'; ?>"></i>
                                <?php echo !empty($selectedDate) ? ($isAvailable ? $availabilityInfo['available'] . ' available' : 'Unavailable') : 'Select date'; ?>
                            </span>
                                                    </div>
                        <div class="booking-card-right">
                            <div class="booking-card-actions">
                                <button type="button" class="btn-view" onclick="openBookingModal(this)" data-name="<?php echo htmlspecialchars($cottage['name'], ENT_QUOTES); ?>" data-description="<?php echo htmlspecialchars($cottage['description'], ENT_QUOTES); ?>" data-price="₱<?php echo number_format($cottage['price_per_night'], 2); ?>" data-capacity="<?php echo htmlspecialchars($cottage['capacity'], ENT_QUOTES); ?>" data-image="<?php echo htmlspecialchars(resolveImageUrl($cottage['image_url'] ?? ''), ENT_QUOTES); ?>" data-price-num="<?php echo $cottage['price_per_night']; ?>">View</button>
                                <button type="button" class="btn-add" 
                                        onclick="addToReservation('cottage', <?php echo $cottage['id']; ?>, '<?php echo htmlspecialchars($cottage['name'], ENT_QUOTES); ?>', <?php echo $cottage['price_per_night']; ?>, <?php echo $cottage['capacity']; ?>)">
                                    Add
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php else: ?>
                    <div class="booking-card" data-type="cottages">
                        <div class="booking-card-left">
                            <div class="booking-card-image"><i class="fas fa-home"></i></div>
                            <h2 class="booking-card-title">No cottages available</h2>
                        </div>
                    </div>
                <?php endif; ?>
                </div>
            </section>

            <div class="guests-entrance-fees" id="guestsSection" style="display:none;">
                <h2 class="entrance-fees-title">Entrance Fees</h2>
                <div class="fees-container">
                    <div class="fee-card">
                        <h3 class="fee-card-title">Day (8AM - 5PM)</h3>
                        <div class="fee-items">
                            <div class="fee-item">
                                <span class="fee-item-label">Adult</span>
                                <span class="fee-item-price">₱150</span>
                            </div>
                            <div class="fee-item">
                                <span class="fee-item-label">Child</span>
                                <span class="fee-item-price">₱80</span>
                            </div>
                        </div>
                    </div>
                    <div class="fee-card">
                        <h3 class="fee-card-title">Night (8PM - 5AM)</h3>
                        <div class="fee-items">
                            <div class="fee-item">
                                <span class="fee-item-label">Adult</span>
                                <span class="fee-item-price">₱180</span>
                            </div>
                            <div class="fee-item">
                                <span class="fee-item-label">Child</span>
                                <span class="fee-item-price">₱100</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div id="bookingModal" class="booking-modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
            <div class="booking-modal-content">
                <button class="booking-modal-close" onclick="closeBookingModal()" aria-label="Close">×</button>
                <h2 id="modalTitle" style="color:#102a43; margin:0; font-size:2rem;">Room ##</h2>
                <div class="booking-modal-grid">
                    <div class="booking-modal-large" id="modalImage"></div>
                    <div class="booking-modal-details">
                        <div class="booking-modal-status">
                            <span>Status</span>
                            <span id="modalStatus">Available</span>
                        </div>
                        <div class="booking-modal-meta">
                            <span><strong>Price</strong><strong id="modalPrice">₱0.00</strong></span>
                            <span><strong>Capacity</strong><strong id="modalCapacity">0 people</strong></span>
                        </div>
                        <p id="modalDescription" style="color:#64748b; margin:1rem 0; line-height:1.7;">Room description goes here.</p>
                        <div id="modalSummary" style="margin-bottom:1rem; color:#334e68;"></div>
                        <div id="modalExtras" style="margin-bottom:1rem; color:#334e68;"></div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Reservation Popup Modal -->
    <div id="reservationModal" class="reservation-modal" role="dialog" aria-modal="true">
        <div class="reservation-modal-content">
            <button class="reservation-modal-close" onclick="closeReservationModal()" aria-label="Close">×</button>
            <h2 style="color:#102a43; margin:0 0 1.5rem 0; font-size:1.8rem;">Your Reservation</h2>
            
            <div id="selectedDateSection" style="display:none; background:#fff3cd; padding:1rem; border-radius:0.75rem; margin-bottom:1.5rem; text-align:center;">
                <i class="fas fa-calendar-day" style="color:#856404;"></i> <strong style="color:#856404;">Selected Date:</strong> <span id="reservationSelectedDate" style="color:#102a43; font-weight:700;"></span>
            </div>
            
            <!-- Hidden date inputs for calculateTotal() -->
            <input type="hidden" id="checkInDate" value="">
            <input type="hidden" id="checkOutDate" value="">
            
            <div id="reservationItems">
                <p style="color:#64748b; text-align:center; padding:2rem;">No items selected yet. Add rooms or cottages to your reservation.</p>
            </div>
            
            <div class="reservation-summary" id="reservationSummary" style="display:none;">
                <div class="reservation-guests" style="background:#f8fafc; padding:1.5rem; border-radius:1rem; margin:1.5rem 0;">
                    <h3 style="color:#102a43; margin:0 0 1rem 0; font-size:1.2rem;">Number of Guests</h3>
                    <div style="display:flex; gap:1rem; flex-wrap:wrap;">
                        <div style="flex:1; min-width:120px;">
                            <label style="display:block; color:#64748b; font-size:0.9rem; margin-bottom:0.5rem;">Adults</label>
                            <input type="number" id="guestAdults" min="1" value="1" onchange="calculateTotal()" style="width:100%; padding:0.75rem; border:2px solid #e2e8f0; border-radius:0.5rem; font-size:1rem;">
                        </div>
                        <div style="flex:1; min-width:120px;">
                            <label style="display:block; color:#64748b; font-size:0.9rem; margin-bottom:0.5rem;">Children</label>
                            <input type="number" id="guestChildren" min="0" value="0" onchange="calculateTotal()" style="width:100%; padding:0.75rem; border:2px solid #e2e8f0; border-radius:0.5rem; font-size:1rem;">
                        </div>
                    </div>
                </div>
                
                <div class="reservation-breakdown" style="background:#f8fafc; padding:1.5rem; border-radius:1rem;">
                    <h3 style="color:#102a43; margin:0 0 1rem 0; font-size:1.2rem;">Bill Breakdown</h3>
                    <div id="billBreakdown"></div>
                </div>
                
                <div class="reservation-total" style="margin-top:1.5rem; padding:1.5rem; background:#ff7a3d; border-radius:1rem; color:#fff;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="font-size:1.2rem; font-weight:700;">Total Amount</span>
                        <span id="reservationTotal" style="font-size:1.5rem; font-weight:800;">₱0.00</span>
                    </div>
                </div>
                
                <button type="button" class="btn-confirm-reservation" onclick="confirmReservation()" style="width:100%; margin-top:1.5rem; padding:1rem; background:#102a43; color:#fff; border:none; border-radius:0.75rem; font-size:1.1rem; font-weight:700; cursor:pointer;">Confirm Reservation</button>
            </div>
        </div>
    </div>

    <?php require_once __DIR__ . '/../includes/footer.php'; ?>

    <script>
        // Reservation Cart Data
        let reservationCart = [];
        let selectedDate = '<?php echo !empty($selectedDate) ? $selectedDate : ''; ?>';
        if (!selectedDate) selectedDate = null;
        const ENTRANCE_FEES = {
            day: { adult: 150, child: 80, senior: 120 },
            night: { adult: 180, child: 100, senior: 144 }
        };
        // Day tour = 8AM-5PM (day use only)
        // Night functionality removed - no rate difference
        let currentDayNight = 'day';
        
        // Availability data from server
        const availabilityData = <?php echo json_encode($availabilityData); ?>;

        // Show unavailable popup notification
        function showUnavailablePopup(roomName) {
            const popup = document.createElement('div');
            popup.style.cssText = `
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                background: #ffffff;
                border: 2px solid #dc2626;
                border-radius: 1rem;
                padding: 2rem;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                z-index: 10000;
                max-width: 400px;
                width: 90%;
                text-align: center;
            `;
            
            popup.innerHTML = `
                <div style="color: #dc2626; font-size: 3rem; margin-bottom: 1rem;">
                    <i class="fas fa-times-circle"></i>
                </div>
                <h3 style="color: #1f2937; margin: 0 0 1rem 0; font-size: 1.5rem;">
                    ${roomName} is Unavailable
                </h3>
                <p style="color: #6b7280; margin: 0 0 1.5rem 0; line-height: 1.6;">
                    This room has reached its daily booking limit for the selected date. Please choose a different room or select another date.
                </p>
                <button onclick="this.parentElement.remove(); document.getElementById('popupOverlay').remove();" 
                        style="background: #dc2626; color: white; border: none; padding: 0.75rem 2rem; 
                               border-radius: 0.5rem; font-weight: 600; cursor: pointer; font-size: 1rem;">
                    I Understand
                </button>
            `;
            
            const overlay = document.createElement('div');
            overlay.id = 'popupOverlay';
            overlay.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.5);
                z-index: 9999;
            `;
            
            overlay.onclick = function() {
                popup.remove();
                overlay.remove();
            };
            
            document.body.appendChild(overlay);
            document.body.appendChild(popup);
        }

        // Show availability exceeded popup
        function showAvailabilityExceededPopup(itemName, available, inCart) {
            const popup = document.createElement('div');
            popup.style.cssText = `
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                background: #ffffff;
                border: 2px solid #f59e0b;
                border-radius: 1rem;
                padding: 2rem;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                z-index: 10000;
                max-width: 450px;
                width: 90%;
                text-align: center;
            `;
            
            popup.innerHTML = `
                <div style="color: #f59e0b; font-size: 3rem; margin-bottom: 1rem;">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h3 style="color: #92400e; margin: 0 0 1rem 0; font-size: 1.5rem;">
                    Maximum ${itemName} Reached
                </h3>
                <p style="color: #6b7280; margin: 0 0 1.5rem 0; line-height: 1.6;">
                    You already have ${inCart} ${itemName}${inCart > 1 ? 's' : ''} in your reservation. 
                    Only ${available} ${itemName}${available > 1 ? 's are' : ' is'} available for the selected date.
                </p>
                <div style="display: flex; gap: 1rem; justify-content: center;">
                    <button onclick="this.parentElement.parentElement.remove(); document.getElementById('popupOverlay').remove();" 
                            style="background: #6b7280; color: white; border: none; padding: 0.75rem 1.5rem; 
                                   border-radius: 0.5rem; font-weight: 600; cursor: pointer; font-size: 1rem;">
                        OK
                    </button>
                    <button onclick="openReservationModal(); this.parentElement.parentElement.remove(); document.getElementById('popupOverlay').remove();" 
                            style="background: #f59e0b; color: white; border: none; padding: 0.75rem 1.5rem; 
                                   border-radius: 0.5rem; font-weight: 600; cursor: pointer; font-size: 1rem;">
                        View Reservation
                    </button>
                </div>
            `;
            
            const overlay = document.createElement('div');
            overlay.id = 'popupOverlay';
            overlay.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.5);
                z-index: 9999;
            `;
            
            overlay.onclick = function() {
                popup.remove();
                overlay.remove();
            };
            
            document.body.appendChild(overlay);
            document.body.appendChild(popup);
        }

        // Show added-to-reservation success popup
        function showAddedPopup(itemName, price, remainingText) {
            const popup = document.createElement('div');
            popup.style.cssText = `
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                background: #ffffff;
                border: 2px solid #10b981;
                border-radius: 1rem;
                padding: 1.5rem;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
                z-index: 10000;
                max-width: 420px;
                width: 90%;
                text-align: center;
            `;

            popup.innerHTML = `
                <div style="color: #10b981; font-size: 2.5rem; margin-bottom: 0.5rem;"><i class="fas fa-check-circle"></i></div>
                <h3 style="color:#102a43; margin:0 0 0.5rem 0;">${itemName} added to reservation</h3>
                <p style="color:#64748b; margin:0 0 1rem 0;">Price: ₱${price.toLocaleString('en-PH', {minimumFractionDigits: 2})}${remainingText}</p>
                <div style="display:flex; gap:0.75rem; justify-content:center;">
                    <button onclick="this.parentElement.parentElement.remove(); document.getElementById('addedPopupOverlay').remove();" style="background:#6b7280; color:white; border:none; padding:0.6rem 1rem; border-radius:8px; font-weight:700;">Continue</button>
                    <button onclick="openReservationModal(); this.parentElement.parentElement.remove(); document.getElementById('addedPopupOverlay').remove();" style="background:#ff7a3d; color:white; border:none; padding:0.6rem 1rem; border-radius:8px; font-weight:700;">View Reservation</button>
                </div>
            `;

            const overlay = document.createElement('div');
            overlay.id = 'addedPopupOverlay';
            overlay.style.cssText = `position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.45);z-index:9999;`;
            overlay.onclick = function() { popup.remove(); overlay.remove(); };
            document.body.appendChild(overlay);
            document.body.appendChild(popup);
        }

        // Add item to reservation cart
        function addToReservation(type, id, name, price, capacity) {
            console.log('addToReservation called with:', { type, id, name, price, capacity });
            
            // Check if user has selected a date before adding items
            if (!selectedDate) {
                const confirmSelect = confirm('⚠️ Please select a date first!\n\nWould you like to select a date now?');
                if (confirmSelect) {
                    openDatePickerModal();
                }
                return;
            }

            // Check availability for the selected date
            if (!availabilityData || !availabilityData[name]) {
                alert('Availability data not available. Please refresh the page and try again.');
                return;
            }

            const itemAvailability = availabilityData[name];
            const availableCount = itemAvailability.available;
            
            // Count how many of this item are already in the cart
            const currentInCart = reservationCart.filter(item => item.name === name).length;
            
            // Check if adding another would exceed availability
            if (currentInCart >= availableCount) {
                showAvailabilityExceededPopup(name, availableCount, currentInCart);
                return;
            }

            // Use base nightly rate for rooms and cottages (no day/night rate difference)
            const tourTypeLabel = currentDayNight === 'day' ? 'Day Tour' : 'Night Tour';

            reservationCart.push({
                type: type,
                id: id,
                name: name,
                price: price,
                originalPrice: price,
                capacity: capacity,
                tourType: currentDayNight,
                tourLabel: tourTypeLabel
            });

            updateFooterTotal();
            
            // Show remaining availability in success message
            const remaining = availableCount - (currentInCart + 1);
            const remainingText = remaining > 0 ? ` (${remaining} more available)` : ' (last available)';
            
            // show reservation toast notice
            showReservationToast(name + ' has been added to your reservation.');

            // show success popup instead of alert
            console.log('Calling showAddedPopup with:', { name, price, remainingText });
            try {
                showAddedPopup(name, price, remainingText);
            } catch (error) {
                console.error('Error showing popup:', error);
                alert(name + ' added to reservation! Price: ₱' + price.toLocaleString('en-PH', {minimumFractionDigits: 2}) + remainingText);
            }
        }

        // Update footer total display
        function updateFooterTotal() {
            const total = reservationCart.reduce((sum, item) => sum + item.price, 0);
            const tourLabel = currentDayNight === 'day' ? 'Day Tour' : 'Night Tour';
            const miniEl = document.getElementById('miniTotal');
            if (miniEl) {
                miniEl.textContent = '₱' + total.toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            }

            // Update availability badges based on cart contents
            updateAvailabilityBadges();
        }

        // Update availability badges based on cart contents
        function updateAvailabilityBadges() {
            if (!availabilityData) return;
            
            // Count items in cart by name
            const cartCounts = {};
            reservationCart.forEach(item => {
                cartCounts[item.name] = (cartCounts[item.name] || 0) + 1;
            });
            
            // Update each booking card's availability badge
            document.querySelectorAll('.booking-card').forEach(card => {
                const roomName = card.getAttribute('data-room-name');
                if (!roomName || !availabilityData[roomName]) return;
                
                const itemAvailability = availabilityData[roomName];
                const inCart = cartCounts[roomName] || 0;
                const remaining = Math.max(0, itemAvailability.available - inCart);
                
                const badge = card.querySelector('.availability-badge');
                const addButton = card.querySelector('.btn-add');
                
                if (badge) {
                    if (remaining > 0) {
                        badge.className = 'availability-badge available';
                        badge.innerHTML = `<i class="fas fa-check-circle"></i> ${remaining} available`;
                        if (addButton) addButton.disabled = false;
                    } else {
                        badge.className = 'availability-badge unavailable';
                        badge.innerHTML = `<i class="fas fa-times-circle"></i> Fully booked`;
                        if (addButton) addButton.disabled = true;
                    }
                }
            });
        }

        // Open reservation modal
        function openReservationModal() {
            const modal = document.getElementById('reservationModal');
            const itemsContainer = document.getElementById('reservationItems');
            const summarySection = document.getElementById('reservationSummary');
            const dateSection = document.getElementById('selectedDateSection');
            const dateDisplay = document.getElementById('reservationSelectedDate');

            // Show selected date if available
            if (selectedDate) {
                const formattedDate = new Date(selectedDate).toLocaleDateString('en-PH', { 
                    month: 'long', 
                    day: 'numeric',
                    year: 'numeric'
                });
                dateDisplay.textContent = formattedDate;
                dateSection.style.display = 'block';
            } else {
                dateSection.style.display = 'none';
            }

            if (reservationCart.length === 0) {
                itemsContainer.innerHTML = '<p style="color:#64748b; text-align:center; padding:2rem;">No items selected yet. Add rooms or cottages to your reservation.</p>';
                if (summarySection) summarySection.style.display = 'none';
            } else {
                // Group items by name to show quantities
                const groupedItems = {};
                reservationCart.forEach((item, index) => {
                    const key = item.name;
                    if (!groupedItems[key]) {
                        groupedItems[key] = {
                            ...item,
                            quantity: 0,
                            indices: []
                        };
                    }
                    groupedItems[key].quantity++;
                    groupedItems[key].indices.push(index);
                });

                let itemsHtml = '';
                Object.values(groupedItems).forEach(group => {
                    const totalPrice = group.price * group.quantity;
                    itemsHtml += `
                        <div class="reservation-item">
                            <div class="reservation-item-info">
                                <h4>${group.name} ${group.quantity > 1 ? `(x${group.quantity})` : ''}</h4>
                                <p>${group.type === 'room' ? 'Room' : 'Cottage'} (Good for ${group.capacity} guests each)</p>
                            </div>
                            <div class="reservation-item-price">₱${totalPrice.toLocaleString('en-PH', {minimumFractionDigits: 2})}</div>
                            <div style="display: flex; gap: 0.5rem;">
                                <button type="button" class="reservation-item-remove" onclick="removeOneFromReservation('${group.name}')" title="Remove one"><i class="fas fa-minus"></i></button>
                                <button type="button" class="reservation-item-remove" onclick="removeAllFromReservation('${group.name}')" title="Remove all"><i class="fas fa-times"></i></button>
                            </div>
                        </div>
                    `;
                });
                itemsContainer.innerHTML = itemsHtml;
                if (summarySection) summarySection.style.display = 'block';
                
                // Set dates - use selected date if available, otherwise use today/tomorrow
                const checkInInput = document.getElementById('checkInDate');
                const checkOutInput = document.getElementById('checkOutDate');
                
                if (selectedDate) {
                    const selected = new Date(selectedDate);
                    const tomorrow = new Date(selected);
                    tomorrow.setDate(tomorrow.getDate() + 1);
                    checkInInput.value = selected.toISOString().split('T')[0];
                    checkOutInput.value = tomorrow.toISOString().split('T')[0];
                } else {
                    const today = new Date();
                    const tomorrow = new Date(today);
                    tomorrow.setDate(tomorrow.getDate() + 1);
                    checkInInput.value = today.toISOString().split('T')[0];
                    checkOutInput.value = tomorrow.toISOString().split('T')[0];
                }
                calculateTotal();
            }

            modal.classList.add('open');
        }

        // Close reservation modal
        function closeReservationModal() {
            const modal = document.getElementById('reservationModal');
            modal.classList.remove('open');
        }

        // Remove item from reservation
        function removeFromReservation(index) {
            const removed = reservationCart.splice(index, 1)[0];
            updateFooterTotal();
            openReservationModal(); // Refresh the modal
        }

        // Remove one instance of an item type from reservation
        function removeOneFromReservation(itemName) {
            const index = reservationCart.findIndex(item => item.name === itemName);
            if (index >= 0) {
                const removed = reservationCart.splice(index, 1)[0];
                updateFooterTotal();
                openReservationModal(); // Refresh the modal
            }
        }

        // Remove all instances of an item type from reservation
        function removeAllFromReservation(itemName) {
            reservationCart = reservationCart.filter(item => item.name !== itemName);
            updateFooterTotal();
            openReservationModal(); // Refresh the modal
        }

        // Calculate total bill
        function calculateTotal() {
            const adults = parseInt(document.getElementById('guestAdults').value) || 0;
            const children = parseInt(document.getElementById('guestChildren').value) || 0;
            const checkIn = new Date(document.getElementById('checkInDate').value);
            const checkOut = new Date(document.getElementById('checkOutDate').value);

            // Calculate nights
            let nights = 1;
            if (!isNaN(checkIn) && !isNaN(checkOut) && checkOut > checkIn) {
                nights = Math.ceil((checkOut - checkIn) / (1000 * 60 * 60 * 24));
                if (nights < 1) nights = 1;
            }

            // Get the tour type from the first item in cart (all items should have same tour type)
            const tourType = reservationCart.length > 0 ? reservationCart[0].tourType : currentDayNight;
            const tourLabel = tourType === 'day' ? 'Day Tour (8AM - 5PM)' : 'Night Tour (8PM - 5AM)';

            // Calculate room/cottage costs (already adjusted for day/night rate)
            let roomCottageTotal = 0;
            reservationCart.forEach(item => {
                roomCottageTotal += item.price * nights;
            });

            // Calculate entrance fees based on tour type
            const fees = ENTRANCE_FEES[tourType];
            const entranceTotal = (adults * fees.adult) + (children * fees.child);
            const totalGuests = adults + children;

            // Build bill breakdown
            let breakdownHtml = '<div class="bill-breakdown-content">';
            
            // Tour type header
            breakdownHtml += `<div style="margin-bottom:1rem; padding:0.75rem; background:#fff3cd; border-radius:0.5rem; text-align:center;"><strong style="color:#856404;">${tourLabel}</strong></div>`;
            
            // Room/Cottage items - group by name
            if (reservationCart.length > 0) {
                breakdownHtml += '<div style="margin-bottom:1rem;"><strong style="color:#102a43;">Accommodations</strong></div>';
                
                // Group items by name
                const groupedItems = {};
                reservationCart.forEach(item => {
                    const key = item.name;
                    if (!groupedItems[key]) {
                        groupedItems[key] = {
                            ...item,
                            quantity: 0
                        };
                    }
                    groupedItems[key].quantity++;
                });

                // Display grouped items
                Object.values(groupedItems).forEach(item => {
                    const itemTotal = item.price * nights * item.quantity;
                    const rateLabel = item.tourType === 'day' ? 'Day Use' : 'Night Use';
                    breakdownHtml += `
                        <div class="bill-line-item">
                            <span class="bill-label">${item.name} x ${item.quantity} (${rateLabel})</span>
                            <span class="bill-value">₱${itemTotal.toLocaleString('en-PH', {minimumFractionDigits: 2})}</span>
                        </div>
                    `;
                });
            }

            // Entrance fees
            if (totalGuests > 0) {
                breakdownHtml += '<div style="margin:1rem 0 1rem 0;"><strong style="color:#102a43;">Entrance Fees (${tourLabel})</strong></div>';
                if (adults > 0) {
                    breakdownHtml += `
                        <div class="bill-line-item">
                            <span class="bill-label">Adult x ${adults}</span>
                            <span class="bill-value">₱${(adults * fees.adult).toLocaleString('en-PH', {minimumFractionDigits: 2})}</span>
                        </div>
                    `;
                }
                if (children > 0) {
                    breakdownHtml += `
                        <div class="bill-line-item">
                            <span class="bill-label">Child x ${children}</span>
                            <span class="bill-value">₱${(children * fees.child).toLocaleString('en-PH', {minimumFractionDigits: 2})}</span>
                        </div>
                    `;
                }
            }

            const grandTotal = roomCottageTotal + entranceTotal;
            breakdownHtml += `
                <div class="bill-subtotal">
                    <span>Total</span>
                    <span>₱${grandTotal.toLocaleString('en-PH', {minimumFractionDigits: 2})}</span>
                </div>
            `;
            breakdownHtml += '</div>';

            document.getElementById('billBreakdown').innerHTML = breakdownHtml;
            document.getElementById('reservationTotal').textContent = '₱' + grandTotal.toLocaleString('en-PH', {minimumFractionDigits: 2});
        }

        // Confirm reservation
        function confirmReservation() {
            // Check if user is logged in
            const isLoggedIn = <?php echo $isLoggedIn ? 'true' : 'false'; ?>;
            if (!isLoggedIn) {
                alert('Please log in to make a reservation.');
                window.location.href = '<?php echo SITE_URL; ?>google-auth.php?action=login';
                return;
            }

            if (reservationCart.length === 0) {
                alert('Please add at least one room or cottage to your reservation.');
                return;
            }

            const adults = parseInt(document.getElementById('guestAdults').value) || 0;
            const children = parseInt(document.getElementById('guestChildren').value) || 0;
            const seniors = 0;
            const checkIn = document.getElementById('checkInDate').value;
            const checkOut = document.getElementById('checkOutDate').value;

            if (!checkIn || !checkOut) {
                alert('Please select check-in and check-out dates.');
                return;
            }

            if (adults + children === 0) {
                alert('Please enter at least one guest.');
                return;
            }

            // Calculate nights
            const checkInDate = new Date(checkIn);
            const checkOutDate = new Date(checkOut);
            let nights = 1;
            if (!isNaN(checkInDate) && !isNaN(checkOutDate) && checkOutDate > checkInDate) {
                nights = Math.ceil((checkOutDate - checkInDate) / (1000 * 60 * 60 * 24));
                if (nights < 1) nights = 1;
            }

            // Prepare reservation data
            const reservationData = {
                selectedDate: selectedDate,
                checkIn: checkIn,
                checkOut: checkOut,
                adults: adults,
                children: children,
                seniors: seniors,
                items: reservationCart.map(item => ({
                    type: item.type,
                    id: item.id,
                    name: item.name,
                    price: item.price,
                    capacity: item.capacity,
                    nights: nights
                })),
                totalAmount: reservationCart.reduce((sum, item) => sum + (item.price * nights), 0),
                tourType: currentDayNight
            };

            // Debug: Log the data being sent
            console.log('Sending reservation data:', reservationData);
            console.log('API URL:', '<?php echo SITE_URL; ?>api/save_reservation.php');

            // Send data to server
            fetch('<?php echo SITE_URL; ?>api/save_reservation.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin', // Include cookies for session
                body: JSON.stringify(reservationData)
            })
            .then(response => {
                console.log('Response status:', response.status);
                console.log('Response headers:', response.headers);
                return response.text().then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.log('Raw response:', text);
                        throw new Error('Invalid JSON response: ' + text);
                    }
                });
            })
            .then(data => {
                console.log('Response data:', data);
                if (data.success) {
                    alert('Reservation submitted successfully! Your booking ID is #' + data.reservation_id + '. Your reservation is now pending approval from our admin team. You will receive a confirmation email once approved.');
                    closeReservationModal();
                    // Clear the cart
                    reservationCart = [];
                    updateFooterTotal();
                    // Stay on booking page - user can navigate to My Bookings when they want
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while confirming your reservation: ' + error.message + '. Please try again.');
            });
        }

        const toggleButtons = document.querySelectorAll('.toggle-button');
        const bookingCards = document.querySelectorAll('.booking-card');

        // Wire toggle buttons (day/night)
        toggleButtons.forEach(btn => btn.addEventListener('click', () => setToggle(btn.dataset.toggle)));

        function openBookingModal(button) {
            const modal = document.getElementById('bookingModal');
            const title = document.getElementById('modalTitle');
            const description = document.getElementById('modalDescription');
            const price = document.getElementById('modalPrice');
            const capacity = document.getElementById('modalCapacity');
            const status = document.getElementById('modalStatus');
            const summary = document.getElementById('modalSummary');
            const extras = document.getElementById('modalExtras');
            const imageContainer = document.getElementById('modalImage');
            
            console.log('openBookingModal called with:', {
                name: button.dataset.name,
                capacity: button.dataset.capacity,
                price: button.dataset.price
            });
            
            title.textContent = button.dataset.name || 'Room #';
            description.textContent = button.dataset.description || 'Room details are not available.';
            price.textContent = button.dataset.price || '₱0.00';
            capacity.textContent = button.dataset.capacity ? button.dataset.capacity + ' people' : '0 people';
            status.textContent = 'Available';
            summary.textContent = button.dataset.summary || '';
            extras.textContent = button.dataset.extras || '';

            const imageUrl = button.dataset.image || '';
            if (imageUrl) {
                imageContainer.innerHTML = '<img src="' + imageUrl + '" alt="' + (button.dataset.name || 'Room') + '" style="width:100%; height:100%; object-fit:cover; border-radius:1rem;">';
            } else {
                imageContainer.textContent = 'Image not available';
            }

            modal.classList.add('open');
        }

        function closeBookingModal() {
            const modal = document.getElementById('bookingModal');
            modal.classList.remove('open');
        }

        
        function setToggle(mode) {
            toggleButtons.forEach(btn => btn.classList.toggle('active', btn.dataset.toggle === mode));
            currentDayNight = mode;
            
            // Recalculate check-out date if dates are set
            const checkInInput = document.getElementById('checkInDate');
            const checkOutInput = document.getElementById('checkOutDate');
            if (checkInInput && checkOutInput && checkInInput.value) {
                const selected = new Date(checkInInput.value);
                
                // Update check-out based on new tour type
                if (mode === 'day') {
                    // Day tour: same day
                    checkOutInput.value = selected.toISOString().split('T')[0];
                } else {
                    // Night tour: next day
                    const tomorrow = new Date(selected);
                    tomorrow.setDate(tomorrow.getDate() + 1);
                    checkOutInput.value = tomorrow.toISOString().split('T')[0];
                }
            }
            
            // Recalculate if reservation modal is open
            if (document.getElementById('reservationModal').classList.contains('open')) {
                calculateTotal();
            }
        }

        // Date Picker Functions
        function openDatePickerModal() {
            const modal = document.getElementById('datePickerModal');
            const dateInput = document.getElementById('selectedReservationDate');
            
            // Prevent selecting past dates - set min to today
            const today = new Date().toISOString().split('T')[0];
            dateInput.min = today;
            
            // Optionally, set a reasonable max date (e.g., 5 years from now)
            const maxYear = new Date().getFullYear() + 5;
            const maxDate = new Date(maxYear, 11, 31).toISOString().split('T')[0];
            dateInput.max = maxDate;
            
            // If a date was already selected, show it
            if (selectedDate) {
                dateInput.value = selectedDate;
            }
            
            modal.style.display = 'flex';
        }

        function closeDatePickerModal() {
            const modal = document.getElementById('datePickerModal');
            modal.style.display = 'none';
        }

        function saveSelectedDate() {
            const dateInput = document.getElementById('selectedReservationDate');
            const dateValue = dateInput.value;
            
            if (!dateValue) {
                alert('Please select a date for your reservation.');
                return;
            }
            
            selectedDate = dateValue;
            
            // Update the "Select Date" button to show the selected date
            const dateBtn = document.querySelector('.btn-select-date');
            const formattedDate = new Date(selectedDate).toLocaleDateString('en-PH', { 
                month: 'short', 
                day: 'numeric',
                year: 'numeric'
            });
            dateBtn.innerHTML = '<i class="fas fa-check-circle" style="color: #27ae60; margin-right: 0.5rem;"></i> Date: ' + formattedDate;
            dateBtn.style.background = '#e8f5e9';
            dateBtn.style.color = '#1b5e20';
            dateBtn.style.borderColor = '#27ae60';
            
            // Hide the date notice once a date is selected
            const dateNotice = document.getElementById('dateNotice');
            if (dateNotice) {
                dateNotice.style.display = 'none';
            }
            
            // Show selected date in reservation modal when opened
            const dateSection = document.getElementById('selectedDateSection');
            const dateDisplay = document.getElementById('reservationSelectedDate');
            const formattedDateLong = new Date(selectedDate).toLocaleDateString('en-PH', { 
                month: 'long', 
                day: 'numeric',
                year: 'numeric'
            });
            dateDisplay.textContent = formattedDateLong;
            dateSection.style.display = 'block';
            
            closeDatePickerModal();
            
            alert('Selected date confirmed: ' + formattedDate);
            
            // Update check-out date in reservation modal if open
            const checkInInput = document.getElementById('checkInDate');
            const checkOutInput = document.getElementById('checkOutDate');
            if (checkInInput && checkOutInput) {
                const selected = new Date(selectedDate);
                
                // Always use next day (day mode only)
                const tomorrow = new Date(selected);
                tomorrow.setDate(tomorrow.getDate() + 1);
                checkInInput.value = selected.toISOString().split('T')[0];
                checkOutInput.value = tomorrow.toISOString().split('T')[0];
                calculateTotal();
            }
            
            // Delay showing notice to ensure DOM is ready
            setTimeout(() => {
                showDateNotice('Date selected: ' + formattedDate + '\nYou can now add rooms or cottages to your reservation.');
            }, 100);

            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('date', selectedDate);
            window.location.search = urlParams.toString();
        }

        toggleButtons.forEach(button => {
            button.addEventListener('click', () => setToggle(button.dataset.toggle));
        });

        document.addEventListener('DOMContentLoaded', () => {
            // Default to day mode and initialize totals
            setToggle('day');
            const miniTotalEl = document.getElementById('miniTotal');
            if (miniTotalEl) miniTotalEl.textContent = '₱#,###,##';

            if (selectedDate) {
                const dateBtn = document.querySelector('.btn-select-date');
                const formattedDate = new Date(selectedDate).toLocaleDateString('en-PH', {
                    month: 'short',
                    day: 'numeric',
                    year: 'numeric'
                });
                if (dateBtn) {
                    dateBtn.innerHTML = '<i class="fas fa-check-circle" style="color: #27ae60; margin-right: 0.5rem;"></i> Date: ' + formattedDate;
                    dateBtn.style.background = '#e8f5e9';
                    dateBtn.style.color = '#1b5e20';
                    dateBtn.style.borderColor = '#27ae60';
                }

                const dateNotice = document.getElementById('dateNotice');
                if (dateNotice) {
                    dateNotice.style.display = 'none';
                }
                
                // Update availability badges for selected date
                updateAvailabilityBadges();
            }
            
            // Load initial calendar when modal opens
            const datePickerModal = document.getElementById('datePickerModal');
            if (datePickerModal) {
                const originalDisplay = document.querySelector('.btn-select-date').onclick;
                document.querySelector('.btn-select-date').addEventListener('click', () => {
                    setTimeout(() => loadCalendarAvailability(), 100);
                });
            }
        });
    </script>

    <!-- Date Notice Pop-up -->
    <div id="dateNotice" class="date-notice" style="display: none; position: fixed; top: 20px; right: 20px; background: #10b981; color: white; padding: 1rem 1.5rem; border-radius: 0.75rem; box-shadow: 0 10px 25px rgba(0,0,0,0.2); z-index: 9999; max-width: 350px; animation: slideIn 0.3s ease;">
        <div style="display: flex; align-items: flex-start; gap: 0.75rem;">
            <i class="fas fa-check-circle" style="color: #34d399; margin-top: 0.25rem; flex-shrink: 0;"></i>
            <div>
                <strong style="display: block; margin-bottom: 0.25rem; font-size: 0.9rem;">Date Selected!</strong>
                <p style="margin: 0; font-size: 0.85rem; line-height: 1.4;" id="dateNoticeMessage"></p>
            </div>
        </div>
        <button onclick="hideDateNotice()" style="position: absolute; top: 0.5rem; right: 0.5rem; background: none; border: none; color: white; font-size: 1.5rem; cursor: pointer; opacity: 0.8; transition: opacity 0.2s ease;" onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.8'">&times;</button>
    </div>

    <!-- Reservation Toast Notification -->
    <div id="reservationToast" style="display: none; position: fixed; top: 88px; right: 20px; background: #0d9488; color: white; padding: 1rem 1.25rem; border-radius: 0.75rem; box-shadow: 0 10px 25px rgba(0,0,0,0.2); z-index: 10001; max-width: 340px; animation: slideIn 0.3s ease; pointer-events: none;">
        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <i class="fas fa-check-circle" style="font-size: 1.1rem; color: #bbf7d0;"></i>
            <div id="reservationToastMessage" style="font-size: 0.95rem; line-height: 1.35;"></div>
        </div>
    </div>

    <script>
        // Show date notice pop-up
        function showDateNotice(message) {
            console.log('showDateNotice called with message:', message);
            
            // Wait for DOM to be ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => {
                    setTimeout(() => showDateNotice(message), 100);
                });
                return;
            }
            
            const notice = document.getElementById('dateNotice');
            const noticeMessage = document.getElementById('dateNoticeMessage');
            
            console.log('Elements found:', { notice: !!notice, message: !!noticeMessage });
            
            if (notice && noticeMessage) {
                noticeMessage.textContent = message;
                notice.style.display = 'block';
                notice.style.visibility = 'visible';
                notice.style.opacity = '1';
                console.log('Notice displayed successfully');
                
                // Auto-hide after 5 seconds
                setTimeout(() => {
                    hideDateNotice();
                }, 5000);
            } else {
                console.error('Notice elements not found:', { notice: !!notice, message: !!noticeMessage });
                // Fallback to alert if pop-up elements not found
                alert('Date selected: ' + message);
            }
        }

        // Hide date notice pop-up
        function hideDateNotice() {
            const notice = document.getElementById('dateNotice');
            if (notice) {
                notice.style.display = 'none';
            }
        }

        function createOrGetReservationToast() {
            let toast = document.getElementById('reservationToast');
            let toastMessage = document.getElementById('reservationToastMessage');

            if (!toast || !toastMessage) {
                toast = document.createElement('div');
                toast.id = 'reservationToast';
                toast.style.cssText = 'display:none;position:fixed;top:88px;right:20px;background:#0d9488;color:white;padding:1rem 1.25rem;border-radius:0.75rem;box-shadow:0 10px 25px rgba(0,0,0,0.2);z-index:10001;max-width:340px;animation:slideIn 0.3s ease;pointer-events:none;';
                toastMessage = document.createElement('div');
                toastMessage.id = 'reservationToastMessage';
                toastMessage.style.cssText = 'font-size:0.95rem;line-height:1.35;';
                const icon = document.createElement('i');
                icon.className = 'fas fa-check-circle';
                icon.style.cssText = 'font-size:1.1rem;color:#bbf7d0;margin-right:0.75rem;';
                const content = document.createElement('div');
                content.style.cssText = 'display:flex;align-items:center;gap:0.75rem;';
                content.appendChild(icon);
                content.appendChild(toastMessage);
                toast.appendChild(content);
                document.body.appendChild(toast);
            }

            return { toast, toastMessage };
        }

        function showReservationToast(message) {
            const { toast, toastMessage } = createOrGetReservationToast();
            if (!toast || !toastMessage) {
                console.error('Reservation toast could not be created.');
                return;
            }

            toastMessage.textContent = message;
            toast.style.display = 'flex';
            toast.style.opacity = '1';
            toast.style.visibility = 'visible';

            if (window.reservationToastTimeout) {
                clearTimeout(window.reservationToastTimeout);
            }

            window.reservationToastTimeout = setTimeout(() => {
                hideReservationToast();
            }, 4500);
        }

        function hideReservationToast() {
            const toast = document.getElementById('reservationToast');
            if (toast) {
                toast.style.display = 'none';
            }
        }

        // Add slide-in animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideIn {
                from {
                    transform: translateX(100%);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>
