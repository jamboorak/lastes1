<?php
require_once '../config/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';

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

// Initialize variables
$loginIdentifier = $password = '';
$error = '';

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $loginIdentifier = trim($_POST['login_identifier'] ?? '');
    $password = trim($_POST['password']);

    // Validate input
    if (empty($loginIdentifier) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {
        // Prepare and execute query to check admin user
        $stmt = $conn->prepare("SELECT id, fullname, email, username, password, role FROM users WHERE (email = ? OR username = ?) AND role = 'admin' LIMIT 1");
        $stmt->bind_param("ss", $loginIdentifier, $loginIdentifier);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $admin = $result->fetch_assoc();

            // Verify password
            if (password_verify($password, $admin['password'])) {
                // Password is correct, start admin session (separate from main user system)
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_name'] = $admin['fullname'];
                $_SESSION['admin_email'] = $admin['email'];
                $_SESSION['admin_role'] = $admin['role'];
                $_SESSION['admin_logged_in'] = true;

                // Update last login
                $updateStmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $updateStmt->bind_param("i", $admin['id']);
                $updateStmt->execute();
                $updateStmt->close();

                // Redirect to admin dashboard
                header("Location: dashboard.php");
                exit();
            } else {
                $error = "Invalid password.";
            }
        } else {
            $error = "Admin account not found or access denied.";
        }
        $stmt->close();
    }
}

$conn->close();

// If there's an error, redirect back to admin login with error message
if (!empty($error)) {
    $_SESSION['admin_error'] = $error;
    header("Location: login.php");
    exit();
}
?>