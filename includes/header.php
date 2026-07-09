<?php
require_once __DIR__ . '/../config/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Review.php';
require_once __DIR__ . '/../controllers/ReviewController.php';

$user = new User();
$reviewController = new ReviewController();
$currentUser = $user->getCurrentUser();
$recentReviews = $reviewController->getRecentReviews(3);

// Fetch recent reservations for the user dropdown
$recentReservations = [];
if (isset($_SESSION['user_id'])) {
    $db = new Database();
    $conn = $db->getConnection();

    $tablesToEnsure = [
        "CREATE TABLE IF NOT EXISTS reservations (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            user_id INT(11) NOT NULL,
            check_in DATE NOT NULL,
            check_out DATE NOT NULL,
            adults INT(11) NOT NULL,
            children INT(11) NOT NULL,
            seniors INT(11) NOT NULL,
            total_amount DECIMAL(10,2) NOT NULL,
            tour_type ENUM('day', 'night') DEFAULT 'day',
            status VARCHAR(20) DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )",
        "CREATE TABLE IF NOT EXISTS reservation_items (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            reservation_id INT(11) NOT NULL,
            item_type VARCHAR(20) NOT NULL,
            item_id INT(11) NOT NULL,
            item_name VARCHAR(100) NOT NULL,
            price DECIMAL(10,2) NOT NULL,
            capacity INT(11) NOT NULL,
            nights INT(11) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (reservation_id) REFERENCES reservations(id)
        )"
    ];

    foreach ($tablesToEnsure as $tableSql) {
        @$conn->query($tableSql);
    }

    $resSql = "SELECT r.id, r.status, r.total_amount, r.created_at, 
               GROUP_CONCAT(ri.item_name SEPARATOR ', ') as item_names
               FROM reservations r 
               LEFT JOIN reservation_items ri ON r.id = ri.reservation_id
               WHERE r.user_id = ? 
               GROUP BY r.id 
               ORDER BY r.created_at DESC 
               LIMIT 3";

    if ($stmt = $conn->prepare($resSql)) {
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $resResult = $stmt->get_result();
        $recentReservations = $resResult->fetch_all(MYSQLI_ASSOC);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?><?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        #loginPopup.login-modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.7);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }

        #loginPopup.login-modal.show {
            display: flex;
        }

        .login-modal-content {
            width: min(520px, 100%);
            max-width: 520px;
            background: #ffffff;
            border-radius: 30px;
            padding: 2.5rem;
            box-shadow: 0 30px 80px rgba(15, 23, 42, 0.18);
            border: 1px solid rgba(148, 163, 184, 0.16);
            position: relative;
            transform: translateY(-10px);
            transition: transform 0.25s ease, opacity 0.25s ease;
        }

        .login-modal.show .login-modal-content {
            transform: translateY(0);
            opacity: 1;
        }

        .login-modal-close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: transparent;
            border: none;
            color: #475569;
            font-size: 1.5rem;
            cursor: pointer;
            transition: color 0.2s ease;
        }

        .login-modal-close:hover {
            color: #0f172a;
        }

        .login-popup-header h2 {
            font-size: 2.2rem;
            margin-bottom: 0.5rem;
            color: #1f3a8a;
        }

        .login-popup-header p {
            color: #64748b;
            line-height: 1.8;
            margin-bottom: 1.8rem;
        }

        .login-popup-buttons {
            display: grid;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .social-btn {
            width: 100%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            padding: 1rem 1.25rem;
            border-radius: 16px;
            font-weight: 700;
            font-size: 1rem;
            border: none;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .social-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.12);
        }

        .social-btn-google {
            background: #2563eb;
            color: #ffffff;
            border: 1px solid transparent;
        }

        .social-btn-facebook {
            background: #111827;
            color: #ffffff;
            border: 1px solid transparent;
        }

        .login-popup-divider {
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 1rem 0 1.2rem;
            color: #94a3b8;
            font-size: 0.9rem;
            letter-spacing: 0.1em;
        }

        .login-popup-divider::before,
        .login-popup-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #e2e8f0;
            margin: 0 1rem;
        }

        .login-popup-disclaimer {
            color: #64748b;
            font-size: 0.88rem;
            line-height: 1.6;
        }

        .login-popup-disclaimer a {
            color: #2563eb;
            text-decoration: underline;
        }

        /* User Avatar Styles */
        .user-avatar-container {
            display: flex;
            align-items: center;
            margin-left: 1rem;
            position: relative;
        }

        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            border: 2px solid white;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            cursor: pointer;
            transition: all 0.3s ease;
            object-fit: cover;
        }

        .user-avatar:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
            border-color: #FF7A3D;
        }

        /* User Dropdown Menu */
        .user-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            margin-top: 0.5rem;
            background: white;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
            border: 1px solid rgba(0, 0, 0, 0.08);
            min-width: 340px;
            max-width: 420px;
            z-index: 1000;
            display: none;
            overflow: hidden;
        }

        .user-dropdown.show {
            display: block;
        }

        .user-dropdown-header {
            padding: 1rem;
            background: #f8fafc;
            border-bottom: 1px solid #e5e7eb;
        }

        .user-dropdown-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .user-dropdown-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            border: 2px solid #FF7A3D;
            object-fit: cover;
        }

        .user-dropdown-details h4 {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
            color: #1f2937;
        }

        .user-dropdown-details p {
            margin: 0;
            font-size: 0.875rem;
            color: #6b7280;
        }

        .user-dropdown-menu {
            padding: 0.75rem;
        }

        .user-dropdown-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            color: #1e3a8a !important;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.2s ease;
            font-size: 0.95rem;
            font-weight: 600;
        }

        .user-dropdown-item:hover {
            background: #f3f4f6;
            color: #1f2937;
        }

        .user-dropdown-item i {
            width: 16px;
            color: #000000;
        }

        .reservation-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            padding: 1rem;
            margin: 0.5rem 0;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
        }

        .reservation-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            margin-bottom: 0.75rem;
        }

        .reservation-card-title {
            font-size: 0.95rem;
            font-weight: 700;
            color: #111827;
            margin: 0;
        }

        .reservation-status {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.35rem 0.75rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: white;
        }

        .reservation-status.pending { background: #f59e0b; }
        .reservation-status.approved { background: #10b981; }
        .reservation-status.cancelled { background: #ef4444; }

        .reservation-card-meta {
            display: grid;
            gap: 0.4rem;
            margin-bottom: 0.75rem;
        }

        .reservation-card-meta span {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.82rem;
            color: #475569;
        }

        .reservation-card-section {
            display: grid;
            gap: 0.5rem;
            margin-bottom: 0.75rem;
        }

        .reservation-card-section strong {
            display: block;
            font-size: 0.78rem;
            color: #0f172a;
            margin-bottom: 0.2rem;
        }

        .reservation-card-details {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .reservation-card-detail {
            flex: 1 1 45%;
            background: #f8fafc;
            border-radius: 12px;
            padding: 0.65rem 0.75rem;
            font-size: 0.82rem;
            color: #334155;
        }

        .reservation-card-action {
            display: block;
            width: 100%;
            margin-top: 0.5rem;
            padding: 0.75rem 0.9rem;
            text-align: center;
            border-radius: 12px;
            border: none;
            background: #3b82f6;
            color: white;
            font-weight: 700;
            text-decoration: none;
            transition: background 0.2s ease;
        }

        .reservation-card-action:hover {
            background: #2563eb;
        }

        .user-dropdown-divider {
            height: 1px;
            background: #e5e7eb;
            margin: 0.5rem 0;
        }

            </style>
        <?php echo isset($pageHead) ? $pageHead : ''; ?>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="nav">
                <div class="logo" style="display: flex; align-items: center; gap: 1rem;">
                    <img src="<?php echo SITE_URL; ?>images/logo.jpg" alt="Villa Soledad Garden Resort Logo" style="height: 60px; width: 60px; object-fit: cover; border-radius: 50%; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
                    <span style="font-size: 1.5rem; font-weight: 700; color: white;">Villa Soledad Garden Resort</span>
                                    </div>
                <button class="mobile-menu-toggle" onclick="toggleMobileMenu()" style="background: none; border: none; color: white; font-size: 1.5rem; cursor: pointer; display: none;">
                    <i class="fas fa-bars"></i>
                </button>
                <nav class="nav-links" id="navLinks">
                    <a href="<?php echo SITE_URL; ?>index.php#home">Home</a>
                    <a href="<?php echo SITE_URL; ?>index.php#cottages">Cottages</a>
                    <a href="<?php echo SITE_URL; ?>index.php#pools">Pools</a>
                    <a href="<?php echo SITE_URL; ?>index.php#rooms">Rooms</a>
                    <?php if ($user->isLoggedIn()): ?>
                        <a href="<?php echo SITE_URL; ?>booking.php">Book Now</a>
                    <?php endif; ?>
                    <a href="<?php echo SITE_URL; ?>food-menu.php">Food Menu</a>
                    
                    <?php if ($user->isLoggedIn()): ?>
                        <div class="user-avatar-container">
                            <img src="<?php echo !empty($_SESSION['user_avatar']) ? $_SESSION['user_avatar'] : 'https://ui-avatars.com/api/?name=' . urlencode($_SESSION['user_name'] ?? 'User') . '&background=FF7A3D&color=fff&size=32'; ?>" 
                                 alt="User Avatar" 
                                 class="user-avatar"
                                 title="<?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?>"
                                 onclick="toggleUserDropdown()">
                            
                            <div class="user-dropdown" id="userDropdown">
                                <div class="user-dropdown-header">
                                    <div class="user-dropdown-info">
                                        <img src="<?php echo !empty($_SESSION['user_avatar']) ? $_SESSION['user_avatar'] : 'https://ui-avatars.com/api/?name=' . urlencode($_SESSION['user_name'] ?? 'User') . '&background=FF7A3D&color=fff&size=48'; ?>" 
                                             alt="User Avatar" 
                                             class="user-dropdown-avatar">
                                        <div class="user-dropdown-details">
                                            <h4><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?></h4>
                                            <p><?php echo htmlspecialchars($currentUser['email'] ?? ''); ?></p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="user-dropdown-menu">
                                    <a href="<?php echo SITE_URL; ?>profile.php" class="user-dropdown-item" onclick="event.stopPropagation()">
                                        <i class="fas fa-user"></i>
                                        My Profile
                                    </a>
                                    <a href="<?php echo SITE_URL; ?>my-bookings.php" class="user-dropdown-item" onclick="event.stopPropagation()">
                                        <i class="fas fa-calendar-check"></i>
                                        My Bookings
                                    </a>
                                    <div class="user-dropdown-divider"></div>
                                    <a href="<?php echo SITE_URL; ?>logout.php" class="user-dropdown-item">
                                        <i class="fas fa-sign-out-alt"></i>
                                        Log out
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <button type="button" onclick="openLoginModal()" class="btn-login" style="background: #FF7A3D; color: white; border: none; padding: 0.9rem 2rem; border-radius: 50px; font-weight: 700; font-size: 1rem; cursor: pointer; display: flex; align-items: center; gap: 0.6rem; transition: all 0.3s ease;" onmouseover="this.style.background='#FF6B1F';" onmouseout="this.style.background='#FF7A3D';"><i class="fas fa-user-circle"></i> Login</button>
                    <?php endif; ?>
                </nav>
            </div>
        </div>
    </header>

    <!-- Login Popup -->
    <div id="loginPopup" class="login-modal" aria-hidden="true" hidden>
        <div class="login-modal-content">
            <button type="button" class="login-modal-close" onclick="closeLoginModal()" aria-label="Close">&times;</button>
            <div class="login-popup-header">
                <h2>Log in</h2>
                <p>Choose a sign-in option to continue to the resort booking system.</p>
            </div>
            <div class="login-popup-buttons">
                <button type="button" class="social-btn social-btn-google" onclick="window.location.href='<?php echo SITE_URL; ?>google-auth.php'">
                    <i class="fab fa-google"></i>
                    Sign in with Google
                </button>
            </div>
            <div class="login-popup-divider"><span>OR</span></div>
            <p class="login-popup-disclaimer">By using our service, you agree with our Terms of service, CCPA Notice and Privacy Notice that details what personal data we collect and use to provide you with the best learning experience.</p>
        </div>
    </div>

    <script>
        function openLoginModal() {
            const modal = document.getElementById('loginPopup');
            if (modal) {
                modal.hidden = false;
                modal.classList.add('show');
                modal.setAttribute('aria-hidden', 'false');
            }
        }

        function closeLoginModal() {
            const modal = document.getElementById('loginPopup');
            if (modal) {
                modal.classList.remove('show');
                modal.setAttribute('aria-hidden', 'true');
                modal.hidden = true;
            }
        }

        document.addEventListener('click', function(event) {
            const modal = document.getElementById('loginPopup');
            if (modal && modal.classList.contains('show') && event.target === modal) {
                closeLoginModal();
            }
        });

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeLoginModal();
                closeUserDropdown();
            }
        });

        // User dropdown functionality
        function toggleUserDropdown() {
            const dropdown = document.getElementById('userDropdown');
            if (dropdown) {
                dropdown.classList.toggle('show');
            }
        }

        function closeUserDropdown() {
            const dropdown = document.getElementById('userDropdown');
            if (dropdown) {
                dropdown.classList.remove('show');
            }
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('userDropdown');
            const avatarContainer = document.querySelector('.user-avatar-container');
            
            if (dropdown && avatarContainer && !avatarContainer.contains(event.target)) {
                closeUserDropdown();
            }
        });

            </script>

    <!-- Flash Messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <?php 
                echo $_SESSION['success']; 
                unset($_SESSION['success']);
            ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error']) || isset($_SESSION['login_error'])): ?>
        <div class="alert alert-error">
            <?php 
                echo isset($_SESSION['error']) ? $_SESSION['error'] : $_SESSION['login_error']; 
                unset($_SESSION['error'], $_SESSION['login_error']);
            ?>
        </div>
    <?php endif; ?>

    <main>
        <?php if (isset($showHero) && $showHero): ?>
            <!-- Hero Section -->
            <section id="home" class="hero">
                <div class="hero-content">
                    <h1>Pool Resort</h1>
                    <p>great for families, barkada hangouts and reunions.</p>
                    <button class="btn-primary" onclick="scrollToBooking()">Start Booking</button>
                </div>
            </section>
        <?php endif; ?>
