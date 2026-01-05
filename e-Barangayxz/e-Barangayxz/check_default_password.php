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

try {
    // Get the staff's password hash
    $stmt = $conn->prepare("SELECT password_hash FROM staff_accounts WHERE staff_id = ?");
    $stmt->bind_param("i", $staff_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $staff = $result->fetch_assoc();
        
        // Check if password is still the default password 'Admin123!'
        $isDefaultPassword = password_verify('Admin123!', $staff['password_hash']);
        
        echo json_encode([
            'success' => true,
            'isDefaultPassword' => $isDefaultPassword
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Staff not found']);
    }
    
    $stmt->close();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

$conn->close();
?>
