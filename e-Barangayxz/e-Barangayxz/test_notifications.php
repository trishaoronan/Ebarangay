<?php
/**
 * test_notifications.php - Test the notifications system
 * This script tests:
 * 1. Creating a test notification
 * 2. Fetching notifications via get_notifications.php API
 * 3. Marking notifications as read
 */

session_start();
include 'db.php';

// For testing, set a staff_id if not in session
if (!isset($_SESSION['staff_id'])) {
    $_SESSION['staff_id'] = 1; // Test staff ID
}

$staff_id = $_SESSION['staff_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? 'info';

// Create notifications table if it doesn't exist
$create_sql = "CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NOT NULL,
    notification_type VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT,
    related_id INT,
    related_type VARCHAR(50),
    is_read INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_staff (staff_id),
    KEY idx_is_read (is_read),
    KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($create_sql);

echo "<h2>Notifications System Test</h2>";
echo "<p>Current Staff ID: <strong>$staff_id</strong></p>";

if ($action === 'create_test') {
    // Create a test notification
    $title = "Test Notification";
    $message = "This is a test notification from test_notifications.php";
    $type = 'document_request';
    
    $stmt = $conn->prepare("INSERT INTO notifications (staff_id, notification_type, title, message, related_id, related_type) VALUES (?, ?, ?, ?, 1, 'request')");
    $stmt->bind_param('isss', $staff_id, $type, $title, $message);
    
    if ($stmt->execute()) {
        $notif_id = $conn->insert_id;
        echo "<div style='background-color: #d4edda; padding: 10px; margin: 10px 0;'>";
        echo "<strong>✓ Success!</strong> Created test notification with ID: $notif_id<br>";
        echo "</div>";
    } else {
        echo "<div style='background-color: #f8d7da; padding: 10px; margin: 10px 0;'>";
        echo "<strong>✗ Error:</strong> " . $stmt->error;
        echo "</div>";
    }
    $stmt->close();
} elseif ($action === 'clear') {
    // Clear all notifications for this staff
    $stmt = $conn->prepare("DELETE FROM notifications WHERE staff_id = ?");
    $stmt->bind_param('i', $staff_id);
    
    if ($stmt->execute()) {
        $deleted = $stmt->affected_rows;
        echo "<div style='background-color: #d4edda; padding: 10px; margin: 10px 0;'>";
        echo "<strong>✓ Success!</strong> Deleted $deleted notifications<br>";
        echo "</div>";
    } else {
        echo "<div style='background-color: #f8d7da; padding: 10px; margin: 10px 0;'>";
        echo "<strong>✗ Error:</strong> " . $stmt->error;
        echo "</div>";
    }
    $stmt->close();
}

// Display current notifications
echo "<h3>Current Notifications for Staff ID $staff_id:</h3>";

$stmt = $conn->prepare("SELECT id, notification_type, title, message, is_read, created_at FROM notifications WHERE staff_id = ? ORDER BY created_at DESC LIMIT 10");
$stmt->bind_param('i', $staff_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo "<table border='1' style='width: 100%; border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>ID</th><th>Type</th><th>Title</th><th>Message</th><th>Read</th><th>Created</th></tr>";
    while ($row = $result->fetch_assoc()) {
        $read_status = $row['is_read'] ? '✓ Yes' : '✗ No';
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['notification_type']}</td>";
        echo "<td>{$row['title']}</td>";
        echo "<td>" . substr($row['message'], 0, 50) . "...</td>";
        echo "<td>$read_status</td>";
        echo "<td>{$row['created_at']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: #666;'>No notifications found for this staff member.</p>";
}

$stmt->close();

// Count unread notifications
$stmt = $conn->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE staff_id = ? AND is_read = 0");
$stmt->bind_param('i', $staff_id);
$stmt->execute();
$countResult = $stmt->get_result();
$countRow = $countResult->fetch_assoc();
$unreadCount = $countRow['unread_count'];
$stmt->close();

echo "<h3>Statistics:</h3>";
echo "<p>Unread Notifications: <strong>$unreadCount</strong></p>";

// Test API endpoints
echo "<h3>Test API Endpoints:</h3>";
echo "<ul>";
echo "<li><a href='?action=create_test' style='color: blue; text-decoration: underline;'>Create Test Notification</a></li>";
echo "<li><a href='?action=clear' style='color: blue; text-decoration: underline;'>Clear All Notifications</a></li>";
echo "<li><a href='get_notifications.php' target='_blank' style='color: blue; text-decoration: underline;'>Fetch Notifications (API JSON)</a></li>";
echo "<li><a href='test_notifications.php' style='color: blue; text-decoration: underline;'>Refresh This Page</a></li>";
echo "</ul>";

echo "<hr>";
echo "<p><strong>Instructions:</strong></p>";
echo "<ol>";
echo "<li>Click 'Create Test Notification' to add a test notification</li>";
echo "<li>Click 'Fetch Notifications (API JSON)' to see the API response</li>";
echo "<li>Visit staff-dashboard.php to see notifications in the dropdown</li>";
echo "<li>Click on a notification in the dropdown to mark it as read</li>";
echo "<li>Click 'Clear All Notifications' to delete all test notifications</li>";
echo "</ol>";

$conn->close();
?>
