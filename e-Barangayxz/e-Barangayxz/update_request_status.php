<?php
// update_request_status.php
session_start();
header('Content-Type: application/json');

// Check if staff is logged in
if (!isset($_SESSION['staff_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

include 'db.php';
include_once 'add_resident_activity.php';

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
$request_id = $input['request_id'] ?? null;
$new_status = $input['status'] ?? null;

// Validate input
if (!$request_id || !$new_status) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

// Validate status value
$allowed_statuses = ['approved', 'rejected', 'pending', 'processing', 'completed'];
if (!in_array($new_status, $allowed_statuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status value']);
    exit;
}

// Extract numeric ID from request string (e.g., "#REQ-13" -> 13)
if (is_string($request_id) && strpos($request_id, '#REQ-') === 0) {
    $request_id = intval(substr($request_id, 5));
}

// Get staff name for activity logging
$staff_name = 'Staff';
$staffStmt = $conn->prepare("SELECT first_name, last_name FROM staff_accounts WHERE staff_id = ?");
if ($staffStmt) {
    $staffStmt->bind_param('i', $_SESSION['staff_id']);
    $staffStmt->execute();
    $staffResult = $staffStmt->get_result();
    if ($staffRow = $staffResult->fetch_assoc()) {
        $staff_name = trim($staffRow['first_name'] . ' ' . $staffRow['last_name']);
    }
    $staffStmt->close();
}

// Update the request status
$stmt = $conn->prepare("UPDATE requests SET status = ? WHERE id = ?");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    exit;
}

$stmt->bind_param('si', $new_status, $request_id);

if ($stmt->execute()) {
    // Get request details for logging/notification
    $detailStmt = $conn->prepare("SELECT requests.resident_id, requests.document_type, residents.first_name, residents.last_name FROM requests JOIN residents ON requests.resident_id = residents.id WHERE requests.id = ?");
    $detailStmt->bind_param('i', $request_id);
    $detailStmt->execute();
    $detailResult = $detailStmt->get_result();
    $requestDetail = $detailResult->fetch_assoc();
    $detailStmt->close();
    
    if ($requestDetail) {
        $resident_id = $requestDetail['resident_id'];
        $docType = $requestDetail['document_type'] ?? 'Document';
        $residentName = trim($requestDetail['first_name'] . ' ' . $requestDetail['last_name']);
        
        // Add resident activity based on status
        if ($new_status === 'approved') {
            addResidentActivity(
                $conn,
                $resident_id,
                $request_id,
                'request_approved',
                'Document Approved - Ready for Payment',
                "Your {$docType} request has been approved by {$staff_name}. Please proceed with payment.",
                $staff_name,
                $docType
            );
        } elseif ($new_status === 'rejected') {
            addResidentActivity(
                $conn,
                $resident_id,
                $request_id,
                'request_rejected',
                'Document Request Rejected',
                "Your {$docType} request has been rejected by {$staff_name}. Please contact the barangay office for more information.",
                $staff_name,
                $docType
            );
        } elseif ($new_status === 'processing') {
            addResidentActivity(
                $conn,
                $resident_id,
                $request_id,
                'request_processing',
                'Document Being Processed',
                "Your {$docType} request is now being processed by {$staff_name}.",
                $staff_name,
                $docType
            );
        }
    }
    
    // Log the activity with richer details
    $staff_id = $_SESSION['staff_id'];
    $residentName = $requestDetail ? trim($requestDetail['first_name'] . ' ' . $requestDetail['last_name']) : 'Unknown Resident';
    $docType = $requestDetail['document_type'] ?? 'Document';
    $action_type = 'Request ' . ucfirst($new_status);
    $action_details = "Staff updated request #{$request_id} ({$docType}) for {$residentName} to {$new_status}";
    
    $logStmt = $conn->prepare("INSERT INTO activity_log (staff_id, action_type, action_details, timestamp) VALUES (?, ?, ?, NOW())");
    if ($logStmt) {
        $logStmt->bind_param('iss', $staff_id, $action_type, $action_details);
        $logStmt->execute();
        $logStmt->close();
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'Request status updated to ' . $new_status,
        'status' => $new_status
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update status: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>
