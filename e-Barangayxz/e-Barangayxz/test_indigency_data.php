<?php
include 'db.php';

$request_id = 6;

echo "Checking request #REQ-6 (id: $request_id)\n\n";

// Check requests table
$result = $conn->query("SELECT * FROM requests WHERE id = $request_id");
if ($row = $result->fetch_assoc()) {
    echo "=== REQUESTS TABLE ===\n";
    print_r($row);
    echo "\n";
} else {
    echo "Request not found!\n";
}

// Check certificate_of_indigency table
$result2 = $conn->query("SELECT * FROM certificate_of_indigency WHERE request_id = $request_id");
if ($row2 = $result2->fetch_assoc()) {
    echo "=== CERTIFICATE_OF_INDIGENCY TABLE ===\n";
    print_r($row2);
    echo "\n";
} else {
    echo "No indigency certificate data found!\n";
}

// Test the actual query used by get_clearance_details.php
$query = "
    SELECT 
        r.id,
        r.document_type,
        r.status,
        ci.last_name,
        ci.first_name,
        ci.middle_name,
        ci.suffix,
        ci.date_of_birth,
        ci.civil_status,
        ci.complete_address,
        ci.contact_number,
        ci.specific_purpose as purpose,
        ci.estimated_monthly_income,
        ci.number_of_dependents,
        ci.valid_id_path,
        ci.proof_of_income_path,
        ci.mode_of_release
    FROM requests r
    LEFT JOIN certificate_of_indigency ci ON r.id = ci.request_id
    WHERE r.id = $request_id
";

$result3 = $conn->query($query);
if ($row3 = $result3->fetch_assoc()) {
    echo "=== JOINED QUERY RESULT ===\n";
    print_r($row3);
} else {
    echo "Joined query returned no data!\n";
}

$conn->close();
?>
