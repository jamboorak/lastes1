<?php
session_start();
require_once '../config/database.php';
require_once '../config/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../google-auth.php?action=login');
    exit();
}

$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'];

// Get database connection
$db = new Database();
$conn = $db->getConnection();

// Fetch user details
$userSql = "SELECT * FROM user_accounts WHERE id = ?";
$userStmt = $conn->prepare($userSql);
$userStmt->bind_param("i", $userId);
$userStmt->execute();
$userResult = $userStmt->get_result();
$user = $userResult->fetch_assoc();

// Fetch upcoming bookings
$upcomingSql = "SELECT b.*, r.room_name, r.price FROM bookings b JOIN rooms r ON b.room_id = r.id WHERE b.user_id = ? AND b.check_out > NOW() ORDER BY b.check_in ASC";
$upcomingStmt = $conn->prepare($upcomingSql);
$upcomingStmt->bind_param("i", $userId);
$upcomingStmt->execute();
$upcomingResult = $upcomingStmt->get_result();
$upcomingBookings = $upcomingResult->fetch_all(MYSQLI_ASSOC);

// Fetch booking history
$historySql = "SELECT b.*, r.room_name, r.price FROM bookings b JOIN rooms r ON b.room_id = r.id WHERE b.user_id = ? AND b.check_out <= NOW() ORDER BY b.check_in DESC LIMIT 10";
$historyStmt = $conn->prepare($historySql);
$historyStmt->bind_param("i", $userId);
$historyStmt->execute();
$historyResult = $historyStmt->get_result();
$bookingHistory = $historyResult->fetch_all(MYSQLI_ASSOC);

// Fetch user reviews
$reviewSql = "SELECT * FROM reviews WHERE user_id = ? ORDER BY created_at DESC";
$reviewStmt = $conn->prepare($reviewSql);
$reviewStmt->bind_param("i", $userId);
$reviewStmt->execute();
$reviewResult = $reviewStmt->get_result();
$userReviews = $reviewResult->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Dashboard - Villa Soledad</title>
    <link rel="stylesheet" href="../css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .dashboard-container {
            display: grid;
            grid-template-columns: 250px 1fr;
            gap: 2rem;
            min-height: calc(100vh - 200px);
            margin: 2rem 0;
        }

        .dashboard-sidebar {
            background: var(--white);
            padding: 2rem;
            border-radius: 10px;
            height: fit-content;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .sidebar-menu {
            list-style: none;
        }

        .sidebar-menu li {
            margin-bottom: 0.5rem;
        }

        .sidebar-menu a {
            display: block;
            padding: 0.8rem 1rem;
            color: var(--text-dark);
            text-decoration: none;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background-color: var(--accent-orange);
            color: var(--white);
        }

        .dashboard-content {
            background: var(--white);
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 2rem;
        }

        .dashboard-section {
            display: none;
        }

        .dashboard-section.active {
            display: block;
        }

        .section-title {
            font-size: 1.8rem;
            color: var(--primary-blue);
            margin-bottom: 1.5rem;
            border-bottom: 2px solid var(--accent-orange);
            padding-bottom: 0.5rem;
        }

        .booking-card {
            background: var(--bg-light);
            padding: 1.5rem;
            border-radius: 10px;
            border-left: 4px solid var(--accent-orange);
            margin-bottom: 1.5rem;
        }

        .booking-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .booking-card h3 {
            color: var(--primary-blue);
            margin: 0 0 0.5rem 0;
        }

        .booking-details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            font-size: 0.9rem;
        }

        .booking-detail-item {
            color: var(--text-light);
        }

        .booking-detail-item strong {
            color: var(--text-dark);
        }

        .status-badge {
            display: inline-block;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .status-upcoming {
            background-color: var(--light-blue);
            color: var(--white);
        }

        .status-completed {
            background-color: var(--success);
            color: var(--white);
        }

        .profile-section {
            background: var(--bg-light);
            padding: 2rem;
            border-radius: 10px;
            margin-bottom: 2rem;
        }

        .profile-field {
            display: grid;
            grid-template-columns: 150px 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
            align-items: center;
        }

        .profile-field label {
            font-weight: 600;
            color: var(--primary-blue);
        }

        .profile-field input,
        .profile-field textarea {
            padding: 0.7rem;
            border: 2px solid var(--border-gray);
            border-radius: 5px;
            font-family: inherit;
        }

        .profile-field input:focus,
        .profile-field textarea:focus {
            outline: none;
            border-color: var(--accent-orange);
        }

        .review-card {
            background: var(--bg-light);
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            border-left: 4px solid var(--primary-blue);
        }

        .review-rating {
            color: var(--accent-orange);
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
        }

        .btn-update {
            background-color: var(--primary-blue);
            color: var(--white);
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            transition: background-color 0.3s ease;
        }

        .btn-update:hover {
            background-color: var(--light-blue);
        }

        .btn-cancel {
            background-color: var(--border-gray);
            color: var(--text-dark);
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            transition: background-color 0.3s ease;
            margin-left: 0.5rem;
        }

        .btn-cancel:hover {
            background-color: #d1d5db;
        }

        .empty-state {
            text-align: center;
            padding: 2rem;
            color: var(--text-light);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
            display: block;
        }

        @media (max-width: 768px) {
            .dashboard-container {
                grid-template-columns: 1fr;
            }

            .booking-details {
                grid-template-columns: 1fr;
            }

            .profile-field {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Header & Navigation -->
    <header class="header">
        <div class="container">
            <div class="nav">
                <a href="../index.php" class="logo">
                    <i class="fas fa-hotel"></i>
                    Villa Soledad
                </a>
                <nav class="nav-links">
                    <a href="../index.php">Home</a>
                    <a href="../index.php#rooms">Rooms</a>
                    <a href="../index.php#cottages">Cottages</a>
                    <a href="../index.php#pools">Pools</a>
                    <a href="dashboard.php" class="btn-profile" style="background-color: var(--accent-orange);">
                        <i class="fas fa-user-circle"></i> Dashboard
                    </a>
                    <a href="../logout.php" style="color: var(--white); text-decoration: none; margin-left: 0.5rem;">Logout</a>
                </nav>
            </div>
        </div>
    </header>

    <!-- Dashboard Container -->
    <div class="container">
        <div class="dashboard-container">
            <!-- Sidebar -->
            <aside class="dashboard-sidebar">
                <ul class="sidebar-menu">
                    <li><a href="#" class="menu-link active" data-section="bookings"><i class="fas fa-calendar-alt"></i> My Bookings</a></li>
                    <li><a href="#" class="menu-link" data-section="history"><i class="fas fa-history"></i> Booking History</a></li>
                    <li><a href="#" class="menu-link" data-section="reviews"><i class="fas fa-star"></i> My Reviews</a></li>
                    <li><a href="#" class="menu-link" data-section="profile"><i class="fas fa-user"></i> Profile Settings</a></li>
                </ul>
            </aside>

            <!-- Main Content -->
            <main class="dashboard-content">
                <!-- My Bookings Section -->
                <div id="bookings" class="dashboard-section active">
                    <h2 class="section-title"><i class="fas fa-calendar-check"></i> Upcoming Bookings</h2>
                    
                    <?php if (empty($upcomingBookings)): ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-plus"></i>
                            <p>No upcoming bookings</p>
                            <a href="index.php#rooms" class="btn-primary" style="margin-top: 1rem;">Book Now</a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($upcomingBookings as $booking): ?>
                        <div class="booking-card">
                            <div class="booking-card-header">
                                <h3><?php echo htmlspecialchars($booking['room_name']); ?></h3>
                                <span class="status-badge status-upcoming">Upcoming</span>
                            </div>
                            <div class="booking-details">
                                <div class="booking-detail-item">
                                    <strong>Check-in:</strong> <?php echo date('M d, Y', strtotime($booking['check_in'])); ?>
                                </div>
                                <div class="booking-detail-item">
                                    <strong>Check-out:</strong> <?php echo date('M d, Y', strtotime($booking['check_out'])); ?>
                                </div>
                                <div class="booking-detail-item">
                                    <strong>Guests:</strong> <?php echo htmlspecialchars($booking['guests']); ?>
                                </div>
                                <div class="booking-detail-item">
                                    <strong>Total Price:</strong> ₱<?php echo number_format($booking['total_price'], 2); ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Booking History Section -->
                <div id="history" class="dashboard-section">
                    <h2 class="section-title"><i class="fas fa-history"></i> Booking History</h2>
                    
                    <?php if (empty($bookingHistory)): ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <p>No booking history yet</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($bookingHistory as $booking): ?>
                        <div class="booking-card">
                            <div class="booking-card-header">
                                <h3><?php echo htmlspecialchars($booking['room_name']); ?></h3>
                                <span class="status-badge status-completed">Completed</span>
                            </div>
                            <div class="booking-details">
                                <div class="booking-detail-item">
                                    <strong>Check-in:</strong> <?php echo date('M d, Y', strtotime($booking['check_in'])); ?>
                                </div>
                                <div class="booking-detail-item">
                                    <strong>Check-out:</strong> <?php echo date('M d, Y', strtotime($booking['check_out'])); ?>
                                </div>
                                <div class="booking-detail-item">
                                    <strong>Guests:</strong> <?php echo htmlspecialchars($booking['guests']); ?>
                                </div>
                                <div class="booking-detail-item">
                                    <strong>Total Price:</strong> ₱<?php echo number_format($booking['total_price'], 2); ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- My Reviews Section -->
                <div id="reviews" class="dashboard-section">
                    <h2 class="section-title"><i class="fas fa-star"></i> My Reviews</h2>
                    
                    <?php if (empty($userReviews)): ?>
                        <div class="empty-state">
                            <i class="fas fa-comment"></i>
                            <p>You haven't written any reviews yet</p>
                            <a href="index.php#reviews" class="btn-primary" style="margin-top: 1rem;">Write a Review</a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($userReviews as $review): ?>
                        <div class="review-card">
                            <div class="review-rating">
                                <?php for ($i = 0; $i < $review['rating']; $i++): ?>
                                    ★
                                <?php endfor; ?>
                            </div>
                            <?php
                                $userReviewText = trim((string)($review['review_text'] ?? $review['comment'] ?? $review['text'] ?? ''));
                                if ($userReviewText === '') {
                                    $userReviewText = '(No comment provided)';
                                }
                            ?>
                            <p><?php echo htmlspecialchars($userReviewText); ?></p>
                            <small style="color: var(--text-light);"><?php echo date('M d, Y', strtotime($review['created_at'])); ?></small>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Profile Settings Section -->
                <div id="profile" class="dashboard-section">
                    <h2 class="section-title"><i class="fas fa-user-cog"></i> Profile Settings</h2>
                    
                    <form method="POST" class="profile-section">
                        <div class="profile-field">
                            <label for="fullname">Full Name:</label>
                            <input type="text" id="fullname" name="fullname" value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" required>
                        </div>

                        <div class="profile-field">
                            <label for="email">Email:</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                        </div>

                        <div class="profile-field">
                            <label for="phone">Phone:</label>
                            <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                        </div>

                        <div class="profile-field">
                            <label for="address">Address:</label>
                            <input type="text" id="address" name="address" value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>">
                        </div>

                        <div class="profile-field">
                            <label for="password">New Password:</label>
                            <input type="password" id="password" name="password" placeholder="Leave blank to keep current password">
                        </div>

                        <div style="margin-top: 2rem;">
                            <button type="submit" class="btn-update">Update Profile</button>
                            <button type="button" class="btn-cancel" onclick="location.reload()">Cancel</button>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer" style="margin-top: 4rem;">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>Quick Links</h3>
                    <ul>
                        <li><a href="about.php">About Us</a></li>
                        <li><a href="contact.php">Contact</a></li>
                        <li><a href="#">Booking Policy</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h3>Contact Info</h3>
                    <p><?php echo RESORT_PHONE ?? '+63 (0) 123 456 789'; ?></p>
                    <p><?php echo RESORT_EMAIL ?? 'info@villasoledad.com'; ?></p>
                </div>
                <div class="footer-section">
                    <h3>Follow Us</h3>
                    <div class="social-icons">
                        <a href="#"><i class="fab fa-facebook"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2024 Villa Soledad Resort. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        // Sidebar menu switching
        document.querySelectorAll('.menu-link').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const section = link.dataset.section;
                
                // Hide all sections
                document.querySelectorAll('.dashboard-section').forEach(s => {
                    s.classList.remove('active');
                });
                
                // Remove active from all links
                document.querySelectorAll('.menu-link').forEach(l => {
                    l.classList.remove('active');
                });
                
                // Show selected section
                document.getElementById(section).classList.add('active');
                link.classList.add('active');
            });
        });
    </script>
</body>
</html>