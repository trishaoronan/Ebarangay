<?php
// update_payment_status.php
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
$payment_status = $input['payment_status'] ?? null;

// Validate input
if (!$request_id || !$payment_status) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
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

// Update the request payment status
$updateStmt = $conn->prepare("UPDATE requests SET payment_status = ? WHERE id = ?");
if (!$updateStmt) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    exit;
}

$updateStmt->bind_param('si', $payment_status, $request_id);

if ($updateStmt->execute()) {
    // Get request details for resident activity
    $detailStmt = $conn->prepare("SELECT resident_id, document_type FROM requests WHERE id = ?");
    if ($detailStmt) {
        $detailStmt->bind_param('i', $request_id);
        $detailStmt->execute();
        $detailResult = $detailStmt->get_result();
        $requestDetail = $detailResult->fetch_assoc();
        $detailStmt->close();
        
        if ($requestDetail && $payment_status === 'Paid') {
            $resident_id = $requestDetail['resident_id'];
            $docType = $requestDetail['document_type'] ?? 'Document';
            
            // Add resident activity for payment confirmation
            addResidentActivity(
                $conn,
                $resident_id,
                $request_id,
                'payment_confirmed',
                'Payment Confirmed',
                "Your payment for {$docType} has been confirmed by {$staff_name}. Your document is now being processed.",
                $staff_name,
                $docType
            );
        }
    }
    
    // Log the activity
    $staff_id = $_SESSION['staff_id'];
    $action_type = 'Payment Updated';
    $action_details = "Staff marked request #$request_id payment as $payment_status";
    
    $logStmt = $conn->prepare("INSERT INTO activity_log (staff_id, action_type, action_details) VALUES (?, ?, ?)");
    if ($logStmt) {
        $logStmt->bind_param('iss', $staff_id, $action_type, $action_details);
        $logStmt->execute();
        $logStmt->close();
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'Payment status updated to ' . $payment_status,
        'payment_status' => $payment_status
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update payment status: ' . $updateStmt->error]);
}

$updateStmt->close();
$conn->close();
?>
