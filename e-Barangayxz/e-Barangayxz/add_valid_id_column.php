<?php
require_once 'db.php';

// Add valid_id_path column to barangay_id table
$result = $conn->query("SHOW COLUMNS FROM barangay_id LIKE 'valid_id_path'");
if ($result && $result->num_rows === 0) {
    $alter = $conn->query("ALTER TABLE barangay_id ADD COLUMN valid_id_path VARCHAR(500) AFTER emergency_relationship");
    if ($alter) {
        echo "SUCCESS: Added valid_id_path column to barangay_id table\n";
    } else {
        echo "ERROR: " . $conn->error . "\n";
    }
} else {
    echo "Column valid_id_path already exists\n";
}

// Verify
$result = $conn->query("SHOW COLUMNS FROM barangay_id");
echo "\nCurrent columns in barangay_id table:\n";
while ($row = $result->fetch_assoc()) {
    echo "- " . $row['Field'] . "\n";
}
