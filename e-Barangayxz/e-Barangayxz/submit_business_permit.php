<?php
// submit_business_permit.php
error_reporting(E_ALL);
ini_set('display_errors', 0);
session_start();
header('Content-Type: application/json');

include 'db.php';
include_once 'add_resident_activity.php';

// Check if resident is logged in
if (!isset($_SESSION['resident_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$resident_id = $_SESSION['resident_id'];

// Get form data
$firstName = trim($_POST['firstName'] ?? '');
$lastName = trim($_POST['lastName'] ?? '');
$middleName = trim($_POST['middleName'] ?? '');
$suffix = trim($_POST['suffix'] ?? '');
$dateOfBirth = trim($_POST['dateOfBirth'] ?? '');
$civilStatus = trim($_POST['civilStatus'] ?? '');
$completeAddress = trim($_POST['completeAddress'] ?? '');
$contactNumber = trim($_POST['contactNumber'] ?? '');
$businessName = trim($_POST['businessName'] ?? '');
$businessType = trim($_POST['businessType'] ?? '');
$businessLocation = trim($_POST['businessLocation'] ?? '');
$modeOfRelease = trim($_POST['modeOfRelease'] ?? '');
$modeOfPayment = trim($_POST['modeOfPayment'] ?? 'GCash');
$documentType = trim($_POST['document_type'] ?? 'Business Permit');

// Debug logging
error_log('Business Permit Submit - resident_id: ' . $resident_id . ', name: ' . $firstName . ' ' . $lastName . ', business: ' . $businessName);

// Validate required fields
if (!$firstName || !$lastName || !$dateOfBirth || !$completeAddress || !$contactNumber || !$businessName || !$businessType || !$businessLocation || !$modeOfRelease || !$modeOfPayment) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Create business_permit table if it doesn't exist
$tableCreate = "CREATE TABLE IF NOT EXISTS business_permit (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    resident_id INT NOT NULL,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    middle_name VARCHAR(100),
    suffix VARCHAR(50),
    date_of_birth DATE,
    civil_status VARCHAR(50),
    complete_address TEXT,
    contact_number VARCHAR(20),
    business_name VARCHAR(255),
    business_type VARCHAR(100),
    business_location TEXT,
    valid_id_path TEXT,
    ownership_proof_path TEXT,
    mode_of_release VARCHAR(50),
    status VARCHAR(20) DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (resident_id) REFERENCES residents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if (!$conn->query($tableCreate)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    exit;
}

// Insert into requests table first
// Ensure mode_of_payment column exists
$colRes = $conn->query("SHOW COLUMNS FROM requests LIKE 'mode_of_payment'");
if ($colRes && $colRes->num_rows === 0) {
    $conn->query("ALTER TABLE requests ADD COLUMN mode_of_payment VARCHAR(20) DEFAULT 'GCash' AFTER document_type");
}

$stmt = $conn->prepare("INSERT INTO requests (resident_id, document_type, mode_of_payment, status, mode_of_release) VALUES (?, ?, ?, 'pending', ?)");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    exit;
}

$stmt->bind_param('isss', $resident_id, $documentType, $modeOfPayment, $modeOfRelease);
if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to create request: ' . $stmt->error]);
    exit;
}

$requestId = $stmt->insert_id;
error_log('Business Permit - Request created, ID: ' . $requestId);
$stmt->close();

// Log activity for resident
addResidentActivity(
    $conn,
    $resident_id,
    $requestId,
    'request_submitted',
    'Document Request Submitted',
    "Your request for Business Permit has been submitted and is pending review.",
    null,
    'Business Permit'
);

// Insert business permit with request_id
// Handle file uploads
$validIdPath = null;
$ownershipProofPath = null;

try {
    if (isset($_FILES['validId']) && $_FILES['validId']['error'] === UPLOAD_ERR_OK) {
        $allowedExts = ['jpg','jpeg','png','pdf'];
        $ext = strtolower(pathinfo($_FILES['validId']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowedExts)) {
            $dir = 'uploads/business_permit_ids/';
            if (!is_dir($dir)) mkdir($dir, 0777, true);
            $validIdPath = $dir . 'valid_id_' . $requestId . '_' . time() . '.' . $ext;
            move_uploaded_file($_FILES['validId']['tmp_name'], $validIdPath);
        }
    }
    if (isset($_FILES['proofOfOwnership']) && $_FILES['proofOfOwnership']['error'] === UPLOAD_ERR_OK) {
        $allowedExts2 = ['jpg','jpeg','png','pdf'];
        $ext2 = strtolower(pathinfo($_FILES['proofOfOwnership']['name'], PATHINFO_EXTENSION));
        if (in_array($ext2, $allowedExts2)) {
            $dir2 = 'uploads/business_permit_proofs/';
            if (!is_dir($dir2)) mkdir($dir2, 0777, true);
            $ownershipProofPath = $dir2 . 'proof_' . $requestId . '_' . time() . '.' . $ext2;
            move_uploaded_file($_FILES['proofOfOwnership']['tmp_name'], $ownershipProofPath);
        }
    }
} catch (Exception $e) {
    // continue without blocking request
}

$stmt = $conn->prepare("INSERT INTO business_permit (request_id, resident_id, first_name, last_name, middle_name, suffix, date_of_birth, civil_status, complete_address, contact_number, business_name, business_type, business_location, valid_id_path, ownership_proof_path, mode_of_release) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    exit;
}

$stmt->bind_param('iissssssssssssss', $requestId, $resident_id, $firstName, $lastName, $middleName, $suffix, $dateOfBirth, $civilStatus, $completeAddress, $contactNumber, $businessName, $businessType, $businessLocation, $validIdPath, $ownershipProofPath, $modeOfRelease);

error_log('Business Permit - About to execute insert');

if (!$stmt->execute()) {
    error_log('Business Permit - Insert failed: ' . $stmt->error);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to insert data: ' . $stmt->error]);
    exit;
}

error_log('Business Permit - Insert successful');
$stmt->close();

// Log activity for request
$logStmt = $conn->prepare("INSERT INTO activity_log (staff_id, action_type, action_details, timestamp) VALUES (0, ?, ?, NOW())");
if ($logStmt) {
    $actionType = 'Request Submitted';
    $actionDetails = "Resident submitted Business Permit request (#" . $requestId . ")";
    $logStmt->bind_param('ss', $actionType, $actionDetails);
    @$logStmt->execute();
    $logStmt->close();
}

// Notify staff about new document request
include_once 'add_staff_notification.php';
$resident_name = trim($firstName . ' ' . $lastName);
addStaffNotification(
    $conn,
    'document_request',
    'New Document Request',
    "$resident_name has requested Business Permit",
    $requestId,
    'request'
);

http_response_code(200);
echo json_encode([
    'success' => true,
    'message' => 'Business Permit request submitted successfully',
    'request_id' => $requestId
]);
$conn->close();
?>
