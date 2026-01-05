<?php
// submit_certificate_indigency.php
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
if (!isset($_POST['specificPurpose']) || trim($_POST['specificPurpose']) === '') $errors[] = 'Specific Purpose';
if (!isset($_POST['estimatedMonthlyIncome']) || trim($_POST['estimatedMonthlyIncome']) === '') $errors[] = 'Estimated Monthly Income';
if (!isset($_POST['modeOfRelease']) || trim($_POST['modeOfRelease']) === '') $errors[] = 'Mode of Release';
if (!isset($_POST['modeOfPayment']) || trim($_POST['modeOfPayment']) === '') $errors[] = 'Mode of Payment';

if (!empty($errors)) {
    error_log("Validation errors: " . implode(', ', $errors));
    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields: ' . implode(', ', $errors)]);
    exit;
}

// Handle valid ID upload
$validIdPath = null;
if (isset($_FILES['validId']) && $_FILES['validId']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = 'uploads/indigency_ids/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $fileExt = strtolower(pathinfo($_FILES['validId']['name'], PATHINFO_EXTENSION));
    $allowedExts = ['jpg', 'jpeg', 'png', 'pdf'];
    
    if (!in_array($fileExt, $allowedExts)) {
        echo json_encode(['success' => false, 'message' => 'Invalid file type for Valid ID. Only JPG, PNG, and PDF are allowed.']);
        exit;
    }
    
    if ($_FILES['validId']['size'] > 5242880) { // 5MB limit
        echo json_encode(['success' => false, 'message' => 'Valid ID file size must not exceed 5MB.']);
        exit;
    }
    
    $fileName = 'indigency_id_' . $resident_id . '_' . time() . '.' . $fileExt;
    $uploadPath = $uploadDir . $fileName;
    
    if (move_uploaded_file($_FILES['validId']['tmp_name'], $uploadPath)) {
        $validIdPath = $uploadPath;
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to upload Valid ID.']);
        exit;
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Please upload a valid ID.']);
    exit;
}

// Handle proof of income upload
$proofOfIncomePath = null;
if (isset($_FILES['proofOfIncome']) && $_FILES['proofOfIncome']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = 'uploads/indigency_proofs/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $fileExt = strtolower(pathinfo($_FILES['proofOfIncome']['name'], PATHINFO_EXTENSION));
    $allowedExts = ['jpg', 'jpeg', 'png', 'pdf'];
    
    if (!in_array($fileExt, $allowedExts)) {
        echo json_encode(['success' => false, 'message' => 'Invalid file type for Proof of Income. Only JPG, PNG, and PDF are allowed.']);
        exit;
    }
    
    if ($_FILES['proofOfIncome']['size'] > 5242880) { // 5MB limit
        echo json_encode(['success' => false, 'message' => 'Proof of Income file size must not exceed 5MB.']);
        exit;
    }
    
    $fileName = 'indigency_proof_' . $resident_id . '_' . time() . '.' . $fileExt;
    $uploadPath = $uploadDir . $fileName;
    
    if (move_uploaded_file($_FILES['proofOfIncome']['tmp_name'], $uploadPath)) {
        $proofOfIncomePath = $uploadPath;
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to upload Proof of Income.']);
        exit;
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Please upload proof of income.']);
    exit;
}

// Start transaction
$conn->begin_transaction();

try {
    // Ensure certificate_of_indigency table exists
    $createTable = "CREATE TABLE IF NOT EXISTS certificate_of_indigency (
        id INT AUTO_INCREMENT PRIMARY KEY,
        request_id INT NOT NULL,
        resident_id INT NOT NULL,
        last_name VARCHAR(100) NOT NULL,
        first_name VARCHAR(100) NOT NULL,
        middle_name VARCHAR(100),
        suffix VARCHAR(20),
        date_of_birth DATE NOT NULL,
        civil_status VARCHAR(50) NOT NULL,
        complete_address TEXT NOT NULL,
        contact_number VARCHAR(20) NOT NULL,
        specific_purpose TEXT NOT NULL,
        estimated_monthly_income VARCHAR(50) NOT NULL,
        number_of_dependents INT,
        valid_id_path VARCHAR(255) NOT NULL,
        proof_of_income_path VARCHAR(255) NOT NULL,
        mode_of_release VARCHAR(50) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $conn->query($createTable);

    // Insert into requests table
    // Ensure mode_of_payment column exists
    $colRes = $conn->query("SHOW COLUMNS FROM requests LIKE 'mode_of_payment'");
    if ($colRes && $colRes->num_rows === 0) {
        $conn->query("ALTER TABLE requests ADD COLUMN mode_of_payment VARCHAR(20) DEFAULT 'GCash' AFTER document_type");
    }
    
    $modeOfPaymentVal = $_POST['modeOfPayment'] ?? 'GCash';
    $stmt = $conn->prepare("INSERT INTO requests (resident_id, document_type, mode_of_payment, status, requested_at, mode_of_release) VALUES (?, 'Certificate of Indigency', ?, 'pending', NOW(), ?)");
    if (!$stmt) {
        throw new Exception('Prepare statement failed for requests: ' . $conn->error);
    }
    $stmt->bind_param('iss', $resident_id, $modeOfPaymentVal, $_POST['modeOfRelease']);
    
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
        "Your request for Certificate of Indigency has been submitted and is pending review.",
        null,
        'Certificate of Indigency'
    );    
    // Insert into certificate_of_indigency table
    $stmt2 = $conn->prepare("INSERT INTO certificate_of_indigency (request_id, resident_id, last_name, first_name, middle_name, suffix, date_of_birth, civil_status, complete_address, contact_number, specific_purpose, estimated_monthly_income, number_of_dependents, valid_id_path, proof_of_income_path, mode_of_release) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt2) {
        throw new Exception('Prepare statement failed for certificate_of_indigency: ' . $conn->error);
    }
    
    $lastName = trim($_POST['lastName']);
    $firstName = trim($_POST['firstName']);
    $middleName = trim($_POST['middleName'] ?? '');
    $suffix = trim($_POST['suffix'] ?? '');
    $dateOfBirth = $_POST['dateOfBirth'];
    $civilStatus = $_POST['civilStatus'];
    $completeAddress = trim($_POST['completeAddress']);
    $contactNumber = trim($_POST['contactNumber']);
    $specificPurpose = trim($_POST['specificPurpose']);
    $estimatedMonthlyIncome = trim($_POST['estimatedMonthlyIncome']);
    $numberOfDependents = isset($_POST['numberOfDependents']) && $_POST['numberOfDependents'] !== '' ? intval($_POST['numberOfDependents']) : null;
    $modeOfRelease = $_POST['modeOfRelease'];
    
    $stmt2->bind_param('iissssssssssisss', $request_id, $resident_id, $lastName, $firstName, $middleName, $suffix, $dateOfBirth, $civilStatus, $completeAddress, $contactNumber, $specificPurpose, $estimatedMonthlyIncome, $numberOfDependents, $validIdPath, $proofOfIncomePath, $modeOfRelease);
    
    if (!$stmt2->execute()) {
        throw new Exception('Failed to save certificate of indigency details: ' . $stmt2->error);
    }
    
    $stmt2->close();
    
    // Notify staff about new document request
    include_once 'add_staff_notification.php';
    $resident_name = trim($firstName . ' ' . $lastName);
    addStaffNotification(
        $conn,
        'document_request',
        'New Document Request',
        "$resident_name has requested Certificate of Indigency",
        $request_id,
        'request'
    );
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Certificate of Indigency request submitted successfully!',
        'request_id' => $request_id
    ]);
    
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    
    // Delete uploaded files if exists
    if ($validIdPath && file_exists($validIdPath)) {
        unlink($validIdPath);
    }
    if ($proofOfIncomePath && file_exists($proofOfIncomePath)) {
        unlink($proofOfIncomePath);
    }
    
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

$conn->close();
?>
