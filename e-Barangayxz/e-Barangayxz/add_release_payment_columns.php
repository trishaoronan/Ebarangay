<?php
require_once 'db.php';

// Add mode_of_release column if it doesn't exist
$sql1 = "ALTER TABLE requests ADD COLUMN mode_of_release VARCHAR(50) DEFAULT 'N/A'";
if ($conn->query($sql1)) {
    echo "mode_of_release column added successfully<br>";
} else {
    if (strpos($conn->error, 'Duplicate column') !== false) {
        echo "mode_of_release column already exists<br>";
    } else {
        echo "Error adding mode_of_release: " . $conn->error . "<br>";
    }
}

// Add payment_status column if it doesn't exist
$sql2 = "ALTER TABLE requests ADD COLUMN payment_status VARCHAR(50) DEFAULT 'Unpaid'";
if ($conn->query($sql2)) {
    echo "payment_status column added successfully<br>";
} else {
    if (strpos($conn->error, 'Duplicate column') !== false) {
        echo "payment_status column already exists<br>";
    } else {
        echo "Error adding payment_status: " . $conn->error . "<br>";
    }
}

echo "<br>Done! You can now delete this file.";
?>
