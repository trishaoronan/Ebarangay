<?php
// get_staff.php
header('Content-Type: application/json');
error_reporting(E_ERROR | E_PARSE);

// Include database connection
include 'db.php';

// Ensure profile_pic column exists
$colCheck = $conn->query("SHOW COLUMNS FROM staff_accounts LIKE 'profile_pic'");
if ($colCheck && $colCheck->num_rows === 0) {
    $conn->query("ALTER TABLE staff_accounts ADD COLUMN profile_pic VARCHAR(255) DEFAULT NULL");
}

// Fetch all staff from database (exclude super admin)
$sql = "SELECT staff_id, first_name, middle_name, last_name, suffix, email, contact_number, status, date_created, profile_pic 
        FROM staff_accounts 
        WHERE email != 'superadmin@gmail.com'
        ORDER BY date_created DESC";

$result = $conn->query($sql);

if (!$result) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Query failed: ' . $conn->error]);
    exit;
}

$staff = [];
while ($row = $result->fetch_assoc()) {
    // Build full name
    $fullName = $row['first_name'];
    if (!empty($row['middle_name'])) {
        $fullName .= ' ' . $row['middle_name'];
    }
    $fullName .= ' ' . $row['last_name'];
    if (!empty($row['suffix'])) {
        $fullName .= ' ' . $row['suffix'];
    }
    
    $staff[] = [
        'id' => (int)$row['staff_id'],
        'fullName' => $fullName,
        'firstName' => $row['first_name'],
        'middleName' => $row['middle_name'],
        'lastName' => $row['last_name'],
        'suffix' => $row['suffix'],
        'email' => $row['email'],
        'contactNumber' => $row['contact_number'],
        'role' => 'Administrator',
        'status' => $row['status'],
        'createdAt' => $row['date_created'],
        'profile_pic' => $row['profile_pic']
    ];
}

echo json_encode(['success' => true, 'data' => $staff, 'count' => count($staff)]);

$conn->close();
?>
