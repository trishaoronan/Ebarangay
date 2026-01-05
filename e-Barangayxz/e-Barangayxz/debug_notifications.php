<?php
// Debug script to check notification system
header('Content-Type: text/html; charset=utf-8');
include 'db.php';

echo "<h2>Notification System Debug</h2>";

// 1. Check notifications table
echo "<h3>1. Checking notifications table...</h3>";
$tableCheck = $conn->query("SHOW TABLES LIKE 'notifications'");
if ($tableCheck && $tableCheck->num_rows > 0) {
    echo "<p style='color:green'>✓ notifications table exists</p>";
    
    // Show table structure
    echo "<h4>Table Structure:</h4>";
    $structure = $conn->query("DESCRIBE notifications");
    echo "<table border='1' cellpadding='5'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    while ($row = $structure->fetch_assoc()) {
        echo "<tr><td>{$row['Field']}</td><td>{$row['Type']}</td><td>{$row['Null']}</td><td>{$row['Key']}</td><td>{$row['Default']}</td></tr>";
    }
    echo "</table>";
    
    // Count notifications
    $countResult = $conn->query("SELECT COUNT(*) as cnt FROM notifications");
    $count = $countResult->fetch_assoc()['cnt'];
    echo "<p>Total notifications in table: <strong>$count</strong></p>";
    
    // Show recent notifications
    echo "<h4>Recent Notifications:</h4>";
    $recent = $conn->query("SELECT * FROM notifications ORDER BY created_at DESC LIMIT 10");
    if ($recent && $recent->num_rows > 0) {
        echo "<table border='1' cellpadding='5'><tr><th>ID</th><th>Staff ID</th><th>Type</th><th>Title</th><th>Message</th><th>Created At</th></tr>";
        while ($row = $recent->fetch_assoc()) {
            echo "<tr><td>{$row['id']}</td><td>{$row['staff_id']}</td><td>{$row['notification_type']}</td><td>{$row['title']}</td><td>{$row['message']}</td><td>{$row['created_at']}</td></tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color:red'>No notifications found in table</p>";
    }
} else {
    echo "<p style='color:red'>✗ notifications table does NOT exist</p>";
}

// 2. Check staff_accounts table
echo "<h3>2. Checking staff accounts...</h3>";
$staffCheck = $conn->query("SELECT staff_id, first_name, last_name, status FROM staff_accounts");
if ($staffCheck && $staffCheck->num_rows > 0) {
    echo "<table border='1' cellpadding='5'><tr><th>Staff ID</th><th>Name</th><th>Status</th></tr>";
    while ($row = $staffCheck->fetch_assoc()) {
        $statusColor = strtolower($row['status']) === 'active' ? 'green' : 'red';
        echo "<tr><td>{$row['staff_id']}</td><td>{$row['first_name']} {$row['last_name']}</td><td style='color:$statusColor'>{$row['status']}</td></tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color:red'>No staff accounts found</p>";
}

// 3. Test inserting a notification
echo "<h3>3. Test Insert Notification</h3>";
if (isset($_GET['test_insert'])) {
    include_once 'add_staff_notification.php';
    $result = addStaffNotification(
        $conn,
        'profile_update',
        'Test Notification',
        'This is a test notification created at ' . date('Y-m-d H:i:s'),
        1,
        'test'
    );
    if ($result) {
        echo "<p style='color:green'>✓ Test notification inserted successfully!</p>";
    } else {
        echo "<p style='color:red'>✗ Failed to insert test notification</p>";
    }
    echo "<p><a href='debug_notifications.php'>Refresh to see results</a></p>";
} else {
    echo "<p><a href='debug_notifications.php?test_insert=1' style='padding:10px 20px; background:#007bff; color:white; text-decoration:none; border-radius:5px;'>Click to Test Insert Notification</a></p>";
}

// 4. Check PHP error log
echo "<h3>4. Recent PHP Errors (if any)</h3>";
$errorLog = ini_get('error_log');
echo "<p>Error log location: $errorLog</p>";

$conn->close();
?>
