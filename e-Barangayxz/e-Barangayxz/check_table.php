<?php
require_once 'db.php';

$result = $conn->query("SHOW COLUMNS FROM barangay_id");
if ($result) {
    echo "Columns in barangay_id table:\n";
    while ($row = $result->fetch_assoc()) {
        echo "- " . $row['Field'] . "\n";
    }
} else {
    echo "Error: " . $conn->error;
}
