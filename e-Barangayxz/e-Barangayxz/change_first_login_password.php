<?php
session_start();
header('Content-Type: application/json');

include 'db.php';

// Check if staff is logged in
if (!isset($_SESSION['staff_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$staff_id = $_SESSION['staff_id'];
$new_password = isset($_POST['new_password']) ? trim($_POST['new_password']) : '';
$old_password = isset($_POST['old_password']) ? trim($_POST['old_password']) : '';

// Validate input
if (empty($new_password)) {
    echo json_encode(['success' => false, 'message' => 'New password is required']);
    exit;
}

// Validate password strength
if (strlen($new_password) < 8) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters long']);
    exit;
}

if (!preg_match('/[A-Z]/', $new_password)) {
    echo json_encode(['success' => false, 'message' => 'Password must contain at least one uppercase letter']);
    exit;
}

if (!preg_match('/[a-z]/', $new_password)) {
    echo json_encode(['success' => false, 'message' => 'Password must contain at least one lowercase letter']);
    exit;
}

if (!preg_match('/[0-9]/', $new_password)) {
    echo json_encode(['success' => false, 'message' => 'Password must contain at least one number']);
    exit;
}

if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $new_password)) {
    echo json_encode(['success' => false, 'message' => 'Password must contain at least one special character']);
    exit;
}

try {
    // First verify they still have the default password
    $stmt = $conn->prepare("SELECT password_hash, first_name, last_name FROM staff_accounts WHERE staff_id = ?");
    $stmt->bind_param("i", $staff_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Staff not found']);
        $stmt->close();
        exit;
    }
    
    $staff = $result->fetch_assoc();
    $stmt->close();
    
    // Prevent reusing the same password (we can still check this without asking for the old password)
    if (password_verify($new_password, $staff['password_hash'])) {
        echo json_encode(['success' => false, 'message' => 'New password cannot be the same as the current password']);
        exit;
    }
    
    // Hash the new password
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    // Update password in database
    $updateStmt = $conn->prepare("UPDATE staff_accounts SET password_hash = ?, date_updated = NOW() WHERE staff_id = ?");
    $updateStmt->bind_param("si", $hashed_password, $staff_id);
    
    if ($updateStmt->execute()) {
        $updateStmt->close();
        
        // Log the password change activity
        $staff_name = trim($staff['first_name'] . ' ' . $staff['last_name']);
        $action_details = $staff_name . ' changed password (first login)';
        
        $logStmt = $conn->prepare("INSERT INTO activity_log (staff_id, action_type, action_details, affected_staff_id, timestamp) VALUES (?, 'Password Change', ?, NULL, NOW())");
        $logStmt->bind_param("is", $staff_id, $action_details);
        $logStmt->execute();
        $logStmt->close();
        
        echo json_encode([
            'success' => true,
            'message' => 'Password changed successfully'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update password']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

$conn->close();
?>
