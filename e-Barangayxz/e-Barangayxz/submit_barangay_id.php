<?php
// submit_barangay_id.php
error_reporting(E_ALL);
ini_set('display_errors', 0);
session_start();
header('Content-Type: application/json');

include 'db.php';
include_once 'add_resident_activity.php';

// Debug log
$logFile = 'uploads/barangay_id_debug.log';
file_put_contents($logFile, date('Y-m-d H:i:s') . " - Request received\n", FILE_APPEND);

if (!isset($_SESSION['resident_id'])) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Error: Not logged in\n", FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit;
}

file_put_contents($logFile, date('Y-m-d H:i:s') . " - Resident ID: " . $_SESSION['resident_id'] . "\n", FILE_APPEND);
file_put_contents($logFile, date('Y-m-d H:i:s') . " - POST: " . json_encode($_POST) . "\n", FILE_APPEND);
file_put_contents($logFile, date('Y-m-d H:i:s') . " - FILES: " . json_encode(array_keys($_FILES)) . "\n", FILE_APPEND);

$conn->begin_transaction();

try {
    $resident_id = $_SESSION['resident_id'];

    // Validate required fields
    $required = ['lastName','firstName','dateOfBirth','gender','civilStatus','placeOfBirth','citizenship','yearsInBarangay','completeAddress','contactNumber','email','emergencyContactPerson','emergencyContactNumber','emergencyRelationship','modeOfRelease','modeOfPayment'];
    foreach ($required as $field) {
        if (!isset($_POST[$field]) || trim($_POST[$field]) === '') {
            throw new Exception('Missing required field: ' . $field);
        }
    }

    file_put_contents($logFile, date('Y-m-d H:i:s') . " - All required fields present\n", FILE_APPEND);

    // Validate file uploads
    if (!isset($_FILES['validId']) || $_FILES['validId']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Valid Government ID is required');
    }
    if (!isset($_FILES['idPicture']) || $_FILES['idPicture']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('2x2 ID Picture is required');
    }
    if (!isset($_FILES['proofOfResidency']) || $_FILES['proofOfResidency']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Proof of Residency is required');
    }

    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Files validated\n", FILE_APPEND);

    // Upload directories
    $idPictureDir = 'uploads/barangay_id_pictures/';
    $proofDir = 'uploads/barangay_id_proofs/';
    $validIdDir = 'uploads/valid_ids/';
    if (!file_exists($idPictureDir)) mkdir($idPictureDir, 0777, true);
    if (!file_exists($proofDir)) mkdir($proofDir, 0777, true);
    if (!file_exists($validIdDir)) mkdir($validIdDir, 0777, true);

    // Upload Valid Government ID
    $validIdPath = null;
    $allowedImageTypes = ['image/jpeg', 'image/png', 'image/jpg'];
    $maxValidIdSize = 10 * 1024 * 1024; // 10MB
    
    if (in_array($_FILES['validId']['type'], $allowedImageTypes) && $_FILES['validId']['size'] <= $maxValidIdSize) {
        $ext = pathinfo($_FILES['validId']['name'], PATHINFO_EXTENSION);
        $validIdPath = $validIdDir . 'valid_' . $resident_id . '_' . time() . '.' . $ext;
        if (!move_uploaded_file($_FILES['validId']['tmp_name'], $validIdPath)) {
            throw new Exception('Failed to upload valid ID');
        }
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Valid ID uploaded: $validIdPath\n", FILE_APPEND);
    } else {
        throw new Exception('Invalid Government ID. Must be JPG/PNG image, max 10MB.');
    }

    // Upload ID Picture
    $idPicturePath = null;
    $maxImageSize = 2 * 1024 * 1024; // 2MB
    
    if (in_array($_FILES['idPicture']['type'], $allowedImageTypes) && $_FILES['idPicture']['size'] <= $maxImageSize) {
        $ext = pathinfo($_FILES['idPicture']['name'], PATHINFO_EXTENSION);
        $idPicturePath = $idPictureDir . 'id_' . $resident_id . '_' . time() . '.' . $ext;
        if (!move_uploaded_file($_FILES['idPicture']['tmp_name'], $idPicturePath)) {
            throw new Exception('Failed to upload ID picture');
        }
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - ID picture uploaded: $idPicturePath\n", FILE_APPEND);
    } else {
        throw new Exception('Invalid ID picture. Must be JPG/PNG, max 2MB.');
    }

    // Upload Proof of Residency
    $proofPath = null;
    $allowedProofTypes = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
    $maxProofSize = 5 * 1024 * 1024; // 5MB
    
    if (in_array($_FILES['proofOfResidency']['type'], $allowedProofTypes) && $_FILES['proofOfResidency']['size'] <= $maxProofSize) {
        $ext = pathinfo($_FILES['proofOfResidency']['name'], PATHINFO_EXTENSION);
        $proofPath = $proofDir . 'proof_' . $resident_id . '_' . time() . '.' . $ext;
        if (!move_uploaded_file($_FILES['proofOfResidency']['tmp_name'], $proofPath)) {
            throw new Exception('Failed to upload proof of residency');
        }
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Proof uploaded: $proofPath\n", FILE_APPEND);
    } else {
        throw new Exception('Invalid proof of residency. Must be JPG/PNG/PDF, max 5MB.');
    }

    // Migrate old table name if present and create simplified table name
    $oldTable = 'barangay_id_applications';
    $newTable = 'barangay_id';
    $checkOld = $conn->query("SHOW TABLES LIKE '$oldTable'");
    $checkNew = $conn->query("SHOW TABLES LIKE '$newTable'");
    if ($checkOld && $checkOld->num_rows > 0 && $checkNew && $checkNew->num_rows === 0) {
        $conn->query("RENAME TABLE $oldTable TO $newTable");
    }

    // Create barangay_id table if it doesn't exist
    $tableCreate = "CREATE TABLE IF NOT EXISTS barangay_id (
        id INT AUTO_INCREMENT PRIMARY KEY,
        request_id INT NOT NULL,
        resident_id INT NOT NULL,
        last_name VARCHAR(100),
        first_name VARCHAR(100),
        middle_name VARCHAR(100),
        suffix VARCHAR(50),
        date_of_birth DATE,
        gender VARCHAR(20),
        civil_status VARCHAR(50),
        place_of_birth VARCHAR(255),
        citizenship VARCHAR(100),
        years_in_barangay VARCHAR(50),
        complete_address TEXT,
        contact_number VARCHAR(20),
        email VARCHAR(100),
        emergency_contact_person VARCHAR(255),
        emergency_contact_number VARCHAR(20),
        emergency_relationship VARCHAR(100),
        valid_id_path VARCHAR(500),
        id_picture_path VARCHAR(500),
        proof_of_residency_path VARCHAR(500),
        mode_of_release VARCHAR(50),
        status VARCHAR(20) DEFAULT 'pending',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (resident_id) REFERENCES residents(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if (!$conn->query($tableCreate)) {
        throw new Exception('Database error creating barangay_id table: ' . $conn->error);
    }

    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Table ready\n", FILE_APPEND);

    // Insert into requests table first
    $documentType = 'Barangay ID';
    $status = 'pending';
    $requestedAt = date('Y-m-d H:i:s');
    $modeOfReleaseValue = $_POST['modeOfRelease'];
    $modeOfPaymentValue = $_POST['modeOfPayment'] ?? 'GCash';
    
    // Ensure mode_of_payment column exists
    $colRes = $conn->query("SHOW COLUMNS FROM requests LIKE 'mode_of_payment'");
    if ($colRes && $colRes->num_rows === 0) {
        $conn->query("ALTER TABLE requests ADD COLUMN mode_of_payment VARCHAR(20) DEFAULT 'GCash' AFTER document_type");
    }
    
    $stmt = $conn->prepare("INSERT INTO requests (resident_id, document_type, mode_of_payment, status, requested_at, mode_of_release) VALUES (?, ?, ?, ?, ?, ?)");
    if (!$stmt) throw new Exception('Database error preparing request insert: ' . $conn->error);
    $stmt->bind_param('isssss', $resident_id, $documentType, $modeOfPaymentValue, $status, $requestedAt, $modeOfReleaseValue);
    $stmt->execute();
    $request_id = $conn->insert_id;
    $stmt->close();

    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Request created: ID $request_id\n", FILE_APPEND);

    // Log activity for resident
    addResidentActivity(
        $conn,
        $resident_id,
        $request_id,
        'request_submitted',
        'Document Request Submitted',
        "Your request for Barangay ID has been submitted and is pending review.",
        null,
        'Barangay ID'
    );

    // Prepare values
    $lastName = trim($_POST['lastName']);
    $firstName = trim($_POST['firstName']);
    $middleName = isset($_POST['middleName']) ? trim($_POST['middleName']) : null;
    $suffix = isset($_POST['suffix']) ? trim($_POST['suffix']) : null;
    $dateOfBirth = $_POST['dateOfBirth'];
    $gender = $_POST['gender'];
    $civilStatus = $_POST['civilStatus'];
    $placeOfBirth = trim($_POST['placeOfBirth']);
    $citizenship = trim($_POST['citizenship']);
    $yearsInBarangay = trim($_POST['yearsInBarangay']);
    $completeAddress = trim($_POST['completeAddress']);
    $contactNumber = trim($_POST['contactNumber']);
    $email = trim($_POST['email']);
    $emergencyContactPerson = trim($_POST['emergencyContactPerson']);
    $emergencyContactNumber = trim($_POST['emergencyContactNumber']);
    $emergencyRelationship = trim($_POST['emergencyRelationship']);
    $modeOfRelease = $_POST['modeOfRelease'];

    // Insert into barangay_id
    $stmt = $conn->prepare("INSERT INTO barangay_id (request_id, resident_id, last_name, first_name, middle_name, suffix, date_of_birth, gender, civil_status, place_of_birth, citizenship, years_in_barangay, complete_address, contact_number, email, emergency_contact_person, emergency_contact_number, emergency_relationship, valid_id_path, id_picture_path, proof_of_residency_path, mode_of_release) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) throw new Exception('Database error preparing barangay ID insert: ' . $conn->error);

    $stmt->bind_param(
        'iissssssssssssssssssss',
        $request_id,
        $resident_id,
        $lastName,
        $firstName,
        $middleName,
        $suffix,
        $dateOfBirth,
        $gender,
        $civilStatus,
        $placeOfBirth,
        $citizenship,
        $yearsInBarangay,
        $completeAddress,
        $contactNumber,
        $email,
        $emergencyContactPerson,
        $emergencyContactNumber,
        $emergencyRelationship,
        $validIdPath,
        $idPicturePath,
        $proofPath,
        $modeOfRelease
    );

    if (!$stmt->execute()) {
        throw new Exception('Execute error: ' . $stmt->error);
    }

    $stmt->close();
    $conn->commit();

    file_put_contents($logFile, date('Y-m-d H:i:s') . " - SUCCESS! Request ID: " . $request_id . "\n", FILE_APPEND);

    // Notify staff about new document request
    include_once 'add_staff_notification.php';
    $resident_name = trim($firstName . ' ' . $lastName);
    addStaffNotification(
        $conn,
        'document_request',
        'New Document Request',
        "$resident_name has requested Barangay ID",
        $request_id,
        'request'
    );

    echo json_encode([
        'success' => true,
        'message' => 'Your Barangay ID application has been submitted successfully. Verification will be conducted, and you will be notified when the document is ready.',
        'request_id' => $request_id
    ]);

} catch (Exception $e) {
    $conn->rollback();
    
    // Clean up uploaded files on error
    if (isset($idPicturePath) && file_exists($idPicturePath)) unlink($idPicturePath);
    if (isset($proofPath) && file_exists($proofPath)) unlink($proofPath);
    
    $errorMsg = $e->getMessage();
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - ERROR: $errorMsg\n", FILE_APPEND);
    
    echo json_encode(['success' => false, 'message' => $errorMsg]);
}

$conn->close();
?>
