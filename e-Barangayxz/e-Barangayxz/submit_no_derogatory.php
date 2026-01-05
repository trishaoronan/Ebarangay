<?php
// submit_no_derogatory.php
session_start();
header('Content-Type: application/json');

include 'db.php';
include_once 'add_resident_activity.php';

if (!isset($_SESSION['resident_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit;
}

$conn->begin_transaction();

try {
    $resident_id = $_SESSION['resident_id'];

    // Validate required fields
    $required = ['lastName','firstName','dateOfBirth','placeOfBirth','completeAddress','contactNumber','specificPurpose','modeOfRelease','modeOfPayment'];
    foreach ($required as $field) {
        if (!isset($_POST[$field]) || trim($_POST[$field]) === '') {
            throw new Exception('Missing required field: ' . $field);
        }
    }

    // Validate valid ID upload
    if (!isset($_FILES['validId']) || $_FILES['validId']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Valid ID upload is required');
    }

    $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
    $maxFileSize = 5 * 1024 * 1024; // 5MB

    $validId = $_FILES['validId'];
    if (!in_array($validId['type'], $allowedTypes)) {
        throw new Exception('Invalid ID file type. Only JPG, PNG, and PDF are allowed.');
    }
    if ($validId['size'] > $maxFileSize) {
        throw new Exception('ID file size too large. Maximum 5MB allowed.');
    }

    // Upload directory
    $idDir = 'uploads/no_derogatory_ids/';
    if (!file_exists($idDir)) mkdir($idDir, 0777, true);

    // Save valid ID
    $idExt = pathinfo($validId['name'], PATHINFO_EXTENSION);
    $validIdPath = $idDir . 'ndid_' . $resident_id . '_' . time() . '.' . $idExt;
    if (!move_uploaded_file($validId['tmp_name'], $validIdPath)) {
        throw new Exception('Failed to upload valid ID');
    }

    // Ensure no_derogatory table exists
    $tableCreate = "CREATE TABLE IF NOT EXISTS no_derogatory (
        id INT AUTO_INCREMENT PRIMARY KEY,
        request_id INT NOT NULL,
        resident_id INT NOT NULL,
        last_name VARCHAR(100),
        first_name VARCHAR(100),
        middle_name VARCHAR(100),
        suffix VARCHAR(50),
        date_of_birth DATE,
        place_of_birth VARCHAR(200),
        complete_address TEXT,
        contact_number VARCHAR(20),
        specific_purpose TEXT,
        valid_id_path TEXT,
        mode_of_release VARCHAR(50),
        status VARCHAR(20) DEFAULT 'pending',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (resident_id) REFERENCES residents(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    if (!$conn->query($tableCreate)) {
        throw new Exception('Database error creating no_derogatory table');
    }

    // Insert into requests table
    $documentType = 'Certificate of No Derogatory Record';
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
    if (!$stmt) throw new Exception('Database error preparing request insert');
    $stmt->bind_param('isssss', $resident_id, $documentType, $modeOfPaymentVal, $status, $requestedAt, $modeOfReleaseVal);
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
        "Your request for Certificate of No Derogatory Record has been submitted and is pending review.",
        null,
        'Certificate of No Derogatory Record'
    );

    // Prepare values
    $lastName = trim($_POST['lastName']);
    $firstName = trim($_POST['firstName']);
    $middleName = isset($_POST['middleName']) ? trim($_POST['middleName']) : null;
    $suffix = isset($_POST['suffix']) ? trim($_POST['suffix']) : null;
    $dateOfBirth = $_POST['dateOfBirth'];
    $placeOfBirth = trim($_POST['placeOfBirth']);
    $completeAddress = trim($_POST['completeAddress']);
    $contactNumber = trim($_POST['contactNumber']);
    $specificPurpose = trim($_POST['specificPurpose']);
    $modeOfRelease = $_POST['modeOfRelease'];

    // Insert into no_derogatory
    $stmt = $conn->prepare("INSERT INTO no_derogatory (request_id, resident_id, last_name, first_name, middle_name, suffix, date_of_birth, place_of_birth, complete_address, contact_number, specific_purpose, valid_id_path, mode_of_release) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) throw new Exception('Database error preparing no_derogatory insert');

    $stmt->bind_param(
        'iisssssssssss',
        $request_id,
        $resident_id,
        $lastName,
        $firstName,
        $middleName,
        $suffix,
        $dateOfBirth,
        $placeOfBirth,
        $completeAddress,
        $contactNumber,
        $specificPurpose,
        $validIdPath,
        $modeOfRelease
    );

    $stmt->execute();
    $stmt->close();

    $conn->commit();

    // Notify staff about new document request
    include_once 'add_staff_notification.php';
    $resident_name = trim($firstName . ' ' . $lastName);
    addStaffNotification(
        $conn,
        'document_request',
        'New Document Request',
        "$resident_name has requested No Derogatory Record Certificate",
        $request_id,
        'request'
    );

    echo json_encode([
        'success' => true,
        'message' => 'Your Certificate of No Derogatory Record request has been submitted successfully. Verification will be conducted, and you will be notified when the certificate is ready.',
        'request_id' => $request_id
    ]);

} catch (Exception $e) {
    $conn->rollback();
    if (isset($validIdPath) && file_exists($validIdPath)) {
        unlink($validIdPath);
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>
