<?php
// mark_deceased.php
// Marks a resident as deceased and locks their account permanently
// This action cannot be undone via the UI

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

if ($residentId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid resident ID.']);
    exit;
}

// Optional fields
$dateOfDeath = isset($input['date_of_death']) && !empty($input['date_of_death']) 
    ? $input['date_of_death'] 
    : null;
$remarks = isset($input['remarks']) ? trim($input['remarks']) : null;
$archiveRequests = isset($input['archive_requests']) ? (bool)$input['archive_requests'] : false;

try {
    // First check if the status column exists, if not create it
    $checkColumn = $conn->query("SHOW COLUMNS FROM residents LIKE 'status'");
    if (!$checkColumn || $checkColumn->num_rows === 0) {
        // Add the status column
        $conn->query("ALTER TABLE residents ADD COLUMN status ENUM('active', 'suspended', 'restricted', 'deceased') NOT NULL DEFAULT 'active' AFTER is_active");
        $conn->query("ALTER TABLE residents ADD COLUMN date_of_death DATE NULL AFTER status");
        $conn->query("ALTER TABLE residents ADD COLUMN death_remarks TEXT NULL AFTER date_of_death");
        $conn->query("ALTER TABLE residents ADD COLUMN status_changed_at DATETIME NULL AFTER death_remarks");
        $conn->query("ALTER TABLE residents ADD COLUMN status_changed_by INT NULL AFTER status_changed_at");
    }
    
    // Check if resident exists and is not already deceased
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
    
    // Check if already deceased
    if (isset($resident['status']) && $resident['status'] === 'deceased') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'This resident is already marked as deceased.']);
        exit;
    }
    
    // Update resident status to deceased
    $staffId = $_SESSION['staff_id'];
    $now = date('Y-m-d H:i:s');
    
    $updateSql = "UPDATE residents SET 
        status = 'deceased', 
        is_active = 0, 
        date_of_death = ?, 
        death_remarks = ?,
        status_changed_at = ?,
        status_changed_by = ?
        WHERE id = ?";
    
    $updateStmt = $conn->prepare($updateSql);
    $updateStmt->bind_param('sssii', $dateOfDeath, $remarks, $now, $staffId, $residentId);
    
    if (!$updateStmt->execute()) {
        throw new Exception('Failed to update resident status: ' . $conn->error);
    }
    
    $updateStmt->close();
    
    // Optionally archive/cancel pending requests
    if ($archiveRequests) {
        $archiveSql = "UPDATE requests SET status = 'cancelled', notes = CONCAT(IFNULL(notes, ''), ' [Auto-cancelled: Resident marked as deceased]') 
                       WHERE resident_id = ? AND status IN ('pending', 'approved')";
        $archiveStmt = $conn->prepare($archiveSql);
        if ($archiveStmt) {
            $archiveStmt->bind_param('i', $residentId);
            $archiveStmt->execute();
            $archiveStmt->close();
        }
    }
    
    // Log this action for audit trail (optional - if activity log exists)
    $logSql = "INSERT INTO activity_log (staff_id, action, details, created_at) VALUES (?, 'mark_deceased', ?, NOW())";
    $logStmt = $conn->prepare($logSql);
    if ($logStmt) {
        $logDetails = json_encode([
            'resident_id' => $residentId,
            'resident_name' => trim($resident['first_name'] . ' ' . $resident['last_name']),
            'date_of_death' => $dateOfDeath,
            'remarks' => $remarks
        ]);
        $logStmt->bind_param('is', $staffId, $logDetails);
        $logStmt->execute();
        $logStmt->close();
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Resident has been marked as deceased successfully.',
        'data' => [
            'resident_id' => $residentId,
            'status' => 'deceased',
            'date_of_death' => $dateOfDeath,
            'remarks' => $remarks
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
