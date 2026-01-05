<?php
// toggle_status.php
header('Content-Type: application/json');
error_reporting(E_ERROR | E_PARSE);

// Include database connection
include 'db.php';

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Only POST requests allowed.']);
    exit;
}

// Get the staff ID
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => 'Invalid staff ID.',
        'debug' => [
            'received_id' => $_POST['id'] ?? 'not set',
            'post_data' => $_POST
        ]
    ]);
    exit;
}

// Get current status
$sql = "SELECT status FROM staff_accounts WHERE staff_id = ?";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    exit;
}

$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Staff member not found.']);
    $stmt->close();
    exit;
}

$row = $result->fetch_assoc();
$currentStatus = $row['status'];
$newStatus = ($currentStatus === 'active') ? 'inactive' : 'active';
$stmt->close();

// Update status
$updateSql = "UPDATE staff_accounts SET status = ?, date_updated = NOW() WHERE staff_id = ?";
$updateStmt = $conn->prepare($updateSql);

if (!$updateStmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    exit;
}

$updateStmt->bind_param("si", $newStatus, $id);

if ($updateStmt->execute()) {
    // Try to log activity (don't fail if this doesn't work)
    try {
        // Get staff name for activity log
        $nameQuery = "SELECT first_name, last_name FROM staff_accounts WHERE staff_id = ?";
        $nameStmt = $conn->prepare($nameQuery);
        if ($nameStmt) {
            $nameStmt->bind_param("i", $id);
            $nameStmt->execute();
            $nameResult = $nameStmt->get_result();
            $staffName = 'Unknown';
            if ($nameRow = $nameResult->fetch_assoc()) {
                $staffName = $nameRow['first_name'] . ' ' . $nameRow['last_name'];
            }
            $nameStmt->close();
            
            // Log activity
            $adminStaffId = 0; // Use 0 to represent Super Admin
            $logSql = "INSERT INTO activity_log (staff_id, action_type, action_details, affected_staff_id, timestamp) VALUES (?, ?, ?, ?, NOW())";
            $logStmt = $conn->prepare($logSql);
            if ($logStmt) {
                $actionType = "Status change";
                $actionDetails = "Super Admin changed {$staffName}'s status from {$currentStatus} to {$newStatus}";
                $logStmt->bind_param("issi", $adminStaffId, $actionType, $actionDetails, $id);
                @$logStmt->execute(); // Suppress errors
                $logStmt->close();
            }
        }
    } catch (Exception $e) {
        // Silently fail activity logging
        error_log("Activity log failed: " . $e->getMessage());
    }
    
    echo json_encode([
        'success' => true,
        'message' => "Status changed to {$newStatus}.",
        'data' => [
            'id' => $id,
            'status' => $newStatus
        ]
    ]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to update status: ' . $updateStmt->error]);
}

$updateStmt->close();
$conn->close();
?>
