<?php
// submit_good_moral.php
session_start();
header('Content-Type: application/json');

// Check if resident is logged in
if (!isset($_SESSION['resident_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit;
}

include 'db.php';
include_once 'add_resident_activity.php';

// Start transaction
$conn->begin_transaction();

try {
    // Get resident ID from session
    $resident_id = $_SESSION['resident_id'];
    
    // Validate required fields
    $required_fields = ['lastName', 'firstName', 'dateOfBirth', 'civilStatus', 'completeAddress', 'contactNumber', 'specificPurpose', 'modeOfRelease', 'modeOfPayment'];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
            throw new Exception("Missing required field: $field");
        }
    }
    
    // Handle file upload for valid ID
    if (!isset($_FILES['validId']) || $_FILES['validId']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Valid ID upload is required');
    }
    
    $validId = $_FILES['validId'];
    $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
    $maxFileSize = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($validId['type'], $allowedTypes)) {
        throw new Exception('Invalid file type. Only JPG, PNG, and PDF are allowed.');
    }
    
    if ($validId['size'] > $maxFileSize) {
        throw new Exception('File size too large. Maximum 5MB allowed.');
    }
    
    // Create upload directory if not exists
    $uploadDir = 'uploads/good_moral_ids/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    // Generate unique filename
    $fileExtension = pathinfo($validId['name'], PATHINFO_EXTENSION);
    $validIdFileName = 'goodmoral_' . $resident_id . '_' . time() . '.' . $fileExtension;
    $validIdPath = $uploadDir . $validIdFileName;
    
    if (!move_uploaded_file($validId['tmp_name'], $validIdPath)) {
        throw new Exception('Failed to upload valid ID');
    }
    
    // Insert into requests table
    $documentType = 'Good Moral Character Certificate';
    $status = 'pending';
    $requestedAt = date('Y-m-d H:i:s');
    $modeOfReleaseVal = $_POST['modeOfRelease'];
    $modeOfPaymentVal = $_POST['modeOfPayment'];
    
    // Ensure mode_of_payment column exists
    $colRes = $conn->query("SHOW COLUMNS FROM requests LIKE 'mode_of_payment'");
    if ($colRes && $colRes->num_rows === 0) {
        $conn->query("ALTER TABLE requests ADD COLUMN mode_of_payment VARCHAR(20) DEFAULT 'GCash' AFTER document_type");
    }
    
    $stmt = $conn->prepare("INSERT INTO requests (resident_id, document_type, mode_of_payment, status, requested_at, mode_of_release) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssss", $resident_id, $documentType, $modeOfPaymentVal, $status, $requestedAt, $modeOfReleaseVal);
    $stmt->execute();
    $request_id = $conn->insert_id;
    $stmt->close();

    // Log activity for resident
    addResidentActivity(
        $conn,
        $resident_id,
        $request_id,
        'request_submitted',
        'Document Request Submitted',
        "Your request for Good Moral Character Certificate has been submitted and is pending review.",
        null,
        'Good Moral Character Certificate'
    );
    
    // Insert into good_moral table
    $stmt = $conn->prepare("
        INSERT INTO good_moral (
            request_id, resident_id, last_name, first_name, middle_name, suffix,
            date_of_birth, civil_status, complete_address, contact_number,
            specific_purpose, valid_id_path, mode_of_release
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $lastName = trim($_POST['lastName']);
    $firstName = trim($_POST['firstName']);
    $middleName = isset($_POST['middleName']) ? trim($_POST['middleName']) : null;
    $suffix = isset($_POST['suffix']) ? trim($_POST['suffix']) : null;
    $dateOfBirth = $_POST['dateOfBirth'];
    $civilStatus = $_POST['civilStatus'];
    $completeAddress = trim($_POST['completeAddress']);
    $contactNumber = trim($_POST['contactNumber']);
    $specificPurpose = trim($_POST['specificPurpose']);
    $modeOfRelease = $_POST['modeOfRelease'];
    
    $stmt->bind_param(
        "iisssssssssss",
        $request_id,
        $resident_id,
        $lastName,
        $firstName,
        $middleName,
        $suffix,
        $dateOfBirth,
        $civilStatus,
        $completeAddress,
        $contactNumber,
        $specificPurpose,
        $validIdPath,
        $modeOfRelease
    );
    
    $stmt->execute();
    $stmt->close();
    
    // TODO: Enable notifications after fixing staff_id column issue
    /*
    // Create notifications - disabled temporarily
    // ...
    */
    
    // Commit transaction
    $conn->commit();
    
    // Notify staff about new document request
    include_once 'add_staff_notification.php';
    $resident_name = trim($firstName . ' ' . $lastName);
    addStaffNotification(
        $conn,
        'document_request',
        'New Document Request',
        "$resident_name has requested Good Moral Certificate",
        $request_id,
        'request'
    );
    
    echo json_encode([
        'success' => true,
        'message' => 'Good Moral Character Certificate request submitted successfully!',
        'request_id' => $request_id
    ]);
    
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    
    // Delete uploaded file if exists
    if (isset($validIdPath) && file_exists($validIdPath)) {
        unlink($validIdPath);
    }
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>
