<?php
/**
 * Review Model
 */

require_once __DIR__ . '/../config/config.php';

class Review {
    private $db;
    
    public function __construct() {
        global $db;
        $this->db = $db;
    }
    
    /**
     * Create a new review
     */
    public function create($userId, $rating, $reviewText) {
        // Validate input
        if (empty($userId) || empty($rating) || empty($reviewText)) {
            return ['success' => false, 'message' => 'All fields are required'];
        }
        
        if ($rating < 1 || $rating > 5) {
            return ['success' => false, 'message' => 'Rating must be between 1 and 5'];
        }
        
        $wordCount = $this->countWords($reviewText);
        if ($wordCount > 30) {
            return ['success' => false, 'message' => 'Review must not exceed 30 words'];
        }
        
        if (strlen($reviewText) < 3) {
            return ['success' => false, 'message' => 'Review must be at least 3 characters'];
        }
        
        if (strlen($reviewText) > 1000) {
            return ['success' => false, 'message' => 'Review must not exceed 1000 characters'];
        }
        
        // Insert review
        $sql = "INSERT INTO reviews (user_id, rating, review_text) VALUES (?, ?, ?)";
        $reviewId = $this->db->insert($sql, [$userId, $rating, $reviewText]);
        
        if ($reviewId) {
            return ['success' => true, 'message' => 'Review submitted successfully', 'review_id' => $reviewId];
        } else {
            return ['success' => false, 'message' => 'Failed to submit review'];
        }
    }
    
    /**
     * Count words in review text
     */
    private function countWords($text) {
        return count(array_filter(preg_split('/\s+/', trim($text)), function($word) {
            return strlen($word) > 0;
        }));
    }

    /**
     * Get review by ID
     */
    public function getReviewById($reviewId) {
        $sql = "SELECT r.*, u.fullname, u.email 
                FROM reviews r 
                LEFT JOIN users u ON r.user_id = u.id 
                WHERE r.id = ?";
        return $this->db->getRow($sql, [$reviewId]);
    }
    
    /**
     * Get all reviews with pagination
     */
    public function getAllReviews($page = 1, $limit = ITEMS_PER_PAGE) {
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT r.*, u.fullname, u.email 
                FROM reviews r 
                LEFT JOIN users u ON r.user_id = u.id 
                ORDER BY r.created_at DESC 
                LIMIT ? OFFSET ?";
        return $this->db->getRows($sql, [$limit, $offset]);
    }
    
    /**
     * Get user's reviews
     */
    public function getUserReviews($userId, $page = 1, $limit = ITEMS_PER_PAGE) {
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT * FROM reviews 
                WHERE user_id = ? 
                ORDER BY created_at DESC 
                LIMIT ? OFFSET ?";
        return $this->db->getRows($sql, [$userId, $limit, $offset]);
    }
    
    /**
     * Get all reviews by a specific user
     */
    public function getAllUserReviews($userId) {
        $sql = "SELECT r.*, u.fullname, u.email 
                FROM reviews r 
                LEFT JOIN users u ON r.user_id = u.id 
                WHERE r.user_id = ? 
                ORDER BY r.created_at DESC";
        return $this->db->getRows($sql, [$userId]);
    }
    
    /**
     * Get recent reviews by all users with enhanced display
     */
    public function getRecentReviewsEnhanced($limit = 10) {
        $sql = "SELECT r.*, u.fullname, u.email, u.avatar 
                FROM reviews r 
                LEFT JOIN users u ON r.user_id = u.id 
                ORDER BY r.created_at DESC 
                LIMIT ?";
        $reviews = $this->db->getRows($sql, [$limit]);
        
        // Enhance each review with additional data
        foreach ($reviews as &$review) {
            $review['stars_html'] = $this->generateStars($review['rating']);
            $review['created_date'] = date('F j, Y', strtotime($review['created_at']));
            $review['user_avatar'] = $review['avatar'] ?? 'https://ui-avatars.com/api/?name=' . urlencode($review['fullname'] ?? 'User') . '&background=FF7A3D&color=fff&size=40';
            $review['user_display_name'] = $review['fullname'] ?? 'Anonymous';
        }
        
        return $reviews;
    }
    
    /**
     * Update a review
     */
    public function update($reviewId, $userId, $rating, $reviewText) {
        // Validate input
        if (empty($rating) || empty($reviewText)) {
            return ['success' => false, 'message' => 'All fields are required'];
        }
        
        if ($rating < 1 || $rating > 5) {
            return ['success' => false, 'message' => 'Rating must be between 1 and 5'];
        }
        
        $wordCount = $this->countWords($reviewText);
        if ($wordCount > 30) {
            return ['success' => false, 'message' => 'Review must not exceed 30 words'];
        }
        
        if (strlen($reviewText) < 10) {
            return ['success' => false, 'message' => 'Review must be at least 10 characters'];
        }
        
        if (strlen($reviewText) > 1000) {
            return ['success' => false, 'message' => 'Review must not exceed 1000 characters'];
        }
        
        // Check if review exists and belongs to user
        $existing = $this->getReviewById($reviewId);
        if (!$existing) {
            return ['success' => false, 'message' => 'Review not found'];
        }
        
        if ($existing['user_id'] != $userId) {
            return ['success' => false, 'message' => 'You can only edit your own reviews'];
        }
        
        // Update review
        $sql = "UPDATE reviews SET rating = ?, review_text = ? WHERE id = ?";
        $affected = $this->db->update($sql, [$rating, $reviewText, $reviewId]);
        
        if ($affected > 0) {
            return ['success' => true, 'message' => 'Review updated successfully'];
        } else {
            return ['success' => false, 'message' => 'No changes made'];
        }
    }
    
    /**
     * Delete a review
     */
    public function delete($reviewId, $userId = null) {
        $review = $this->getReviewById($reviewId);
        
        if (!$review) {
            return ['success' => false, 'message' => 'Review not found'];
        }
        
        // Check if user owns the review (if userId is provided)
        if ($userId && $review['user_id'] != $userId) {
            return ['success' => false, 'message' => 'You can only delete your own reviews'];
        }
        
        $sql = "DELETE FROM reviews WHERE id = ?";
        $affected = $this->db->delete($sql, [$reviewId]);
        
        if ($affected > 0) {
            return ['success' => true, 'message' => 'Review deleted successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to delete review'];
        }
    }
    
    /**
     * Get review statistics
     */
    public function getStatistics() {
        $stats = [];
        
        // Total reviews
        $sql = "SELECT COUNT(*) as total FROM reviews";
        $result = $this->db->getRow($sql);
        $stats['total_reviews'] = $result['total'];
        
        // Average rating
        $sql = "SELECT AVG(rating) as avg_rating FROM reviews";
        $result = $this->db->getRow($sql);
        $stats['average_rating'] = round($result['avg_rating'], 1);
        
        // Rating distribution
        $sql = "SELECT rating, COUNT(*) as count FROM reviews GROUP BY rating ORDER BY rating";
        $results = $this->db->getRows($sql);
        $stats['rating_distribution'] = [];
        foreach ($results as $row) {
            $stats['rating_distribution'][$row['rating']] = $row['count'];
        }
        
        // Recent reviews
        $sql = "SELECT r.*, u.fullname 
                FROM reviews r 
                LEFT JOIN users u ON r.user_id = u.id 
                ORDER BY r.created_at DESC 
                LIMIT 5";
        $stats['recent_reviews'] = $this->db->getRows($sql);
        
        return $stats;
    }
    
    /**
     * Get recent reviews for display
     */
    public function getRecentReviews($limit = 10) {
        $sql = "SELECT r.*, u.fullname 
                FROM reviews r 
                LEFT JOIN users u ON r.user_id = u.id 
                ORDER BY r.created_at DESC 
                LIMIT ?";
        return $this->db->getRows($sql, [$limit]);
    }
    
    /**
     * Get reviews with star rating display
     */
    public function getReviewsWithStars($page = 1, $limit = ITEMS_PER_PAGE) {
        $reviews = $this->getAllReviews($page, $limit);
        
        foreach ($reviews as &$review) {
            $review['stars_html'] = $this->generateStars($review['rating']);
            $review['created_date'] = date('F j, Y', strtotime($review['created_at']));
        }
        
        return $reviews;
    }
    
    /**
     * Generate star rating HTML
     */
    private function generateStars($rating) {
        $stars = '';
        for ($i = 1; $i <= 5; $i++) {
            if ($i <= $rating) {
                $stars .= '<i class="fas fa-star"></i>';
            } else {
                $stars .= '<i class="far fa-star"></i>';
            }
        }
        return $stars;
    }
    
    /**
     * Calculate time ago string
     */
    private function timeAgo($datetime) {
        $time = strtotime($datetime);
        $now = time();
        $diff = $now - $time;
        
        if ($diff < 60) {
            return 'Just now';
        } elseif ($diff < 3600) {
            return floor($diff / 60) . ' minutes ago';
        } elseif ($diff < 86400) {
            return floor($diff / 3600) . ' hours ago';
        } elseif ($diff < 2592000) {
            return floor($diff / 86400) . ' days ago';
        } else {
            return date('M j, Y', $time);
        }
    }
    
    /**
     * Get total reviews count
     */
    public function getTotalReviews() {
        $sql = "SELECT COUNT(*) as total FROM reviews";
        $result = $this->db->getRow($sql);
        return $result['total'];
    }
    
    /**
     * Get user's total reviews count
     */
    public function getUserTotalReviews($userId) {
        $sql = "SELECT COUNT(*) as total FROM reviews WHERE user_id = ?";
        $result = $this->db->getRow($sql, [$userId]);
        return $result['total'];
    }
}
