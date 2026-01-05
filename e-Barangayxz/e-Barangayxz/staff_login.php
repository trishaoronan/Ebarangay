<?php
// staff_login.php - Staff Login Authentication
session_start();
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

// Get credentials
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$password = isset($_POST['password']) ? trim($_POST['password']) : '';

// Validate input
if (empty($email) || empty($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email and password are required.']);
    exit;
}

try {
    // Query staff account (exclude super admin)
    $sql = "SELECT staff_id, email, password_hash, first_name, last_name, status 
            FROM staff_accounts 
            WHERE email = ? AND email != 'superadmin@gmail.com' AND status = 'active'";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
        $stmt->close();
        exit;
    }
    
    $staff = $result->fetch_assoc();
    $stmt->close();
    
    // Check if password_hash exists and verify password
    if (!isset($staff['password_hash']) || empty($staff['password_hash'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Account not configured for login.']);
        exit;
    }
    
    // Verify password using password_verify for hashed passwords
    if (!password_verify($password, $staff['password_hash'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
        exit;
    }
    
    // Set session variables
    $fullName = trim($staff['first_name'] . ' ' . $staff['last_name']);
    $_SESSION['staff_id'] = $staff['staff_id'];
    $_SESSION['staff_email'] = $staff['email'];
    $_SESSION['staff_name'] = $fullName;
    $_SESSION['staff_role'] = 'staff';
    $_SESSION['logged_in'] = true;
    
    // Log the login activity
    try {
        $logSql = "INSERT INTO activity_log (staff_id, action_type, action_details, affected_staff_id, timestamp) 
                   VALUES (?, 'Login', ?, NULL, NOW())";
        $logStmt = $conn->prepare($logSql);
        if ($logStmt) {
            $loginDetails = $fullName . ' logged in';
            $logStmt->bind_param("is", $staff['staff_id'], $loginDetails);
            @$logStmt->execute();
            $logStmt->close();
        }
    } catch (Exception $e) {
        // Silently fail activity logging
    }
    
    // Return success
    echo json_encode([
        'success' => true,
        'message' => 'Login successful.',
        'data' => [
            'staff_id' => $staff['staff_id'],
            'name' => $fullName,
            'email' => $staff['email'],
            'role' => 'staff'
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

$conn->close();
?>
