<?php
// get_clearance_details.php
session_start();
header('Content-Type: application/json');

// Check if staff is logged in
if (!isset($_SESSION['staff_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

include 'db.php';

$request_id = $_GET['request_id'] ?? null;

if (!$request_id) {
    echo json_encode(['success' => false, 'message' => 'Request ID is required']);
    exit;
}

// Extract numeric ID from request string (e.g., "#REQ-13" -> 13)
if (is_string($request_id) && strpos($request_id, '#REQ-') === 0) {
    $request_id = intval(substr($request_id, 5));
}

// First, get the document type
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

// Build query based on document type
if ($documentType === 'Certificate of Indigency') {
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
            ci.mode_of_release,
            ci.created_at
        FROM requests r
        LEFT JOIN residents res ON r.resident_id = res.id
        LEFT JOIN certificate_of_indigency ci ON r.id = ci.request_id
        WHERE r.id = ?
    ";
} elseif ($documentType === 'Good Moral Character Certificate') {
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
} elseif ($documentType === 'Blotter Report') {
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
            br.last_name,
            br.first_name,
            br.middle_name,
            br.suffix,
            br.incident_date,
            NULL as civil_status,
            br.complete_address,
            br.contact_number,
            br.narrative as purpose,
            NULL as valid_id_path,
            br.evidence_paths,
            br.mode_of_release,
            br.created_at
        FROM requests r
        LEFT JOIN residents res ON r.resident_id = res.id
        LEFT JOIN blotter_reports br ON r.id = br.request_id
        WHERE r.id = ?
    ";
} elseif ($documentType === 'Barangay ID') {
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
            bid.last_name,
            bid.first_name,
            bid.middle_name,
            bid.suffix,
            bid.date_of_birth,
            bid.civil_status,
            bid.complete_address,
            bid.contact_number,
            bid.place_of_birth as purpose,
            bid.id_picture_path as valid_id_path,
            bid.proof_of_residency_path as proof_of_residency_path,
            bid.mode_of_release,
            bid.created_at
        FROM requests r
        LEFT JOIN residents res ON r.resident_id = res.id
        LEFT JOIN barangay_id bid ON r.id = bid.request_id
        WHERE r.id = ?
    ";
} elseif ($documentType === 'Low Income Certificate') {
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
            li.last_name,
            li.first_name,
            li.middle_name,
            li.suffix,
            li.date_of_birth,
            li.civil_status,
            li.complete_address,
            li.contact_number,
            li.purpose,
            li.valid_id_path,
            li.mode_of_release,
            li.monthly_income,
            li.household_members,
            li.occupation,
            li.proof_residency_path,
            li.created_at
        FROM requests r
        LEFT JOIN residents res ON r.resident_id = res.id
        LEFT JOIN low_income li ON r.id = li.request_id
        WHERE r.id = ?
    ";
} elseif ($documentType === 'Burial Assistance') {
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
            ba.claimant_last_name as last_name,
            ba.claimant_first_name as first_name,
            ba.claimant_middle_name as middle_name,
            ba.claimant_suffix as suffix,
            ba.deceased_last_name,
            ba.deceased_first_name,
            ba.deceased_middle_name,
            ba.deceased_suffix,
            ba.relationship,
            ba.cause_of_death,
            ba.date_of_death,
            ba.place_of_death,
            ba.contact_number,
            ba.complete_address,
            ba.monthly_income,
            ba.valid_id_path,
            ba.mode_of_release,
            ba.created_at
        FROM requests r
        LEFT JOIN residents res ON r.resident_id = res.id
        LEFT JOIN burial_assistance ba ON r.id = ba.request_id
        WHERE r.id = ?
    ";
} elseif ($documentType === 'Business Permit') {
    // Guard optional columns for Business Permit
    $hasBp = false;
    $tblCheckBp = $conn->query("SHOW TABLES LIKE 'business_permit'");
    if ($tblCheckBp && $tblCheckBp->num_rows > 0) { $hasBp = true; }
    $bpValidIdCol = 'NULL';
    $bpOwnershipCol = 'NULL';
    if ($hasBp) {
        $colValid = $conn->query("SHOW COLUMNS FROM business_permit LIKE 'valid_id_path'");
        if ($colValid && $colValid->num_rows > 0) { $bpValidIdCol = 'bp.valid_id_path'; }
        $colOwn = $conn->query("SHOW COLUMNS FROM business_permit LIKE 'ownership_proof_path'");
        if ($colOwn && $colOwn->num_rows > 0) { $bpOwnershipCol = 'bp.ownership_proof_path'; }
    }
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
            bp.last_name,
            bp.first_name,
            bp.middle_name,
            bp.suffix,
            bp.date_of_birth,
            bp.civil_status,
            bp.complete_address,
            bp.contact_number,
            bp.business_name,
            bp.business_type,
            bp.business_location,
            $bpValidIdCol AS valid_id_path,
            $bpOwnershipCol AS ownership_proof_path,
            bp.mode_of_release,
            bp.created_at
        FROM requests r
        LEFT JOIN residents res ON r.resident_id = res.id
        LEFT JOIN business_permit bp ON r.id = bp.request_id
        WHERE r.id = ?
    ";
} elseif ($documentType === 'Solo Parent Certificate') {
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
            sp.last_name,
            sp.first_name,
            sp.middle_name,
            sp.suffix,
            sp.date_of_birth,
            sp.civil_status,
            sp.complete_address,
            sp.contact_number,
            sp.children_count,
            sp.children_ages,
            sp.reason,
            sp.valid_id_path,
            sp.supporting_paths,
            sp.mode_of_release,
            sp.created_at
        FROM requests r
        LEFT JOIN residents res ON r.resident_id = res.id
        LEFT JOIN solo_parent sp ON r.id = sp.request_id
        WHERE r.id = ?
    ";
} elseif ($documentType === 'Certificate of Residency') {
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
            cr.last_name,
            cr.first_name,
            cr.middle_name,
            cr.suffix,
            cr.date_of_birth,
            cr.civil_status,
            cr.complete_address,
            cr.contact_number,
            cr.purpose,
            cr.date_started_residing,
            cr.household_head_name,
            cr.valid_id_path,
            cr.mode_of_release,
            cr.created_at
        FROM requests r
        LEFT JOIN residents res ON r.resident_id = res.id
        LEFT JOIN certificate_of_residency cr ON r.id = cr.request_id
        WHERE r.id = ?
    ";
} elseif ($documentType === 'Certificate of Non-Employment') {
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
            ne.last_name,
            ne.first_name,
            ne.middle_name,
            ne.suffix,
            ne.date_of_birth,
            ne.civil_status,
            ne.complete_address,
            ne.contact_number,
            ne.purpose,
            ne.valid_id_path,
            ne.mode_of_release,
            ne.created_at
        FROM requests r
        LEFT JOIN residents res ON r.resident_id = res.id
        LEFT JOIN non_employment ne ON r.id = ne.request_id
        WHERE r.id = ?
    ";
} elseif ($documentType === 'No Derogatory Certificate') {
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
            nd.last_name,
            nd.first_name,
            nd.middle_name,
            nd.suffix,
            nd.date_of_birth,
            nd.civil_status,
            nd.complete_address,
            nd.contact_number,
            nd.purpose,
            nd.place_of_birth,
            nd.valid_id_path,
            nd.mode_of_release,
            nd.created_at
        FROM requests r
        LEFT JOIN residents res ON r.resident_id = res.id
        LEFT JOIN no_derogatory nd ON r.id = nd.request_id
        WHERE r.id = ?
    ";
} else {
    // Default to barangay clearance or other documents
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
            bc.last_name,
            bc.first_name,
            bc.middle_name,
            bc.suffix,
            bc.date_of_birth,
            bc.civil_status,
            bc.complete_address,
            bc.contact_number,
            bc.purpose,
            bc.valid_id_path,
            bc.mode_of_release,
            bc.created_at
        FROM requests r
        LEFT JOIN residents res ON r.resident_id = res.id
        LEFT JOIN barangay_clearance bc ON r.id = bc.request_id
        WHERE r.id = ?
    ";
}

$stmt = $conn->prepare($query);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    exit;
}

$stmt->bind_param('i', $request_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo json_encode([
        'success' => true,
        'data' => $row
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Request not found']);
}

$stmt->close();
$conn->close();
?>
