<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['staff_id'])) {
    die('Unauthorized');
}

echo "<h2>Database Diagnosis</h2>";
echo "<hr>";

// Check requests table
$sql = "SELECT id, document_type, mode_of_release FROM requests LIMIT 10";
$result = $conn->query($sql);
echo "<h3>Requests Table Sample:</h3>";
if ($result) {
    echo "<table border='1' cellpadding='5'><tr><th>ID</th><th>Document Type</th><th>Mode of Release</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr><td>{$row['id']}</td><td>{$row['document_type']}</td><td>{$row['mode_of_release']}</td></tr>";
    }
    echo "</table>";
}

echo "<br><hr>";

// Check business_permit table
$sql = "SELECT id, request_id, mode_of_release FROM business_permit LIMIT 5";
$result = $conn->query($sql);
echo "<h3>Business Permit Table Sample:</h3>";
if ($result) {
    echo "<table border='1' cellpadding='5'><tr><th>ID</th><th>Request ID</th><th>Mode of Release</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr><td>{$row['id']}</td><td>{$row['request_id']}</td><td>{$row['mode_of_release']}</td></tr>";
    }
    echo "</table>";
}

echo "<br><hr>";

// Check barangay_clearance table
$sql = "SELECT id, request_id, mode_of_release FROM barangay_clearance LIMIT 5";
$result = $conn->query($sql);
echo "<h3>Barangay Clearance Table Sample:</h3>";
if ($result) {
    echo "<table border='1' cellpadding='5'><tr><th>ID</th><th>Request ID</th><th>Mode of Release</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr><td>{$row['id']}</td><td>{$row['request_id']}</td><td>{$row['mode_of_release']}</td></tr>";
    }
    echo "</table>";
} else {
    echo "Error: " . $conn->error;
}

echo "<br><hr>";

// Count requests with N/A
$sql = "SELECT COUNT(*) as count FROM requests WHERE mode_of_release = 'N/A' OR mode_of_release IS NULL";
$result = $conn->query($sql);
$row = $result->fetch_assoc();
echo "<p><strong>Requests with N/A or NULL: {$row['count']}</strong></p>";

?>
