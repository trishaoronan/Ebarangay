<?php
include 'db.php';

echo "<h2>Fix Notifications Table - Force Rebuild</h2>";

// First, check if table exists and show current structure
$checkResult = $conn->query("SELECT * FROM information_schema.COLUMNS WHERE TABLE_NAME = 'notifications' AND TABLE_SCHEMA = DATABASE()");

if ($checkResult && $checkResult->num_rows > 0) {
    echo "<h3>Current Table Structure:</h3>";
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Column</th><th>Type</th></tr>";
    while($col = $checkResult->fetch_assoc()) {
        echo "<tr><td>" . $col['COLUMN_NAME'] . "</td><td>" . $col['COLUMN_TYPE'] . "</td></tr>";
    }
    echo "</table>";
}

// Disable foreign key checks temporarily
$conn->query("SET FOREIGN_KEY_CHECKS=0");

// Drop the table if it exists
if ($conn->query("DROP TABLE IF EXISTS notifications")) {
    echo "<p style='color: orange;'>✓ Dropped old notifications table</p>";
} else {
    echo "<p style='color: red;'>✗ Error dropping table: " . $conn->error . "</p>";
}

// Create with correct structure
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
    echo "<p style='color: green; font-weight: bold;'>✓ Created notifications table with correct structure!</p>";
    
    // Show new structure
    echo "<h3>New Table Structure:</h3>";
    $result = $conn->query('DESCRIBE notifications');
    
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th></tr>";
    while($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td><strong>" . $row['Field'] . "</strong></td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<p style='color: blue; margin-top: 20px;'><strong>✓ Done! Now try submitting a Certificate of Indigency request again.</strong></p>";
} else {
    echo "<p style='color: red;'>✗ Error creating table: " . $conn->error . "</p>";
}

// Re-enable foreign key checks
$conn->query("SET FOREIGN_KEY_CHECKS=1");

$conn->close();
?>
