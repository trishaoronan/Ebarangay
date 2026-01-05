<?php
// update_resident_status.php
// Updates a resident's status (active, suspended, restricted)
// Suspended and restricted statuses automatically expire after 2 weeks

session_start();
header('Content-Type: application/json');
include 'db.php';

// Check if staff is logged in
if (!isset($_SESSION['staff_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please login as staff.']);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use POST.']);
    exit;
}

// Get input data
$contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
$input = [];

if (stripos($contentType, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true) ?: [];
} else {
    $input = $_POST;
}

// Validate required fields
$residentId = isset($input['resident_id']) ? intval($input['resident_id']) : 0;
$newStatus = isset($input['status']) ? strtolower(trim($input['status'])) : '';

if ($residentId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid resident ID.']);
    exit;
}

// Validate status value
$allowedStatuses = ['active', 'suspended', 'restricted'];
if (!in_array($newStatus, $allowedStatuses)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid status. Allowed values: active, suspended, restricted.']);
    exit;
}

try {
    // First check if status_expires_at column exists, if not create it
    $checkColumn = $conn->query("SHOW COLUMNS FROM residents LIKE 'status_expires_at'");
    if (!$checkColumn || $checkColumn->num_rows === 0) {
        $conn->query("ALTER TABLE residents ADD COLUMN status_expires_at DATETIME NULL AFTER status_changed_by");
    }
    
    // Check if restricted_documents column exists, if not create it
    $checkColumn2 = $conn->query("SHOW COLUMNS FROM residents LIKE 'restricted_documents'");
    if (!$checkColumn2 || $checkColumn2->num_rows === 0) {
        $conn->query("ALTER TABLE residents ADD COLUMN restricted_documents TEXT NULL AFTER status_expires_at");
    }
    
    // Check if resident exists and is not deceased
    $checkStmt = $conn->prepare("SELECT id, first_name, last_name, status FROM residents WHERE id = ?");
    $checkStmt->bind_param('i', $residentId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Resident not found.']);
        $checkStmt->close();
        exit;
    }
    
    $resident = $result->fetch_assoc();
    $checkStmt->close();
    
    // Cannot change status of deceased residents
    if ($resident['status'] === 'deceased') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Cannot change the status of a deceased resident.']);
        exit;
    }
    
    // Calculate expiration date (2 weeks from now) for suspended/restricted
    $staffId = $_SESSION['staff_id'];
    $now = date('Y-m-d H:i:s');
    $expiresAt = null;
    $restrictedDocuments = null;
    
    if ($newStatus === 'suspended' || $newStatus === 'restricted') {
        // 2 weeks = 14 days
        $expiresAt = date('Y-m-d H:i:s', strtotime('+2 weeks'));
    }
    
    // For restricted status, specify which documents are restricted
    if ($newStatus === 'restricted') {
        $restrictedDocuments = json_encode([
            'Barangay Clearance',
            'Barangay ID',
            'Certificate of Good Moral Character',
            'Business Clearance',
            'Business Permit'
        ]);
    }
    
    // Update resident status
    $updateSql = "UPDATE residents SET 
        status = ?, 
        status_changed_at = ?,
        status_changed_by = ?,
        status_expires_at = ?,
        restricted_documents = ?
        WHERE id = ?";
    
    $updateStmt = $conn->prepare($updateSql);
    $updateStmt->bind_param('ssissi', $newStatus, $now, $staffId, $expiresAt, $restrictedDocuments, $residentId);
    
    if (!$updateStmt->execute()) {
        throw new Exception('Failed to update resident status: ' . $conn->error);
    }
    
    $updateStmt->close();
    
    // Prepare response message
    $statusMessages = [
        'active' => 'Resident status set to Active. They can request documents without restrictions.',
        'suspended' => 'Resident has been suspended for 2 weeks. They cannot request any documents until ' . date('F d, Y', strtotime($expiresAt)) . '.',
        'restricted' => 'Resident has been restricted for 2 weeks. They cannot request Barangay Clearance, Barangay ID, Good Moral Certificate, or Business Permit until ' . date('F d, Y', strtotime($expiresAt)) . '.'
    ];
    
    echo json_encode([
        'success' => true,
        'message' => $statusMessages[$newStatus],
        'data' => [
            'resident_id' => $residentId,
            'status' => $newStatus,
            'status_changed_at' => $now,
            'status_expires_at' => $expiresAt,
            'restricted_documents' => $newStatus === 'restricted' ? json_decode($restrictedDocuments) : null
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
