<?php
// update_staff_contact.php - Update logged-in staff contact number
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
$contact_number = isset($_POST['contact_number']) ? trim($_POST['contact_number']) : '';

// Remove formatting (dashes, spaces)
$contact_number = preg_replace('/[^0-9]/', '', $contact_number);

// Validate contact number (11 digits starting with 09)
if (!preg_match('/^09\d{9}$/', $contact_number)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid contact number. Must be 11 digits starting with 09.']);
    exit;
}

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
    
    // Update contact number
    $updateSql = "UPDATE staff_accounts SET contact_number = ?, date_updated = NOW() WHERE staff_id = ?";
    $updateStmt = $conn->prepare($updateSql);
    
    if (!$updateStmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $updateStmt->bind_param("si", $contact_number, $staff_id);
    
    if ($updateStmt->execute()) {
        $updateStmt->close();
        
        // Log activity
        try {
            $logSql = "INSERT INTO activity_log (staff_id, action_type, action_details, affected_staff_id, timestamp) 
                       VALUES (?, 'Contact Update', ?, NULL, NOW())";
            $logStmt = $conn->prepare($logSql);
            if ($logStmt) {
                $actionDetails = 'Staff ' . $staffName . ' updated their contact number to ' . $contact_number;
                $logStmt->bind_param("is", $staff_id, $actionDetails);
                @$logStmt->execute();
                $logStmt->close();
            }
        } catch (Exception $e) {
            // Silently fail activity logging
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Contact number updated successfully.'
        ]);
    } else {
        throw new Exception('Failed to update contact number: ' . $updateStmt->error);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>
