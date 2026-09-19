<?php
require_once __DIR__ . '/../../config/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../config/database.php';

echo "<h2>Admin Access Test</h2>";

echo "<h3>Session Status:</h3>";
if (isset($_SESSION['user_id'])) {
    echo "✅ User ID: " . $_SESSION['user_id'] . "<br>";
    echo "✅ Session is active<br>";
} else {
    echo "❌ No user ID in session<br>";
    echo "❌ User is not logged in<br>";
}

echo "<h3>Available Users:</h3>";
$db = new Database();
$conn = $db->getConnection();

// Check users table
$users = $conn->query("SELECT id, fullname, email FROM users LIMIT 5");
if ($users && $users->num_rows > 0) {
    echo "<strong>Users table:</strong><br>";
    while ($user = $users->fetch_assoc()) {
        echo "ID: {$user['id']} - {$user['fullname']} ({$user['email']})<br>";
    }
} else {
    echo "❌ No users found in users table<br>";
}

// Check users table only (user_accounts was removed during migration)
echo "<h3>Test Login:</h3>";
echo "<form method='post'>";
echo "User ID: <input type='number' name='test_user_id' value='1' min='1'><br><br>";
echo "<input type='submit' name='login_test' value='Login as Admin'>";
echo "</form>";

if (isset($_POST['login_test'])) {
    $testUserId = $_POST['test_user_id'];
    $_SESSION['user_id'] = $testUserId;
    echo "<br><strong>✅ Logged in as User ID: $testUserId</strong><br>";
    echo "<a href='index.php'>Go to Admin Dashboard</a><br>";
    echo "<a href='../login.php'>Go to Login Page</a>";
}

echo "<h3>Direct Access Test:</h3>";
echo "<a href='index.php'>Try Admin Dashboard</a><br>";
echo "<a href='../../admin/dashboard.php?section=reservations'>Try Reservations Page</a>";
?>
