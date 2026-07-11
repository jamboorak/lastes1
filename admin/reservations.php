<?php
require_once '../config/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';
require_once '../includes/EmailService.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

require_once '../includes/header.php';

// Prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Handle reservation approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $reservationId = $_POST['reservation_id'];
    $action = $_POST['action'];
    
    $db = new Database();
    $conn = $db->getConnection();

    if ($action === 'approve' || $action === 'reject') {
        $newStatus = $action === 'approve' ? 'approved' : 'cancelled';
        $stmt = $conn->prepare("UPDATE reservations SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $newStatus, $reservationId);
        $stmt->execute();

        // Get reservation details for notification
        $reservationDetails = getReservationNotificationData($conn, $reservationId);

        if ($action === 'approve') {
            if (!empty($reservationDetails['email'])) {
                sendApprovalEmail($reservationDetails);
            }
            error_log("Reservation $reservationId approved by admin");
            header("Location: reservations.php?success=approved");
            exit();
        } elseif ($action === 'reject') {
            if (!empty($reservationDetails['email'])) {
                sendCancellationEmail($reservationDetails);
            }
            error_log("Reservation $reservationId rejected by admin");
            header("Location: reservations.php?success=rejected");
            exit();
        }
    }
}

function getReservationNotificationData($conn, $reservationId) {
    $query = "SELECT r.id, r.user_id, r.check_in, r.check_out, r.total_amount, r.status,
                     COALESCE(u.email, ua.email) AS email,
                     COALESCE(u.fullname, ua.fullname, 'Guest') AS fullname,
                     GROUP_CONCAT(CONCAT(ri.item_name, ' (', ri.item_type, ')') SEPARATOR ', ') AS items
              FROM reservations r
              LEFT JOIN users u ON r.user_id = u.id
              LEFT JOIN user_accounts ua ON r.user_id = ua.id
              LEFT JOIN reservation_items ri ON r.id = ri.reservation_id
              WHERE r.id = ?
              GROUP BY r.id";

    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $reservationId);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc() ?: [];
}

function sendApprovalEmail($reservation) {
    $guestName = htmlspecialchars($reservation['fullname'] ?? 'Guest');
    $guestEmail = htmlspecialchars($reservation['email']);
    $reservationId = $reservation['id'];
    $checkIn = date('F d, Y', strtotime($reservation['check_in']));
    $checkOut = date('F d, Y', strtotime($reservation['check_out']));
    $items = htmlspecialchars($reservation['items'] ?? 'N/A');
    $totalAmount = number_format((float)$reservation['total_amount'], 2);
    
    error_log("📧 Attempting to send approval email for Reservation #$reservationId to: $guestEmail");
    
    $subject = "Reservation Approved - Villa Soledad Garden Resort #" . $reservationId;
    $message = "<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #f9f9f9; border-radius: 8px; }
        .header { background: linear-gradient(135deg, #1e3a8a 0%, #ff8a3d 100%); color: white; padding: 20px; border-radius: 8px 8px 0 0; text-align: center; }
        .content { background: white; padding: 20px; border-radius: 0 0 8px 8px; }
        .reservation-details { background: #f0f4ff; padding: 15px; border-radius: 6px; margin: 15px 0; border-left: 4px solid #1e3a8a; }
        .detail-row { display: flex; justify-content: space-between; margin: 10px 0; }
        .detail-label { font-weight: bold; color: #1e3a8a; }
        .detail-value { color: #333; }
        .amount { font-size: 1.3em; font-weight: bold; color: #ff8a3d; }
        .footer { text-align: center; color: #999; font-size: 0.9em; margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>Reservation Approved! ✓</h1>
        </div>
        <div class='content'>
            <p>Dear <strong>{$guestName}</strong>,</p>
            <p>Great news! Your reservation at <strong>Villa Soledad Garden Resort</strong> has been <strong style='color: #28a745;'>approved</strong>.</p>
            <div class='reservation-details'>
                <h3 style='color: #1e3a8a; margin-top: 0;'>Reservation Details</h3>
                <div class='detail-row'>
                    <span class='detail-label'>Reservation ID:</span>
                    <span class='detail-value'>#{$reservationId}</span>
                </div>
                <div class='detail-row'>
                    <span class='detail-label'>Check-in Date:</span>
                    <span class='detail-value'>{$checkIn}</span>
                </div>
                <div class='detail-row'>
                    <span class='detail-label'>Check-out Date:</span>
                    <span class='detail-value'>{$checkOut}</span>
                </div>
                <div class='detail-row'>
                    <span class='detail-label'>Items Booked:</span>
                    <span class='detail-value'>{$items}</span>
                </div>
                <div class='detail-row'>
                    <span class='detail-label'>Total Amount:</span>
                    <span class='detail-value amount'>₱{$totalAmount}</span>
                </div>
            </div>
            <p>Your reservation is now confirmed and you're all set for your stay. Please arrive at least 30 minutes before your check-in time.</p>
            <p>If you have any questions or need to make changes to your reservation, please contact us:</p>
            <p><strong>Phone:</strong> " . SITE_PHONE . "<br>
               <strong>Email:</strong> " . SITE_EMAIL . "<br>
               <strong>Website:</strong> " . SITE_URL . "</p>
            <p>We look forward to welcoming you at Villa Soledad Garden Resort!</p>
            <p>Best regards,<br><strong>Villa Soledad Garden Resort Management</strong></p>
        </div>
        <div class='footer'>
            <p>This is an automated email. Please do not reply to this message.</p>
            <p>&copy; " . date('Y') . " Villa Soledad Garden Resort. All rights reserved.</p>
        </div>
    </div>
</body>
</html>";

    $emailResult = sendHtmlEmail($guestEmail, $subject, $message, SITE_EMAIL, SITE_NAME);
    if ($emailResult['success']) {
        error_log("✅ Approval email SENT via SMTP for Reservation #$reservationId to: $guestEmail");
    } else {
        error_log("⚠️  Approval email saved to file for Reservation #$reservationId - " . $emailResult['message']);
    }
}

function sendCancellationEmail($reservation) {
    $guestName = htmlspecialchars($reservation['fullname'] ?? 'Guest');
    $guestEmail = htmlspecialchars($reservation['email']);
    $reservationId = $reservation['id'];
    $checkIn = date('F d, Y', strtotime($reservation['check_in']));
    $checkOut = date('F d, Y', strtotime($reservation['check_out']));
    $items = htmlspecialchars($reservation['items'] ?? 'N/A');
    $totalAmount = number_format((float)$reservation['total_amount'], 2);

    error_log("📧 Attempting to send cancellation email for Reservation #$reservationId to: $guestEmail");

    $subject = "Reservation Cancelled - Villa Soledad Garden Resort #" . $reservationId;
    $message = "<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #f9f9f9; border-radius: 8px; }
        .header { background: linear-gradient(135deg, #d32f2f 0%, #ff6b6b 100%); color: white; padding: 20px; border-radius: 8px 8px 0 0; text-align: center; }
        .content { background: white; padding: 20px; border-radius: 0 0 8px 8px; }
        .reservation-details { background: #fff5f5; padding: 15px; border-radius: 6px; margin: 15px 0; border-left: 4px solid #d32f2f; }
        .detail-row { display: flex; justify-content: space-between; margin: 10px 0; }
        .detail-label { font-weight: bold; color: #d32f2f; }
        .detail-value { color: #333; }
        .amount { font-size: 1.3em; font-weight: bold; color: #d32f2f; }
        .footer { text-align: center; color: #999; font-size: 0.9em; margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>Reservation Cancelled ✕</h1>
        </div>
        <div class='content'>
            <p>Dear <strong>{$guestName}</strong>,</p>
            <p>Your reservation at <strong>Villa Soledad Garden Resort</strong> has been <strong style='color: #d32f2f;'>cancelled</strong>.</p>
            <div class='reservation-details'>
                <h3 style='color: #d32f2f; margin-top: 0;'>Cancelled Reservation Details</h3>
                <div class='detail-row'>
                    <span class='detail-label'>Reservation ID:</span>
                    <span class='detail-value'>#{$reservationId}</span>
                </div>
                <div class='detail-row'>
                    <span class='detail-label'>Check-in Date:</span>
                    <span class='detail-value'>{$checkIn}</span>
                </div>
                <div class='detail-row'>
                    <span class='detail-label'>Check-out Date:</span>
                    <span class='detail-value'>{$checkOut}</span>
                </div>
                <div class='detail-row'>
                    <span class='detail-label'>Items Booked:</span>
                    <span class='detail-value'>{$items}</span>
                </div>
                <div class='detail-row'>
                    <span class='detail-label'>Total Amount:</span>
                    <span class='detail-value amount'>₱{$totalAmount}</span>
                </div>
            </div>
            <p>If you have any questions or want help rebooking, please contact us using the details below.</p>
            <p><strong>Phone:</strong> " . SITE_PHONE . "<br>
               <strong>Email:</strong> " . SITE_EMAIL . "<br>
               <strong>Website:</strong> " . SITE_URL . "</p>
            <p>Best regards,<br><strong>Villa Soledad Garden Resort Management</strong></p>
        </div>
        <div class='footer'>
            <p>This is an automated email. Please do not reply to this message.</p>
            <p>&copy; " . date('Y') . " Villa Soledad Garden Resort. All rights reserved.</p>
        </div>
    </div>
</body>
</html>";

    $emailResult = sendHtmlEmail($guestEmail, $subject, $message, SITE_EMAIL, SITE_NAME);
    if ($emailResult['success']) {
        error_log("✅ Cancellation email SENT via SMTP for Reservation #$reservationId to: $guestEmail");
    } else {
        error_log("⚠️  Cancellation email saved to file for Reservation #$reservationId - " . $emailResult['message']);
    }
}

// Fetch all reservations with user details
$db = new Database();
$conn = $db->getConnection();

// Simple direct query - no COALESCE tricks
$reservations = $conn->query("
    SELECT r.id, r.check_in, r.check_out, r.adults, r.children, r.seniors, r.total_amount, r.status, r.tour_type, r.created_at,
           COALESCE(u.fullname, ua.fullname, 'Guest') as user_name,
           COALESCE(u.email, ua.email, '') as user_email,
           COALESCE(u.phone, ua.phone, '') as user_phone
    FROM reservations r 
    LEFT JOIN users u ON r.user_id = u.id
    LEFT JOIN user_accounts ua ON r.user_id = ua.id
    WHERE COALESCE(NULLIF(r.status, ''), 'pending') = 'pending'
    ORDER BY r.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

error_log("============ MANAGE RESERVATIONS PAGE LOADED ============");
error_log("Total pending reservations found: " . count($reservations));
foreach ($reservations as $res) {
    error_log("  - ID: {$res['id']}, Status: '{$res['status']}', User: {$res['user_name']}");
}

// Fetch reservation items for each reservation
foreach ($reservations as &$reservation) {
    $itemsSql = "SELECT ri.* FROM reservation_items ri WHERE ri.reservation_id = ?";
    $itemStmt = $conn->prepare($itemsSql);
    $itemStmt->bind_param("i", $reservation['id']);
    $itemStmt->execute();
    $itemsResult = $itemStmt->get_result();
    $reservation['items'] = $itemsResult->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservations Management - Villa Soledad Admin</title>
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--bg-light);
            color: var(--text-dark);
        }

        .admin-page .container {
            width: 100%;
            max-width: none;
            margin: 0;
            padding: 0;
        }

        .admin-container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Navigation */
        .sidebar {
            width: 250px;
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--light-blue) 100%);
            color: var(--white);
            padding: 2rem 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }

        .header .container {
            padding: 0 2rem;
        }

        .sidebar-header {
            padding: 0 1.5rem 2rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 2rem;
        }

        .sidebar-header h2 {
            font-size: 1.5rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .sidebar-nav {
            list-style: none;
        }

        .sidebar-nav li {
            margin-bottom: 0.5rem;
        }

        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1.5rem;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
        }

        .sidebar-nav a:hover,
        .sidebar-nav a.active {
            background: rgba(255,255,255,0.16);
            border-left-color: var(--accent-orange);
        }

        .sidebar-nav i {
            width: 20px;
            text-align: center;
        }

        /* Main Content */
        .main-content {
            margin-left: 250px;
            flex: 1;
            padding: 2rem;
        }

        .content-header {
            background: white;
            padding: 1.5rem 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .content-header h1 {
            font-size: 2rem;
            color: var(--primary-blue);
        }

        .breadcrumb {
            color: var(--text-light);
            font-size: 0.9rem;
        }

        /* Filter Tabs */
        .filter-tabs {
            background: white;
            padding: 1rem 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .tab-btn {
            padding: 0.5rem 1rem;
            border: 1px solid #e5e7eb;
            background: white;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .tab-btn.active {
            background: var(--primary-blue);
            color: white;
            border-color: var(--primary-blue);
        }

        .tab-btn:hover:not(.active) {
            background: #f9fafb;
        }

        /* Reservations Table */
        .reservations-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .table-header {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .search-box {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: #f9fafb;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            border: 1px solid #e5e7eb;
        }

        .search-box input {
            border: none;
            background: none;
            outline: none;
            width: 200px;
        }

        .reservations-table {
            width: 100%;
            border-collapse: collapse;
        }

        .reservations-table th {
            background: #f9fafb;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: #374151;
            border-bottom: 1px solid #e5e7eb;
        }

        .reservations-table td {
            padding: 1rem;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: top;
        }

        .reservations-table tr:hover {
            background: #f9fafb;
        }

        .reservation-id {
            font-weight: 600;
            color: var(--primary-blue);
        }

        .customer-info h4 {
            font-size: 0.9rem;
            color: var(--text-dark);
            margin-bottom: 0.25rem;
        }

        .customer-info p {
            font-size: 0.8rem;
            color: var(--text-light);
            margin-bottom: 0.1rem;
        }

        .reservation-details {
            font-size: 0.85rem;
            color: var(--text-light);
        }

        .reservation-details p {
            margin-bottom: 0.25rem;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            display: inline-block;
        }

        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status-approved {
            background: #d1fae5;
            color: #065f46;
        }

        .status-cancelled {
            background: #fee2e2;
            color: #991b1b;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .btn-approve, .btn-reject, .btn-view {
            padding: 0.4rem 0.8rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.8rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-approve {
            background: #10b981;
            color: white;
        }

        .btn-approve:hover {
            background: #059669;
        }

        .btn-reject {
            background: #ef4444;
            color: white;
        }

        .btn-reject:hover {
            background: #dc2626;
        }

        .btn-view {
            background: #6b7280;
            color: white;
        }

        .btn-view:hover {
            background: #4b5563;
        }

        /* Success Message */
        .success-message {
            background: #d1fae5;
            color: #065f46;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                width: 200px;
            }
            
            .main-content {
                margin-left: 200px;
            }
            
            .filter-tabs {
                flex-wrap: wrap;
            }
            
            .reservations-table {
                font-size: 0.8rem;
            }
            
            .reservations-table th,
            .reservations-table td {
                padding: 0.5rem;
            }
        }

        @media (max-width: 640px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            .sidebar.open {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body class="admin-page">
    <div class="admin-container">
        <!-- Sidebar Navigation -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-cog"></i> Admin Panel</h2>
            </div>
            <nav>
                <ul class="sidebar-nav">
                    <li>
                        <a href="index.php?page=dashboard">
                            <i class="fas fa-tachometer-alt"></i>
                            Dashboard
                        </a>
                    </li>
                    <li>
                        <a href="reservations.php" class="active">
                            <i class="fas fa-calendar-check"></i>
                            Reservations
                        </a>
                    </li>
                    <li>
                        <a href="index.php?page=users">
                            <i class="fas fa-users"></i>
                            Users
                        </a>
                    </li>
                    <li>
                        <a href="index.php?page=rooms">
                            <i class="fas fa-bed"></i>
                            Rooms
                        </a>
                    </li>
                    <li>
                        <a href="index.php?page=cottages">
                            <i class="fas fa-home"></i>
                            Cottages
                        </a>
                    </li>
                    <li>
                        <a href="index.php?page=reports">
                            <i class="fas fa-chart-bar"></i>
                            Reports
                        </a>
                    </li>
                    <li>
                        <a href="index.php?page=settings">
                            <i class="fas fa-cog"></i>
                            Settings
                        </a>
                    </li>
                    <li>
                        <a href="../logout.php">
                            <i class="fas fa-sign-out-alt"></i>
                            Logout
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="content-header">
                <div>
                    <h1>Reservations Management</h1>
                    <div class="breadcrumb">
                        <span>Admin</span> / <span>Reservations</span>
                    </div>
                </div>
                <div>
                    <span><?php echo count($reservations); ?> Total Reservations</span>
                </div>
            </div>

            <?php if (isset($_GET['success'])): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i>
                    <?php 
                    if ($_GET['success'] === 'approved') {
                        echo 'Reservation approved successfully!';
                    } elseif ($_GET['success'] === 'rejected') {
                        echo 'Reservation cancelled successfully!';
                    }
                    ?>
                </div>
            <?php endif; ?>

            <!-- Filter Tabs -->
            <div class="filter-tabs">
                <button class="tab-btn active" onclick="filterReservations('pending')">Pending Requests</button>
                <button class="tab-btn" onclick="filterReservations('all')">All Pending</button>
            </div>

            <!-- Reservations Table -->
            <div class="reservations-container">
                <div class="table-header">
                    <h2>Pending Reservations</h2>
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" placeholder="Search pending reservations..." id="searchInput">
                    </div>
                </div>
                
                <p style="margin: 0 0 1rem 0; color: var(--text-light);">Approved and cancelled reservations are moved to Booking Records.</p>
                <table class="reservations-table">
                    <thead>
                        <tr>
                            <th>Reservation ID</th>
                            <th>Customer</th>
                            <th>Details</th>
                            <th>Tour Hours</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($reservations)): ?>
                            <?php foreach ($reservations as $reservation): ?>
                                <?php $reservationStatus = empty($reservation['status']) ? 'pending' : $reservation['status']; ?>
                                <tr class="reservation-row" data-status="<?php echo htmlspecialchars($reservationStatus); ?>">
                                    <td class="reservation-id">#<?php echo $reservation['id']; ?></td>
                                    <td>
                                        <div class="customer-info">
                                            <h4><?php echo htmlspecialchars($reservation['user_name']); ?></h4>
                                            <p><?php echo htmlspecialchars($reservation['user_email']); ?></p>
                                            <p><?php echo htmlspecialchars($reservation['user_phone']); ?></p>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="reservation-details">
                                            <p><strong>Check-in:</strong> <?php echo date('M d, Y', strtotime($reservation['check_in'])); ?></p>
                                            <p><strong>Check-out:</strong> <?php 
                                                if ($reservation['tour_type'] === 'day') {
                                                    echo date('M d, Y', strtotime($reservation['check_in']));
                                                } else {
                                                    echo date('M d, Y', strtotime($reservation['check_in'] . ' +1 day'));
                                                }
                                            ?></p>
                                            <p><strong>Tour:</strong> <?php echo ucfirst($reservation['tour_type']); ?></p>
                                            <p><strong>Guests:</strong> <?php echo $reservation['adults'] + $reservation['children'] + $reservation['seniors']; ?></p>
                                            <p><strong>Items:</strong> <?php echo count($reservation['items']); ?></p>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="reservation-details">
                                            <?php if ($reservation['tour_type'] === 'day'): ?>
                                                <p><strong>Check-in:</strong> 8:00 AM</p>
                                                <p><strong>Check-out:</strong> 5:00 PM</p>
                                            <?php else: ?>
                                                <p><strong>Check-in:</strong> 8:00 PM</p>
                                                <p><strong>Check-out:</strong> 5:00 AM (next day)</p>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td><strong>₱<?php echo number_format($reservation['total_amount'], 2); ?></strong></td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower(htmlspecialchars($reservationStatus)); ?>">
                                            <?php echo ucfirst(htmlspecialchars($reservationStatus)); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <?php if ($reservationStatus === 'pending'): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="reservation_id" value="<?php echo $reservation['id']; ?>">
                                                    <input type="hidden" name="action" value="approve">
                                                    <button type="submit" class="btn-approve" onclick="return confirm('Approve this reservation?')">
                                                        <i class="fas fa-check"></i> Approve
                                                    </button>
                                                </form>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="reservation_id" value="<?php echo $reservation['id']; ?>">
                                                    <input type="hidden" name="action" value="reject">
                                                    <button type="submit" class="btn-reject" onclick="return confirm('Reject this reservation?')">
                                                        <i class="fas fa-times"></i> Reject
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <button class="btn-view" onclick="viewDetails(<?php echo $reservation['id']; ?>)">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 2rem; color: var(--text-light);">
                                    <i class="fas fa-calendar-xmark" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
                                    <p>No reservations found</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <script>
        // Filter reservations
        function filterReservations(status) {
            const rows = document.querySelectorAll('.reservation-row');
            const tabs = document.querySelectorAll('.tab-btn');
            
            // Update active tab
            tabs.forEach(tab => tab.classList.remove('active'));
            event.target.classList.add('active');
            
            // Filter rows
            rows.forEach(row => {
                if (status === 'all' || row.dataset.status === status) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('.reservation-row');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if (text.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });

        // View details (placeholder)
        function viewDetails(reservationId) {
            alert('View details for reservation #' + reservationId);
            // You could implement a modal here to show full details
        }

        // Mobile menu toggle
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('open');
        }
    </script>
</body>
</html>
