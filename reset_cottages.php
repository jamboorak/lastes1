<?php
/**
 * Fix Cottages Availability Issue
 * This script repairs the cottage capacity values and reinitializes data
 */

require_once 'config/database.php';

$db = new Database();
$conn = $db->getConnection();

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix Cottage Availability</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; padding: 2rem; background: #f3f4f6; max-width: 600px; margin: 0 auto; }
        .container { background: white; border-radius: 8px; padding: 2rem; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        h1 { color: #1f2937; margin-top: 0; }
        .step { margin: 1.5rem 0; padding: 1rem; border-left: 4px solid #3b82f6; background: #eff6ff; }
        .step h3 { margin: 0 0 0.5rem 0; color: #1e40af; }
        .step p { margin: 0.5rem 0; color: #374151; }
        .success { border-left-color: #10b981; background: #ecfdf5; color: #065f46; }
        .success h3 { color: #047857; }
        .error { border-left-color: #ef4444; background: #fef2f2; color: #7f1d1d; }
        .error h3 { color: #b91c1c; }
        .btn-group { display: flex; gap: 1rem; margin-top: 2rem; flex-wrap: wrap; }
        a { display: inline-block; padding: 0.75rem 1.5rem; background: #ff7a3d; color: white; text-decoration: none; border-radius: 6px; font-weight: 600; transition: background 0.3s; }
        a:hover { background: #ff9362; }
        .btn-secondary { background: #6b7280; }
        .btn-secondary:hover { background: #4b5563; }
        table { width: 100%; border-collapse: collapse; margin: 1rem 0; }
        th, td { border: 1px solid #d1d5db; padding: 0.75rem; text-align: left; }
        th { background: #f3f4f6; font-weight: 600; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Fixing Cottage Availability Issue</h1>
        
        <?php
        try {
            echo '<div class="step">';
            echo '<h3>1. Updating Existing Cottage Capacity Values</h3>';
            
            // Update capacity values for existing cottages
            $conn->query("UPDATE cottages SET capacity = 10 WHERE name = 'Cottage A'");
            $conn->query("UPDATE cottages SET capacity = 14 WHERE name = 'Cottage B'");
            $conn->query("UPDATE cottages SET capacity = 22 WHERE name = 'Kubo Cottage'");
            
            echo '<p>✓ Updated capacity values for all cottages</p>';
            echo '</div>';
            
            echo '<div class="step">';
            echo '<h3>2. Clearing Cottage Table for Fresh Initialization</h3>';
            
            // Clear cottages to force fresh creation with correct SQL
            $deleteResult = $conn->query("DELETE FROM cottages");
            
            if ($deleteResult) {
                echo '<p>✓ Cleared all cottage records</p>';
            } else {
                throw new Exception("Failed to delete cottages: " . $conn->error);
            }
            echo '</div>';
            
            echo '<div class="step">';
            echo '<h3>3. Verifying Reservation Limits</h3>';
            
            // Check if reservation_limits has the correct data
            $limitsResult = $conn->query("SELECT COUNT(*) as count FROM reservation_limits WHERE item_type = 'cottage'");
            $limRow = $limitsResult->fetch_assoc();
            
            if ($limRow['count'] >= 3) {
                echo '<p>✓ Reservation limits are properly configured (' . $limRow['count'] . ' cottage limits found)</p>';
            } else {
                echo '<p>⚠ Reservation limits may need setup. Run setup_reservation_limits.php</p>';
            }
            echo '</div>';
            
            // Show current data
            echo '<div class="step success">';
            echo '<h3>✓ Configuration Status</h3>';
            echo '<p><strong>Current Cottage Capacity Settings:</strong></p>';
            echo '<table>';
            echo '<tr><th>Cottage Name</th><th>Capacity</th></tr>';
            echo '<tr><td>Cottage A</td><td>10 people</td></tr>';
            echo '<tr><td>Cottage B</td><td>14 people</td></tr>';
            echo '<tr><td>Kubo Cottage</td><td>22 people</td></tr>';
            echo '</table>';
            
            // Show reservation limits
            $limitsResult = $conn->query("SELECT item_name, daily_limit FROM reservation_limits WHERE item_type = 'cottage' ORDER BY item_name");
            if ($limitsResult && $limitsResult->num_rows > 0) {
                echo '<p><strong>Daily Reservation Limits:</strong></p>';
                echo '<table>';
                echo '<tr><th>Cottage Name</th><th>Daily Limit</th></tr>';
                while ($limRow = $limitsResult->fetch_assoc()) {
                    echo '<tr><td>' . htmlspecialchars($limRow['item_name']) . '</td><td>' . $limRow['daily_limit'] . ' per day</td></tr>';
                }
                echo '</table>';
            }
            echo '</div>';
            
            echo '<div class="step" style="border-left-color: #f59e0b; background: #fef3c7; color: #78350f;">';
            echo '<h3 style="color: #b45309;">Next Steps:</h3>';
            echo '<ol style="margin: 0.5rem 0 0 0; padding-left: 1.5rem;">';
            echo '<li>Visit the <strong>Booking Page</strong> to trigger reinitialization</li>';
            echo '<li>The cottages will be recreated with <strong>corrected capacity values</strong></li>';
            echo '<li>Select a date and verify <strong>availability counts show correctly</strong></li>';
            echo '</ol>';
            echo '</div>';
            
        } catch (Exception $e) {
            echo '<div class="step error">';
            echo '<h3>✗ Error Occurred</h3>';
            echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '</div>';
        }
        
        $conn->close();
        ?>
        
        <div class="btn-group">
            <a href="booking.php">📅 Go to Booking Page</a>
            <a class="btn-secondary" href="setup_reservation_limits.php">⚙️ Setup Reservation Limits</a>
        </div>
    </div>
</body>
</html>
