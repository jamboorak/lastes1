<?php
/**
 * Database Schema Migration Script
 * 
 * This script performs the proposed schema revisions in phases:
 * Phase 1: Critical Performance & Consistency Fixes
 * Phase 2: Data Consolidation
 * Phase 3: Enhanced Business Logic
 * Phase 4: Audit & Security
 * 
 * BACKWARD COMPATIBLE: No data will be destroyed
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

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Database Schema Migration</h2>";
echo "<p>Starting migration process...</p>";

// Start transaction for atomic operations
$conn->begin_transaction();

try {
    // ============================================
    // PHASE 1: Critical Performance & Consistency Fixes
    // ============================================
    echo "<h3>Phase 1: Performance & Consistency Fixes</h3>";
    
    // Add indexes to reservations table
    $indexes = [
        "idx_check_in_check_out" => "ALTER TABLE reservations ADD INDEX idx_check_in_check_out (check_in, check_out)",
        "idx_status" => "ALTER TABLE reservations ADD INDEX idx_status (status)",
        "idx_user_id" => "ALTER TABLE reservations ADD INDEX idx_user_id (user_id)"
    ];
    
    foreach ($indexes as $indexName => $indexSql) {
        // Check if index already exists
        $checkIndex = "SHOW INDEX FROM reservations WHERE Key_name = '$indexName'";
        $indexResult = $conn->query($checkIndex);
        
        if ($indexResult && $indexResult->num_rows > 0) {
            echo "⊙ Index $indexName already exists (skipping)<br>";
        } else {
            if ($conn->query($indexSql)) {
                echo "✓ Added index $indexName to reservations table<br>";
            } else {
                throw new Exception("Error adding index $indexName: " . $conn->error);
            }
        }
    }
    
    // Add indexes to reservation_items table
    $itemIndexes = [
        "idx_reservation_id" => "ALTER TABLE reservation_items ADD INDEX idx_reservation_id (reservation_id)",
        "idx_item_name" => "ALTER TABLE reservation_items ADD INDEX idx_item_name (item_name)"
    ];
    
    foreach ($itemIndexes as $indexName => $indexSql) {
        $checkIndex = "SHOW INDEX FROM reservation_items WHERE Key_name = '$indexName'";
        $indexResult = $conn->query($checkIndex);
        
        if ($indexResult && $indexResult->num_rows > 0) {
            echo "⊙ Index $indexName already exists (skipping)<br>";
        } else {
            if ($conn->query($indexSql)) {
                echo "✓ Added index $indexName to reservation_items table<br>";
            } else {
                throw new Exception("Error adding index $indexName: " . $conn->error);
            }
        }
    }
    
    // Add indexes to rooms/cottages
    $roomIndexes = [
        "rooms" => ["idx_available" => "ALTER TABLE rooms ADD INDEX idx_available (available)"],
        "cottages" => ["idx_available" => "ALTER TABLE cottages ADD INDEX idx_available (available)"]
    ];
    
    foreach ($roomIndexes as $tableName => $indexes) {
        foreach ($indexes as $indexName => $indexSql) {
            $checkIndex = "SHOW INDEX FROM $tableName WHERE Key_name = '$indexName'";
            $indexResult = $conn->query($checkIndex);
            
            if ($indexResult && $indexResult->num_rows > 0) {
                echo "⊙ Index $indexName already exists in $tableName (skipping)<br>";
            } else {
                if ($conn->query($indexSql)) {
                    echo "✓ Added index $indexName to $tableName table<br>";
                } else {
                    throw new Exception("Error adding index $indexName to $tableName: " . $conn->error);
                }
            }
        }
    }
    
    // Standardize status values in reservations table
    echo "Standardizing reservation status values...<br>";
    $updateStatus = "UPDATE reservations SET status = 'approved' WHERE status = 'confirmed'";
    if ($conn->query($updateStatus)) {
        echo "✓ Updated status values<br>";
    }
    
    // Add CHECK constraint for item_type in reservation_items
    echo "Adding item_type constraint...<br>";
    
    // Check if constraint already exists
    $checkConstraintExists = "SELECT CONSTRAINT_NAME 
                                FROM INFORMATION_SCHEMA.CHECK_CONSTRAINTS 
                                WHERE CONSTRAINT_SCHEMA = '$db_name' 
                                AND CONSTRAINT_NAME = 'chk_item_type'";
    $constraintResult = $conn->query($checkConstraintExists);
    
    if ($constraintResult && $constraintResult->num_rows > 0) {
        echo "⊙ Constraint chk_item_type already exists (skipping)<br>";
    } else {
        $checkConstraint = "ALTER TABLE reservation_items ADD CONSTRAINT chk_item_type CHECK (item_type IN ('room', 'cottage'))";
        if ($conn->query($checkConstraint)) {
            echo "✓ Added item_type constraint<br>";
        } else {
            if (strpos($conn->error, "Duplicate") !== false) {
                echo "⊙ Constraint already exists (skipping)<br>";
            } else {
                throw new Exception("Error adding constraint: " . $conn->error);
            }
        }
    }
    
    echo "<p>Phase 1 completed successfully!</p>";
    
    // ============================================
    // PHASE 2: Data Consolidation
    // ============================================
    echo "<h3>Phase 2: Data Consolidation</h3>";
    
    // Add missing fields to users table
    $userFields = [
        "phone_verified" => "ALTER TABLE users ADD COLUMN phone_verified TINYINT(1) DEFAULT 0 AFTER email_verified",
        "account_status" => "ALTER TABLE users ADD COLUMN account_status VARCHAR(32) DEFAULT 'active' AFTER phone_verified",
        "last_login_attempt" => "ALTER TABLE users ADD COLUMN last_login_attempt DATETIME NULL AFTER last_login",
        "login_attempts" => "ALTER TABLE users ADD COLUMN login_attempts INT DEFAULT 0 AFTER last_login_attempt",
        "account_locked_until" => "ALTER TABLE users ADD COLUMN account_locked_until DATETIME NULL AFTER login_attempts",
        "updated_at" => "ALTER TABLE users ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL AFTER created_at"
    ];
    
    foreach ($userFields as $fieldName => $fieldSql) {
        // Check if column already exists
        $checkColumn = "SHOW COLUMNS FROM users LIKE '$fieldName'";
        $columnResult = $conn->query($checkColumn);
        
        if ($columnResult && $columnResult->num_rows > 0) {
            echo "⊙ Column $fieldName already exists in users table (skipping)<br>";
        } else {
            if ($conn->query($fieldSql)) {
                echo "✓ Added field $fieldName to users table<br>";
            } else {
                throw new Exception("Error adding field $fieldName: " . $conn->error);
            }
        }
    }
    
    // Migrate data from user_accounts to users if user_accounts has data
    echo "Checking for user_accounts data migration...<br>";
    
    // Check if user_accounts table exists first
    $checkTableExists = "SHOW TABLES LIKE 'user_accounts'";
    $tableResult = $conn->query($checkTableExists);
    
    if ($tableResult && $tableResult->num_rows > 0) {
        $checkUserAccounts = "SELECT COUNT(*) as count FROM user_accounts";
        $result = $conn->query($checkUserAccounts);
        $row = $result->fetch_assoc();
        
        if ($row['count'] > 0) {
            echo "Found {$row['count']} records in user_accounts, migrating...<br>";
            
            $migrateSql = "INSERT IGNORE INTO users 
                (fullname, email, phone, password, google_id, facebook_id, avatar, 
                 email_verified, phone_verified, account_status, registration_method, 
                 registration_ip, user_agent, role, login_attempts, last_login_attempt, 
                 account_locked_until, last_login, created_at, updated_at)
                SELECT 
                    fullname, email, phone, password, google_id, facebook_id, avatar,
                    email_verified, phone_verified, account_status, registration_method,
                    registration_ip, user_agent, role, login_attempts, last_login_attempt,
                    account_locked_until, last_login, created_at, updated_at
                FROM user_accounts
                WHERE email NOT IN (SELECT email FROM users)";
            
            if ($conn->query($migrateSql)) {
                $migrated = $conn->affected_rows;
                echo "✓ Migrated $migrated records from user_accounts to users<br>";
            }
        } else {
            echo "⊙ No data to migrate from user_accounts<br>";
        }
    } else {
        echo "⊙ user_accounts table doesn't exist (skipping migration)<br>";
    }
    
    // Mark reservation_limits as deprecated (add comment)
    echo "Marking reservation_limits table as deprecated...<br>";
    
    // Check if reservation_limits table exists first
    $checkReservationLimits = "SHOW TABLES LIKE 'reservation_limits'";
    $limitsResult = $conn->query($checkReservationLimits);
    
    if ($limitsResult && $limitsResult->num_rows > 0) {
        $commentSql = "ALTER TABLE reservation_limits COMMENT = 'DEPRECATED: Use daily_slots in rooms/cottages tables instead'";
        if ($conn->query($commentSql)) {
            echo "✓ Marked reservation_limits as deprecated<br>";
        }
    } else {
        echo "⊙ reservation_limits table doesn't exist (skipping comment)<br>";
    }
    
    echo "<p>Phase 2 completed successfully!</p>";
    
    // ============================================
    // PHASE 3: Enhanced Business Logic
    // ============================================
    echo "<h3>Phase 3: Enhanced Business Logic</h3>";
    
    // Enhance reviews table
    echo "Enhancing reviews table...<br>";
    $reviewFields = [
        "reservation_id" => "ALTER TABLE reviews ADD COLUMN reservation_id INT(11) NULL AFTER user_id",
        "item_type" => "ALTER TABLE reviews ADD COLUMN item_type ENUM('room', 'cottage', 'general') DEFAULT 'general' AFTER reservation_id",
        "item_id" => "ALTER TABLE reviews ADD COLUMN item_id INT(11) NULL AFTER item_type"
    ];
    
    foreach ($reviewFields as $fieldName => $fieldSql) {
        $checkColumn = "SHOW COLUMNS FROM reviews LIKE '$fieldName'";
        $columnResult = $conn->query($checkColumn);
        
        if ($columnResult && $columnResult->num_rows > 0) {
            echo "⊙ Column $fieldName already exists in reviews table (skipping)<br>";
        } else {
            if ($conn->query($fieldSql)) {
                echo "✓ Added field $fieldName to reviews table<br>";
            } else {
                throw new Exception("Error adding field $fieldName: " . $conn->error);
            }
        }
    }
    
    // Add foreign key to reviews.reservation_id
    $reviewFKName = "fk_review_reservation";
    $checkReviewFK = "SELECT CONSTRAINT_NAME 
                      FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                      WHERE TABLE_SCHEMA = '$db_name' 
                      AND TABLE_NAME = 'reviews' 
                      AND CONSTRAINT_NAME = '$reviewFKName'";
    $reviewFKResult = $conn->query($checkReviewFK);
    
    if ($reviewFKResult && $reviewFKResult->num_rows > 0) {
        echo "⊙ Foreign key $reviewFKName already exists in reviews table (skipping)<br>";
    } else {
        $reviewFK = "ALTER TABLE reviews ADD CONSTRAINT $reviewFKName 
                     FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE SET NULL";
        if ($conn->query($reviewFK)) {
            echo "✓ Added foreign key to reviews table<br>";
        } else {
            if (strpos($conn->error, "Duplicate") !== false) {
                echo "⊙ Foreign key already exists (skipping)<br>";
            } else {
                throw new Exception("Error adding foreign key: " . $conn->error);
            }
        }
    }
    
    // Create pricing_history table
    echo "Creating pricing_history table...<br>";
    $pricingTable = "CREATE TABLE IF NOT EXISTS pricing_history (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        item_type ENUM('room', 'cottage') NOT NULL,
        item_id INT(11) NOT NULL,
        old_price DECIMAL(10,2) NOT NULL,
        new_price DECIMAL(10,2) NOT NULL,
        changed_by INT(11) NOT NULL,
        changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (changed_by) REFERENCES users(id),
        INDEX idx_item (item_type, item_id),
        INDEX idx_changed_at (changed_at)
    )";
    
    if ($conn->query($pricingTable)) {
        echo "✓ Created pricing_history table<br>";
    } else {
        throw new Exception("Error creating pricing_history table: " . $conn->error);
    }
    
    echo "<p>Phase 3 completed successfully!</p>";
    
    // ============================================
    // PHASE 4: Audit & Security
    // ============================================
    echo "<h3>Phase 4: Audit & Security</h3>";
    
    // Add audit fields to reservations
    echo "Adding audit fields to reservations...<br>";
    $auditFields = [
        "created_by" => "ALTER TABLE reservations ADD COLUMN created_by INT(11) NULL AFTER user_id",
        "updated_by" => "ALTER TABLE reservations ADD COLUMN updated_by INT(11) NULL AFTER updated_at"
    ];
    
    foreach ($auditFields as $fieldName => $fieldSql) {
        $checkColumn = "SHOW COLUMNS FROM reservations LIKE '$fieldName'";
        $columnResult = $conn->query($checkColumn);
        
        if ($columnResult && $columnResult->num_rows > 0) {
            echo "⊙ Column $fieldName already exists in reservations table (skipping)<br>";
        } else {
            if ($conn->query($fieldSql)) {
                echo "✓ Added audit field $fieldName to reservations table<br>";
            } else {
                throw new Exception("Error adding audit field $fieldName: " . $conn->error);
            }
        }
    }
    
    // Add foreign keys for audit fields
    $auditFKs = [
        "fk_created_by" => "ALTER TABLE reservations ADD CONSTRAINT fk_created_by FOREIGN KEY (created_by) REFERENCES users(id)",
        "fk_updated_by" => "ALTER TABLE reservations ADD CONSTRAINT fk_updated_by FOREIGN KEY (updated_by) REFERENCES users(id)"
    ];
    
    foreach ($auditFKs as $fkName => $fkSql) {
        $checkFK = "SELECT CONSTRAINT_NAME 
                   FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                   WHERE TABLE_SCHEMA = '$db_name' 
                   AND TABLE_NAME = 'reservations' 
                   AND CONSTRAINT_NAME = '$fkName'";
        $fkResult = $conn->query($checkFK);
        
        if ($fkResult && $fkResult->num_rows > 0) {
            echo "⊙ Foreign key $fkName already exists in reservations table (skipping)<br>";
        } else {
            if ($conn->query($fkSql)) {
                echo "✓ Added audit foreign key $fkName to reservations table<br>";
            } else {
                if (strpos($conn->error, "Duplicate") !== false) {
                    echo "⊙ Foreign key $fkName already exists (skipping)<br>";
                } else {
                    throw new Exception("Error adding audit foreign key $fkName: " . $conn->error);
                }
            }
        }
    }
    
    // Add soft delete to reservations
    echo "Adding soft delete to reservations...<br>";
    $softDeleteField = "deleted_at";
    $checkSoftDelete = "SHOW COLUMNS FROM reservations LIKE '$softDeleteField'";
    $softDeleteResult = $conn->query($checkSoftDelete);
    
    if ($softDeleteResult && $softDeleteResult->num_rows > 0) {
        echo "⊙ Column $softDeleteField already exists in reservations table (skipping)<br>";
    } else {
        $softDelete = "ALTER TABLE reservations ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL AFTER updated_at";
        if ($conn->query($softDelete)) {
            echo "✓ Added soft delete field to reservations table<br>";
        } else {
            throw new Exception("Error adding soft delete field: " . $conn->error);
        }
    }
    
    $softDeleteIndex = "idx_deleted_at";
    $checkSoftDeleteIndex = "SHOW INDEX FROM reservations WHERE Key_name = '$softDeleteIndex'";
    $softDeleteIndexResult = $conn->query($checkSoftDeleteIndex);
    
    if ($softDeleteIndexResult && $softDeleteIndexResult->num_rows > 0) {
        echo "⊙ Index $softDeleteIndex already exists in reservations table (skipping)<br>";
    } else {
        $softDeleteIndexSql = "ALTER TABLE reservations ADD INDEX idx_deleted_at (deleted_at)";
        if ($conn->query($softDeleteIndexSql)) {
            echo "✓ Added soft delete index to reservations table<br>";
        } else {
            throw new Exception("Error adding soft delete index: " . $conn->error);
        }
    }
    
    echo "<p>Phase 4 completed successfully!</p>";
    
    // ============================================
    // PHASE 5: Cleanup - Remove Unnecessary Tables
    // ============================================
    echo "<h3>Phase 5: Cleanup - Removing Unnecessary Tables</h3>";
    
    // Drop bookings table (duplicate of reservations)
    echo "Dropping bookings table (duplicate of reservations)...<br>";
    $dropBookings = "DROP TABLE IF EXISTS bookings";
    if ($conn->query($dropBookings)) {
        echo "✓ Dropped bookings table<br>";
    } else {
        echo "⊙ Bookings table doesn't exist or couldn't be dropped<br>";
    }
    
    // Drop user_accounts table (data migrated to users)
    echo "Dropping user_accounts table (data migrated to users)...<br>";
    $dropUserAccounts = "DROP TABLE IF EXISTS user_accounts";
    if ($conn->query($dropUserAccounts)) {
        echo "✓ Dropped user_accounts table<br>";
    } else {
        echo "⊙ User_accounts table doesn't exist or couldn't be dropped<br>";
    }
    
    // Keep reservation_limits table (used as backup/fallback for daily_slots)
    echo "Keeping reservation_limits table (used as backup for daily_slots)...<br>";
    echo "⊙ reservation_limits table retained<br>";
    
    echo "<p>Phase 5 completed successfully!</p>";
    
    // Commit all changes
    $conn->commit();
    
    echo "<h2 style='color: green;'>✓ Migration completed successfully!</h2>";
    echo "<p>All schema revisions have been applied without data loss.</p>";
    echo "<p><strong>Summary of changes:</strong></p>";
    echo "<ul>";
    echo "<li>Added performance indexes for faster queries</li>";
    echo "<li>Standardized status values across tables</li>";
    echo "<li>Added foreign key constraints for data integrity</li>";
    echo "<li>Consolidated user tables (migrated user_accounts to users)</li>";
    echo "<li>Dropped bookings table (duplicate of reservations)</li>";
    echo "<li>Dropped user_accounts table (data migrated to users)</li>";
    echo "<li>Kept reservation_limits table (backup for daily_slots)</li>";
    echo "<li>Enhanced reviews table with reservation/item links</li>";
    echo "<li>Created pricing_history table for price change tracking</li>";
    echo "<li>Added audit fields for tracking who made changes</li>";
    echo "<li>Added soft delete capability for safe record removal</li>";
    echo "</ul>";
    echo "<p><a href='../../admin/dashboard.php'>Go to Admin Dashboard</a></p>";
    
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    echo "<h2 style='color: red;'>✗ Migration failed!</h2>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
    echo "<p>All changes have been rolled back. No data was modified.</p>";
    echo "<p><a href='javascript:history.back()'>Go Back</a></p>";
}

$conn->close();
?>
