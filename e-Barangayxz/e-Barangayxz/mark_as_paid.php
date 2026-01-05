<?php
// mark_as_paid.php
session_start();
include 'db.php';

// Check if staff is logged in
if (!isset($_SESSION['staff_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get request data
$data = json_decode(file_get_contents('php://input'), true);
$request_id = $data['request_id'] ?? null;

if (!$request_id) {
    echo json_encode(['success' => false, 'message' => 'Request ID is required']);
    exit;
}

// Check if payment_status column exists
$colCheck = $conn->query("SHOW COLUMNS FROM requests LIKE 'payment_status'");
if (!$colCheck || $colCheck->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Payment status column does not exist']);
    exit;
}

// Update payment status to Paid
$stmt = $conn->prepare("UPDATE requests SET payment_status = 'Paid' WHERE id = ?");
$stmt->bind_param('i', $request_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Payment marked as paid successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update payment status: ' . $conn->error]);
}

$stmt->close();
$conn->close();
?>
