<?php
include 'db.php';

echo "<h2>Checking notifications Table</h2>";

// Check if table exists
$check = $conn->query("SHOW TABLES LIKE 'notifications'");
if ($check && $check->num_rows > 0) {
    echo "<p style='color: green;'>✓ notifications table EXISTS</p>";
    
    // Show structure
    echo "<h3>Table Structure:</h3>";
    $result = $conn->query('DESCRIBE notifications');
    
    if ($result) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        while($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td><strong>" . $row['Field'] . "</strong></td>";
            echo "<td>" . $row['Type'] . "</td>";
            echo "<td>" . $row['Null'] . "</td>";
            echo "<td>" . $row['Key'] . "</td>";
            echo "<td>" . ($row['Default'] ?? 'NULL') . "</td>";
            echo "<td>" . ($row['Extra'] ?? '') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Show count
    $countResult = $conn->query("SELECT COUNT(*) as cnt FROM notifications");
    $countRow = $countResult->fetch_assoc();
    echo "<p>Total notifications: <strong>" . $countRow['cnt'] . "</strong></p>";
    
    echo "<hr>";
    echo "<h3>Actions:</h3>";
    echo "<a href='?action=drop' style='color: red; font-weight: bold;'>DROP notifications table (to recreate with correct structure)</a>";
    
    if (isset($_GET['action']) && $_GET['action'] === 'drop') {
        $conn->query("DROP TABLE notifications");
        echo "<p style='color: green; font-weight: bold;'>✓ Table dropped! Refresh this page to see status.</p>";
    }
    
} else {
    echo "<p style='color: orange;'>✗ notifications table DOES NOT EXIST</p>";
    echo "<p>The table will be auto-created when the first request is submitted.</p>";
}

$conn->close();
?>
