<?php
/**
 * Booking Controller
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../models/Booking.php';
require_once __DIR__ . '/../models/User.php';

class BookingController {
    private $booking;
    private $user;
    
    public function __construct() {
        $this->booking = new Booking();
        $this->user = new User();
    }
    
    /**
     * Require authentication
     */
    public function requireAuth() {
        if (!$this->user->isLoggedIn()) {
            $_SESSION['error'] = 'Please login to make a booking';
            header("Location: ../google-auth.php?action=login");
            exit();
        }
    }
    
    /**
     * Create a new booking
     */
    public function create() {
        $this->requireAuth();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('../booking.php');
            return;
        }
        
        $userId = $_SESSION['user_id'];
        $bookingType = trim($_POST['booking_type'] ?? '');
        $checkIn = trim($_POST['check_in'] ?? '');
        $checkOut = trim($_POST['check_out'] ?? '');
        $guests = intval($_POST['guests'] ?? 0);
        
        // Calculate total amount based on booking type and duration
        $totalAmount = $this->calculateTotalAmount($bookingType, $checkIn, $checkOut);
        
        $result = $this->booking->create($userId, $bookingType, $checkIn, $checkOut, $guests, $totalAmount);
        
        if ($result['success']) {
            $_SESSION['success'] = $result['message'];
            $this->redirect('../my-bookings.php');
        } else {
            $_SESSION['error'] = $result['message'];
            $_SESSION['form_data'] = $_POST;
            $this->redirect('../booking.php');
        }
    }
    
    /**
     * Get user's bookings
     */
    public function getUserBookings($page = 1) {
        $this->requireAuth();
        
        $userId = $_SESSION['user_id'];
        return $this->booking->getUserBookings($userId, $page);
    }
    
    /**
     * Get booking details
     */
    public function getBooking($bookingId) {
        $this->requireAuth();
        
        $booking = $this->booking->getBookingById($bookingId);
        
        // Check if user owns this booking
        if ($booking && $booking['user_id'] != $_SESSION['user_id']) {
            return null;
        }
        
        return $booking;
    }
    
    /**
     * Cancel booking
     */
    public function cancel($bookingId) {
        $this->requireAuth();
        
        $userId = $_SESSION['user_id'];
        $result = $this->booking->cancel($bookingId, $userId);
        
        if ($result['success']) {
            $_SESSION['success'] = $result['message'];
        } else {
            $_SESSION['error'] = $result['message'];
        }
        
        $this->redirect('../my-bookings.php');
    }
    
    /**
     * Get all bookings (admin only)
     */
    public function getAllBookings($page = 1) {
        $this->requireAdmin();
        
        return $this->booking->getAllBookings($page);
    }
    
    /**
     * Update booking status (admin only)
     */
    public function updateStatus($bookingId, $status) {
        $this->requireAdmin();
        
        $result = $this->booking->updateStatus($bookingId, $status);
        
        if ($result['success']) {
            $_SESSION['success'] = $result['message'];
        } else {
            $_SESSION['error'] = $result['message'];
        }
        
        $this->redirect('../admin/bookings.php');
    }
    
    /**
     * Get booking statistics (admin only)
     */
    public function getStatistics() {
        $this->requireAdmin();
        
        return $this->booking->getStatistics();
    }
    
    /**
     * Check availability
     */
    public function checkAvailability($bookingType, $checkIn, $checkOut) {
        return $this->booking->checkAvailability($bookingType, $checkIn, $checkOut);
    }
    
    /**
     * Check daily availability for all rooms
     */
    public function checkDailyAvailability($date) {
        return $this->booking->checkDailyAvailability($date);
    }
    
    /**
     * Check if specific room is available
     */
    public function isRoomAvailable($roomName, $date) {
        return $this->booking->isRoomAvailable($roomName, $date);
    }
    
    /**
     * Get available rooms for date
     */
    public function getAvailableRooms($date) {
        return $this->booking->getAvailableRooms($date);
    }
    
    /**
     * Calculate total amount
     */
    private function calculateTotalAmount($bookingType, $checkIn, $checkOut) {
        $rates = [
            'cottage' => 1500,
            'room' => 1200,
            'pool' => 2000
        ];
        
        $rate = $rates[$bookingType] ?? 1000;
        
        // Calculate nights
        $checkInDate = new DateTime($checkIn);
        $checkOutDate = new DateTime($checkOut);
        $interval = $checkInDate->diff($checkOutDate);
        $nights = $interval->days;
        
        return $rate * $nights;
    }
    
    /**
     * Require admin authentication
     */
    private function requireAdmin() {
        $this->requireAuth();
        
        // In a real system, you'd check if user has admin role
        // For now, we'll assume user ID 1 is admin
        if ($_SESSION['user_id'] != 1) {
            $_SESSION['error'] = 'Access denied. Admin privileges required.';
            header("Location: ../index.php");
            exit();
        }
    }
    
    /**
     * Redirect to a page
     */
    private function redirect($page) {
        header("Location: $page");
        exit();
    }
}

// Handle route actions
if (isset($_GET['action'])) {
    $controller = new BookingController();
    
    switch ($_GET['action']) {
        case 'create':
            $controller->create();
            break;
        case 'cancel':
            $bookingId = $_GET['id'] ?? 0;
            $controller->cancel($bookingId);
            break;
        case 'update_status':
            $bookingId = $_GET['id'] ?? 0;
            $status = $_GET['status'] ?? 'pending';
            $controller->updateStatus($bookingId, $status);
            break;
        default:
            header("Location: ../index.php");
            exit();
    }
}
