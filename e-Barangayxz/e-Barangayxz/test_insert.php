<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/plain');

include 'db.php';

echo "Database connected successfully\n";

$staff_id = 0;
$action_type = 'Login';
$action_details = 'Super Admin logged in';

$sql = "INSERT INTO activity_log (staff_id, action_type, action_details, affected_staff_id, timestamp) 
        VALUES (?, ?, ?, NULL, NOW())";

echo "Preparing statement...\n";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Prepare failed: " . $conn->error . "\n");
}

echo "Binding parameters...\n";
$stmt->bind_param("iss", $staff_id, $action_type, $action_details);

echo "Executing...\n";
if ($stmt->execute()) {
    echo "SUCCESS! Insert ID: " . $stmt->insert_id . "\n";
} else {
    echo "FAILED: " . $stmt->error . "\n";
}

$stmt->close();
$conn->close();
?>
