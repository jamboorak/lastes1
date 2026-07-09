<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Admin Dashboard Debug</h2>";

// Test basic includes
echo "<h3>Testing Includes:</h3>";

try {
    require_once '../config/database.php';
    echo "✅ Database config loaded<br>";
} catch (Exception $e) {
    echo "❌ Database config error: " . $e->getMessage() . "<br>";
}

try {
    require_once '../config/config.php';
    echo "✅ Main config loaded<br>";
} catch (Exception $e) {
    echo "❌ Main config error: " . $e->getMessage() . "<br>";
}

// Test session
echo "<h3>Session Test:</h3>";
session_start();
echo "Session status: " . session_status() . "<br>";
echo "Session ID: " . session_id() . "<br>";

if (isset($_SESSION['user_id'])) {
    echo "✅ User ID in session: " . $_SESSION['user_id'] . "<br>";
} else {
    echo "❌ No user ID in session<br>";
    echo "Setting test user ID...<br>";
    $_SESSION['user_id'] = 1;
    echo "✅ Test user ID set to 1<br>";
}

// Test database connection
echo "<h3>Database Test:</h3>";
try {
    $db = new Database();
    $conn = $db->getConnection();
    echo "✅ Database connection successful<br>";
    
    // Test query
    $result = $conn->query("SELECT COUNT(*) as count FROM reservations");
    if ($result) {
        $count = $result->fetch_assoc()['count'];
        echo "✅ Found $count reservations<br>";
    }
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "<br>";
}

echo "<h3>Next Steps:</h3>";
echo "1. <a href='test_access.php'>Run Access Test</a><br>";
echo "2. <a href='index.php'>Try Admin Dashboard</a><br>";
echo "3. <a href='../login.php'>Go to Login</a>";
?>
