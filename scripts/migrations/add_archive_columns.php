<?php
require_once __DIR__ . '/../../config/database.php';

$database = new Database();
$conn = $database->getConnection();

foreach (['rooms', 'cottages', 'pools', 'foods'] as $table) {
    $result = $conn->query("SHOW COLUMNS FROM `{$table}` LIKE 'archived'");
    if ($result && $result->num_rows === 0) {
        $conn->query("ALTER TABLE `{$table}` ADD COLUMN archived TINYINT(1) NOT NULL DEFAULT 0");
        echo "Added archived column to {$table}.<br>";
    } else {
        echo "{$table} already supports archiving.<br>";
    }
}

echo 'Archive migration completed.';
