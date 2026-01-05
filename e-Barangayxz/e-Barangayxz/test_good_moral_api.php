<?php
// test_good_moral_api.php
session_start();
$_SESSION['staff_id'] = 1; // Temporary for testing

include 'db.php';

$request_id = 10; // Good Moral request ID from screenshot

// Get document type
$typeQuery = "SELECT document_type FROM requests WHERE id = ?";
$typeStmt = $conn->prepare($typeQuery);
$typeStmt->bind_param('i', $request_id);
$typeStmt->execute();
$typeResult = $typeStmt->get_result();
$documentType = null;

if ($typeRow = $typeResult->fetch_assoc()) {
    $documentType = $typeRow['document_type'];
}
$typeStmt->close();

echo "Document Type: " . $documentType . "\n\n";

// Query Good Moral data
if ($documentType === 'Good Moral Character Certificate') {
    $query = "
        SELECT 
            r.id,
            r.document_type,
            r.status,
            r.notes,
            r.requested_at,
            r.given_at,
            res.first_name as resident_first_name,
            res.last_name as resident_last_name,
            res.email,
            res.mobile as resident_mobile,
            res.street,
            res.municipality,
            res.barangay,
            gm.last_name,
            gm.first_name,
            gm.middle_name,
            gm.suffix,
            gm.date_of_birth,
            gm.civil_status,
            gm.complete_address,
            gm.contact_number,
            gm.specific_purpose as purpose,
            gm.valid_id_path,
            gm.mode_of_release,
            gm.created_at
        FROM requests r
        LEFT JOIN residents res ON r.resident_id = res.id
        LEFT JOIN good_moral gm ON r.id = gm.request_id
        WHERE r.id = ?
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $request_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo "Data found:\n";
        print_r($row);
    } else {
        echo "No data found\n";
    }
    $stmt->close();
} else {
    echo "Not a Good Moral request\n";
}

$conn->close();
?>
