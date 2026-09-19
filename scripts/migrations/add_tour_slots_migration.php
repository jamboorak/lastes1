<?php
require_once __DIR__ . '/../../config/database.php';

$database = new Database();
$conn = $database->getConnection();

function addTourSlotColumn(mysqli $conn, string $table, string $column): void {
    $check = $conn->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
    if ($check && $check->num_rows === 0) {
        $conn->query("ALTER TABLE `{$table}` ADD COLUMN `{$column}` INT(11) NOT NULL DEFAULT 1 AFTER daily_slots");
        echo "Added {$column} to {$table}.<br>";
    }
}

foreach (['rooms', 'cottages'] as $table) {
    addTourSlotColumn($conn, $table, 'day_slots');
    addTourSlotColumn($conn, $table, 'night_slots');
    $conn->query("UPDATE `{$table}` SET day_slots = daily_slots, night_slots = daily_slots WHERE day_slots = 1 AND night_slots = 1");
}

echo 'Tour slot migration completed. Update day_slots and night_slots independently in the database or admin tools.';