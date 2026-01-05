<?php
// check_good_moral_data.php
include 'db.php';

echo "<h3>Checking good_moral table:</h3>";

// Check if table exists
$result = $conn->query("SHOW TABLES LIKE 'good_moral'");
if ($result->num_rows > 0) {
    echo "✓ Table 'good_moral' exists<br><br>";
    
    // Check table structure
    echo "<h4>Table Structure:</h4>";
    $columns = $conn->query("DESCRIBE good_moral");
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th></tr>";
    while ($col = $columns->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$col['Field']}</td>";
        echo "<td>{$col['Type']}</td>";
        echo "<td>{$col['Null']}</td>";
        echo "<td>{$col['Key']}</td>";
        echo "</tr>";
    }
    echo "</table><br>";
    
    // Count records
    $count = $conn->query("SELECT COUNT(*) as total FROM good_moral");
    $total = $count->fetch_assoc()['total'];
    echo "<h4>Total Records: $total</h4><br>";
    
    // Show recent records
    if ($total > 0) {
        echo "<h4>Recent Records:</h4>";
        $records = $conn->query("SELECT * FROM good_moral ORDER BY created_at DESC LIMIT 5");
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Request ID</th><th>Resident ID</th><th>Name</th><th>Purpose</th><th>Created At</th></tr>";
        while ($row = $records->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$row['id']}</td>";
            echo "<td>{$row['request_id']}</td>";
            echo "<td>{$row['resident_id']}</td>";
            echo "<td>{$row['first_name']} {$row['last_name']}</td>";
            echo "<td>" . substr($row['specific_purpose'], 0, 50) . "...</td>";
            echo "<td>{$row['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: orange;'>⚠ No records found in good_moral table</p>";
    }
    
} else {
    echo "✗ Table 'good_moral' does NOT exist<br>";
    echo "<p style='color: red;'>Please run create_good_moral_table.php first!</p>";
}

$conn->close();
?>
