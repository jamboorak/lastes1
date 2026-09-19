<?php
/**
 * Ensure guest_info table and reservations.guest_info_id exist.
 */
function ensureGuestInfoSchema($conn) {
    if (!$conn) {
        return;
    }

    $conn->query("CREATE TABLE IF NOT EXISTS guest_info (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        user_id INT(11) NULL,
        first_name VARCHAR(100) NOT NULL,
        last_name VARCHAR(100) NOT NULL,
        email VARCHAR(150) NOT NULL,
        mobile_country_code VARCHAR(10) NOT NULL DEFAULT '+63',
        mobile_number VARCHAR(30) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_guest_user (user_id),
        INDEX idx_guest_email (email)
    )");

    $colCheck = $conn->query("SHOW COLUMNS FROM reservations LIKE 'guest_info_id'");
    if ($colCheck && $colCheck->num_rows === 0) {
        $conn->query("ALTER TABLE reservations ADD COLUMN guest_info_id INT(11) NULL AFTER user_id");
        $conn->query("ALTER TABLE reservations ADD INDEX idx_guest_info_id (guest_info_id)");
    }
}

/**
 * SQL expression for display guest name (prefer guest_info over account fullname)
 */
function guestDisplayNameSql($guestAlias = 'gi', $userAlias = 'u') {
    return "COALESCE(
        NULLIF(TRIM(CONCAT(UPPER(COALESCE({$guestAlias}.first_name, '')), ' ', UPPER(COALESCE({$guestAlias}.last_name, '')))), ''),
        NULLIF(TRIM({$userAlias}.fullname), ''),
        'Guest'
    )";
}
