<?php
// submit_blotter.php
error_reporting(E_ALL);
ini_set('display_errors', 0);
session_start();
header('Content-Type: application/json');

include 'db.php';
include_once 'add_resident_activity.php';

// Debug log
$logFile = 'uploads/blotter_debug.log';
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
    $required = ['lastName','firstName','contactNumber','completeAddress','incidentType','incidentDate','incidentTime','incidentLocation','narrative','modeOfRelease','modeOfPayment'];
    foreach ($required as $field) {
        if (!isset($_POST[$field]) || trim($_POST[$field]) === '') {
            throw new Exception('Missing required field: ' . $field);
        }
    }

    file_put_contents($logFile, date('Y-m-d H:i:s') . " - All required fields present\n", FILE_APPEND);

    // Validate evidence upload
    if (!isset($_FILES['evidence']) || empty($_FILES['evidence']['name'][0])) {
        throw new Exception('At least one evidence file is required');
    }

    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Evidence files: " . count($_FILES['evidence']['name']) . "\n", FILE_APPEND);

    $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf', 'video/mp4'];
    $maxFileSize = 10 * 1024 * 1024; // 10MB
    $maxFiles = 3;

    // Upload directory
    $evidenceDir = 'uploads/blotter_evidence/';
    if (!file_exists($evidenceDir)) mkdir($evidenceDir, 0777, true);

    // Process evidence uploads
    $evidencePaths = [];
    $fileCount = count($_FILES['evidence']['name']);
    
    if ($fileCount > $maxFiles) {
        throw new Exception('Maximum 3 evidence files allowed');
    }

    foreach ($_FILES['evidence']['name'] as $idx => $name) {
        if ($_FILES['evidence']['error'][$idx] === UPLOAD_ERR_OK && $_FILES['evidence']['name'][$idx] !== '') {
            $tmp = $_FILES['evidence']['tmp_name'][$idx];
            $type = $_FILES['evidence']['type'][$idx];
            $size = $_FILES['evidence']['size'][$idx];
            
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - Processing file $idx: $name (type: $type, size: $size)\n", FILE_APPEND);
            
            if (!in_array($type, $allowedTypes)) {
                throw new Exception('Invalid file type for ' . $name . '. Only JPG, PNG, PDF, and MP4 are allowed.');
            }
            if ($size > $maxFileSize) {
                throw new Exception('File ' . $name . ' is too large. Maximum 10MB allowed.');
            }
            
            $ext = pathinfo($name, PATHINFO_EXTENSION);
            $dest = $evidenceDir . 'evidence_' . $resident_id . '_' . time() . '_' . $idx . '.' . $ext;
            
            if (move_uploaded_file($tmp, $dest)) {
                $evidencePaths[] = $dest;
                file_put_contents($logFile, date('Y-m-d H:i:s') . " - File uploaded: $dest\n", FILE_APPEND);
            } else {
                throw new Exception('Failed to upload evidence file: ' . $name);
            }
        }
    }

    if (empty($evidencePaths)) {
        throw new Exception('Failed to upload any evidence files');
    }

    file_put_contents($logFile, date('Y-m-d H:i:s') . " - All evidence files uploaded: " . count($evidencePaths) . "\n", FILE_APPEND);

    // Create blotter_reports table if it doesn't exist
    $tableCreate = "CREATE TABLE IF NOT EXISTS blotter_reports (
        id INT AUTO_INCREMENT PRIMARY KEY,
        request_id INT NOT NULL,
        resident_id INT NOT NULL,
        last_name VARCHAR(100),
        first_name VARCHAR(100),
        middle_name VARCHAR(100),
        suffix VARCHAR(50),
        contact_number VARCHAR(20),
        email VARCHAR(100),
        complete_address TEXT,
        incident_type VARCHAR(100),
        incident_date DATE,
        incident_time TIME,
        incident_location TEXT,
        narrative TEXT,
        respondent_name VARCHAR(255),
        respondent_address TEXT,
        evidence_paths TEXT,
        mode_of_release VARCHAR(50),
        status VARCHAR(20) DEFAULT 'pending',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (resident_id) REFERENCES residents(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if (!$conn->query($tableCreate)) {
        throw new Exception('Database error creating blotter_reports table: ' . $conn->error);
    }

    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Table ready\n", FILE_APPEND);

    // Insert into requests table first
    $documentType = 'Blotter Report';
    $status = 'pending';
    $requestedAt = date('Y-m-d H:i:s');
    $modeOfReleaseVal = $_POST['modeOfRelease'];
    $modeOfPaymentVal = $_POST['modeOfPayment'] ?? 'GCash';
    
    // Ensure mode_of_payment column exists
    $colRes = $conn->query("SHOW COLUMNS FROM requests LIKE 'mode_of_payment'");
    if ($colRes && $colRes->num_rows === 0) {
        $conn->query("ALTER TABLE requests ADD COLUMN mode_of_payment VARCHAR(20) DEFAULT 'GCash' AFTER document_type");
    }
    
    $stmt = $conn->prepare("INSERT INTO requests (resident_id, document_type, mode_of_payment, status, requested_at, mode_of_release) VALUES (?, ?, ?, ?, ?, ?)");
    if (!$stmt) throw new Exception('Database error preparing request insert: ' . $conn->error);
    $stmt->bind_param('isssss', $resident_id, $documentType, $modeOfPaymentVal, $status, $requestedAt, $modeOfReleaseVal);
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
        "Your request for Blotter Report has been submitted and is pending review.",
        null,
        'Blotter Report'
    );

    // Prepare values
    $lastName = trim($_POST['lastName']);
    $firstName = trim($_POST['firstName']);
    $middleName = isset($_POST['middleName']) ? trim($_POST['middleName']) : null;
    $suffix = isset($_POST['suffix']) ? trim($_POST['suffix']) : null;
    $contactNumber = trim($_POST['contactNumber']);
    $email = isset($_POST['email']) ? trim($_POST['email']) : null;
    $completeAddress = trim($_POST['completeAddress']);
    $incidentType = trim($_POST['incidentType']);
    $incidentDate = $_POST['incidentDate'];
    $incidentTime = $_POST['incidentTime'];
    $incidentLocation = trim($_POST['incidentLocation']);
    $narrative = trim($_POST['narrative']);
    $respondentName = isset($_POST['respondentName']) ? trim($_POST['respondentName']) : null;
    $respondentAddress = isset($_POST['respondentAddress']) ? trim($_POST['respondentAddress']) : null;
    $modeOfRelease = $_POST['modeOfRelease'];
    $evidenceText = json_encode($evidencePaths);

    // Insert into blotter_reports
    $stmt = $conn->prepare("INSERT INTO blotter_reports (request_id, resident_id, last_name, first_name, middle_name, suffix, contact_number, email, complete_address, incident_type, incident_date, incident_time, incident_location, narrative, respondent_name, respondent_address, evidence_paths, mode_of_release) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) throw new Exception('Database error preparing blotter insert: ' . $conn->error);

    $stmt->bind_param(
        'iissssssssssssssss',
        $request_id,
        $resident_id,
        $lastName,
        $firstName,
        $middleName,
        $suffix,
        $contactNumber,
        $email,
        $completeAddress,
        $incidentType,
        $incidentDate,
        $incidentTime,
        $incidentLocation,
        $narrative,
        $respondentName,
        $respondentAddress,
        $evidenceText,
        $modeOfRelease
    );

    if (!$stmt->execute()) {
        throw new Exception('Execute error: ' . $stmt->error);
    }

    $stmt->close();

    $conn->commit();

    file_put_contents($logFile, date('Y-m-d H:i:s') . " - SUCCESS! Report ID: " . $request_id . "\n", FILE_APPEND);

    // Notify staff about new document request
    include_once 'add_staff_notification.php';
    $resident_name = trim($firstName . ' ' . $lastName);
    addStaffNotification(
        $conn,
        'document_request',
        'New Document Request',
        "$resident_name has requested Blotter Report",
        $request_id,
        'request'
    );

    echo json_encode([
        'success' => true,
        'message' => 'Your Blotter Report has been submitted successfully. Verification will be conducted, and you will be notified when the document is ready.',
        'request_id' => $request_id
    ]);

} catch (Exception $e) {
    $conn->rollback();
    if (!empty($evidencePaths)) {
        foreach ($evidencePaths as $p) { 
            if (file_exists($p)) unlink($p); 
        }
    }
    
    $errorMsg = $e->getMessage();
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - ERROR: $errorMsg\n", FILE_APPEND);
    
    echo json_encode(['success' => false, 'message' => $errorMsg]);
}

$conn->close();
?>
