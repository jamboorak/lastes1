<?php
require_once '../config/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Simple admin check - you might want to implement proper role-based access
// For now, let's assume user with ID 1 is admin
if ($_SESSION['user_id'] != 1) {
    header("Location: ../index.php");
    exit();
}

require_once '../includes/header.php';

// Get current page from URL
$page = $_GET['page'] ?? 'dashboard';

// Fetch dashboard statistics
$db = new Database();
$conn = $db->getConnection();

// Get total reservations
$totalReservations = $conn->query("SELECT COUNT(*) as count FROM reservations")->fetch_assoc()['count'];

// Get pending reservations
$pendingReservations = $conn->query("SELECT COUNT(*) as count FROM reservations WHERE status = 'pending'")->fetch_assoc()['count'];

// Get approved reservations
$approvedReservations = $conn->query("SELECT COUNT(*) as count FROM reservations WHERE status = 'approved'")->fetch_assoc()['count'];

// Get cancelled reservations
$cancelledReservations = $conn->query("SELECT COUNT(*) as count FROM reservations WHERE status = 'cancelled'")->fetch_assoc()['count'];

// Get recent reservations
$recentReservations = $conn->query("
    SELECT r.*, 
           COALESCE(u.fullname, ua.fullname, 'Unknown') as user_name,
           COALESCE(u.email, ua.email, '') as user_email
    FROM reservations r 
    LEFT JOIN users u ON r.user_id = u.id
    LEFT JOIN user_accounts ua ON r.user_id = ua.id
    ORDER BY r.created_at DESC 
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Villa Soledad</title>
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

        /* Dashboard Stats */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .stat-icon.total {
            background: linear-gradient(135deg, var(--primary-blue), var(--light-blue));
            color: white;
        }

        .stat-icon.pending {
            background: linear-gradient(135deg, var(--warning), var(--accent-orange));
            color: white;
        }

        .stat-icon.approved {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }

        .stat-icon.cancelled {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }

        .stat-info h3 {
            font-size: 2rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.25rem;
        }

        .stat-info p {
            color: var(--text-light);
            font-size: 0.9rem;
        }

        /* Recent Reservations */
        .recent-reservations {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .section-header {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .section-header h2 {
            font-size: 1.25rem;
            color: var(--text-dark);
        }

        .view-all-btn {
            background: var(--primary-blue);
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.9rem;
            transition: background 0.3s ease;
        }

        .view-all-btn:hover {
            background: var(--light-blue);
        }

        .reservation-list {
            padding: 1rem;
        }

        .reservation-item {
            padding: 1rem;
            border-bottom: 1px solid #f3f4f6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .reservation-item:last-child {
            border-bottom: none;
        }

        .reservation-info h4 {
            font-size: 1rem;
            color: var(--text-dark);
            margin-bottom: 0.25rem;
        }

        .reservation-info p {
            color: var(--text-light);
            font-size: 0.85rem;
        }

        .reservation-status {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
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

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                width: 200px;
            }
            
            .main-content {
                margin-left: 200px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
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
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar Navigation -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-cog"></i> Admin Panel</h2>
            </div>
            <nav>
                <ul class="sidebar-nav">
                    <li>
                        <a href="?page=dashboard" class="<?php echo $page === 'dashboard' ? 'active' : ''; ?>">
                            <i class="fas fa-tachometer-alt"></i>
                            Dashboard
                        </a>
                    </li>
                    <li>
                        <a href="?page=reservations" class="<?php echo $page === 'reservations' ? 'active' : ''; ?>">
                            <i class="fas fa-calendar-check"></i>
                            Reservations
                        </a>
                    </li>
                    <li>
                        <a href="?page=users" class="<?php echo $page === 'users' ? 'active' : ''; ?>">
                            <i class="fas fa-users"></i>
                            Users
                        </a>
                    </li>
                    <li>
                        <a href="?page=rooms" class="<?php echo $page === 'rooms' ? 'active' : ''; ?>">
                            <i class="fas fa-bed"></i>
                            Rooms
                        </a>
                    </li>
                    <li>
                        <a href="?page=cottages" class="<?php echo $page === 'cottages' ? 'active' : ''; ?>">
                            <i class="fas fa-home"></i>
                            Cottages
                        </a>
                    </li>
                    <li>
                        <a href="?page=reports" class="<?php echo $page === 'reports' ? 'active' : ''; ?>">
                            <i class="fas fa-chart-bar"></i>
                            Reports
                        </a>
                    </li>
                    <li>
                        <a href="?page=settings" class="<?php echo $page === 'settings' ? 'active' : ''; ?>">
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
                    <h1>Admin Dashboard</h1>
                    <div class="breadcrumb">
                        <span>Admin</span> / <span>Dashboard</span>
                    </div>
                </div>
                <div>
                    <span>Welcome back, Admin!</span>
                </div>
            </div>

            <?php if ($page === 'dashboard'): ?>
                <!-- Dashboard Content -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon total">
                            <i class="fas fa-calendar"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $totalReservations; ?></h3>
                            <p>Total Reservations</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon pending">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $pendingReservations; ?></h3>
                            <p>Pending Approval</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon approved">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $approvedReservations; ?></h3>
                            <p>Approved</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon cancelled">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $cancelledReservations; ?></h3>
                            <p>Cancelled</p>
                        </div>
                    </div>
                </div>

                <div class="recent-reservations">
                    <div class="section-header">
                        <h2>Recent Reservations</h2>
                        <a href="?page=reservations" class="view-all-btn">View All</a>
                    </div>
                    <div class="reservation-list">
                        <?php if (!empty($recentReservations)): ?>
                            <?php foreach ($recentReservations as $reservation): ?>
                                <div class="reservation-item">
                                    <div class="reservation-info">
                                        <h4>#<?php echo $reservation['id']; ?> - <?php echo htmlspecialchars($reservation['user_name']); ?></h4>
                                        <p><?php echo date('M d, Y', strtotime($reservation['created_at'])); ?> • <?php echo ucfirst($reservation['tour_type']); ?> Tour • ₱<?php echo number_format($reservation['total_amount'], 2); ?></p>
                                    </div>
                                    <div class="reservation-status">
                                        <span class="status-badge status-<?php echo $reservation['status']; ?>">
                                            <?php echo ucfirst($reservation['status']); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="text-align: center; padding: 2rem; color: var(--text-light);">
                                <i class="fas fa-calendar-xmark" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
                                <p>No reservations yet</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            <?php elseif ($page === 'reservations'): ?>
                <!-- Reservations Page -->
                <div class="content-header">
                    <h1>Reservations Management</h1>
                </div>
                <div style="background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                    <p>Reservations management page will be implemented here.</p>
                </div>

            <?php elseif ($page === 'rooms'): ?>
                <!-- Rooms Management Page -->
                <?php
                require_once '../config/RoomConfig.php';
                $selectedDate = $_GET['date'] ?? date('Y-m-d');
                
                // Fetch all rooms
                $roomsSql = "SELECT * FROM rooms ORDER BY id";
                $roomsResult = $conn->query($roomsSql);
                $rooms = $roomsResult ? $roomsResult->fetch_all(MYSQLI_ASSOC) : [];
                
                // Calculate remaining slots for each room on selected date
                $roomAvailability = [];
                foreach ($rooms as $room) {
                    $roomName = $room['name'];
                    $limit = getReservationLimit($roomName);
                    
                    // Count existing reservations for this room on selected date
                    $countSql = "SELECT COUNT(*) as count 
                                FROM reservation_items ri 
                                JOIN reservations r ON ri.reservation_id = r.id 
                                WHERE ri.item_name = ? AND ri.item_type = 'room' 
                                AND r.check_in = ? AND r.status != 'cancelled'";
                    $countStmt = $conn->prepare($countSql);
                    $countStmt->bind_param("ss", $roomName, $selectedDate);
                    $countStmt->execute();
                    $countResult = $countStmt->get_result();
                    $bookedCount = $countResult->fetch_assoc()['count'] ?? 0;
                    
                    $remainingSlots = max(0, $limit - $bookedCount);
                    $roomAvailability[$room['id']] = [
                        'limit' => $limit,
                        'booked' => $bookedCount,
                        'remaining' => $remainingSlots
                    ];
                }
                ?>
                <div class="content-header">
                    <div>
                        <h1>Rooms Management</h1>
                        <div class="breadcrumb">
                            <span>Admin</span> / <span>Rooms</span>
                        </div>
                    </div>
                    <div style="display: flex; gap: 1rem; align-items: center;">
                        <button onclick="openDateModal()" style="background: var(--primary-blue); color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 8px; cursor: pointer; font-weight: 600; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-calendar"></i> Check Availability
                        </button>
                        <button onclick="window.location.href='../views/rooms.php'" style="background: var(--accent-orange); color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 8px; cursor: pointer; font-weight: 600;">
                            <i class="fas fa-plus"></i> Add Room
                        </button>
                    </div>
                </div>

                <?php if ($selectedDate): ?>
                <div style="background: #e0f2fe; padding: 1rem 1.5rem; border-radius: 8px; margin-bottom: 1.5rem; border-left: 4px solid var(--primary-blue);">
                    <strong>Selected Date:</strong> <?php echo date('F d, Y', strtotime($selectedDate)); ?>
                    <button onclick="clearDate()" style="background: none; border: none; color: var(--primary-blue); cursor: pointer; margin-left: 1rem; text-decoration: underline;">Clear</button>
                </div>
                <?php endif; ?>

                <div class="rooms-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.5rem;">
                    <?php if (!empty($rooms)): ?>
                        <?php foreach ($rooms as $room): ?>
                            <?php $availability = $roomAvailability[$room['id']]; ?>
                            <div class="room-card" style="background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); overflow: hidden;">
                                <div style="height: 180px; background: #f8fafc; display: flex; align-items: center; justify-content: center; overflow: hidden;">
                                    <?php if (!empty($room['image_url'])): ?>
                                        <img src="<?php echo htmlspecialchars($room['image_url']); ?>" alt="<?php echo htmlspecialchars($room['name']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                    <?php else: ?>
                                        <i class="fas fa-bed" style="font-size: 3rem; color: #cbd5e1;"></i>
                                    <?php endif; ?>
                                </div>
                                <div style="padding: 1.5rem;">
                                    <h3 style="color: var(--primary-blue); font-size: 1.25rem; margin-bottom: 0.5rem;"><?php echo htmlspecialchars($room['name']); ?></h3>
                                    <p style="color: #64748b; font-size: 0.9rem; margin-bottom: 1rem;"><?php echo htmlspecialchars($room['description']); ?></p>
                                    <div style="background: #f1f5f9; padding: 0.75rem; border-radius: 8px; margin-bottom: 1rem;">
                                        <div style="font-size: 0.85rem; color: #64748b; margin-bottom: 0.25rem;">Capacity: <?php echo (int)$room['capacity']; ?> pax</div>
                                        <div style="font-size: 0.85rem; color: #64748b; margin-bottom: 0.25rem;">Price: ₱<?php echo number_format((float)$room['price_per_night'], 2); ?></div>
                                        <?php if ($selectedDate): ?>
                                        <div style="font-size: 0.85rem; color: #64748b; margin-bottom: 0.25rem;">Daily Limit: <?php echo $availability['limit']; ?></div>
                                        <div style="font-size: 0.85rem; color: #64748b; margin-bottom: 0.25rem;">Booked: <?php echo $availability['booked']; ?></div>
                                        <div style="font-size: 0.9rem; font-weight: 700; color: <?php echo $availability['remaining'] > 0 ? '#10b981' : '#ef4444'; ?>;">
                                            Remaining slots: <?php echo $availability['remaining']; ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <div style="display: flex; gap: 0.5rem;">
                                        <button onclick="viewRoom(<?php echo $room['id']; ?>)" style="flex: 1; background: var(--primary-blue); color: white; border: none; padding: 0.6rem; border-radius: 6px; cursor: pointer; font-weight: 600;">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                        <button onclick="editRoom(<?php echo $room['id']; ?>)" style="flex: 1; background: var(--accent-orange); color: white; border: none; padding: 0.6rem; border-radius: 6px; cursor: pointer; font-weight: 600;">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="grid-column: 1/-1; text-align: center; padding: 3rem; color: var(--text-light);">
                            <i class="fas fa-bed" style="font-size: 3rem; margin-bottom: 1rem; display: block;"></i>
                            <p>No rooms found. Add your first room to get started.</p>
                        </div>
                    <?php endif; ?>
                </div>

            <?php elseif ($page === 'cottages'): ?>
                <!-- Cottages Management Page -->
                <?php
                require_once '../config/RoomConfig.php';
                $selectedDate = $_GET['date'] ?? date('Y-m-d');
                
                // Fetch all cottages
                $cottagesSql = "SELECT * FROM cottages ORDER BY id";
                $cottagesResult = $conn->query($cottagesSql);
                $cottages = $cottagesResult ? $cottagesResult->fetch_all(MYSQLI_ASSOC) : [];
                
                // Calculate remaining slots for each cottage on selected date
                $cottageAvailability = [];
                foreach ($cottages as $cottage) {
                    $cottageName = $cottage['name'];
                    $limit = getReservationLimit($cottageName);
                    
                    // Count existing reservations for this cottage on selected date
                    $countSql = "SELECT COUNT(*) as count 
                                FROM reservation_items ri 
                                JOIN reservations r ON ri.reservation_id = r.id 
                                WHERE ri.item_name = ? AND ri.item_type = 'cottage' 
                                AND r.check_in = ? AND r.status != 'cancelled'";
                    $countStmt = $conn->prepare($countSql);
                    $countStmt->bind_param("ss", $cottageName, $selectedDate);
                    $countStmt->execute();
                    $countResult = $countStmt->get_result();
                    $bookedCount = $countResult->fetch_assoc()['count'] ?? 0;
                    
                    $remainingSlots = max(0, $limit - $bookedCount);
                    $cottageAvailability[$cottage['id']] = [
                        'limit' => $limit,
                        'booked' => $bookedCount,
                        'remaining' => $remainingSlots
                    ];
                }
                ?>
                <div class="content-header">
                    <div>
                        <h1>Cottages Management</h1>
                        <div class="breadcrumb">
                            <span>Admin</span> / <span>Cottages</span>
                        </div>
                    </div>
                    <div style="display: flex; gap: 1rem; align-items: center;">
                        <button onclick="openDateModal()" style="background: var(--primary-blue); color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 8px; cursor: pointer; font-weight: 600; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-calendar"></i> Check Availability
                        </button>
                        <button onclick="window.location.href='../views/cottages.php'" style="background: var(--accent-orange); color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 8px; cursor: pointer; font-weight: 600;">
                            <i class="fas fa-plus"></i> Add Cottage
                        </button>
                    </div>
                </div>

                <?php if ($selectedDate): ?>
                <div style="background: #e0f2fe; padding: 1rem 1.5rem; border-radius: 8px; margin-bottom: 1.5rem; border-left: 4px solid var(--primary-blue);">
                    <strong>Selected Date:</strong> <?php echo date('F d, Y', strtotime($selectedDate)); ?>
                    <button onclick="clearDate()" style="background: none; border: none; color: var(--primary-blue); cursor: pointer; margin-left: 1rem; text-decoration: underline;">Clear</button>
                </div>
                <?php endif; ?>

                <div class="cottages-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.5rem;">
                    <?php if (!empty($cottages)): ?>
                        <?php foreach ($cottages as $cottage): ?>
                            <?php $availability = $cottageAvailability[$cottage['id']]; ?>
                            <div class="cottage-card" style="background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); overflow: hidden;">
                                <div style="height: 180px; background: #f8fafc; display: flex; align-items: center; justify-content: center; overflow: hidden;">
                                    <?php if (!empty($cottage['image_url'])): ?>
                                        <img src="<?php echo htmlspecialchars($cottage['image_url']); ?>" alt="<?php echo htmlspecialchars($cottage['name']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                    <?php else: ?>
                                        <i class="fas fa-home" style="font-size: 3rem; color: #cbd5e1;"></i>
                                    <?php endif; ?>
                                </div>
                                <div style="padding: 1.5rem;">
                                    <h3 style="color: var(--primary-blue); font-size: 1.25rem; margin-bottom: 0.5rem;"><?php echo htmlspecialchars($cottage['name']); ?></h3>
                                    <p style="color: #64748b; font-size: 0.9rem; margin-bottom: 1rem;"><?php echo htmlspecialchars($cottage['description']); ?></p>
                                    <div style="background: #f1f5f9; padding: 0.75rem; border-radius: 8px; margin-bottom: 1rem;">
                                        <div style="font-size: 0.85rem; color: #64748b; margin-bottom: 0.25rem;">Capacity: <?php echo (int)$cottage['capacity']; ?> pax</div>
                                        <div style="font-size: 0.85rem; color: #64748b; margin-bottom: 0.25rem;">Price: ₱<?php echo number_format((float)$cottage['price_per_night'], 2); ?></div>
                                        <?php if ($selectedDate): ?>
                                        <div style="font-size: 0.85rem; color: #64748b; margin-bottom: 0.25rem;">Daily Limit: <?php echo $availability['limit']; ?></div>
                                        <div style="font-size: 0.85rem; color: #64748b; margin-bottom: 0.25rem;">Booked: <?php echo $availability['booked']; ?></div>
                                        <div style="font-size: 0.9rem; font-weight: 700; color: <?php echo $availability['remaining'] > 0 ? '#10b981' : '#ef4444'; ?>;">
                                            Remaining slots: <?php echo $availability['remaining']; ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <div style="display: flex; gap: 0.5rem;">
                                        <button onclick="viewCottage(<?php echo $cottage['id']; ?>)" style="flex: 1; background: var(--primary-blue); color: white; border: none; padding: 0.6rem; border-radius: 6px; cursor: pointer; font-weight: 600;">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                        <button onclick="editCottage(<?php echo $cottage['id']; ?>)" style="flex: 1; background: var(--accent-orange); color: white; border: none; padding: 0.6rem; border-radius: 6px; cursor: pointer; font-weight: 600;">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="grid-column: 1/-1; text-align: center; padding: 3rem; color: var(--text-light);">
                            <i class="fas fa-home" style="font-size: 3rem; margin-bottom: 1rem; display: block;"></i>
                            <p>No cottages found. Add your first cottage to get started.</p>
                        </div>
                    <?php endif; ?>
                </div>

            <?php else: ?>
                <!-- Other pages placeholder -->
                <div class="content-header">
                    <h1><?php echo ucfirst($page); ?></h1>
                </div>
                <div style="background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                    <p><?php echo ucfirst($page); ?> page will be implemented here.</p>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Date Picker Modal -->
    <div id="dateModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center;">
        <div style="background: white; padding: 2rem; border-radius: 12px; max-width: 400px; width: 90%; box-shadow: 0 10px 40px rgba(0,0,0,0.3);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h2 style="color: var(--primary-blue); margin: 0;">Select Date</h2>
                <button onclick="closeDateModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-light);">&times;</button>
            </div>
            <div style="margin-bottom: 1.5rem;">
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-dark); font-weight: 600;">Choose a date to check availability:</label>
                <input type="date" id="dateInput" style="width: 100%; padding: 0.75rem; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 1rem;">
            </div>
            <div style="display: flex; gap: 1rem;">
                <button onclick="closeDateModal()" style="flex: 1; padding: 0.75rem; background: #e5e7eb; color: var(--text-dark); border: none; border-radius: 6px; font-weight: 600; cursor: pointer;">Cancel</button>
                <button onclick="applyDate()" style="flex: 1; padding: 0.75rem; background: var(--primary-blue); color: white; border: none; border-radius: 6px; font-weight: 600; cursor: pointer;">Check Availability</button>
            </div>
        </div>
    </div>

    <script>
        // Mobile menu toggle
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('open');
        }

        // Simple page navigation
        document.querySelectorAll('.sidebar-nav a').forEach(link => {
            link.addEventListener('click', function(e) {
                if (this.getAttribute('href').includes('?page=')) {
                    // Let the page reload naturally
                }
            });
        });

        // Date picker modal functions
        function openDateModal() {
            document.getElementById('dateModal').style.display = 'flex';
            // Set minimum date to today
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('dateInput').min = today;
            document.getElementById('dateInput').value = today;
        }

        function closeDateModal() {
            document.getElementById('dateModal').style.display = 'none';
        }

        function applyDate() {
            const selectedDate = document.getElementById('dateInput').value;
            if (selectedDate) {
                const currentPage = new URLSearchParams(window.location.search).get('page') || 'rooms';
                window.location.href = `?page=${currentPage}&date=${selectedDate}`;
            }
        }

        function clearDate() {
            const currentPage = new URLSearchParams(window.location.search).get('page') || 'rooms';
            window.location.href = `?page=${currentPage}`;
        }

        // Placeholder functions for view/edit
        function viewRoom(id) {
            alert('View room functionality to be implemented for room ID: ' + id);
        }

        function editRoom(id) {
            alert('Edit room functionality to be implemented for room ID: ' + id);
        }

        function viewCottage(id) {
            alert('View cottage functionality to be implemented for cottage ID: ' + id);
        }

        function editCottage(id) {
            alert('Edit cottage functionality to be implemented for cottage ID: ' + id);
        }

        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('dateModal');
            if (event.target === modal) {
                closeDateModal();
            }
        });
    </script>
</body>
</html>
