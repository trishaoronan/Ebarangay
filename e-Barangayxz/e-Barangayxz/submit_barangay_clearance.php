<?php
// submit_barangay_clearance.php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['resident_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please log in to submit a request.']);
    exit;
}

include 'db.php';
include_once 'add_resident_activity.php';

$resident_id = $_SESSION['resident_id'];

// Debug: Log received data
error_log("POST Data: " . print_r($_POST, true));
error_log("FILES Data: " . print_r($_FILES, true));

// Validate required fields
$errors = [];
if (!isset($_POST['lastName']) || trim($_POST['lastName']) === '') $errors[] = 'Last Name';
if (!isset($_POST['firstName']) || trim($_POST['firstName']) === '') $errors[] = 'First Name';
if (!isset($_POST['dateOfBirth']) || trim($_POST['dateOfBirth']) === '') $errors[] = 'Date of Birth';
if (!isset($_POST['civilStatus']) || trim($_POST['civilStatus']) === '') $errors[] = 'Civil Status';
if (!isset($_POST['completeAddress']) || trim($_POST['completeAddress']) === '') $errors[] = 'Complete Address';
if (!isset($_POST['contactNumber']) || trim($_POST['contactNumber']) === '') $errors[] = 'Contact Number';
if (!isset($_POST['purpose']) || trim($_POST['purpose']) === '') $errors[] = 'Purpose';
if (!isset($_POST['modeOfRelease']) || trim($_POST['modeOfRelease']) === '') $errors[] = 'Mode of Release';
if (!isset($_POST['modeOfPayment']) || trim($_POST['modeOfPayment']) === '') $errors[] = 'Mode of Payment';

if (!empty($errors)) {
    error_log("Validation errors: " . implode(', ', $errors));
    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields: ' . implode(', ', $errors)]);
    exit;
}

// Handle file upload
$validIdPath = null;
if (isset($_FILES['validId']) && $_FILES['validId']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = 'uploads/clearance_ids/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $fileExt = strtolower(pathinfo($_FILES['validId']['name'], PATHINFO_EXTENSION));
    $allowedExts = ['jpg', 'jpeg', 'png', 'pdf'];
    
    if (!in_array($fileExt, $allowedExts)) {
        echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, and PDF are allowed.']);
        exit;
    }
    
    if ($_FILES['validId']['size'] > 5242880) { // 5MB limit
        echo json_encode(['success' => false, 'message' => 'File size must not exceed 5MB.']);
        exit;
    }
    
    $fileName = 'clearance_' . $resident_id . '_' . time() . '.' . $fileExt;
    $uploadPath = $uploadDir . $fileName;
    
    if (move_uploaded_file($_FILES['validId']['tmp_name'], $uploadPath)) {
        $validIdPath = $uploadPath;
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to upload file.']);
        exit;
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Please upload a valid ID.']);
    exit;
}

// Start transaction
$conn->begin_transaction();

try {
    // Get mode of payment
    $modeOfPayment = $_POST['modeOfPayment'];
    
    // Check if mode_of_payment column exists, if not add it
    $columnCheck = $conn->query("SHOW COLUMNS FROM requests LIKE 'mode_of_payment'");
    if ($columnCheck->num_rows === 0) {
        $conn->query("ALTER TABLE requests ADD COLUMN mode_of_payment VARCHAR(20) DEFAULT 'GCash' AFTER document_type");
    }
    
    // Insert into requests table
    $stmt = $conn->prepare("INSERT INTO requests (resident_id, document_type, mode_of_payment, status, requested_at, mode_of_release) VALUES (?, 'Barangay Clearance', ?, 'pending', NOW(), ?)");
    if (!$stmt) {
        throw new Exception('Prepare statement failed for requests: ' . $conn->error);
    }
    $stmt->bind_param('iss', $resident_id, $modeOfPayment, $_POST['modeOfRelease']);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to create request: ' . $stmt->error);
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
        "Your request for Barangay Clearance has been submitted and is pending review.",
        null,
        'Barangay Clearance'
    );
    
    // Insert into barangay_clearance table
    $stmt2 = $conn->prepare("INSERT INTO barangay_clearance (request_id, resident_id, last_name, first_name, middle_name, suffix, date_of_birth, civil_status, complete_address, contact_number, purpose, valid_id_path, mode_of_release) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt2) {
        throw new Exception('Prepare statement failed for barangay_clearance: ' . $conn->error);
    }
    
    $lastName = trim($_POST['lastName']);
    $firstName = trim($_POST['firstName']);
    $middleName = trim($_POST['middleName'] ?? '');
    $suffix = trim($_POST['suffix'] ?? '');
    $dateOfBirth = $_POST['dateOfBirth'];
    $civilStatus = $_POST['civilStatus'];
    $completeAddress = trim($_POST['completeAddress']);
    $contactNumber = trim($_POST['contactNumber']);
    $purpose = trim($_POST['purpose']);
    $modeOfRelease = $_POST['modeOfRelease'];
    
    $stmt2->bind_param('iisssssssssss', $request_id, $resident_id, $lastName, $firstName, $middleName, $suffix, $dateOfBirth, $civilStatus, $completeAddress, $contactNumber, $purpose, $validIdPath, $modeOfRelease);
    
    if (!$stmt2->execute()) {
        throw new Exception('Failed to save clearance details: ' . $stmt2->error);
    }
    
    $stmt2->close();
    
    // Notify staff about new document request
    include_once 'add_staff_notification.php';
    $resident_name = trim($firstName . ' ' . $lastName);
    addStaffNotification(
        $conn,
        'document_request',
        'New Document Request',
        "$resident_name has requested Barangay Clearance",
        $request_id,
        'request'
    );
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Barangay Clearance request submitted successfully!',
        'request_id' => $request_id
    ]);
    
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    
    // Delete uploaded file if exists
    if ($validIdPath && file_exists($validIdPath)) {
        unlink($validIdPath);
    }
    
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

$conn->close();
?>
