<?php
/**
 * Booking Model
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/RoomConfig.php';

class Booking {
    private $db;
    
    public function __construct() {
        global $db;
        $this->db = $db;
    }
    
    /**
     * Create a new booking
     */
    public function create($userId, $bookingType, $checkIn, $checkOut, $guests, $totalAmount) {
        // Validate input
        if (empty($userId) || empty($bookingType) || empty($checkIn) || empty($checkOut) || empty($guests)) {
            return ['success' => false, 'message' => 'All fields are required'];
        }
        
        if ($checkIn >= $checkOut) {
            return ['success' => false, 'message' => 'Check-out date must be after check-in date'];
        }
        
        if (strtotime($checkIn) < strtotime(date('Y-m-d'))) {
            return ['success' => false, 'message' => 'Check-in date cannot be in the past'];
        }
        
        if ($guests < 1) {
            return ['success' => false, 'message' => 'Number of guests must be at least 1'];
        }
        
        // Start transaction
        $this->db->beginTransaction();
        
        try {
            // Insert booking
            $sql = "INSERT INTO bookings (user_id, booking_type, check_in, check_out, guests, total_amount, status) 
                    VALUES (?, ?, ?, ?, ?, ?, 'pending')";
            $bookingId = $this->db->insert($sql, [$userId, $bookingType, $checkIn, $checkOut, $guests, $totalAmount]);
            
            if (!$bookingId) {
                throw new Exception('Failed to create booking');
            }
            
            // Update availability of the booked item
            $this->updateAvailability($bookingType, $checkIn, $checkOut, false);
            
            $this->db->commit();
            
            return ['success' => true, 'message' => 'Booking created successfully', 'booking_id' => $bookingId];
            
        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'message' => 'Booking failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get booking by ID
     */
    public function getBookingById($bookingId) {
        $sql = "SELECT b.*, u.fullname, u.email 
                FROM bookings b 
                LEFT JOIN users u ON b.user_id = u.id 
                WHERE b.id = ?";
        return $this->db->getRow($sql, [$bookingId]);
    }
    
    /**
     * Get user's bookings
     */
    public function getUserBookings($userId, $page = 1, $limit = ITEMS_PER_PAGE) {
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT * FROM bookings 
                WHERE user_id = ? 
                ORDER BY created_at DESC 
                LIMIT ? OFFSET ?";
        return $this->db->getRows($sql, [$userId, $limit, $offset]);
    }
    
    /**
     * Get all bookings (for admin)
     */
    public function getAllBookings($page = 1, $limit = ITEMS_PER_PAGE) {
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT b.*, u.fullname, u.email 
                FROM bookings b 
                LEFT JOIN users u ON b.user_id = u.id 
                ORDER BY b.created_at DESC 
                LIMIT ? OFFSET ?";
        return $this->db->getRows($sql, [$limit, $offset]);
    }
    
    /**
     * Update booking status
     */
    public function updateStatus($bookingId, $status) {
        $validStatuses = ['pending', 'confirmed', 'cancelled', 'completed'];
        
        if (!in_array($status, $validStatuses)) {
            return ['success' => false, 'message' => 'Invalid status'];
        }
        
        $sql = "UPDATE bookings SET status = ? WHERE id = ?";
        $affected = $this->db->update($sql, [$status, $bookingId]);
        
        if ($affected > 0) {
            // If cancelled, restore availability
            if ($status == 'cancelled') {
                $booking = $this->getBookingById($bookingId);
                if ($booking) {
                    $this->updateAvailability($booking['booking_type'], $booking['check_in'], $booking['check_out'], true);
                }
            }
            return ['success' => true, 'message' => 'Booking status updated successfully'];
        } else {
            return ['success' => false, 'message' => 'No changes made'];
        }
    }
    
    /**
     * Cancel booking
     */
    public function cancel($bookingId, $userId = null) {
        $booking = $this->getBookingById($bookingId);
        
        if (!$booking) {
            return ['success' => false, 'message' => 'Booking not found'];
        }
        
        // Check if user owns the booking (if userId is provided)
        if ($userId && $booking['user_id'] != $userId) {
            return ['success' => false, 'message' => 'You can only cancel your own bookings'];
        }
        
        if ($booking['status'] == 'cancelled') {
            return ['success' => false, 'message' => 'Booking is already cancelled'];
        }
        
        if ($booking['status'] == 'completed') {
            return ['success' => false, 'message' => 'Cannot cancel completed booking'];
        }
        
        return $this->updateStatus($bookingId, 'cancelled');
    }
    
    /**
     * Get booking statistics
     */
    public function getStatistics() {
        $stats = [];
        
        // Total bookings
        $sql = "SELECT COUNT(*) as total FROM bookings";
        $result = $this->db->getRow($sql);
        $stats['total_bookings'] = $result['total'];
        
        // Bookings by status
        $sql = "SELECT status, COUNT(*) as count FROM bookings GROUP BY status";
        $results = $this->db->getRows($sql);
        $stats['by_status'] = [];
        foreach ($results as $row) {
            $stats['by_status'][$row['status']] = $row['count'];
        }
        
        // Total revenue
        $sql = "SELECT SUM(total_amount) as revenue FROM bookings WHERE status = 'completed'";
        $result = $this->db->getRow($sql);
        $stats['total_revenue'] = $result['revenue'] ?: 0;
        
        // Monthly bookings
        $sql = "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as bookings 
                FROM bookings 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                ORDER BY month";
        $stats['monthly_bookings'] = $this->db->getRows($sql);
        
        return $stats;
    }
    
    /**
     * Check availability for a date range
     */
    public function checkAvailability($bookingType, $checkIn, $checkOut) {
        $sql = "SELECT COUNT(*) as conflicts 
                FROM bookings 
                WHERE booking_type = ? 
                AND status IN ('pending', 'confirmed') 
                AND ((check_in <= ? AND check_out > ?) OR (check_in < ? AND check_out >= ?))";
        
        $result = $this->db->getRow($sql, [$bookingType, $checkIn, $checkIn, $checkOut, $checkOut]);
        return $result['conflicts'] == 0;
    }
    
    /**
     * Update availability (helper method)
     */
    private function updateAvailability($bookingType, $checkIn, $checkOut, $makeAvailable) {
        // This would update the availability in cottages/rooms tables
        // For now, we'll assume availability is managed through booking status
        // In a real system, you'd have more complex availability management
    }
    
    /**
     * Get total bookings count
     */
    public function getTotalBookings() {
        $sql = "SELECT COUNT(*) as total FROM bookings";
        $result = $this->db->getRow($sql);
        return $result['total'];
    }
    
    /**
     * Get user's total bookings count
     */
    public function getUserTotalBookings($userId) {
        $sql = "SELECT COUNT(*) as total FROM bookings WHERE user_id = ?";
        $result = $this->db->getRow($sql, [$userId]);
        return $result['total'];
    }
    
    /**
     * Check daily availability for rooms and cottages
     */
    public function checkDailyAvailability($date, $roomName = null, $tourType = 'day') {
        $tourType = strtolower((string)$tourType) === 'night' ? 'night' : 'day';
        // Load independent limits for the selected tour.
        $itemLimits = [];
        $slotColumn = $tourType === 'night' ? 'night_slots' : 'day_slots';

        // Get rooms with their daily_slots
        try {
            $rooms = $this->db->getRows("SELECT name, {$slotColumn} AS tour_slots FROM rooms WHERE archived = 0");
            foreach ($rooms as $room) {
                $itemLimits[$room['name']] = (int)($room['tour_slots'] ?? 1);
            }
        } catch (Exception $e) {
            // Query failed, use fallback
        }

        // Get cottages with their daily_slots
        try {
            $cottages = $this->db->getRows("SELECT name, {$slotColumn} AS tour_slots FROM cottages WHERE archived = 0");
            foreach ($cottages as $cottage) {
                $itemLimits[$cottage['name']] = (int)($cottage['tour_slots'] ?? 1);
            }
        } catch (Exception $e) {
            // Query failed, use fallback
        }

        // Fallback to centralized config if no data found
        if (empty($itemLimits)) {
            $itemLimits = getAllReservationLimits();
        }

        $itemNames = array_keys($itemLimits);
        $placeholders = implode(',', array_fill(0, count($itemNames), '?'));

        $sql = "SELECT ri.item_name, COUNT(*) as booked_count
                FROM reservation_items ri
                JOIN reservations r ON ri.reservation_id = r.id
                WHERE r.status IN ('pending', 'approved')
                                    AND COALESCE(r.tour_type, 'day') = ?
                  AND (
                        (r.check_in = r.check_out AND ? = r.check_in)
                     OR (r.check_in <> r.check_out AND ? >= r.check_in AND ? < r.check_out)
                  )
                  AND ri.item_name IN ($placeholders)
                GROUP BY ri.item_name";

        $params = array_merge([$tourType, $date, $date, $date], $itemNames);
        $results = $this->db->getRows($sql, $params);

        $availability = [];
        foreach ($itemLimits as $itemName => $limit) {
            $booked = 0;
            foreach ($results as $result) {
                if ($result['item_name'] === $itemName) {
                    $booked = (int) $result['booked_count'];
                    break;
                }
            }
            $availability[$itemName] = [
                'limit' => $limit,
                'booked' => $booked,
                'available' => max(0, $limit - $booked)
            ];
        }

        if ($roomName !== null) {
            return $availability[$roomName] ?? ['limit' => 0, 'booked' => 0, 'available' => 0];
        }

        return $availability;
    }

    /**
     * Check if specific room is available on given date
     */
    public function isRoomAvailable($roomName, $date) {
        $availability = $this->checkDailyAvailability($date, $roomName);
        return isset($availability['available']) && $availability['available'] > 0;
    }
    
    /**
     * Get available rooms for a specific date
     */
    public function getAvailableRooms($date) {
        $availability = $this->checkDailyAvailability($date);
        $availableRooms = [];
        
        foreach ($availability as $roomName => $info) {
            if ($info['available'] > 0) {
                $availableRooms[] = $roomName;
            }
        }
        
        return $availableRooms;
    }
}
