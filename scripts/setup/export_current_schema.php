<?php
/**
 * Current Database Schema Export Script
 * 
 * This script exports the current database schema as it exists.
 * Useful for:
 * - Backup and documentation
 * - Recreating the database elsewhere
 * - Understanding current structure
 * - Version control of schema
 */

// Database configuration
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'resort_db';

// Create connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Current Database Schema Export</h2>";
echo "<p>Database: <strong>$db_name</strong></p>";
echo "<p>Export Date: <strong>" . date('Y-m-d H:i:s') . "</strong></p>";

// Get all tables
$tables = [];
$result = $conn->query("SHOW TABLES");
while ($row = $result->fetch_array()) {
    $tables[] = $row[0];
}

echo "<h3>Tables Found: " . count($tables) . "</h3>";
echo "<ul>";
foreach ($tables as $table) {
    echo "<li><strong>$table</strong></li>";
}
echo "</ul>";

// Export schema for each table
echo "<hr>";
echo "<h2>Detailed Schema</h2>";

foreach ($tables as $table) {
    echo "<h3>Table: $table</h3>";
    
    // Get CREATE TABLE statement
    $createResult = $conn->query("SHOW CREATE TABLE $table");
    $createRow = $createResult->fetch_array();
    $createSQL = $createRow[1];
    
    echo "<pre style='background: #f4f4f4; padding: 15px; border-radius: 5px; overflow-x: auto;'>";
    echo htmlspecialchars($createSQL);
    echo "</pre>";
    
    // Get table information
    echo "<h4>Table Information</h4>";
    
    // Row count
    $countResult = $conn->query("SELECT COUNT(*) as count FROM $table");
    $countRow = $countResult->fetch_assoc();
    echo "<p><strong>Row Count:</strong> " . number_format($countRow['count']) . "</p>";
    
    // Columns
    echo "<h4>Columns</h4>";
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    
    $columnsResult = $conn->query("DESCRIBE $table");
    while ($columnRow = $columnsResult->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($columnRow['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($columnRow['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($columnRow['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($columnRow['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($columnRow['Default'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($columnRow['Extra']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Indexes
    echo "<h4>Indexes</h4>";
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>Key_name</th><th>Column_name</th><th>Unique</th></tr>";
    
    $indexesResult = $conn->query("SHOW INDEX FROM $table");
    $shownIndexes = [];
    while ($indexRow = $indexesResult->fetch_assoc()) {
        $keyName = $indexRow['Key_name'];
        if (!in_array($keyName, $shownIndexes)) {
            $shownIndexes[] = $keyName;
            echo "<tr>";
            echo "<td>" . htmlspecialchars($keyName) . "</td>";
            echo "<td>" . htmlspecialchars($indexRow['Column_name']) . "</td>";
            echo "<td>" . ($indexRow['Non_unique'] == 0 ? 'Yes' : 'No') . "</td>";
            echo "</tr>";
        }
    }
    echo "</table>";
    
    // Foreign Keys
    echo "<h4>Foreign Keys</h4>";
    $fkResult = $conn->query("
        SELECT 
            CONSTRAINT_NAME,
            COLUMN_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = '$db_name'
        AND TABLE_NAME = '$table'
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ");
    
    if ($fkResult->num_rows > 0) {
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
        echo "<tr><th>Constraint</th><th>Column</th><th>References</th><th>Ref Column</th></tr>";
        while ($fkRow = $fkResult->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($fkRow['CONSTRAINT_NAME']) . "</td>";
            echo "<td>" . htmlspecialchars($fkRow['COLUMN_NAME']) . "</td>";
            echo "<td>" . htmlspecialchars($fkRow['REFERENCED_TABLE_NAME']) . "</td>";
            echo "<td>" . htmlspecialchars($fkRow['REFERENCED_COLUMN_NAME']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p><em>No foreign keys found</em></p>";
    }
    
    echo "<hr>";
}

// Generate SQL file for download
echo "<h2>Download SQL Schema</h2>";
echo "<p>Click below to download the complete schema as a SQL file:</p>";

$sqlContent = "-- Database Schema Export\n";
$sqlContent .= "-- Database: $db_name\n";
$sqlContent .= "-- Export Date: " . date('Y-m-d H:i:s') . "\n";
$sqlContent .= "-- Generated by export_current_schema.php\n\n";
$sqlContent .= "CREATE DATABASE IF NOT EXISTS `$db_name`;\n";
$sqlContent .= "USE `$db_name`;\n\n";

foreach ($tables as $table) {
    $createResult = $conn->query("SHOW CREATE TABLE $table");
    $createRow = $createResult->fetch_array();
    $createSQL = $createRow[1];
    
    $sqlContent .= "-- Table: $table\n";
    $sqlContent .= "DROP TABLE IF EXISTS `$table`;\n";
    $sqlContent .= $createSQL . ";\n\n";
}

// Save to file
$filename = "schema_export_" . date('Y-m-d_H-i-s') . ".sql";
file_put_contents($filename, $sqlContent);

echo "<a href='$filename' download style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Download SQL File</a>";
echo "<p>File: <strong>$filename</strong></p>";

$conn->close();
?>
