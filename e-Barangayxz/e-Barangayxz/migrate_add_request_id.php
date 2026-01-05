<?php
// migrate_add_request_id.php
// Run this once to add request_id to existing tables

include 'db.php';

echo "<h3>Migration: Adding request_id columns to tables</h3>";

// Add request_id to business_permit table
$sql1 = "ALTER TABLE business_permit ADD COLUMN request_id INT AFTER id";
if ($conn->query($sql1)) {
    echo "<p>✓ Added request_id to business_permit</p>";
} else {
    echo "<p>✗ business_permit: " . $conn->error . "</p>";
}

// Add request_id to solo_parent table (if it doesn't have it)
$sql2 = "ALTER TABLE solo_parent MODIFY COLUMN request_id INT NOT NULL FIRST";
if ($conn->query($sql2)) {
    echo "<p>✓ Updated request_id in solo_parent</p>";
} else {
    echo "<p>Note: solo_parent: " . $conn->error . "</p>";
}

// Add request_id to no_derogatory table (if it doesn't have it)
$sql3 = "ALTER TABLE no_derogatory MODIFY COLUMN request_id INT NOT NULL FIRST";
if ($conn->query($sql3)) {
    echo "<p>✓ Updated request_id in no_derogatory</p>";
} else {
    echo "<p>Note: no_derogatory: " . $conn->error . "</p>";
}

// Now link existing records
echo "<br><h4>Linking existing records...</h4>";

// For business_permit records that don't have a request_id
$linkBP = "
UPDATE business_permit bp
LEFT JOIN requests r ON r.resident_id = bp.resident_id 
    AND r.document_type = 'Business Permit' 
    AND DATE(r.requested_at) = DATE(bp.created_at)
SET bp.request_id = r.id
WHERE bp.request_id IS NULL OR bp.request_id = 0
";
if ($conn->query($linkBP)) {
    echo "<p>✓ Linked business_permit records (affected: " . $conn->affected_rows . ")</p>";
} else {
    echo "<p>✗ Link business_permit: " . $conn->error . "</p>";
}

echo "<br><p><strong>Migration complete!</strong></p>";
echo "<p><a href='sidebar-requests.php'>Go to Requests Dashboard</a></p>";

$conn->close();
?>
