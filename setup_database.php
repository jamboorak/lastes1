<?php
// Database configuration
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'resort_db';

// Create connection
$conn = new mysqli($db_host, $db_user, $db_pass);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database if it doesn't exist
$sql = "CREATE DATABASE IF NOT EXISTS $db_name";
if ($conn->query($sql) === TRUE) {
    echo "Database created successfully or already exists<br>";
} else {
    echo "Error creating database: " . $conn->error . "<br>";
}

// Select the database
$conn->select_db($db_name);

// Create users table
$sql = "CREATE TABLE IF NOT EXISTS users (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    fullname VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20) NULL,
    password VARCHAR(255) NULL,
    google_id VARCHAR(255) NULL,
    facebook_id VARCHAR(255) NULL,
    avatar VARCHAR(500) NULL,
    email_verified TINYINT(1) DEFAULT 0,
    registration_method ENUM('email', 'google', 'facebook', 'unknown') DEFAULT 'email',
    role ENUM('user', 'admin') DEFAULT 'user',
    last_login DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    echo "Table 'users' created successfully or already exists<br>";
} else {
    echo "Error creating table 'users': " . $conn->error . "<br>";
}

// Create bookings table
$sql = "CREATE TABLE IF NOT EXISTS bookings (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    booking_type VARCHAR(50) NOT NULL,
    check_in DATE NOT NULL,
    check_out DATE NOT NULL,
    guests INT(11) NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    status VARCHAR(20) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
)";

if ($conn->query($sql) === TRUE) {
    echo "Table 'bookings' created successfully or already exists<br>";
} else {
    echo "Error creating table 'bookings': " . $conn->error . "<br>";
}

// Create reservations table for multi-item reservations
$sql = "CREATE TABLE IF NOT EXISTS reservations (
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
)";

if ($conn->query($sql) === TRUE) {
    echo "Table 'reservations' created successfully or already exists<br>";
} else {
    echo "Error creating table 'reservations': " . $conn->error . "<br>";
}

// Create reservation_items table for each booked room or cottage
$sql = "CREATE TABLE IF NOT EXISTS reservation_items (
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
)";

if ($conn->query($sql) === TRUE) {
    echo "Table 'reservation_items' created successfully or already exists<br>";
} else {
    echo "Error creating table 'reservation_items': " . $conn->error . "<br>";
}

// Create reviews table
$sql = "CREATE TABLE IF NOT EXISTS reviews (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    rating INT(11) NOT NULL,
    review_text TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
)";

if ($conn->query($sql) === TRUE) {
    echo "Table 'reviews' created successfully or already exists<br>";
} else {
    echo "Error creating table 'reviews': " . $conn->error . "<br>";
}

// Create cottages table
$sql = "CREATE TABLE IF NOT EXISTS cottages (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    capacity INT(11) NOT NULL,
    price_per_night DECIMAL(10,2) NOT NULL,
    image_url VARCHAR(255),
    available BOOLEAN DEFAULT TRUE,
    daily_slots INT(11) DEFAULT 1
)";

if ($conn->query($sql) === TRUE) {
    echo "Table 'cottages' created successfully or already exists<br>";
} else {
    echo "Error creating table 'cottages': " . $conn->error . "<br>";
}

// Create rooms table
$sql = "CREATE TABLE IF NOT EXISTS rooms (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    capacity INT(11) NOT NULL,
    price_per_night DECIMAL(10,2) NOT NULL,
    image_url VARCHAR(255),
    available BOOLEAN DEFAULT TRUE,
    daily_slots INT(11) DEFAULT 1
)";

if ($conn->query($sql) === TRUE) {
    echo "Table 'rooms' created successfully or already exists<br>";
} else {
    echo "Error creating table 'rooms': " . $conn->error . "<br>";
}

// Insert sample data for cottages
$sql = "INSERT IGNORE INTO cottages (name, description, capacity, price_per_night, image_url) VALUES
('Cottage A', 'Modern pavilion perfect for poolside gatherings', 10, 750.00, 'images/cottage a.png'),
('Cottage B', 'Traditional nipa hut for authentic experience', 14, 1000.00, 'images/kubo cottage.jpg'),
('Kubo Cottage', 'Rustic cottage with comfortable room amenities', 22, 1500.00, 'images/kubo with room cottage.jpg')";

if ($conn->query($sql) === TRUE) {
    echo "Sample cottages data inserted successfully<br>";
} else {
    echo "Error inserting sample cottages data: " . $conn->error . "<br>";
}

// Insert sample data for rooms
$sql = "INSERT IGNORE INTO rooms (name, description, capacity, price_per_night, image_url) VALUES
('Standard Room', 'Comfortable rooms perfect for couples with resort access for 2', 2, 2500.00, 'images/standard.jpg'),
('Deluxe Room', 'Spacious rooms with premium amenities and resort access for 2-4', 4, 2800.00, 'images/deluxe.jpg'),
('Family Room', 'Room for families with resort access for 4-6 and plenty of space', 6, 3500.00, 'images/family-room.svg'),
('Family Deluxe Room', 'Large family deluxe room with extra comfort and resort access for 6-8', 8, 5500.00, 'images/family-deluxe-room.svg')";

if ($conn->query($sql) === TRUE) {
    echo "Sample rooms data inserted successfully<br>";
} else {
    echo "Error inserting sample rooms data: " . $conn->error . "<br>";
}

// Create user_accounts table for admin/staff accounts and advanced account features
$sql = "CREATE TABLE IF NOT EXISTS user_accounts (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    fullname VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    phone VARCHAR(50) NULL,
    password VARCHAR(255) NULL,
    google_id VARCHAR(255) NULL,
    facebook_id VARCHAR(255) NULL,
    avatar VARCHAR(500) NULL,
    email_verified TINYINT(1) DEFAULT 0,
    phone_verified TINYINT(1) DEFAULT 0,
    account_status VARCHAR(32) DEFAULT 'pending',
    verification_token VARCHAR(255) NULL,
    verification_expires DATETIME NULL,
    registration_method VARCHAR(50) DEFAULT 'email',
    registration_ip VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    role VARCHAR(32) DEFAULT 'user',
    login_attempts INT DEFAULT 0,
    last_login_attempt DATETIME NULL,
    account_locked_until DATETIME NULL,
    last_login DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL
)";

if ($conn->query($sql) === TRUE) {
    echo "Table 'user_accounts' created successfully or already exists<br>";
} else {
    echo "Error creating table 'user_accounts': " . $conn->error . "<br>";
}

// Create login_attempts table
$sql = "CREATE TABLE IF NOT EXISTS login_attempts (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NULL,
    email VARCHAR(150) NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    login_method VARCHAR(50) DEFAULT 'email',
    attempt_status VARCHAR(32) DEFAULT 'failed',
    failure_reason VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    echo "Table 'login_attempts' created successfully or already exists<br>";
} else {
    echo "Error creating table 'login_attempts': " . $conn->error . "<br>";
}

// Create user_sessions table
$sql = "CREATE TABLE IF NOT EXISTS user_sessions (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    session_id VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    echo "Table 'user_sessions' created successfully or already exists<br>";
} else {
    echo "Error creating table 'user_sessions': " . $conn->error . "<br>";
}

// Create reservation_limits table for daily limits per item
$sql = "CREATE TABLE IF NOT EXISTS reservation_limits (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    item_type VARCHAR(50) NOT NULL,
    item_name VARCHAR(150) NOT NULL,
    daily_limit INT(11) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    echo "Table 'reservation_limits' created successfully or already exists<br>";
} else {
    echo "Error creating table 'reservation_limits': " . $conn->error . "<br>";
}

// Create otp_codes table for storing OTP verification codes
$sql = "CREATE TABLE IF NOT EXISTS otp_codes (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(150) NOT NULL,
    otp_code VARCHAR(6) NOT NULL,
    user_data TEXT NULL,
    expires_at DATETIME NOT NULL,
    is_used TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    echo "Table 'otp_codes' created successfully or already exists<br>";
} else {
    echo "Error creating table 'otp_codes': " . $conn->error . "<br>";
}

// Create admin account for admin login
$adminEmail = 'adminvillasoledad@gmail.com';
$adminPassword = 'admin123';
$adminHash = password_hash($adminPassword, PASSWORD_DEFAULT);

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$checkSql = "SELECT id FROM users WHERE email = ?";
$stmt = $conn->prepare($checkSql);
$stmt->bind_param('s', $adminEmail);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $insertSql = "INSERT INTO users (fullname, email, phone, password, role, email_verified, registration_method) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $insertStmt = $conn->prepare($insertSql);
    $fullname = 'Admin Villa Soledad';
    $phone = '0000000000';
    $role = 'admin';
    $emailVerified = 1;
    $registrationMethod = 'email';
    $insertStmt->bind_param('sssssis', $fullname, $adminEmail, $phone, $adminHash, $role, $emailVerified, $registrationMethod);
    $insertStmt->execute();
    echo "Admin account created successfully<br>";
} else {
    $updateSql = "UPDATE users SET fullname = ?, phone = ?, password = ?, role = ?, email_verified = ?, registration_method = ? WHERE email = ?";
    $updateStmt = $conn->prepare($updateSql);
    $fullname = 'Admin Villa Soledad';
    $phone = '0000000000';
    $role = 'admin';
    $emailVerified = 1;
    $registrationMethod = 'email';
    $updateStmt->bind_param('sssssis', $fullname, $phone, $adminHash, $role, $emailVerified, $registrationMethod, $adminEmail);
    $updateStmt->execute();
    echo "Admin account updated successfully<br>";
}

$conn->close();

echo "<br><strong>Database setup completed!</strong><br>";
echo "<a href='index.html'>Go to Resort Website</a>";
?>
