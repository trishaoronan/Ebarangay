<?php
session_start();
require_once 'db.php';

// Check if staff is logged in
if (!isset($_SESSION['staff_id'])) {
    die('Unauthorized');
}

echo "<h2>Database Migration</h2>";

// Add mode_of_release column
$sql = "ALTER TABLE requests ADD COLUMN IF NOT EXISTS mode_of_release VARCHAR(50) DEFAULT 'N/A'";
if ($conn->query($sql)) {
    echo "✓ mode_of_release column added/exists<br>";
} else {
    if (strpos($conn->error, 'Duplicate') !== false) {
        echo "✓ mode_of_release column already exists<br>";
    } else {
        echo "✗ Error: " . $conn->error . "<br>";
    }
}

// Add payment_status column
$sql = "ALTER TABLE requests ADD COLUMN IF NOT EXISTS payment_status VARCHAR(50) DEFAULT 'Unpaid'";
if ($conn->query($sql)) {
    echo "✓ payment_status column added/exists<br>";
} else {
    if (strpos($conn->error, 'Duplicate') !== false) {
        echo "✓ payment_status column already exists<br>";
    } else {
        echo "✗ Error: " . $conn->error . "<br>";
    }
}

// Set some default values
$conn->query("UPDATE requests SET mode_of_release = 'Download' WHERE mode_of_release = 'N/A' LIMIT 100");
echo "✓ Updated default release modes<br>";

echo "<p>Migration complete! <a href='sidebar-requests.php'>Go back to requests</a></p>";
?>
