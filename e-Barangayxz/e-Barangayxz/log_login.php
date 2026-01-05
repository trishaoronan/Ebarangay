<?php
// log_login.php - Log login activity
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

// Get staff ID (0 for Super Admin)
$staff_id = isset($_POST['staff_id']) ? intval($_POST['staff_id']) : 0;

// Get staff name
$staff_name = 'Super Admin';
if ($staff_id > 0) {
    $nameQuery = "SELECT first_name, last_name FROM staff_accounts WHERE staff_id = ?";
    $nameStmt = $conn->prepare($nameQuery);
    if ($nameStmt) {
        $nameStmt->bind_param("i", $staff_id);
        $nameStmt->execute();
        $nameResult = $nameStmt->get_result();
        if ($nameRow = $nameResult->fetch_assoc()) {
            $staff_name = $nameRow['first_name'] . ' ' . $nameRow['last_name'];
        }
        $nameStmt->close();
    }
}

// Log the login activity
try {
    $logSql = "INSERT INTO activity_log (staff_id, action_type, action_details, affected_staff_id, timestamp) 
               VALUES (?, 'Login', ?, NULL, NOW())";
    $logStmt = $conn->prepare($logSql);
    
    if (!$logStmt) {
        throw new Exception('Failed to prepare statement: ' . $conn->error);
    }
    
    $actionDetails = 'Staff ' . $staff_name . ' logged in';
    $logStmt->bind_param("is", $staff_id, $actionDetails);
    
    if ($logStmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Login activity logged successfully.',
            'staff_name' => $staff_name
        ]);
    } else {
        throw new Exception('Failed to execute: ' . $logStmt->error);
    }
    
    $logStmt->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to log activity: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
