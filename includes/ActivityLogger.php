<?php
/**
 * Shared user activity audit log.
 */
function ensureActivityLogSchema(mysqli $conn) {
    $conn->query("CREATE TABLE IF NOT EXISTS user_activity_log (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        user_id INT(11) NULL,
        action VARCHAR(80) NOT NULL,
        description VARCHAR(255) NOT NULL,
        ip_address VARCHAR(45) NULL,
        user_agent VARCHAR(255) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_activity_user (user_id),
        INDEX idx_activity_action (action),
        INDEX idx_activity_created (created_at)
    )");
}

function logUserActivity(mysqli $conn, $userId, $action, $description) {
    ensureActivityLogSchema($conn);
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
    $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
    $stmt = $conn->prepare('INSERT INTO user_activity_log (user_id, action, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)');
    if (!$stmt) {
        return false;
    }
    $userId = $userId ? (int)$userId : null;
    $stmt->bind_param('issss', $userId, $action, $description, $ipAddress, $userAgent);
    $success = $stmt->execute();
    $stmt->close();
    return $success;
}
