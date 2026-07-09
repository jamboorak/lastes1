<?php
require_once 'config/database.php';

$database = new Database();
$conn = $database->getConnection();
$message = '';
$error = '';

// Handle form submission for individual updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_individual'])) {
    $cottage_id = $_POST['cottage_id'];
    $new_capacity = trim($_POST['capacity']);
    
    // Debug: Show what we received
    $message = "Debug: Received capacity: '$new_capacity' for cottage ID: $cottage_id<br>";
    
    // Validate capacity format (allow numbers, hyphens, spaces, plus signs)
    if (!empty($new_capacity) && preg_match('/^[0-9\-\s\+]+$/', $new_capacity)) {
        $sql = "UPDATE cottages SET capacity = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $new_capacity, $cottage_id);
        
        if ($stmt->execute()) {
            $message = "Cottage capacity updated successfully to: '$new_capacity'!";
        } else {
            $error = "Error updating cottage capacity: " . $conn->error;
        }
        $stmt->close();
    } else {
        $error = "Invalid capacity format: '$new_capacity'. Please use formats like '12-18', '8-12', '15', etc.";
    }
}

// Handle batch update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['batch_update'])) {
    $batch_updates = $_POST['batch_capacities'];
    $success_count = 0;
    $error_count = 0;
    $invalid_formats = [];
    
    foreach ($batch_updates as $cottage_id => $capacity) {
        $capacity = trim($capacity);
        if (!empty($capacity)) {
            // Validate capacity format (allow numbers, hyphens, spaces, plus signs)
            if (preg_match('/^[0-9\-\s\+]+$/', $capacity)) {
                $sql = "UPDATE cottages SET capacity = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("si", $capacity, $cottage_id);
                
                if ($stmt->execute()) {
                    $success_count++;
                } else {
                    $error_count++;
                    $error .= "Error updating cottage ID $cottage_id: " . $conn->error . "<br>";
                }
                $stmt->close();
            } else {
                $invalid_formats[] = "Cottage ID $cottage_id: '$capacity'";
                $error_count++;
            }
        }
    }
    
    if ($error_count === 0) {
        $message = "Successfully updated $success_count cottage capacities!";
    } else {
        $message = "Updated $success_count cottage capacities. $error_count errors occurred.";
        if (!empty($invalid_formats)) {
            $error .= "Invalid formats: " . implode(", ", $invalid_formats) . "<br>";
            $error .= "Please use formats like '12-18', '8-12', '15', etc.";
        }
    }
}

// Get all cottages
$cottages = $conn->query("SELECT * FROM cottages ORDER BY name")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Cottage Capacities</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            text-align: center;
            margin-bottom: 30px;
        }
        .message {
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .tabs {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 2px solid #ddd;
        }
        .tab {
            padding: 10px 20px;
            cursor: pointer;
            border: none;
            background: none;
            font-size: 16px;
            border-bottom: 2px solid transparent;
        }
        .tab.active {
            border-bottom: 2px solid #007bff;
            color: #007bff;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        input[type="text"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }
        .btn-primary {
            background-color: #007bff;
            color: white;
        }
        .btn-success {
            background-color: #28a745;
            color: white;
        }
        .btn:hover {
            opacity: 0.9;
        }
        .navigation {
            margin-top: 20px;
            text-align: center;
        }
        .navigation a {
            display: inline-block;
            margin: 0 10px;
            padding: 10px 20px;
            background-color: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }
        .navigation a:hover {
            background-color: #5a6268;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Manage Cottage Capacities</h1>
        
        <?php if ($message): ?>
            <div class="message success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="message error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="tabs">
            <button class="tab active" onclick="showTab('individual')">Individual Update</button>
            <button class="tab" onclick="showTab('batch')">Batch Update</button>
            <button class="tab" onclick="showTab('view')">View Current</button>
        </div>
        
        <!-- Individual Update Tab -->
        <div id="individual" class="tab-content active">
            <h2>Update Individual Cottage Capacity</h2>
            <p><strong>Accepted formats:</strong> "12-18", "8-12", "15", "20+", "10-15", etc. (Numbers, hyphens, spaces, and plus signs only)</p>
            <form method="POST">
                <input type="hidden" name="update_individual" value="1">
                <table>
                    <tr>
                        <th>Cottage Name</th>
                        <th>Current Capacity</th>
                        <th>New Capacity</th>
                        <th>Action</th>
                    </tr>
                    <?php foreach ($cottages as $cottage): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($cottage['name']); ?></td>
                        <td><?php echo htmlspecialchars($cottage['capacity']); ?></td>
                        <td>
                            <input type="text" name="capacity" 
                                   pattern="[0-9\-\s\+]+"
                                   title="Enter capacity like 12-18, 8-12, 15, 20+, etc."
                                   placeholder="e.g., 12-18, 8-12, 15, 20+" 
                                   value="<?php echo htmlspecialchars($cottage['capacity']); ?>">
                        </td>
                        <td>
                            <input type="hidden" name="cottage_id" value="<?php echo $cottage['id']; ?>">
                            <button type="submit" class="btn btn-primary">Update</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </form>
        </div>
        
        <!-- Batch Update Tab -->
        <div id="batch" class="tab-content">
            <h2>Batch Update Cottage Capacities</h2>
            <p><strong>Accepted formats:</strong> "12-18", "8-12", "15", "20+", "10-15", etc. (Numbers, hyphens, spaces, and plus signs only)</p>
            <p>Update multiple cottage capacities at once. Leave blank to keep current value.</p>
            <form method="POST">
                <input type="hidden" name="batch_update" value="1">
                <table>
                    <tr>
                        <th>Cottage Name</th>
                        <th>Current Capacity</th>
                        <th>New Capacity</th>
                    </tr>
                    <?php foreach ($cottages as $cottage): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($cottage['name']); ?></td>
                        <td><?php echo htmlspecialchars($cottage['capacity']); ?></td>
                        <td>
                            <input type="text" 
                                   name="batch_capacities[<?php echo $cottage['id']; ?>]" 
                                   pattern="[0-9\-\s\+]+"
                                   title="Enter capacity like 12-18, 8-12, 15, 20+, etc."
                                   placeholder="e.g., 12-18, 8-12, 15, 20+" 
                                   value="<?php echo htmlspecialchars($cottage['capacity']); ?>">
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
                <div style="margin-top: 20px;">
                    <button type="submit" class="btn btn-success">Update All</button>
                </div>
            </form>
        </div>
        
        <!-- View Current Tab -->
        <div id="view" class="tab-content">
            <h2>Current Cottage Capacities</h2>
            <table>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Capacity</th>
                    <th>Price</th>
                    <th>Description</th>
                </tr>
                <?php foreach ($cottages as $cottage): ?>
                <tr>
                    <td><?php echo $cottage['id']; ?></td>
                    <td><?php echo htmlspecialchars($cottage['name']); ?></td>
                    <td><?php echo htmlspecialchars($cottage['capacity']); ?></td>
                    <td><?php echo htmlspecialchars($cottage['price']); ?></td>
                    <td><?php echo htmlspecialchars($cottage['description']); ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
        
        <div class="navigation">
            <a href="booking.php">Back to Booking</a>
            <a href="admin/dashboard.php">Admin Dashboard</a>
            <a href="index.php">Home</a>
        </div>
    </div>
    
    <script>
        function showTab(tabName) {
            // Hide all tab contents
            var tabContents = document.getElementsByClassName('tab-content');
            for (var i = 0; i < tabContents.length; i++) {
                tabContents[i].classList.remove('active');
            }
            
            // Remove active class from all tabs
            var tabs = document.getElementsByClassName('tab');
            for (var i = 0; i < tabs.length; i++) {
                tabs[i].classList.remove('active');
            }
            
            // Show selected tab content
            document.getElementById(tabName).classList.add('active');
            
            // Add active class to clicked tab
            event.target.classList.add('active');
        }
    </script>
</body>
</html>

<?php $conn->close(); ?>
