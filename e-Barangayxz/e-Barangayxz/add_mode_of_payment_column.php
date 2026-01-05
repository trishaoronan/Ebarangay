<?php
// add_mode_of_payment_column.php
// Migration script to add mode_of_payment column to requests table
include 'db.php';

header('Content-Type: application/json');

$results = [];

// Check if mode_of_payment column exists
$colRes = $conn->query("SHOW COLUMNS FROM requests LIKE 'mode_of_payment'");
if ($colRes && $colRes->num_rows === 0) {
    // Add mode_of_payment column
    $sql = "ALTER TABLE requests ADD COLUMN mode_of_payment VARCHAR(20) DEFAULT 'GCash' AFTER document_type";
    if ($conn->query($sql)) {
        $results[] = "Added mode_of_payment column to requests table";
    } else {
        $results[] = "Error adding mode_of_payment column: " . $conn->error;
    }
} else {
    $results[] = "mode_of_payment column already exists";
}

// Check if mode_of_release column exists
$colRes = $conn->query("SHOW COLUMNS FROM requests LIKE 'mode_of_release'");
if ($colRes && $colRes->num_rows === 0) {
    // Add mode_of_release column
    $sql = "ALTER TABLE requests ADD COLUMN mode_of_release VARCHAR(20) DEFAULT 'Pickup' AFTER mode_of_payment";
    if ($conn->query($sql)) {
        $results[] = "Added mode_of_release column to requests table";
    } else {
        $results[] = "Error adding mode_of_release column: " . $conn->error;
    }
} else {
    $results[] = "mode_of_release column already exists";
}

echo json_encode(['success' => true, 'results' => $results]);
$conn->close();
?>
