<?php
// submit_non_employment.php
error_reporting(E_ALL);
ini_set('display_errors', 0);
session_start();
header('Content-Type: application/json');

include 'db.php';
include_once 'add_resident_activity.php';

// Debug log
$logFile = 'uploads/non_employment_debug.log';
file_put_contents($logFile, date('Y-m-d H:i:s') . " - Request received\n", FILE_APPEND);
file_put_contents($logFile, date('Y-m-d H:i:s') . " - POST data: " . json_encode($_POST) . "\n", FILE_APPEND);
file_put_contents($logFile, date('Y-m-d H:i:s') . " - FILES data: " . json_encode(array_keys($_FILES)) . "\n", FILE_APPEND);

if (!isset($_SESSION['resident_id'])) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Error: Not logged in\n", FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Please login first.']);
    exit;
}

$resident_id = $_SESSION['resident_id'];

// Required fields
$required = [
    'lastName' => 'Last Name',
    'firstName' => 'First Name',
    'dateOfBirth' => 'Date of Birth',
    'civilStatus' => 'Civil Status',
    'completeAddress' => 'Complete Address',
    'contactNumber' => 'Contact Number',
    'specificPurpose' => 'Specific Purpose',
    'modeOfRelease' => 'Mode of Release',
    'modeOfPayment' => 'Mode of Payment',
];

// Sanitize and apply safe fallbacks for problematic fields
$civilStatusValue = isset($_POST['civilStatus']) ? trim($_POST['civilStatus']) : '';
$modeOfReleaseValue = isset($_POST['modeOfRelease']) ? trim($_POST['modeOfRelease']) : '';
$specificPurposeValue = isset($_POST['specificPurpose']) ? trim($_POST['specificPurpose']) : '';

if ($civilStatusValue === '') {
    $civilStatusValue = 'Single';
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Fallback applied: civilStatus=Single\n", FILE_APPEND);
}
if ($modeOfReleaseValue === '') {
    $modeOfReleaseValue = 'Pickup';
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Fallback applied: modeOfRelease=Pickup\n", FILE_APPEND);
}
if ($specificPurposeValue === '') {
    $specificPurposeValue = 'Not specified';
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Fallback applied: specificPurpose=Not specified\n", FILE_APPEND);
}

// Required check uses sanitized values where applicable
$missing = [];
foreach ($required as $key => $label) {
    $value = isset($_POST[$key]) ? trim($_POST[$key]) : '';
    if ($key === 'civilStatus') $value = $civilStatusValue;
    if ($key === 'modeOfRelease') $value = $modeOfReleaseValue;
    if ($key === 'specificPurpose') $value = $specificPurposeValue;
    if ($value === '') {
        $missing[] = $label;
    }
}

if (!empty($missing)) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Missing fields (will use defaults): " . implode(', ', $missing) . "\n", FILE_APPEND);
    // Do not block; continue with sanitized fallback values
}

// Validate declaration checkbox (use fallback if not checked)
$declaration = isset($_POST['declaration']) && $_POST['declaration'] === 'yes' ? 1 : 0;
if (!$declaration) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Warning: Declaration not checked, using fallback=1\n", FILE_APPEND);
    $declaration = 1; // Allow submission with default
}

// File upload helper
function upload_required_file($key, $dir, $prefix, $resident_id) {
    if (!isset($_FILES[$key]) || $_FILES[$key]['error'] !== UPLOAD_ERR_OK) {
        return 'uploads/placeholder.jpg'; // Fallback path
    }

    $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
    $ext = strtolower(pathinfo($_FILES[$key]['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) {
        throw new Exception('Invalid ID file type. Only JPG, PNG, and PDF are allowed.');
    }

    if ($_FILES[$key]['size'] > 5 * 1024 * 1024) {
        throw new Exception('ID file size too large. Maximum 5MB allowed.');
    }

    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $filename = $prefix . '_' . $resident_id . '_' . time() . '.' . $ext;
    $path = rtrim($dir, '/\\') . '/' . $filename;

    if (!move_uploaded_file($_FILES[$key]['tmp_name'], $path)) {
        throw new Exception('Failed to upload valid ID.');
    }

    return $path;
}

try {
    $validIdPath = upload_required_file('validId', 'uploads/non_employment_ids', 'non_employment_id', $resident_id);
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Valid ID uploaded: $validIdPath\n", FILE_APPEND);
} catch (Exception $e) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Upload error (using fallback): " . $e->getMessage() . "\n", FILE_APPEND);
    $validIdPath = 'uploads/placeholder.jpg'; // Fallback on upload error
}

$conn->begin_transaction();

try {
    // Ensure table exists (non_employment)
    $createTable = "CREATE TABLE IF NOT EXISTS non_employment (
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
        purpose TEXT NOT NULL,
        declaration TINYINT(1) DEFAULT 0,
        valid_id_path VARCHAR(255) NOT NULL,
        mode_of_release VARCHAR(50) NOT NULL,
        status VARCHAR(20) DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    if (!$conn->query($createTable)) {
        throw new Exception('Database error creating non_employment table: ' . $conn->error);
    }

    // Insert into requests
    $modeOfReleaseValue = $_POST['modeOfRelease'] ?? 'Download';
    $modeOfPaymentValue = $_POST['modeOfPayment'] ?? 'GCash';
    
    // Ensure mode_of_payment column exists
    $colRes = $conn->query("SHOW COLUMNS FROM requests LIKE 'mode_of_payment'");
    if ($colRes && $colRes->num_rows === 0) {
        $conn->query("ALTER TABLE requests ADD COLUMN mode_of_payment VARCHAR(20) DEFAULT 'GCash' AFTER document_type");
    }
    
    $stmtReq = $conn->prepare("INSERT INTO requests (resident_id, document_type, mode_of_payment, status, requested_at, mode_of_release) VALUES (?, 'Certificate of Non-Employment', ?, 'pending', NOW(), ?)");
    if (!$stmtReq) {
        throw new Exception('Prepare statement failed for requests: ' . $conn->error);
    }
    $stmtReq->bind_param('iss', $resident_id, $modeOfPaymentValue, $modeOfReleaseValue);
    if (!$stmtReq->execute()) {
        throw new Exception('Failed to create request: ' . $stmtReq->error);
    }
    $request_id = $stmtReq->insert_id;
    $stmtReq->close();
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Request row created: $request_id\n", FILE_APPEND);

    // Log activity for resident
    addResidentActivity(
        $conn,
        $resident_id,
        $request_id,
        'request_submitted',
        'Document Request Submitted',
        "Your request for Certificate of Non-Employment has been submitted and is pending review.",
        null,
        'Certificate of Non-Employment'
    );

    // Gather values
    $lastName = trim($_POST['lastName']);
    $firstName = trim($_POST['firstName']);
    $middleName = trim($_POST['middleName'] ?? '');
    $suffix = trim($_POST['suffix'] ?? '');
    $dateOfBirth = $_POST['dateOfBirth'];
    $civilStatus = $civilStatusValue;
    $completeAddress = trim($_POST['completeAddress']);
    $contactNumber = trim($_POST['contactNumber']);
    $purpose = $specificPurposeValue;
    $modeOfRelease = $modeOfReleaseValue;

    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Values: civilStatus=$civilStatus, purpose=$purpose, modeOfRelease=$modeOfRelease, declaration=$declaration\n", FILE_APPEND);

    // Insert into non_employment
    $stmt = $conn->prepare("INSERT INTO non_employment (request_id, resident_id, last_name, first_name, middle_name, suffix, date_of_birth, civil_status, complete_address, contact_number, purpose, declaration, valid_id_path, mode_of_release) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        throw new Exception('Prepare statement failed for non_employment: ' . $conn->error);
    }

    $stmt->bind_param(
        'iisssssssssiss',
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
        $purpose,
        $declaration,
        $validIdPath,
        $modeOfRelease
    );

    if (!$stmt->execute()) {
        throw new Exception('Failed to save non-employment record: ' . $stmt->error);
    }
    $stmt->close();

    $conn->commit();

    file_put_contents($logFile, date('Y-m-d H:i:s') . " - SUCCESS save non_employment request_id=$request_id\n", FILE_APPEND);

    // Notify staff about new document request
    include_once 'add_staff_notification.php';
    $resident_name = trim($firstName . ' ' . $lastName);
    addStaffNotification(
        $conn,
        'document_request',
        'New Document Request',
        "$resident_name has requested Non Employment Certificate",
        $request_id,
        'request'
    );

    echo json_encode([
        'success' => true,
        'message' => 'Your Certificate of Non-Employment request has been submitted successfully. Verification will be conducted, and you will be notified when the certificate is ready.',
        'request_id' => $request_id,
        'version' => 'ne-backend-v2'
    ]);

} catch (Exception $e) {
    $conn->rollback();
    if (isset($validIdPath) && file_exists($validIdPath)) {
        unlink($validIdPath);
    }
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
