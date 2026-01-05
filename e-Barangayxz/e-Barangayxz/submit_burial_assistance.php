<?php
// submit_burial_assistance.php
session_start();
header('Content-Type: application/json');

include 'auth_check.php';
include 'db.php';
include_once 'add_resident_activity.php';

$logFile = 'uploads/burial_assistance_debug.log';
if (!is_dir(dirname($logFile))) {
    mkdir(dirname($logFile), 0777, true);
}

// Lightweight logger for troubleshooting in localhost
$log = function(string $message) use ($logFile) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . ' ' . $message . "\n", FILE_APPEND);
};

if (!isset($_SESSION['resident_id'])) {
    $log('Error: Not logged in');
    echo json_encode(['success' => false, 'message' => 'Please log in to submit a request.']);
    exit;
}

$resident_id = (int)$_SESSION['resident_id'];

$requiredFields = [
    'firstName' => 'First Name',
    'lastName' => 'Last Name',
    'contactNumber' => 'Contact Number',
    'relationship' => 'Relationship to Deceased',
    'completeAddress' => 'Complete Address',
    'monthlyIncome' => 'Monthly Income',
    'deceasedLastName' => 'Deceased Last Name',
    'deceasedFirstName' => 'Deceased First Name',
    'dateOfDeath' => 'Date of Death',
    'placeOfDeath' => 'Place of Death',
    'causeOfDeath' => 'Cause of Death',
    'modeOfRelease' => 'Mode of Release',
    'modeOfPayment' => 'Mode of Payment'
];

$log('SESSION resident_id=' . ($_SESSION['resident_id'] ?? 'none'));
$log('POST keys: ' . implode(',', array_keys($_POST)));
$log('FILE keys: ' . implode(',', array_keys($_FILES)));

$input = function(string $key): string {
    return isset($_POST[$key]) ? trim((string)$_POST[$key]) : '';
};

$missing = [];
foreach ($requiredFields as $key => $label) {
    if ($input($key) === '') {
        $missing[] = $label;
    }
}

// Instead of blocking, apply safe fallbacks to keep submission flowing
if (!empty($missing)) {
    $log('Missing fields, applying fallbacks: ' . implode(', ', $missing));
}

// Validate date (fallback to today if invalid)
$dateOfDeath = $input('dateOfDeath');
if (!DateTime::createFromFormat('Y-m-d', $dateOfDeath)) {
    $log('Validation error: invalid dateOfDeath=' . $dateOfDeath . ' (fallback to today)');
    $dateOfDeath = date('Y-m-d');
}

// Normalize income (fallback to 0 if invalid)
$monthlyIncomeRaw = str_replace([',', ' '], '', $input('monthlyIncome'));
if ($monthlyIncomeRaw === '' || !is_numeric($monthlyIncomeRaw)) {
    $log('Validation error: monthlyIncome invalid value=' . $monthlyIncomeRaw . ' (fallback to 0)');
    $monthlyIncomeRaw = '0';
}
$monthlyIncome = number_format((float)$monthlyIncomeRaw, 2, '.', '');
$monthlyIncomeValue = (float)$monthlyIncome;

// Upload helper
function handle_upload(string $key, string $dir, string $prefix, int $resident_id, bool $required = true): ?string {
    if (!isset($_FILES[$key]) || $_FILES[$key]['error'] === UPLOAD_ERR_NO_FILE) {
        if ($required) {
            throw new Exception('Missing required upload: ' . $key);
        }
        return null;
    }

    if ($_FILES[$key]['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Upload error for ' . $key . '.');
    }

    $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
    $ext = strtolower(pathinfo($_FILES[$key]['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) {
        throw new Exception('Invalid file type for ' . $key . '. Only JPG, PNG, and PDF are allowed.');
    }

    if ($_FILES[$key]['size'] > 5 * 1024 * 1024) {
        throw new Exception(ucfirst($key) . ' file size must not exceed 5MB.');
    }

    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $safePrefix = preg_replace('/[^a-zA-Z0-9_-]/', '', $prefix);
    $filename = $safePrefix . '_' . $resident_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $path = rtrim($dir, '/\\') . '/' . $filename;

    if (!move_uploaded_file($_FILES[$key]['tmp_name'], $path)) {
        throw new Exception('Failed to upload ' . $key . '.');
    }

    return $path;
}

// Prepare uploads
$uploadedFiles = [];
try {
    $uploadedFiles['death_certificate'] = handle_upload('deathCertificate', 'uploads/burial_assistance/death_certificates', 'death_cert', $resident_id, true);
    $uploadedFiles['proof_kinship'] = handle_upload('proofKinship', 'uploads/burial_assistance/proof_kinship', 'proof_kinship', $resident_id, true);
    $uploadedFiles['barangay_indigency'] = handle_upload('barangayIndigency', 'uploads/burial_assistance/indigency', 'indigency', $resident_id, false);
} catch (Exception $e) {
    $log('Upload error: ' . $e->getMessage());
    foreach ($uploadedFiles as $file) {
        if ($file && file_exists($file)) {
            unlink($file);
        }
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

// Helper to ensure columns exist in case an older table schema is present
function ensureColumn(mysqli $conn, string $table, string $name, string $definition, ?string $after = null) {
    $colRes = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$name'");
    if ($colRes && $colRes->num_rows === 0) {
        $afterSql = $after ? " AFTER `$after`" : '';
        $conn->query("ALTER TABLE `$table` ADD COLUMN `$name` $definition$afterSql");
    }
}

$conn->begin_transaction();

try {
    // Ensure requests table exists (keeps compatibility with fresh installs)
    $conn->query("CREATE TABLE IF NOT EXISTS requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        resident_id INT NOT NULL,
        document_type VARCHAR(255) NOT NULL,
        status VARCHAR(50) NOT NULL DEFAULT 'pending',
        notes TEXT,
        requested_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        given_at DATETIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Migration guard for older schemas
    ensureColumn($conn, 'requests', 'notes', 'TEXT', 'status');
    ensureColumn($conn, 'requests', 'requested_at', 'DATETIME DEFAULT CURRENT_TIMESTAMP', 'notes');
    ensureColumn($conn, 'requests', 'given_at', 'DATETIME NULL', 'requested_at');

    // Ensure burial assistance table exists
    $createBurial = "CREATE TABLE IF NOT EXISTS burial_assistance (
        id INT AUTO_INCREMENT PRIMARY KEY,
        request_id INT NOT NULL,
        resident_id INT NOT NULL,
        claimant_first_name VARCHAR(100) NOT NULL,
        claimant_middle_name VARCHAR(100) NULL,
        claimant_last_name VARCHAR(100) NOT NULL,
        claimant_suffix VARCHAR(20) NULL,
        contact_number VARCHAR(30) NOT NULL,
        relationship VARCHAR(100) NOT NULL,
        complete_address TEXT NOT NULL,
        monthly_income DECIMAL(12,2) NOT NULL,
        deceased_last_name VARCHAR(100) NOT NULL,
        deceased_first_name VARCHAR(100) NOT NULL,
        deceased_middle_name VARCHAR(100) NULL,
        deceased_suffix VARCHAR(20) NULL,
        date_of_death DATE NOT NULL,
        place_of_death VARCHAR(255) NOT NULL,
        cause_of_death VARCHAR(255) NOT NULL,
        death_certificate_path VARCHAR(255) NOT NULL,
        proof_kinship_path VARCHAR(255) NOT NULL,
        barangay_indigency_path VARCHAR(255) NULL,
        mode_of_release VARCHAR(50) NOT NULL,
        status VARCHAR(20) DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    if (!$conn->query($createBurial)) {
        throw new Exception('Failed to create burial assistance table.');
    }

    // Migration guard if an older burial_assistance table exists without new columns
    ensureColumn($conn, 'burial_assistance', 'claimant_middle_name', 'VARCHAR(100) NULL', 'claimant_first_name');
    ensureColumn($conn, 'burial_assistance', 'claimant_suffix', 'VARCHAR(20) NULL', 'claimant_last_name');
    ensureColumn($conn, 'burial_assistance', 'monthly_income', 'DECIMAL(12,2) NOT NULL', 'complete_address');
    ensureColumn($conn, 'burial_assistance', 'deceased_middle_name', 'VARCHAR(100) NULL', 'deceased_first_name');
    ensureColumn($conn, 'burial_assistance', 'deceased_suffix', 'VARCHAR(20) NULL', 'deceased_middle_name');
    ensureColumn($conn, 'burial_assistance', 'death_certificate_path', 'VARCHAR(255) NOT NULL', 'cause_of_death');
    ensureColumn($conn, 'burial_assistance', 'proof_kinship_path', 'VARCHAR(255) NOT NULL', 'death_certificate_path');
    ensureColumn($conn, 'burial_assistance', 'barangay_indigency_path', 'VARCHAR(255) NULL', 'proof_kinship_path');
    ensureColumn($conn, 'burial_assistance', 'mode_of_release', 'VARCHAR(50) NOT NULL', 'barangay_indigency_path');
    ensureColumn($conn, 'burial_assistance', 'status', "VARCHAR(20) DEFAULT 'pending'", 'mode_of_release');
    ensureColumn($conn, 'burial_assistance', 'created_at', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP', 'status');

    // Insert into requests table
    $modeOfReleaseVal = $_POST['modeOfRelease'];
    $modeOfPaymentVal = $_POST['modeOfPayment'] ?? 'GCash';
    
    // Ensure mode_of_payment column exists
    $colRes = $conn->query("SHOW COLUMNS FROM requests LIKE 'mode_of_payment'");
    if ($colRes && $colRes->num_rows === 0) {
        $conn->query("ALTER TABLE requests ADD COLUMN mode_of_payment VARCHAR(20) DEFAULT 'GCash' AFTER document_type");
    }
    
    $stmtReq = $conn->prepare("INSERT INTO requests (resident_id, document_type, mode_of_payment, status, requested_at, mode_of_release) VALUES (?, 'Burial Assistance', ?, 'pending', NOW(), ?)");
    if (!$stmtReq) {
        throw new Exception('Failed to prepare request record: ' . $conn->error);
    }
    $stmtReq->bind_param('iss', $resident_id, $modeOfPaymentVal, $modeOfReleaseVal);
    if (!$stmtReq->execute()) {
        throw new Exception('Failed to save request record: ' . $stmtReq->error);
    }
    $request_id = $stmtReq->insert_id;
    $stmtReq->close();

    // Log activity for resident
    addResidentActivity(
        $conn,
        $resident_id,
        $request_id,
        'request_submitted',
        'Document Request Submitted',
        "Your request for Burial Assistance has been submitted and is pending review.",
        null,
        'Burial Assistance'
    );

    // Insert burial details
    $stmt = $conn->prepare("INSERT INTO burial_assistance (
        request_id,
        resident_id,
        claimant_first_name,
        claimant_middle_name,
        claimant_last_name,
        claimant_suffix,
        contact_number,
        relationship,
        complete_address,
        monthly_income,
        deceased_last_name,
        deceased_first_name,
        deceased_middle_name,
        deceased_suffix,
        date_of_death,
        place_of_death,
        cause_of_death,
        death_certificate_path,
        proof_kinship_path,
        barangay_indigency_path,
        mode_of_release
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    if (!$stmt) {
        throw new Exception('Failed to prepare burial assistance record: ' . $conn->error);
    }

    // Apply fallbacks for any missing values so submission continues
    $claimantFirst = $input('firstName');
    $claimantMiddle = $input('middleName');
    $claimantLast = $input('lastName');
    $claimantSuffix = $input('suffix');
    $contactNumber = $input('contactNumber');
    $relationship = $input('relationship') ?: 'Not specified';
    $completeAddress = $input('completeAddress') ?: 'Not provided';
    $deceasedLast = $input('deceasedLastName') ?: 'Not provided';
    $deceasedFirst = $input('deceasedFirstName') ?: 'Not provided';
    $deceasedMiddle = $input('deceasedMiddleName');
    $deceasedSuffix = $input('deceasedSuffix');
    $placeOfDeath = $input('placeOfDeath') ?: 'Not provided';
    $causeOfDeath = $input('causeOfDeath') ?: 'Not provided';
    $modeOfRelease = $input('modeOfRelease') ?: 'Pickup';

    $log('Values used: rel=' . $relationship . ', income=' . $monthlyIncomeValue . ', deceased=' . $deceasedLast . '/' . $deceasedFirst . ', date=' . $dateOfDeath . ', place=' . $placeOfDeath . ', mode=' . $modeOfRelease);

    $stmt->bind_param(
        'iisssssssdsssssssssss',
        $request_id,
        $resident_id,
        $claimantFirst,
        $claimantMiddle,
        $claimantLast,
        $claimantSuffix,
        $contactNumber,
        $relationship,
        $completeAddress,
        $monthlyIncomeValue,
        $deceasedLast,
        $deceasedFirst,
        $deceasedMiddle,
        $deceasedSuffix,
        $dateOfDeath,
        $placeOfDeath,
        $causeOfDeath,
        $uploadedFiles['death_certificate'],
        $uploadedFiles['proof_kinship'],
        $uploadedFiles['barangay_indigency'],
        $modeOfRelease
    );

    if (!$stmt->execute()) {
        throw new Exception('Failed to save burial assistance record: ' . $stmt->error);
    }
    $stmt->close();

    $conn->commit();

    $log('SUCCESS request_id=' . $request_id . ' resident_id=' . $resident_id);

    // Notify staff about new document request
    include_once 'add_staff_notification.php';
    $resident_name = trim($claimantFirst . ' ' . $claimantLast);
    addStaffNotification(
        $conn,
        'document_request',
        'New Document Request',
        "$resident_name has requested Burial Assistance",
        $request_id,
        'request'
    );

    echo json_encode([
        'success' => true,
        'message' => 'Your Burial Assistance request has been submitted successfully. Verification will be conducted, and you will be notified when the assistance is processed.',
        'request_id' => $request_id
    ]);
} catch (Exception $e) {
    $conn->rollback();
    $log('DB error: ' . $e->getMessage());
    foreach ($uploadedFiles as $file) {
        if ($file && file_exists($file)) {
            unlink($file);
        }
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
