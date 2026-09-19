<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/RoomConfig.php';
require_once __DIR__ . '/../includes/header.php';

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

$db = new Database();
$conn = $db->getConnection();
syncRoomBrochureData($conn);
$publicRoomsSql = "SELECT * FROM rooms WHERE available = 1 ORDER BY id";
$publicRoomsResult = $conn->query($publicRoomsSql);
$publicRooms = $publicRoomsResult ? $publicRoomsResult->fetch_all(MYSQLI_ASSOC) : [];
?>

    <!-- Hero Section -->
    <section id="home" class="hero" style="background: linear-gradient(rgba(30, 58, 138, 0.7), rgba(30, 58, 138, 0.7)), url('<?php echo SITE_URL; ?>images/hero-bg.jpg') no-repeat center/cover; min-height: 3px; padding: 0; display: flex; align-items: center; justify-content: center;">
        <div class="hero-content" style="text-align: center; padding: 0; margin: 0;">
            <h1 style="font-size: 3rem; margin: 0; padding: 0; color: white;">Our Rooms</h1>
            <p style="font-size: 1.3rem; margin: 0; padding: 0; color: rgba(255,255,255,0.95);">Choose your perfect accommodation</p>
        </div>
    </section>

    <!-- Rooms Section -->
    <section id="rooms" class="facilities" style="background: var(--white); padding: 3rem 0 0;">
        <div class="container">
            <h2 style="text-align: center; color: var(--accent-orange); margin-bottom: 2rem; font-size: 2.2rem; font-weight: 700;">Rooms we offer</h2>

            <div class="room-slider-wrapper" style="perspective: 1400px;">
                <div class="room-slider" id="roomSlider" style="display: flex; align-items: center; justify-content: center; gap: 3rem; position: relative;">
                    <button onclick="rotateRooms(-1)" style="background: none; border: none; font-size: 2rem; color: var(--primary-blue); cursor: pointer; padding: 0; width: 50px; height: 50px; margin-right: 2.5rem; border-radius: 50%; background: var(--bg-light); transition: all 0.3s ease;" onmouseover="this.style.background='var(--accent-orange)'; this.style.color='white';" onmouseout="this.style.background='var(--bg-light)'; this.style.color='var(--primary-blue)';">
                        <i class="fas fa-chevron-left"></i>
                    </button>

                    <div class="slider-stage" style="width: 100%; max-width: 760px; height: 580px; position: relative; transform-style: preserve-3d; transition: transform 0.8s ease;">
                        <?php if (!empty($publicRooms)): ?>
                            <?php foreach ($publicRooms as $index => $room): ?>
                            <?php
                                $roomInclusionsHtml = renderRoomInclusionsHtml($room['name'] ?? '', true);
                                $roomDescription = $room['description'] ?? '';
                            ?>
                            <div class="room-card room-card-<?php echo $index; ?>" style="position: absolute; top: 0; left: 50%; width: 380px; height: 560px; transform-style: preserve-3d; transform-origin: center center; transition: transform 0.8s ease, opacity 0.8s ease;">
                                <div style="width: 100%; height: 100%; border-radius: 28px; overflow: hidden; box-shadow: 0 28px 60px rgba(15, 23, 42, 0.14); background: #fff; display: flex; flex-direction: column;">
                                    <img src="<?php echo htmlspecialchars(resolveImageUrl($room['image_url'] ?? '', SITE_URL . 'images/standard.jpg')); ?>" alt="<?php echo htmlspecialchars($room['name'] ?? 'Room'); ?>" style="width: 100%; height: 170px; object-fit: cover; display: block; flex-shrink: 0;">
                                    <div style="padding: 1.15rem 1.35rem 1.25rem; display: flex; flex-direction: column; flex: 1;">
                                        <h3 style="color: var(--primary-blue); font-size: 1.4rem; margin-bottom: 0.45rem; margin-top: 0;"><?php echo htmlspecialchars($room['name'] ?? 'Room'); ?></h3>
                                        <div style="margin-bottom: 0.5rem;">
                                            <?php if ($roomInclusionsHtml !== ''): ?>
                                                <?php echo $roomInclusionsHtml; ?>
                                            <?php else: ?>
                                                <p style="color: #475569; line-height: 1.6; margin: 0;"><?php echo htmlspecialchars($roomDescription); ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <div style="display: flex; align-items: center; justify-content: flex-start; gap: 1rem; margin-top: 0.35rem;">
                                            <span style="color: var(--accent-orange); font-weight: 700; font-size: 1.05rem;">₱<?php echo number_format((float)($room['price_per_night'] ?? 0), 2); ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="padding: 2rem; text-align: center; color: var(--text-light);">No rooms available yet.</div>
                        <?php endif; ?>
                    </div>

                    <button onclick="rotateRooms(1)" style="background: none; border: none; font-size: 2rem; color: var(--primary-blue); cursor: pointer; padding: 0; width: 50px; height: 50px; margin-left: 2.5rem; border-radius: 50%; background: var(--bg-light); transition: all 0.3s ease;" onmouseover="this.style.background='var(--accent-orange)'; this.style.color='white';" onmouseout="this.style.background='var(--bg-light)'; this.style.color='var(--primary-blue)';">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
        </div>
    </section>

    <!-- Modal Popup -->
    <div id="descriptionModalRoom" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 2000; align-items: center; justify-content: center;">
        <div style="background: white; padding: 2rem; border-radius: 10px; max-width: 440px; width: 90%; box-shadow: 0 10px 40px rgba(0,0,0,0.3);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h2 id="modalTitleRoom" style="color: var(--primary-blue); margin: 0;"></h2>
                <button onclick="closeModalRoom()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-light);">&times;</button>
            </div>
            <div id="modalPromoRoom" style="display:none; margin-bottom:0.75rem;"></div>
            <div id="modalDescriptionRoom" style="color: var(--text-dark); margin-bottom: 1rem; line-height: 1.6;"></div>
            <div id="modalPriceRoom" style="font-size: 1.5rem; color: var(--accent-orange); font-weight: 700; margin-bottom: 1.5rem;"></div>
            <button onclick="closeModalRoom()" style="width: 100%; padding: 0.8rem; background: var(--primary-blue); color: white; border: none; border-radius: 5px; font-weight: 600; cursor: pointer;">Close</button>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>Quick Links</h3>
                    <ul>
                        <li><a href="about.php">About Us</a></li>
                        <li><a href="contact.php">Contact</a></li>
                        <li><a href="#">Booking Policy</a></li>
                        <li><a href="#">Terms & Conditions</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h3>Contact Info</h3>
                    <p>Phone: <?php echo RESORT_PHONE; ?></p>
                    <p>Email: <?php echo RESORT_EMAIL; ?></p>
                    <p>Address: <?php echo RESORT_ADDRESS; ?></p>
                </div>
                <div class="footer-section">
                    <h3>Follow Us</h3>
                    <div class="social-icons">
                        <a href="#" title="Facebook"><i class="fab fa-facebook"></i></a>
                        <a href="#" title="Instagram"><i class="fab fa-instagram"></i></a>
                        <a href="#" title="Twitter"><i class="fab fa-twitter"></i></a>
                        <a href="#" title="YouTube"><i class="fab fa-youtube"></i></a>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2024 Villa Soledad Resort. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Scroll to Top Button -->
    <div class="scroll-top" id="scrollTop" onclick="scrollToTop()">
        <i class="fas fa-arrow-up"></i>
    </div>

    <script src="<?php echo SITE_URL; ?>js/script.js"></script>
    <script>
        let roomIndex = 0;
        const roomCards = document.querySelectorAll('.room-card');

        function updateRoomRotation() {
            const total = roomCards.length;
            const prevIndex = (roomIndex - 1 + total) % total;
            const nextIndex = (roomIndex + 1) % total;

            roomCards.forEach((card, index) => {
                let transform = 'translateX(-50%) rotateY(0deg) translateZ(120px) scale(1)';
                let opacity = 1;
                let zIndex = 3;

                if (index === prevIndex) {
                    transform = 'translateX(calc(-50% - 320px)) rotateY(30deg) translateZ(-20px) scale(0.78)';
                    opacity = 0.7;
                    zIndex = 2;
                } else if (index === nextIndex) {
                    transform = 'translateX(calc(-50% + 320px)) rotateY(-30deg) translateZ(-20px) scale(0.78)';
                    opacity = 0.7;
                    zIndex = 2;
                } else if (index !== roomIndex) {
                    transform = 'translateX(-50%) rotateY(0deg) translateZ(-140px) scale(0.72)';
                    opacity = 0.45;
                    zIndex = 1;
                }

                card.style.transform = transform;
                card.style.opacity = opacity;
                card.style.zIndex = zIndex;
            });
        }

        function rotateRooms(direction) {
            roomIndex = (roomIndex + direction + roomCards.length) % roomCards.length;
            updateRoomRotation();
        }

        function openModalRoom(button) {
            document.getElementById('modalTitleRoom').textContent = button.dataset.name || 'Room';
            document.getElementById('modalPriceRoom').textContent = button.dataset.price || '';
            document.getElementById('modalDescriptionRoom').textContent = button.dataset.description || 'No description available.';

            const promoEl = document.getElementById('modalPromoRoom');
            if (promoEl) {
                promoEl.style.display = 'none';
                promoEl.textContent = '';
            }

            document.getElementById('descriptionModalRoom').style.display = 'flex';
        }

        function closeModalRoom() {
            document.getElementById('descriptionModalRoom').style.display = 'none';
        }

        window.addEventListener('click', function(event) {
            const modal = document.getElementById('descriptionModalRoom');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });

        document.addEventListener('DOMContentLoaded', updateRoomRotation);

        // Login Modal Functions
        function openLoginModal() {
            document.getElementById('loginModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeLoginModal() {
            document.getElementById('loginModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Close login modal when clicking outside
        document.addEventListener('click', function(event) {
            const loginModal = document.getElementById('loginModal');
            if (loginModal && event.target == loginModal) {
                closeLoginModal();
            }
        });
    </script>

    <!-- Login Modal -->
    <div id="loginModal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); align-items: center; justify-content: center;">
        <div style="background: white; border-radius: 15px; padding: 2.5rem; width: 90%; max-width: 450px; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3); position: relative;">
            <!-- Close Button -->
            <button onclick="closeLoginModal()" style="position: absolute; top: 1.5rem; right: 1.5rem; background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #999;">
                <i class="fas fa-times"></i>
            </button>

            <h2 style="color: #1e3a8a; font-size: 2rem; margin-bottom: 1rem; font-weight: 700;">Log in</h2>
            <p style="color: #6b7280; font-size: 0.95rem; margin-bottom: 2rem; line-height: 1.4;">We'll sign you in, or create an account if you don't have one.</p>

            <button onclick="window.location.href='<?php echo SITE_URL; ?>google-auth.php?action=login'" style="width: 100%; padding: 0.95rem; background: #1E56DB; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 0.7rem; font-size: 1rem; margin-bottom: 1rem; transition: all 0.3s ease;">
                <i class="fab fa-google"></i> Sign in with Google
            </button>

            <div style="text-align: center; margin-bottom: 2rem; color: #9ca3af; font-size: 0.9rem;">OR</div>

            <p style="color: #9ca3af; font-size: 0.75rem; text-align: center; margin-top: 1.5rem; line-height: 1.4;">
                By using our service, you agree with our <a href="#" style="color: #6b7280; text-decoration: underline;">Terms of service</a>, <a href="#" style="color: #6b7280; text-decoration: underline;">CCPA Notice</a> and <a href="#" style="color: #6b7280; text-decoration: underline;">Privacy Notice</a> that details what personal data we collect and use to provide you with the best learning experience.
            </p>
        </div>
    </div>
</body>
</html>
