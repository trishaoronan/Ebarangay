<?php
// submit_certificate_residency.php
session_start();
header('Content-Type: application/json');

// Check if resident is logged in
if (!isset($_SESSION['resident_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please log in first.']);
    exit;
}

include 'db.php';
include_once 'add_resident_activity.php';

$resident_id = $_SESSION['resident_id'];

// Get form data
$last_name = $_POST['last_name'] ?? '';
$first_name = $_POST['first_name'] ?? '';
$middle_name = $_POST['middle_name'] ?? '';
$suffix = $_POST['suffix'] ?? '';
$date_of_birth = $_POST['date_of_birth'] ?? '';
$civil_status = $_POST['civil_status'] ?? '';
$complete_address = $_POST['complete_address'] ?? '';
$date_started_residing = $_POST['date_started_residing'] ?? '';
$household_head_name = $_POST['household_head_name'] ?? '';
$contact_number = $_POST['contact_number'] ?? '';
$purpose = $_POST['purpose'] ?? '';
$mode_of_release = $_POST['mode_of_release'] ?? '';

// Validate required fields
if (empty($last_name) || empty($first_name) || empty($date_of_birth) || empty($civil_status) || 
    empty($complete_address) || empty($date_started_residing) || empty($contact_number) || 
    empty($purpose) || empty($mode_of_release)) {
    echo json_encode(['success' => false, 'message' => 'All required fields must be filled.']);
    exit;
}

// Handle valid ID upload
$valid_id_path = null;
if (isset($_FILES['valid_id']) && $_FILES['valid_id']['error'] === UPLOAD_ERR_OK) {
    $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
    $filename = $_FILES['valid_id']['name'];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    if (!in_array($ext, $allowed)) {
        echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, and PDF allowed.']);
        exit;
    }
    
    // Create uploads directory if it doesn't exist
    $upload_dir = 'uploads/documents/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $new_filename = 'residency_id_' . $resident_id . '_' . time() . '.' . $ext;
    $valid_id_path = $upload_dir . $new_filename;
    
    if (!move_uploaded_file($_FILES['valid_id']['tmp_name'], $valid_id_path)) {
        echo json_encode(['success' => false, 'message' => 'Failed to upload valid ID.']);
        exit;
    }
}

// Insert into requests table
$document_type = 'Certificate of Residency';
$status = 'pending';
// Don't overwrite - already set from form data at line 29
$mode_of_payment = $_POST['modeOfPayment'] ?? 'GCash';

// Ensure mode_of_payment column exists
$colRes = $conn->query("SHOW COLUMNS FROM requests LIKE 'mode_of_payment'");
if ($colRes && $colRes->num_rows === 0) {
    $conn->query("ALTER TABLE requests ADD COLUMN mode_of_payment VARCHAR(20) DEFAULT 'GCash' AFTER document_type");
}

$stmt = $conn->prepare("INSERT INTO requests (resident_id, document_type, mode_of_payment, status, requested_at, mode_of_release) VALUES (?, ?, ?, ?, NOW(), ?)");
$stmt->bind_param('issss', $resident_id, $document_type, $mode_of_payment, $status, $mode_of_release);

if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Failed to create request: ' . $stmt->error]);
    $stmt->close();
    exit;
}

$request_id = $conn->insert_id;
$stmt->close();

// Log activity for resident
addResidentActivity(
    $conn,
    $resident_id,
    $request_id,
    'request_submitted',
    'Document Request Submitted',
    "Your request for Certificate of Residency has been submitted and is pending review.",
    null,
    'Certificate of Residency'
);

// Insert into certificate_of_residency table
$stmt2 = $conn->prepare("INSERT INTO certificate_of_residency 
    (request_id, resident_id, last_name, first_name, middle_name, suffix, date_of_birth, civil_status, 
     complete_address, date_started_residing, household_head_name, contact_number, purpose, valid_id_path, mode_of_release) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

$stmt2->bind_param('iisssssssssssss', 
    $request_id, $resident_id, $last_name, $first_name, $middle_name, $suffix, 
    $date_of_birth, $civil_status, $complete_address, $date_started_residing, 
    $household_head_name, $contact_number, $purpose, $valid_id_path, $mode_of_release);

if ($stmt2->execute()) {
    // Notify staff about new document request
    include_once 'add_staff_notification.php';
    $resident_name = trim($first_name . ' ' . $last_name);
    addStaffNotification(
        $conn,
        'document_request',
        'New Document Request',
        "$resident_name has requested Certificate of Residency",
        $request_id,
        'request'
    );
    
    echo json_encode([
        'success' => true, 
        'message' => 'Certificate of Residency request submitted successfully!',
        'request_id' => $request_id
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to save certificate details: ' . $stmt2->error]);
}

$stmt2->close();
$conn->close();
?>
