<?php
require_once 'includes/header.php';
require_once __DIR__ . '/config/RoomConfig.php';

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
$conn->query("CREATE TABLE IF NOT EXISTS cottages (id INT(11) AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL, description TEXT, capacity INT(11) NOT NULL, price_per_night DECIMAL(10,2) NOT NULL, image_url VARCHAR(255), available BOOLEAN DEFAULT TRUE)");
$conn->query("CREATE TABLE IF NOT EXISTS pools (id INT(11) AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL, description TEXT, capacity INT(11) NOT NULL, features TEXT, status VARCHAR(20) NOT NULL DEFAULT 'active', image_url VARCHAR(255), available BOOLEAN DEFAULT TRUE)");
syncRoomBrochureData($conn);
$publicRoomsSql = "SELECT * FROM rooms WHERE available = 1 AND archived = 0 ORDER BY id";
$publicRoomsResult = $conn->query($publicRoomsSql);
$publicRooms = $publicRoomsResult ? $publicRoomsResult->fetch_all(MYSQLI_ASSOC) : [];
$publicCottagesSql = "SELECT * FROM cottages WHERE available = 1 AND archived = 0 ORDER BY id";
$publicCottagesResult = $conn->query($publicCottagesSql);
$publicCottages = $publicCottagesResult ? $publicCottagesResult->fetch_all(MYSQLI_ASSOC) : [];
$publicPoolsSql = "SELECT name, status FROM pools WHERE status IN ('active', 'maintenance') AND archived = 0 ORDER BY id";
$publicPoolsResult = $conn->query($publicPoolsSql);
$publicPools = $publicPoolsResult ? $publicPoolsResult->fetch_all(MYSQLI_ASSOC) : [];
$poolStatusByName = [];
foreach ($publicPools as $publicPool) {
    $poolStatusByName[strtolower(trim($publicPool['name'] ?? ''))] = $publicPool['status'] ?? 'active';
}
?>

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

    <!-- Welcome + Contact Section -->
    <section id="welcome" class="section welcome-contact" style="background: linear-gradient(rgba(15, 23, 42, 0.72), rgba(15, 23, 42, 0.78)), url('images/villasoledadbg.png') center/cover no-repeat; padding: 5rem 0;">
        <div class="container">
            <div class="section-intro" style="text-align: center; max-width: 800px; margin: 0 auto 3rem; color: #ffffff;">
                <h2 style="color: #ffffff;">Welcome to Villa Soledad Resort</h2>
                <p style="color: #f8fafc;">Your perfect destination for relaxation and unforgettable memories. Whether you're planning a family vacation, a romantic getaway, or a group celebration, our resort offers the ideal setting for your special moments.</p>
                <p style="color: #f8fafc;">Enjoy our world-class amenities, comfortable accommodations, and exceptional service that will make your stay truly memorable.</p>
            </div>
            <div style="text-align: center; margin-bottom: 1.5rem; color: #ffffff;">
                <h2 style="color: #ffffff;">Get in Touch</h2>
                <p style="color: #e2e8f0; max-width: 560px; margin: 1rem auto 0;">Ready to book your stay? Contact us today!</p>
            </div>
            <div class="contact-cards" style="display: grid; grid-template-columns: repeat(3, minmax(300px, 360px)); gap: 2.5rem; width: min(100%, 1200px); justify-content: center; justify-items: center; align-items: start; margin: 0 auto;">
                <!-- Contact card -->
                <div class="contact-card" style="background: transparent; border-radius: 14px; box-shadow: none; padding: 1.6rem 1.25rem; min-height: 260px; max-width: 360px; margin: 0 auto; display: flex; flex-direction: column; align-items: center; text-align: center; gap: 0.75rem;">
                    <div style="width: 48px; height: 48px; display: grid; place-items: center; background: rgba(249, 115, 22, 0.3); border-radius: 12px;">
                        <i class="fas fa-phone" style="font-size: 1.25rem; color: var(--accent-orange);"></i>
                    </div>
                    <h3 style="margin: 0; font-size: 1.05rem; color: #ffffff; font-weight: 700;">Contact Us</h3>
                    <div style="width: 100%; display: flex; flex-direction: column; gap: 0.35rem; align-items: center;">
                        <p style="margin: 0; color: #e2e8f0; line-height: 1.6;"><strong style="color: #ffffff;">Phone No.:</strong> 09690204045</p>
                        <p style="margin: 0; color: #e2e8f0; line-height: 1.6;"><strong style="color: #ffffff;">Email:</strong> Villasoledadgardernresort@gmail.com</p>
                        <p style="margin: 0; color: #e2e8f0; line-height: 1.6;"><strong style="color: #ffffff;">Facebook:</strong> Villa Soledad Garden Resort</p>
                    </div>
                </div>
                <div class="contact-card" style="background: transparent; border-radius: 14px; box-shadow: none; padding: 1.6rem 1.25rem; min-height: 260px; max-width: 360px; margin: 0 auto; display: flex; flex-direction: column; align-items: center; text-align: center; gap: 0.75rem;">
                    <div style="width: 48px; height: 48px; display: grid; place-items: center; background: rgba(249, 115, 22, 0.3); border-radius: 12px;">
                        <i class="fas fa-clock" style="font-size: 1.25rem; color: var(--accent-orange);"></i>
                    </div>
                    <h3 style="margin: 0; font-size: 1.05rem; color: #ffffff; font-weight: 700;">Tour Hours</h3>
                    <div style="width: 100%; display: flex; flex-direction: column; gap: 0.35rem; align-items: center;">
                        <p style="margin: 0; color: #e2e8f0; line-height: 1.6;"><strong style="color: #ffffff;">Day Tour Hours</strong> 8:00 AM - 5:00 PM</p>
                        <p style="margin: 0; color: #e2e8f0; line-height: 1.6;"><strong style="color: #ffffff;">Night Tour Hours</strong> 8:00 PM - 5:00 AM</p>
                    </div>
                </div>
                <div class="contact-card" style="background: transparent; border-radius: 14px; box-shadow: none; padding: 1.6rem 1.25rem; min-height: 260px; max-width: 360px; margin: 0 auto; display: flex; flex-direction: column; align-items: center; text-align: center; gap: 0.75rem;">
                    <div style="width: 48px; height: 48px; display: grid; place-items: center; background: rgba(249, 115, 22, 0.3); border-radius: 12px;">
                        <i class="fas fa-peso-sign" style="font-size: 1.25rem; color: var(--accent-orange);"></i>
                    </div>
                    <h3 style="margin: 0; font-size: 1.05rem; color: #ffffff; font-weight: 700;">Entrance Fees</h3>
                    <div style="width: 100%; display: flex; flex-direction: column; gap: 0.35rem; align-items: center;">
                        <p style="margin: 0; color: #e2e8f0; line-height: 1.6;"><strong style="color: #ffffff;">Day Rate:</strong> ₱150/Adult, ₱80/Child</p>
                        <p style="margin: 0; color: #e2e8f0; line-height: 1.6;"><strong style="color: #ffffff;">Night Rate:</strong> ₱180/Adult, ₱100/Child</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Rooms Section -->
    <section id="rooms" class="facilities" style="background: linear-gradient(rgba(15, 23, 42, 0.72), rgba(15, 23, 42, 0.78)), url('images/villasoledadbg.png') center/cover no-repeat; padding: 3rem 0 2rem;">
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

    <!-- Cottages Section -->
    <section id="cottages" style="background: linear-gradient(rgba(15, 23, 42, 0.72), rgba(15, 23, 42, 0.78)), url('images/villasoledadbg.png') center/cover no-repeat; padding: 3rem 0 2rem;">
        <div class="container">
            <h2 style="text-align: center; color: var(--accent-orange); margin-bottom: 2rem; font-size: 2.2rem; font-weight: 700;">Cottages we offer</h2>
            <div class="cottage-slider-wrapper" style="perspective: 1400px;">
                <div class="cottage-slider" id="cottageSlider" style="display: flex; align-items: center; justify-content: center; gap: 3rem; position: relative;">
                    <button onclick="rotateCottages(-1)" style="background: none; border: none; font-size: 2rem; color: var(--primary-blue); cursor: pointer; padding: 0; width: 50px; height: 50px; margin-right: 2.5rem; border-radius: 50%; background: var(--bg-light); transition: all 0.3s ease;" onmouseover="this.style.background='var(--accent-orange)'; this.style.color='white';" onmouseout="this.style.background='var(--bg-light)'; this.style.color='var(--primary-blue)';">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <div class="slider-stage" style="width: 100%; max-width: 760px; height: 420px; position: relative; transform-style: preserve-3d; transition: transform 0.8s ease;">
                        <?php if (!empty($publicCottages)): ?>
                            <?php foreach ($publicCottages as $index => $cottage): ?>
                            <div class="cottage-card cottage-card-<?php echo $index; ?>" style="position: absolute; top: 0; left: 50%; width: 360px; height: 420px; transform-style: preserve-3d; transform-origin: center center; transition: transform 0.8s ease, opacity 0.8s ease;">
                                <div style="width: 100%; height: 100%; border-radius: 28px; overflow: hidden; box-shadow: 0 28px 60px rgba(15, 23, 42, 0.14); background: #fff;">
                                    <img src="<?php echo htmlspecialchars(!empty($cottage['image_url']) ? $cottage['image_url'] : 'images/cottage a.png'); ?>" alt="<?php echo htmlspecialchars($cottage['name'] ?? 'Cottage'); ?>" style="width: 100%; height: 220px; object-fit: cover; display: block;">
                                    <div style="padding: 1.5rem;">
                                        <h3 style="color: var(--primary-blue); font-size: 1.6rem; margin-bottom: 0.75rem;"><?php echo htmlspecialchars($cottage['name'] ?? 'Cottage'); ?></h3>
                                        <p style="color: #475569; line-height: 1.6; margin-bottom: 1rem;">Good for <?php echo (int)($cottage['capacity'] ?? 0); ?> pax • ₱<?php echo number_format((float)($cottage['price_per_night'] ?? 0), 2); ?></p>
                                        <div style="display: flex; align-items: center; justify-content: flex-start; gap: 1rem;">
                                            <span style="color: var(--accent-orange); font-weight: 700;">₱<?php echo number_format((float)($cottage['price_per_night'] ?? 0), 2); ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="padding: 2rem; text-align: center; color: var(--text-light);">No cottages available yet.</div>
                        <?php endif; ?>
                    </div>
                    <button onclick="rotateCottages(1)" style="background: none; border: none; font-size: 2rem; color: var(--primary-blue); cursor: pointer; padding: 0; width: 50px; height: 50px; margin-left: 2.5rem; border-radius: 50%; background: var(--bg-light); transition: all 0.3s ease;" onmouseover="this.style.background='var(--accent-orange)'; this.style.color='white';" onmouseout="this.style.background='var(--bg-light)'; this.style.color='var(--primary-blue)';">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
        </div>
    </section>

    <div id="descriptionModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 2000; align-items: center; justify-content: center;">
        <div style="background: white; padding: 2rem; border-radius: 10px; max-width: 400px; width: 90%; box-shadow: 0 10px 40px rgba(0,0,0,0.3);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h2 id="modalTitle" style="color: var(--primary-blue); margin: 0;"></h2>
                <button onclick="closeModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-light);">&times;</button>
            </div>
            <p id="modalDescription" style="color: var(--text-dark); margin-bottom: 1rem; line-height: 1.6;"></p>
            <div id="modalPrice" style="font-size: 1.5rem; color: var(--accent-orange); font-weight: 700; margin-bottom: 1.5rem;"></div>
            <button onclick="closeModal()" style="width: 100%; padding: 0.8rem; background: var(--primary-blue); color: white; border: none; border-radius: 5px; font-weight: 600; cursor: pointer;">Close</button>
        </div>
    </div>

    <!-- Pools Section -->
    <section id="pools" style="background: linear-gradient(rgba(15, 23, 42, 0.72), rgba(15, 23, 42, 0.78)), url('images/villasoledadbg.png') center/cover no-repeat; padding: 3rem 0 2rem; display: none;">
        <div class="container">
            <h2 style="text-align: center; color: var(--accent-orange); margin-bottom: 2rem; font-size: 2.2rem; font-weight: 700;">Pools we offer</h2>
            <div class="pool-slider-wrapper" style="perspective: 1400px;">
                <div class="pool-slider" id="poolSlider" style="display: flex; align-items: center; justify-content: center; gap: 3rem; position: relative;">
                    <button onclick="rotatePools(-1)" style="background: none; border: none; font-size: 2rem; color: var(--primary-blue); cursor: pointer; padding: 0; width: 50px; height: 50px; margin-right: 2.5rem; border-radius: 50%; background: var(--bg-light); transition: all 0.3s ease;" onmouseover="this.style.background='var(--accent-orange)'; this.style.color='white';" onmouseout="this.style.background='var(--bg-light)'; this.style.color='var(--primary-blue)';">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <div class="slider-stage" style="width: 100%; max-width: 760px; height: 420px; position: relative; transform-style: preserve-3d; transition: transform 0.8s ease;">
                        <div class="pool-card pool-card-0" style="position: absolute; top: 0; left: 50%; width: 360px; height: 420px; transform-style: preserve-3d; transform-origin: center center; transition: transform 0.8s ease, opacity 0.8s ease;">
                            <div style="width: 100%; height: 100%; border-radius: 28px; overflow: hidden; box-shadow: 0 28px 60px rgba(15, 23, 42, 0.14); background: #fff; position: relative;">
                                <img src="images/guest%20pool.png" alt="Guest Pool" style="width: 100%; height: 220px; object-fit: cover; display: block;" />
                                <?php if (($poolStatusByName['main pool'] ?? 'active') === 'maintenance'): ?>
                                    <div class="pool-maintenance-badge">Under Maintenance</div>
                                <?php endif; ?>
                                <div style="padding: 1.5rem;">
                                    <h3 style="color: var(--primary-blue); font-size: 1.6rem; margin-bottom: 0.75rem;">Main Pool</h3>
                                    <p style="color: #475569; line-height: 1.8; margin-bottom: 1rem;">A spacious guest pool ideal for relaxing swims and social time with family.</p>
                                    <div style="display: flex; align-items: center; justify-content: flex-start; gap: 1rem;">
                                        <span style="color: var(--accent-orange); font-weight: 700;">FREE for guests</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="pool-card pool-card-1" style="position: absolute; top: 0; left: 50%; width: 360px; height: 420px; transform-style: preserve-3d; transform-origin: center center; transition: transform 0.8s ease, opacity 0.8s ease;">
                            <div style="width: 100%; height: 100%; border-radius: 28px; overflow: hidden; box-shadow: 0 28px 60px rgba(15, 23, 42, 0.14); background: #fff; position: relative;">
                                <img src="images/kids%20pool.png" alt="Kids Pool" style="width: 100%; height: 220px; object-fit: cover; display: block;" />
                                <?php if (($poolStatusByName['kiddie pool'] ?? 'active') === 'maintenance'): ?>
                                    <div class="pool-maintenance-badge">Under Maintenance</div>
                                <?php endif; ?>
                                <div style="padding: 1.5rem;">
                                    <h3 style="color: var(--primary-blue); font-size: 1.6rem; margin-bottom: 0.75rem;">Kiddie Pool</h3>
                                    <p style="color: #475569; line-height: 1.8; margin-bottom: 1rem;">A safe, shallow pool designed for children and family fun.</p>
                                    <div style="display: flex; align-items: center; justify-content: flex-start; gap: 1rem;">
                                        <span style="color: var(--accent-orange); font-weight: 700;">FREE for guests</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="pool-card pool-card-2" style="position: absolute; top: 0; left: 50%; width: 360px; height: 420px; transform-style: preserve-3d; transform-origin: center center; transition: transform 0.8s ease, opacity 0.8s ease;">
                            <div style="width: 100%; height: 100%; border-radius: 28px; overflow: hidden; box-shadow: 0 28px 60px rgba(15, 23, 42, 0.14); background: #fff; position: relative;">
                                <img src="images/private%20pool.png" alt="Private Pool" style="width: 100%; height: 220px; object-fit: cover; display: block;" />
                                <?php if (($poolStatusByName['private pool'] ?? 'active') === 'maintenance'): ?>
                                    <div class="pool-maintenance-badge">Under Maintenance</div>
                                <?php endif; ?>
                                <div style="padding: 1.5rem;">
                                    <h3 style="color: var(--primary-blue); font-size: 1.6rem; margin-bottom: 0.75rem;">Private Pool</h3>
                                    <p style="color: #475569; line-height: 1.8; margin-bottom: 1rem;">An exclusive private pool for guests seeking privacy and tranquility.</p>
                                    <div style="display: flex; align-items: center; justify-content: flex-start; gap: 1rem;">
                                        <span style="color: var(--accent-orange); font-weight: 700;">Available for hotel guests</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <button onclick="rotatePools(1)" style="background: none; border: none; font-size: 2rem; color: var(--primary-blue); cursor: pointer; padding: 0; width: 50px; height: 50px; margin-left: 2.5rem; border-radius: 50%; background: var(--bg-light); transition: all 0.3s ease;" onmouseover="this.style.background='var(--accent-orange)'; this.style.color='white';" onmouseout="this.style.background='var(--bg-light)'; this.style.color='var(--primary-blue)';">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
        </div>
    </section>

    <div id="descriptionModalPool" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 2000; align-items: center; justify-content: center;">
        <div style="background: white; padding: 2rem; border-radius: 10px; max-width: 400px; width: 90%; box-shadow: 0 10px 40px rgba(0,0,0,0.3);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h2 id="modalTitlePool" style="color: var(--primary-blue); margin: 0;"></h2>
                <button onclick="closeModalPool()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-light);">&times;</button>
            </div>
            <p id="modalDescriptionPool" style="color: var(--text-dark); margin-bottom: 1rem; line-height: 1.6;"></p>
            <div id="modalPricePool" style="font-size: 1.5rem; color: var(--accent-orange); font-weight: 700; margin-bottom: 1.5rem;"></div>
            <button onclick="closeModalPool()" style="width: 100%; padding: 0.8rem; background: var(--primary-blue); color: white; border: none; border-radius: 5px; font-weight: 600; cursor: pointer;">Close</button>
        </div>
    </div>

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

        let cottageIndex = 0;
        const cottageCards = document.querySelectorAll('.cottage-card');

        function updateCottageRotation() {
            const total = cottageCards.length;
            const prevIndex = (cottageIndex - 1 + total) % total;
            const nextIndex = (cottageIndex + 1) % total;

            cottageCards.forEach((card, index) => {
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
                } else if (index !== cottageIndex) {
                    transform = 'translateX(-50%) rotateY(0deg) translateZ(-140px) scale(0.72)';
                    opacity = 0.45;
                    zIndex = 1;
                }

                card.style.transform = transform;
                card.style.opacity = opacity;
                card.style.zIndex = zIndex;
            });
        }

        function rotateCottages(direction) {
            cottageIndex = (cottageIndex + direction + cottageCards.length) % cottageCards.length;
            updateCottageRotation();
        }

        function openModal(title, description, price) {
            document.getElementById('modalTitle').textContent = title;
            document.getElementById('modalDescription').textContent = description;
            document.getElementById('modalPrice').textContent = price;
            document.getElementById('descriptionModal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('descriptionModal').style.display = 'none';
        }

        let poolIndex = 0;
        const poolCards = document.querySelectorAll('.pool-card');

        function updatePoolRotation() {
            const total = poolCards.length;
            const prevIndex = (poolIndex - 1 + total) % total;
            const nextIndex = (poolIndex + 1) % total;

            poolCards.forEach((card, index) => {
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
                } else if (index !== poolIndex) {
                    transform = 'translateX(-50%) rotateY(0deg) translateZ(-140px) scale(0.72)';
                    opacity = 0.45;
                    zIndex = 1;
                }

                card.style.transform = transform;
                card.style.opacity = opacity;
                card.style.zIndex = zIndex;
            });
        }

        function rotatePools(direction) {
            poolIndex = (poolIndex + direction + poolCards.length) % poolCards.length;
            updatePoolRotation();
        }

        function openModalPool(title, description, price) {
            document.getElementById('modalTitlePool').textContent = title;
            document.getElementById('modalDescriptionPool').textContent = description;
            document.getElementById('modalPricePool').textContent = price;
            document.getElementById('descriptionModalPool').style.display = 'flex';
        }

        function closeModalPool() {
            document.getElementById('descriptionModalPool').style.display = 'none';
        }

        document.addEventListener('DOMContentLoaded', function() {
            if (roomCards.length) updateRoomRotation();
            if (cottageCards.length) updateCottageRotation();
            if (poolCards.length) updatePoolRotation();
            updatePageSections();
        });

        window.addEventListener('click', function(event) {
            const roomModal = document.getElementById('descriptionModalRoom');
            const cottageModal = document.getElementById('descriptionModal');
            const poolModal = document.getElementById('descriptionModalPool');
            if (event.target === roomModal) closeModalRoom();
            if (event.target === cottageModal) closeModal();
            if (event.target === poolModal) closeModalPool();
        });

        function showSection(sectionId) {
            window.location.hash = sectionId;
        }

        function updatePageSections() {
            const homeSections = ['home', 'welcome', 'contact', 'location', 'reviews'];
            const sectionIds = [...homeSections, 'rooms', 'cottages', 'pools'];
            const target = window.location.hash.replace('#', '') || 'home';

            sectionIds.forEach(id => {
                const section = document.getElementById(id);
                if (!section) return;
                const isHomeTarget = homeSections.includes(target) && homeSections.includes(id);
                const isSpecialTarget = target === id;
                section.style.display = (isHomeTarget || isSpecialTarget) ? 'block' : 'none';
            });
        }

        window.addEventListener('hashchange', updatePageSections);
    </script>

    <!-- Location and Facade Section -->
    <section id="location" class="location" style="background: linear-gradient(rgba(15, 23, 42, 0.72), rgba(15, 23, 42, 0.78)), url('images/villasoledadbg.png') center/cover no-repeat;">
        <div class="container">
            <div class="location-facade-layout">
                <div class="location-facade-header">
                    <h2>LOCATION &amp; FACADE</h2>
                </div>
                <div class="location-facade-content">
                    <h3 class="location-heading">IT'S LOCATED AT</h3>
                    <div class="map-container">
                        <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3870.6601965650625!2d121.3124313108307!3d14.038145390542423!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x33bd4301035b4ef3%3A0xd22111becbb8a4!2sVilla%20Soledad%20Garden%20Resort!5e0!3m2!1sen!2sph!4v1777265586353!5m2!1sen!2sph" title="Villa Soledad Garden Resort location map" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                    </div>
                    <h3 class="facade-heading">FACADE</h3>
                    <div class="facade-grid">
                        <figure class="facade-card">
                            <img class="facade-image" src="image1.png" alt="Villa Soledad Garden Resort facade">
                        </figure>
                        <figure class="facade-card">
                            <img class="facade-image" src="image2.png" alt="Villa Soledad Garden Resort grounds">
                        </figure>
                    </div>
                </div>
            </div>
        </div>
    </section>

    
    <!-- Reviews Section -->
    <section id="reviews" class="section reviews">
        <div class="container">
            <div style="text-align: center; margin-bottom: 2rem;">
                <h2>Reviews</h2>
                <p>Share your experience or read what other guests loved about Villa Soledad.</p>
            </div>

            <!-- Review Form - First -->
            <div class="review-form" style="background: white; padding: 2rem; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 2rem;">
                <?php if (isset($user) && $user->isLoggedIn()): ?>
                    <form action="<?php echo SITE_URL; ?>controllers/ReviewController.php?action=create" method="POST" id="reviewForm">
                        <h3 style="color: var(--primary-blue); margin-bottom: 1rem;">Write a Review</h3>
                        <p style="color: #6b7280; font-size: 0.9rem; margin-bottom: 0.75rem;">Select a rating: <span style="color: red;">*</span></p>
                        <div class="star-rating" style="margin-bottom: 1rem;">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star" data-rating="<?php echo $i; ?>" style="font-size: 1.5rem; cursor: pointer; color: #d1d5db;"></i>
                            <?php endfor; ?>
                        </div>
                        <textarea name="review_text" id="reviewText" placeholder="Tell us about your stay at Villa Soledad" required style="width: 100%; min-height: 100px; padding: 1rem; border: 1px solid #e5e7eb; border-radius: 8px; font-family: inherit; resize: vertical;"></textarea>
                        <div class="review-note" style="margin:0.75rem 0 1rem; color:#475569; font-size:0.95rem;">Maximum 30 words. <span id="wordCount">0</span>/30 words used.</div>
                        <input type="hidden" name="rating" id="ratingInput" value="0">
                        <button type="submit" class="btn-primary">Submit Review</button>
                    </form>
                <?php else: ?>
                    <div class="review-form-box" style="text-align: center; padding: 2rem;">
                        <p><strong><a href="#" onclick="event.preventDefault(); openLoginModal()">Login</a></strong> to share your experience with the resort.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Submitted Reviews - Below the form -->
            <div style="text-align: center; margin-bottom: 1rem;">
                <h3 style="color: var(--primary-blue);">Guest Reviews</h3>
            </div>
            <div class="reviews-list" id="reviewsList">
                <?php if (!empty($recentReviews)): ?>
                    <?php foreach ($recentReviews as $review): ?>
                        <div class="review-item" style="background: white; padding: 1.5rem; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 1rem; border-left: 4px solid var(--accent-orange);">
                            <div class="review-header">
                                <span class="review-author" style="font-weight: 600; color: var(--primary-blue);"><?php echo htmlspecialchars($review['fullname'] ?? 'Guest'); ?></span>
                                <span class="review-rating" style="color: var(--accent-orange); margin-left: 0.5rem;"><?php echo $review['stars_html'] ?? ''; ?></span>
                            </div>
                            <p style="margin: 0.75rem 0; color: #374151; line-height: 1.6;"><?php echo htmlspecialchars($review['review_text']); ?></p>
                            <div class="review-date" style="font-size: 0.85rem; color: #6b7280;"><?php echo htmlspecialchars($review['created_date'] ?? ''); ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="review-item" style="text-align: center; padding: 2rem; background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                        <p>No reviews have been submitted yet. Be the first to share your stay!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <script>
        (function() {
            const reviewText = document.getElementById('reviewText');
            const wordCount = document.getElementById('wordCount');
            const reviewForm = document.getElementById('reviewForm');

            function countWords(value) {
                return value.trim().split(/\s+/).filter(word => word.length > 0).length;
            }

            if (reviewText && wordCount) {
                reviewText.addEventListener('input', function() {
                    const words = countWords(this.value);
                    wordCount.textContent = words;
                    if (words > 30) {
                        this.setCustomValidity('Please limit your review to 30 words.');
                    } else {
                        this.setCustomValidity('');
                    }
                });
            }

            if (reviewForm && reviewText) {
                reviewForm.addEventListener('submit', function(event) {
                    const words = countWords(reviewText.value);
                    const rating = parseInt(document.getElementById('ratingInput').value) || 0;
                    
                    if (words > 30) {
                        event.preventDefault();
                        alert('Please limit your review to 30 words.');
                        return;
                    }
                    
                    if (rating === 0) {
                        event.preventDefault();
                        alert('Please select a star rating before submitting your review.');
                        return;
                    }
                });
            }

            // Handle star rating clicks
            const stars = document.querySelectorAll('.star-rating i');
            const ratingInput = document.getElementById('ratingInput');

            if (stars.length > 0) {
                stars.forEach(star => {
                    star.addEventListener('click', function() {
                        const rating = this.getAttribute('data-rating');
                        ratingInput.value = rating;

                        // Update visual state
                        stars.forEach(s => {
                            s.style.color = '#d1d5db';
                        });
                        for (let i = 0; i < rating; i++) {
                            stars[i].style.color = '#ff7a3d';
                        }
                    });

                    star.addEventListener('mouseenter', function() {
                        const rating = this.getAttribute('data-rating');
                        stars.forEach(s => {
                            s.style.color = '#d1d5db';
                        });
                        for (let i = 0; i < rating; i++) {
                            stars[i].style.color = '#ff7a3d';
                        }
                    });
                });

                // Reset on mouse leave
                const starRatingDiv = document.querySelector('.star-rating');
                if (starRatingDiv) {
                    starRatingDiv.addEventListener('mouseleave', function() {
                        const rating = ratingInput.value || 0;
                        stars.forEach(s => {
                            s.style.color = '#d1d5db';
                        });
                        for (let i = 0; i < rating; i++) {
                            stars[i].style.color = '#ff7a3d';
                        }
                    });
                }
            }
        })();
    </script>

<?php
require_once 'includes/footer.php';
?>
