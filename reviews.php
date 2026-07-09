<?php
require_once 'includes/header.php';

// Initialize review controller
$reviewController = new ReviewController();
$allReviews = $reviewController->getAllReviewsForSection(50);

// Set page title
$pageTitle = 'Guest Reviews - Villa Soledad Garden Resort';
?>

<main>
    <!-- Reviews Section -->
    <section class="reviews-section" style="padding: 80px 0; background: #f8fafc;">
        <div class="container">
            <div class="section-header" style="text-align: center; margin-bottom: 3rem;">
                <h1 style="font-size: 2.5rem; font-weight: 700; color: #1f2937; margin-bottom: 1rem;">
                    Guest Reviews
                </h1>
                <p style="font-size: 1.2rem; color: #6b7280; max-width: 600px; margin: 0 auto;">
                    See what our guests are saying about their experience at Villa Soledad Garden Resort
                </p>
            </div>

            <!-- Review Statistics -->
            <div class="review-stats" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 2rem; margin-bottom: 3rem;">
                <div class="stat-card" style="background: white; padding: 2rem; border-radius: 15px; text-align: center; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                    <div style="font-size: 3rem; font-weight: 700; color: #FF7A3D; margin-bottom: 0.5rem;">
                        <?php echo count($allReviews); ?>
                    </div>
                    <div style="color: #6b7280; font-weight: 500;">Total Reviews</div>
                </div>
                <div class="stat-card" style="background: white; padding: 2rem; border-radius: 15px; text-align: center; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                    <div style="font-size: 3rem; font-weight: 700; color: #FF7A3D; margin-bottom: 0.5rem;">
                        <?php 
                        $avgRating = 0;
                        if (!empty($allReviews)) {
                            $totalRating = array_sum(array_column($allReviews, 'rating'));
                            $avgRating = round($totalRating / count($allReviews), 1);
                        }
                        echo $avgRating;
                        ?>
                    </div>
                    <div style="color: #6b7280; font-weight: 500;">Average Rating</div>
                </div>
                <div class="stat-card" style="background: white; padding: 2rem; border-radius: 15px; text-align: center; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                    <div style="font-size: 3rem; font-weight: 700; color: #FF7A3D; margin-bottom: 0.5rem;">
                        4.8
                    </div>
                    <div style="color: #6b7280; font-weight: 500;">Guest Satisfaction</div>
                </div>
            </div>

            <!-- Reviews List -->
            <div class="reviews-list" style="display: grid; gap: 2rem;">
                <?php if (empty($allReviews)): ?>
                    <div class="empty-reviews" style="text-align: center; padding: 4rem 2rem; background: white; border-radius: 15px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                        <i class="fas fa-star" style="font-size: 4rem; color: #e5e7eb; margin-bottom: 1rem;"></i>
                        <h3 style="color: #6b7280; margin-bottom: 1rem;">No Reviews Yet</h3>
                        <p style="color: #9ca3af;">Be the first to share your experience!</p>
                        <?php if ($user->isLoggedIn()): ?>
                            <a href="index.php#reviews" class="btn-primary" style="display: inline-block; margin-top: 1rem; background: #FF7A3D; color: white; padding: 0.75rem 1.5rem; border-radius: 8px; text-decoration: none; font-weight: 600;">Write a Review</a>
                        <?php else: ?>
                            <a href="google-auth.php?action=login" class="btn-primary" style="display: inline-block; margin-top: 1rem; background: #FF7A3D; color: white; padding: 0.75rem 1.5rem; border-radius: 8px; text-decoration: none; font-weight: 600;">Login to Review</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($allReviews as $review): ?>
                        <div class="review-card" style="background: white; padding: 2rem; border-radius: 15px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); border-left: 4px solid #FF7A3D;">
                            <div class="review-header" style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem;">
                                <img src="<?php echo htmlspecialchars($review['user_avatar']); ?>" alt="<?php echo htmlspecialchars($review['user_display_name']); ?>" style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover; border: 2px solid #e5e7eb;">
                                <div class="reviewer-info" style="flex: 1;">
                                    <h4 style="margin: 0; color: #1f2937; font-weight: 600;"><?php echo htmlspecialchars($review['user_display_name']); ?></h4>
                                    <div style="display: flex; align-items: center; gap: 1rem; margin-top: 0.25rem;">
                                        <div class="stars" style="color: #FF7A3D;">
                                            <?php echo $review['stars_html']; ?>
                                        </div>
                                        <span style="color: #6b7280; font-size: 0.875rem;"><?php echo $review['created_date']; ?></span>
                                    </div>
                                </div>
                                <?php if (!empty($review['review_type_label']) && $review['review_type_label'] !== 'General Review'): ?>
                                    <span class="review-type-badge" style="background: #f3f4f6; color: #374151; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.75rem; font-weight: 500;">
                                        <?php echo htmlspecialchars($review['review_type_label']); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="review-content" style="margin-bottom: 1rem;">
                                <p style="color: #374151; line-height: 1.6; margin: 0;"><?php echo htmlspecialchars($review['review_text']); ?></p>
                            </div>
                            
                            <div class="review-footer" style="display: flex; justify-content: space-between; align-items: center; padding-top: 1rem; border-top: 1px solid #f3f4f6;">
                                <div class="rating-display" style="display: flex; align-items: center; gap: 0.5rem;">
                                    <span style="color: #6b7280; font-size: 0.875rem;">Rating:</span>
                                    <div style="background: #FF7A3D; color: white; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.875rem; font-weight: 600;">
                                        <?php echo $review['rating']; ?>/5
                                    </div>
                                </div>
                                <?php if ($user->isLoggedIn() && isset($_SESSION['user_id']) && $review['user_id'] == $_SESSION['user_id']): ?>
                                    <div class="review-actions" style="display: flex; gap: 0.5rem;">
                                        <a href="edit-review.php?id=<?php echo $review['id']; ?>" style="color: #6b7280; text-decoration: none; font-size: 0.875rem; padding: 0.25rem 0.5rem; border-radius: 4px; transition: all 0.2s;">Edit</a>
                                        <a href="controllers/ReviewController.php?action=delete&id=<?php echo $review['id']; ?>" style="color: #dc2626; text-decoration: none; font-size: 0.875rem; padding: 0.25rem 0.5rem; border-radius: 4px; transition: all 0.2s;" onclick="return confirm('Are you sure you want to delete this review?');">Delete</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Write Review CTA -->
            <div class="write-review-cta" style="text-align: center; margin-top: 3rem; padding: 3rem; background: white; border-radius: 15px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                <h3 style="color: #1f2937; margin-bottom: 1rem;">Share Your Experience</h3>
                <p style="color: #6b7280; margin-bottom: 2rem;">Your feedback helps us improve and helps other guests make informed decisions.</p>
                <?php if ($user->isLoggedIn()): ?>
                    <a href="index.php#reviews" class="btn-primary" style="display: inline-block; background: #FF7A3D; color: white; padding: 1rem 2rem; border-radius: 8px; text-decoration: none; font-weight: 600; transition: all 0.3s;">Write a Review</a>
                <?php else: ?>
                    <a href="google-auth.php?action=login" class="btn-primary" style="display: inline-block; background: #FF7A3D; color: white; padding: 1rem 2rem; border-radius: 8px; text-decoration: none; font-weight: 600; transition: all 0.3s;">Login to Write Review</a>
                <?php endif; ?>
            </div>
        </div>
    </section>
</main>

<style>
.reviews-section {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.review-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    transition: all 0.3s ease;
}

.review-type-badge {
    border: 1px solid #e5e7eb;
}

.review-actions a:hover {
    background: #f3f4f6;
}

.btn-primary:hover {
    background: #e66a1f !important;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(255, 122, 61, 0.3);
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    transition: all 0.3s ease;
}

@media (max-width: 768px) {
    .review-stats {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .review-header {
        flex-direction: column;
        text-align: center;
        gap: 0.75rem;
    }
    
    .review-footer {
        flex-direction: column;
        gap: 1rem;
        align-items: flex-start;
    }
}
</style>

<script>
// Add interactive star rating display
document.addEventListener('DOMContentLoaded', function() {
    const stars = document.querySelectorAll('.stars i');
    stars.forEach(star => {
        star.style.fontSize = '1rem';
        star.style.marginRight = '0.25rem';
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
