<?php
/**
 * Add Maintenance Fees Table
 * This script creates a table to track maintenance and repair fees for resort facilities
 */

require_once __DIR__ . '/../../config/database.php';

$db = new Database();
$conn = $db->getConnection();

// Create maintenance_fees table
$sql = "CREATE TABLE IF NOT EXISTS maintenance_fees (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    fee_type VARCHAR(50) NOT NULL COMMENT 'Type of fee: maintenance, repair, upgrade',
    facility_type VARCHAR(50) NOT NULL COMMENT 'Type of facility: pool, cottage, room, general',
    facility_name VARCHAR(150) NOT NULL COMMENT 'Name of specific facility',
    amount DECIMAL(10,2) NOT NULL COMMENT 'Fee amount',
    description TEXT NULL COMMENT 'Description of the fee',
    date_incurred DATE NOT NULL COMMENT 'Date when fee was incurred',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    echo "✓ Table 'maintenance_fees' created successfully or already exists<br>";
} else {
    echo "✗ Error creating table 'maintenance_fees': " . $conn->error . "<br>";
}

// Create operating_expenses table for broader expense tracking
$sql = "CREATE TABLE IF NOT EXISTS operating_expenses (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    expense_type VARCHAR(50) NOT NULL COMMENT 'Type of expense: utilities, salaries, supplies, marketing, other',
    amount DECIMAL(10,2) NOT NULL COMMENT 'Expense amount',
    description TEXT NULL COMMENT 'Description of the expense',
    date_incurred DATE NOT NULL COMMENT 'Date when expense was incurred',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    echo "✓ Table 'operating_expenses' created successfully or already exists<br>";
} else {
    echo "✗ Error creating table 'operating_expenses': " . $conn->error . "<br>";
}

// Create cost_of_goods_sold table for COGS tracking
$sql = "CREATE TABLE IF NOT EXISTS cost_of_goods_sold (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    item_type VARCHAR(50) NOT NULL COMMENT 'Type: food, supplies, materials',
    item_name VARCHAR(150) NOT NULL COMMENT 'Name of item',
    quantity DECIMAL(10,2) NOT NULL COMMENT 'Quantity purchased/used',
    unit_cost DECIMAL(10,2) NOT NULL COMMENT 'Cost per unit',
    total_cost DECIMAL(10,2) NOT NULL COMMENT 'Total cost (quantity * unit_cost)',
    date_incurred DATE NOT NULL COMMENT 'Date when cost was incurred',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    echo "✓ Table 'cost_of_goods_sold' created successfully or already exists<br>";
} else {
    echo "✗ Error creating table 'cost_of_goods_sold': " . $conn->error . "<br>";
}

echo "<h3>Financial Tables Setup Complete!</h3>";
echo "<p><a href='../../admin/dashboard.php?section=reports'>Go to Reports Section</a></p>";

$conn->close();
?>
