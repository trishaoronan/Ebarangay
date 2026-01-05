<?php
// update_staff_password.php - Update logged-in staff password
session_start();
header('Content-Type: application/json');
error_reporting(E_ERROR | E_PARSE);

// Include database connection
include 'db.php';

// Check if staff is logged in
if (!isset($_SESSION['staff_id']) || !isset($_SESSION['logged_in'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
    exit;
}

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Only POST requests allowed.']);
    exit;
}

$staff_id = $_SESSION['staff_id'];
$new_password = isset($_POST['new_password']) ? trim($_POST['new_password']) : '';
$confirm_password = isset($_POST['confirm_password']) ? trim($_POST['confirm_password']) : '';

// Validate input
if (empty($new_password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'New password is required.']);
    exit;
}

if (empty($confirm_password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please confirm your new password.']);
    exit;
}

if ($new_password !== $confirm_password) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
    exit;
}

// Validate password strength - strong password policy
if (strlen($new_password) < 8) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters long.']);
    exit;
}

// Check for uppercase letter
if (!preg_match('/[A-Z]/', $new_password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Password must contain at least one uppercase letter.']);
    exit;
}

// Check for lowercase letter
if (!preg_match('/[a-z]/', $new_password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Password must contain at least one lowercase letter.']);
    exit;
}

// Check for number
if (!preg_match('/[0-9]/', $new_password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Password must contain at least one number.']);
    exit;
}

// Check for special character
if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $new_password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Password must contain at least one special character (!@#$%^&*(),.?":{}|<>).']);
    exit;
}

// Hash the password
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

try {
    // Get staff name for activity log
    $nameQuery = "SELECT first_name, last_name FROM staff_accounts WHERE staff_id = ?";
    $nameStmt = $conn->prepare($nameQuery);
    if (!$nameStmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $nameStmt->bind_param("i", $staff_id);
    $nameStmt->execute();
    $nameResult = $nameStmt->get_result();
    $staffData = $nameResult->fetch_assoc();
    $staffName = $staffData ? trim($staffData['first_name'] . ' ' . $staffData['last_name']) : 'Unknown';
    $nameStmt->close();
    
    // Update password with hashed value
    $updateSql = "UPDATE staff_accounts SET password_hash = ?, date_updated = NOW() WHERE staff_id = ?";
    $updateStmt = $conn->prepare($updateSql);
    
    if (!$updateStmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $updateStmt->bind_param("si", $hashed_password, $staff_id);
    
    if ($updateStmt->execute()) {
        $updateStmt->close();
        
        // Log activity
        try {
            $logSql = "INSERT INTO activity_log (staff_id, action_type, action_details, affected_staff_id, timestamp) 
                       VALUES (?, 'Password Change', ?, NULL, NOW())";
            $logStmt = $conn->prepare($logSql);
            if ($logStmt) {
                $actionDetails = 'Staff ' . $staffName . ' changed their password';
                $logStmt->bind_param("is", $staff_id, $actionDetails);
                @$logStmt->execute();
                $logStmt->close();
            }
        } catch (Exception $e) {
            // Silently fail activity logging
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Password updated successfully.'
        ]);
    } else {
        throw new Exception('Failed to update password: ' . $updateStmt->error);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>
