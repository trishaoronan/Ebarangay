<?php
// add_notes_column.php - Run this once to add the notes column
include 'db.php';

// Check if notes column exists
$checkQuery = "SHOW COLUMNS FROM requests LIKE 'notes'";
$result = $conn->query($checkQuery);

if ($result->num_rows == 0) {
    // Column doesn't exist, add it
    $alterQuery = "ALTER TABLE requests ADD COLUMN notes TEXT NULL AFTER document_path";
    
    if ($conn->query($alterQuery)) {
        echo "SUCCESS: Notes column added to requests table.";
    } else {
        echo "ERROR: " . $conn->error;
    }
} else {
    echo "INFO: Notes column already exists.";
}

$conn->close();
?>
