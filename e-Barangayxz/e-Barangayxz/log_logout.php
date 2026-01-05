<?php
session_start();
include 'db.php';

header('Content-Type: application/json');

// Get staff_id from POST
$staff_id = isset($_POST['staff_id']) ? intval($_POST['staff_id']) : 0;

// Get staff name from session or database
$staff_name = 'Super Admin';

if ($staff_id === 0) {
    // Super Admin logout
    if (isset($_SESSION['admin_name'])) {
        $staff_name = $_SESSION['admin_name'];
    }
} else {
    // Regular staff logout (if needed in the future)
    if (isset($_SESSION['staff_name'])) {
        $staff_name = $_SESSION['staff_name'];
    } else {
        // Fetch from database
        $stmt = $conn->prepare("SELECT first_name, last_name FROM staff_accounts WHERE staff_id = ?");
        $stmt->bind_param("i", $staff_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $staff = $result->fetch_assoc();
            $staff_name = trim($staff['first_name'] . ' ' . $staff['last_name']);
        }
        $stmt->close();
    }
}

// Log the logout activity
try {
    $action_type = 'Logout';
    $action_details = 'Staff ' . $staff_name . ' logged out';
    
    $stmt = $conn->prepare("INSERT INTO activity_log (staff_id, action_type, action_details, affected_staff_id, timestamp) VALUES (?, ?, ?, NULL, NOW())");
    $stmt->bind_param("iss", $staff_id, $action_type, $action_details);
    
    if ($stmt->execute()) {
        $stmt->close();
        
        // Destroy session
        session_unset();
        session_destroy();
        
        echo json_encode([
            'success' => true,
            'message' => 'Logout logged successfully',
            'staff_name' => $staff_name
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Failed to log logout: ' . $conn->error
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Exception: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
