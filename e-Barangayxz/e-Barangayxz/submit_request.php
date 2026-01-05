<?php
// submit_request.php
include 'auth_check.php';
include 'db.php';
include_once 'add_resident_activity.php';

header('Content-Type: application/json');

$resident_id = $_SESSION['resident_id'] ?? null;
if (!$resident_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) $input = $_POST;

$document_type = trim($input['document_type'] ?? '');
$notes = trim($input['notes'] ?? '');
$mode_of_payment = trim($input['mode_of_payment'] ?? 'GCash');
$mode_of_release = trim($input['mode_of_release'] ?? 'Pickup');

if ($document_type === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Document type is required']);
    exit;
}

// Ensure requests table exists
$create = "CREATE TABLE IF NOT EXISTS requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    resident_id INT NOT NULL,
    document_type VARCHAR(255) NOT NULL,
    mode_of_payment VARCHAR(20) DEFAULT 'GCash',
    mode_of_release VARCHAR(20) DEFAULT 'Pickup',
    status VARCHAR(50) NOT NULL DEFAULT 'pending',
    notes TEXT,
    requested_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    given_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($create);

// Migration: ensure expected columns exist in older DBs
// Add 'notes' if missing
$colRes = $conn->query("SHOW COLUMNS FROM requests LIKE 'notes'");
if ($colRes && $colRes->num_rows === 0) {
    $conn->query("ALTER TABLE requests ADD COLUMN notes TEXT AFTER status");
}
// Add 'given_at' if missing
$colRes = $conn->query("SHOW COLUMNS FROM requests LIKE 'given_at'");
if ($colRes && $colRes->num_rows === 0) {
    $conn->query("ALTER TABLE requests ADD COLUMN given_at DATETIME NULL AFTER requested_at");
}
// Add 'mode_of_payment' if missing
$colRes = $conn->query("SHOW COLUMNS FROM requests LIKE 'mode_of_payment'");
if ($colRes && $colRes->num_rows === 0) {
    $conn->query("ALTER TABLE requests ADD COLUMN mode_of_payment VARCHAR(20) DEFAULT 'GCash' AFTER document_type");
}
// Add 'mode_of_release' if missing
$colRes = $conn->query("SHOW COLUMNS FROM requests LIKE 'mode_of_release'");
if ($colRes && $colRes->num_rows === 0) {
    $conn->query("ALTER TABLE requests ADD COLUMN mode_of_release VARCHAR(20) DEFAULT 'Pickup' AFTER mode_of_payment");
}

$stmt = $conn->prepare("INSERT INTO requests (resident_id, document_type, mode_of_payment, mode_of_release, status, notes) VALUES (?, ?, ?, ?, 'pending', ?)");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB prepare failed']);
    exit;
}
$stmt->bind_param('issss', $resident_id, $document_type, $mode_of_payment, $mode_of_release, $notes);
$ok = $stmt->execute();
$newId = $stmt->insert_id;
$stmt->close();

if ($ok) {
    // Log activity for resident
    addResidentActivity(
        $conn,
        $resident_id,
        $newId,
        'request_submitted',
        'Document Request Submitted',
        "Your request for $document_type has been submitted and is pending review.",
        null,
        $document_type
    );

    // Log to activity_log for dashboard feed
    $conn->query("CREATE TABLE IF NOT EXISTS activity_log (
        log_id INT AUTO_INCREMENT PRIMARY KEY,
        staff_id INT NULL,
        resident_id INT NULL,
        action_type VARCHAR(100),
        action_details TEXT,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $residentName = '';
    $res = $conn->query("SELECT first_name, last_name FROM residents WHERE id = " . intval($resident_id));
    if ($res && $row = $res->fetch_assoc()) {
        $residentName = trim($row['first_name'] . ' ' . $row['last_name']);
    }
    $action_type = 'Request Submitted';
    $action_details = $residentName . " submitted a request for " . $document_type;
    $stmtLog = $conn->prepare("INSERT INTO activity_log (resident_id, action_type, action_details) VALUES (?, ?, ?)");
    if ($stmtLog) {
        $stmtLog->bind_param('iss', $resident_id, $action_type, $action_details);
        $stmtLog->execute();
        $stmtLog->close();
    }

    echo json_encode(['success' => true, 'message' => 'Request submitted', 'request_id' => $newId]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Insert failed']);
}

