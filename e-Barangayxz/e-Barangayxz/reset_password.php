<?php
session_start();
include 'db.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] !== 'superadmin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

header('Content-Type: application/json');

// Function to sanitize input
function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// Get POST data
$staff_id = isset($_POST['staff_id']) ? intval($_POST['staff_id']) : 0;
$new_password = sanitize($_POST['new_password'] ?? '');

// Validation
if ($staff_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid staff ID']);
    exit;
}

if (empty($new_password)) {
    echo json_encode(['success' => false, 'error' => 'Password is required']);
    exit;
}

if (strlen($new_password) < 6) {
    echo json_encode(['success' => false, 'error' => 'Password must be at least 6 characters']);
    exit;
}

// Hash the password before storing
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

// Get staff information before updating
$stmt = $conn->prepare("SELECT first_name, middle_name, last_name, email FROM staff_accounts WHERE staff_id = ?");
$stmt->bind_param("i", $staff_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'Staff not found']);
    exit;
}

$staff = $result->fetch_assoc();
$stmt->close();

// Prevent resetting super admin password through this endpoint
if ($staff['email'] === 'superadmin@gmail.com') {
    echo json_encode(['success' => false, 'error' => 'Cannot reset super admin password']);
    exit;
}

// Update password with hashed value
$update_stmt = $conn->prepare("UPDATE staff_accounts SET password_hash = ?, date_updated = NOW() WHERE staff_id = ?");
$update_stmt->bind_param("si", $hashed_password, $staff_id);

if ($update_stmt->execute()) {
    $update_stmt->close();
    
    // Log activity
    $staff_name = trim($staff['first_name'] . ' ' . $staff['middle_name'] . ' ' . $staff['last_name']);
    $action_type = 'Password Reset';
    $action_details = "Super Admin reset password for staff {$staff_name}";
    
    $log_stmt = $conn->prepare("INSERT INTO activity_log (staff_id, action_type, action_details, affected_staff_id, timestamp) VALUES (0, ?, ?, ?, NOW())");
    $log_stmt->bind_param("ssi", $action_type, $action_details, $staff_id);
    $log_stmt->execute();
    $log_stmt->close();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Password reset successfully for ' . $staff_name
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to reset password: ' . $conn->error]);
}

$conn->close();
?>
