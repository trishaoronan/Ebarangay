<?php
// submit_low_income.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['resident_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please log in to submit a request.']);
    exit;
}

include 'db.php';
include_once 'add_resident_activity.php';

$resident_id = $_SESSION['resident_id'];

$errors = [];
$required = [
    'lastName' => 'Last Name',
    'firstName' => 'First Name',
    'dateOfBirth' => 'Date of Birth',
    'civilStatus' => 'Civil Status',
    'completeAddress' => 'Complete Address',
    'contactNumber' => 'Contact Number',
    'occupation' => 'Occupation',
    'monthlyIncome' => 'Estimated Monthly Income',
    'householdMembers' => 'Total Household Members',
    'purpose' => 'Purpose',
    'modeOfRelease' => 'Mode of Release',
    'modeOfPayment' => 'Mode of Payment'
];

foreach ($required as $key => $label) {
    if (!isset($_POST[$key]) || trim($_POST[$key]) === '') {
        $errors[] = $label;
    }
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields: ' . implode(', ', $errors)]);
    exit;
}

// Upload helpers
function handle_upload($fileKey, $uploadDir, $prefix, $resident_id) {
    if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Missing or invalid upload: ' . $fileKey);
    }

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $fileExt = strtolower(pathinfo($_FILES[$fileKey]['name'], PATHINFO_EXTENSION));
    $allowedExts = ['jpg', 'jpeg', 'png', 'pdf'];
    if (!in_array($fileExt, $allowedExts)) {
        throw new Exception('Invalid file type for ' . $fileKey . '. Only JPG, PNG, and PDF are allowed.');
    }

    if ($_FILES[$fileKey]['size'] > 5242880) { // 5MB
        throw new Exception(ucfirst($fileKey) . ' file size must not exceed 5MB.');
    }

    $fileName = $prefix . '_' . $resident_id . '_' . time() . '.' . $fileExt;
    $uploadPath = rtrim($uploadDir, '/\\') . '/' . $fileName;

    if (!move_uploaded_file($_FILES[$fileKey]['tmp_name'], $uploadPath)) {
        throw new Exception('Failed to upload ' . $fileKey . '.');
    }

    return $uploadPath;
}

try {
    $validIdPath = handle_upload('validId', 'uploads/low_income_ids', 'low_income_id', $resident_id);
    $proofResidencyPath = handle_upload('proofResidency', 'uploads/low_income_residency', 'low_income_residency', $resident_id);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

$conn->begin_transaction();

try {
    // Migrate old table name if present and create simplified table name
    $oldTable = 'low_income_certificates';
    $newTable = 'low_income';
    $checkOld = $conn->query("SHOW TABLES LIKE '$oldTable'");
    $checkNew = $conn->query("SHOW TABLES LIKE '$newTable'");
    if ($checkOld && $checkOld->num_rows > 0 && $checkNew && $checkNew->num_rows === 0) {
        $conn->query("RENAME TABLE $oldTable TO $newTable");
    }

    $createTable = "CREATE TABLE IF NOT EXISTS low_income (
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
        occupation VARCHAR(255) NOT NULL,
        monthly_income VARCHAR(50) NOT NULL,
        household_members INT,
        purpose TEXT NOT NULL,
        valid_id_path VARCHAR(255) NOT NULL,
        proof_residency_path VARCHAR(255) NOT NULL,
        mode_of_release VARCHAR(50) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $conn->query($createTable);

    // Ensure mode_of_payment column exists
    $colRes = $conn->query("SHOW COLUMNS FROM requests LIKE 'mode_of_payment'");
    if ($colRes && $colRes->num_rows === 0) {
        $conn->query("ALTER TABLE requests ADD COLUMN mode_of_payment VARCHAR(20) DEFAULT 'GCash' AFTER document_type");
    }
    
    $modeOfPaymentValue = $_POST['modeOfPayment'] ?? 'GCash';
    $stmt = $conn->prepare("INSERT INTO requests (resident_id, document_type, mode_of_payment, status, requested_at, mode_of_release) VALUES (?, 'Low Income Certificate', ?, 'pending', NOW(), ?)");
    if (!$stmt) {
        throw new Exception('Prepare statement failed for requests: ' . $conn->error);
    }
    $stmt->bind_param('iss', $resident_id, $modeOfPaymentValue, $_POST['modeOfRelease']);
    if (!$stmt->execute()) {
        throw new Exception('Failed to create request: ' . $stmt->error);
    }
    $request_id = $stmt->insert_id;
    $stmt->close();

    // Log activity for resident
    addResidentActivity(
        $conn,
        $resident_id,
        $request_id,
        'request_submitted',
        'Document Request Submitted',
        "Your request for Low Income Certificate has been submitted and is pending review.",
        null,
        'Low Income Certificate'
    );

    $stmt2 = $conn->prepare("INSERT INTO low_income (request_id, resident_id, last_name, first_name, middle_name, suffix, date_of_birth, civil_status, complete_address, contact_number, occupation, monthly_income, household_members, purpose, valid_id_path, proof_residency_path, mode_of_release) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt2) {
        throw new Exception('Prepare statement failed for low_income: ' . $conn->error);
    }

    $lastName = trim($_POST['lastName']);
    $firstName = trim($_POST['firstName']);
    $middleName = trim($_POST['middleName'] ?? '');
    $suffix = trim($_POST['suffix'] ?? '');
    $dateOfBirth = $_POST['dateOfBirth'];
    $civilStatus = $_POST['civilStatus'];
    $completeAddress = trim($_POST['completeAddress']);
    $contactNumber = trim($_POST['contactNumber']);
    $occupation = trim($_POST['occupation']);
    $monthlyIncome = trim($_POST['monthlyIncome']);
    $householdMembers = isset($_POST['householdMembers']) && $_POST['householdMembers'] !== '' ? intval($_POST['householdMembers']) : null;
    $purpose = trim($_POST['purpose']);
    $modeOfRelease = $_POST['modeOfRelease'];

    $stmt2->bind_param(
        'iissssssssssissss',
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
        $occupation,
        $monthlyIncome,
        $householdMembers,
        $purpose,
        $validIdPath,
        $proofResidencyPath,
        $modeOfRelease
    );

    if (!$stmt2->execute()) {
        throw new Exception('Failed to save low income certificate details: ' . $stmt2->error);
    }
    $stmt2->close();

    $conn->commit();

    // Notify staff about new document request
    include_once 'add_staff_notification.php';
    $resident_name = trim($firstName . ' ' . $lastName);
    addStaffNotification(
        $conn,
        'document_request',
        'New Document Request',
        "$resident_name has requested Low Income Certificate",
        $request_id,
        'request'
    );

    echo json_encode([
        'success' => true,
        'message' => 'Your Low Income Certificate request has been submitted successfully. Verification will be conducted, and you will be notified when the document is ready.',
        'request_id' => $request_id
    ]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
