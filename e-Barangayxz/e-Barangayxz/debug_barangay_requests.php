<?php
require_once 'db.php';

echo "=== DEBUGGING REQUESTS ===\n\n";

// Check requests table
$result = $conn->query("SELECT id, resident_id, document_type, status, requested_at FROM requests ORDER BY requested_at DESC LIMIT 5");
echo "Last 5 requests in requests table:\n";
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "ID: {$row['id']}, Resident: {$row['resident_id']}, Doc: {$row['document_type']}, Status: {$row['status']}, Date: {$row['requested_at']}\n";
    }
} else {
    echo "No requests found or error: " . $conn->error . "\n";
}

echo "\n";

// Check barangay_id table
$result2 = $conn->query("SELECT id, request_id, resident_id, last_name, first_name FROM barangay_id ORDER BY created_at DESC LIMIT 5");
echo "Last 5 entries in barangay_id table:\n";
if ($result2 && $result2->num_rows > 0) {
    while ($row = $result2->fetch_assoc()) {
        echo "ID: {$row['id']}, Request ID: {$row['request_id']}, Resident: {$row['resident_id']}, Name: {$row['first_name']} {$row['last_name']}\n";
    }
} else {
    echo "No barangay_id entries found or error: " . $conn->error . "\n";
}

echo "\n";

// Test the actual query from sidebar-requests.php
$testQuery = "
  SELECT 
    r.id,
    r.resident_id,
    r.document_type,
    r.status,
    r.requested_at,
    r.payment_status,
    r.mode_of_release,
    res.first_name,
    res.last_name
  FROM requests r
  LEFT JOIN residents res ON r.resident_id = res.id
  LEFT JOIN barangay_id bid ON r.id = bid.request_id
  WHERE r.document_type = 'Barangay ID'
  ORDER BY r.requested_at DESC
  LIMIT 5
";

echo "Testing query for Barangay ID requests:\n";
$result3 = $conn->query($testQuery);
if ($result3 && $result3->num_rows > 0) {
    while ($row = $result3->fetch_assoc()) {
        echo "ID: {$row['id']}, Name: {$row['first_name']} {$row['last_name']}, Doc: {$row['document_type']}, Status: {$row['status']}\n";
    }
} else {
    echo "No results or error: " . $conn->error . "\n";
}
