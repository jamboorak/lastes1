<?php
require_once __DIR__ . '/../../config/database.php';

$db = new Database();
$conn = $db->getConnection();

$sql = "CREATE TABLE IF NOT EXISTS property_gallery_images (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    property_type ENUM('room', 'cottage') NOT NULL,
    property_id INT(11) NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    sort_order INT(11) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_property_gallery (property_type, property_id)
)";

if (!$conn->query($sql)) {
    die('Unable to create property gallery table: ' . $conn->error . PHP_EOL);
}

echo "Property gallery table is ready." . PHP_EOL;
