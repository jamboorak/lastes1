<?php
require_once __DIR__ . '/../../config/database.php';

$db = new Database();
$conn = $db->getConnection();

$columns = [
    'phone_verified' => 'ALTER TABLE users ADD COLUMN phone_verified TINYINT(1) NOT NULL DEFAULT 0 AFTER email_verified'
];

foreach ($columns as $column => $sql) {
    $result = $conn->query("SHOW COLUMNS FROM users LIKE '$column'");
    if ($result && $result->num_rows === 0) {
        if (!$conn->query($sql)) {
            throw new RuntimeException("Unable to add $column: " . $conn->error);
        }
        echo "Added $column to users.<br>";
    } else {
        echo "$column already exists.<br>";
    }
}

echo 'Firebase phone authentication fields are ready.';
