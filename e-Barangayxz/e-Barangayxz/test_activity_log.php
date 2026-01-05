<?php
include 'db.php';

echo "Testing activity_log table:\n\n";

// Check if table exists
$tableCheck = $conn->query("SHOW TABLES LIKE 'activity_log'");
if ($tableCheck && $tableCheck->num_rows > 0) {
    echo "✓ activity_log table exists\n\n";
} else {
    echo "✗ activity_log table does not exist\n";
    exit;
}

// Check schema
echo "Table schema:\n";
$schemaResult = $conn->query("DESCRIBE activity_log");
if ($schemaResult) {
    while ($row = $schemaResult->fetch_assoc()) {
        echo "  " . $row['Field'] . " (" . $row['Type'] . ")\n";
    }
} else {
    echo "  Error: " . $conn->error . "\n";
}

echo "\n\nLatest 10 activity records:\n";

// Fetch recent activities
$result = $conn->query("SELECT * FROM activity_log ORDER BY timestamp DESC LIMIT 10");
if ($result) {
    if ($result->num_rows === 0) {
        echo "  No records found\n";
    } else {
        while ($row = $result->fetch_assoc()) {
            echo "\n  ID: " . $row['log_id'] . "\n";
            echo "  Staff ID: " . $row['staff_id'] . "\n";
            echo "  Action: " . $row['action_type'] . "\n";
            echo "  Details: " . $row['action_details'] . "\n";
            echo "  Timestamp: " . $row['timestamp'] . "\n";
            echo "  Affected Staff: " . ($row['affected_staff_id'] ?? 'None') . "\n";
        }
    }
} else {
    echo "  Query error: " . $conn->error . "\n";
}

$conn->close();
?>
