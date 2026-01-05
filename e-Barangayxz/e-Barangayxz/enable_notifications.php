<?php
/**
 * enable_notifications.php - Re-enable the notifications system
 * This script properly sets up the notifications table and re-enables notification code
 */

include 'db.php';

echo "<h2>Enable Notifications System</h2>";

// Step 1: Drop and recreate the notifications table with correct structure
echo "<h3>Step 1: Setting up notifications table...</h3>";

$conn->query("SET FOREIGN_KEY_CHECKS=0");
$conn->query("DROP TABLE IF EXISTS notifications");

$create_sql = "CREATE TABLE notifications (
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

if ($conn->query($create_sql)) {
    echo "<p style='color: green;'>✓ notifications table created successfully</p>";
} else {
    echo "<p style='color: red;'>✗ Error: " . $conn->error . "</p>";
    $conn->close();
    exit;
}

$conn->query("SET FOREIGN_KEY_CHECKS=1");

// Step 2: Verify table structure
echo "<h3>Step 2: Verifying table structure...</h3>";
$result = $conn->query('DESCRIBE notifications');
$columns = [];
echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th></tr>";
while($row = $result->fetch_assoc()) {
    $columns[] = $row['Field'];
    echo "<tr>";
    echo "<td><strong>" . $row['Field'] . "</strong></td>";
    echo "<td>" . $row['Type'] . "</td>";
    echo "<td>" . $row['Null'] . "</td>";
    echo "<td>" . $row['Key'] . "</td>";
    echo "</tr>";
}
echo "</table>";

if (in_array('staff_id', $columns) && in_array('id', $columns)) {
    echo "<p style='color: green;'>✓ Table structure is correct</p>";
} else {
    echo "<p style='color: red;'>✗ Table structure is incorrect</p>";
    $conn->close();
    exit;
}

// Step 3: Instructions to re-enable notifications
echo "<h3>Step 3: Re-enable notifications in code</h3>";
echo "<p style='background-color: #fff3cd; padding: 10px; margin: 10px 0;'>";
echo "<strong>To complete the re-enablement:</strong><br>";
echo "1. Uncomment the notification code in these files:<br>";
echo "   - submit_request.php<br>";
echo "   - submit_barangay_clearance.php<br>";
echo "   - submit_certificate_indigency.php<br>";
echo "   - submit_certificate_residency.php<br>";
echo "   - submit_good_moral.php<br>";
echo "   - update_request_status.php<br>";
echo "2. Then test by submitting a new request<br>";
echo "</p>";

echo "<h3>✓ Notifications table is ready!</h3>";
echo "<p style='color: green; font-weight: bold;'>The notifications table has been properly created. You can now uncomment the notification code in the submit files.</p>";

$conn->close();
?>
