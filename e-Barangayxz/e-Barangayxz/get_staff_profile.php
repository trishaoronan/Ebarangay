<?php
// get_staff_profile.php - Get logged-in staff profile
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

$staff_id = $_SESSION['staff_id'];

try {
    // Ensure gender and civil_status columns exist
    $colCheckG = $conn->query("SHOW COLUMNS FROM staff_accounts LIKE 'gender'");
    if ($colCheckG && $colCheckG->num_rows === 0) { $conn->query("ALTER TABLE staff_accounts ADD COLUMN gender VARCHAR(30) DEFAULT NULL"); }
    $colCheckCS = $conn->query("SHOW COLUMNS FROM staff_accounts LIKE 'civil_status'");
    if ($colCheckCS && $colCheckCS->num_rows === 0) { $conn->query("ALTER TABLE staff_accounts ADD COLUMN civil_status VARCHAR(50) DEFAULT NULL"); }
    // Ensure name_edit_used column exists (for Female+Single one-time edit tracking)
    $colCheckNE = $conn->query("SHOW COLUMNS FROM staff_accounts LIKE 'name_edit_used'");
    if ($colCheckNE && $colCheckNE->num_rows === 0) { $conn->query("ALTER TABLE staff_accounts ADD COLUMN name_edit_used TINYINT(1) DEFAULT 0"); }
    
    // Ensure profile_pic column exists
    $colCheckPic = $conn->query("SHOW COLUMNS FROM staff_accounts LIKE 'profile_pic'");
    if ($colCheckPic && $colCheckPic->num_rows === 0) { $conn->query("ALTER TABLE staff_accounts ADD COLUMN profile_pic VARCHAR(255) DEFAULT NULL"); }

    // Query staff account
    $sql = "SELECT staff_id, first_name, middle_name, last_name, suffix, email, contact_number, status, date_created, gender, civil_status, name_edit_used, profile_pic 
            FROM staff_accounts 
            WHERE staff_id = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $stmt->bind_param("i", $staff_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Staff profile not found.']);
        $stmt->close();
        exit;
    }
    
    $staff = $result->fetch_assoc();
    $stmt->close();
    
    // Return staff profile
    echo json_encode([
        'success' => true,
        'data' => [
            'staff_id' => $staff['staff_id'],
            'first_name' => $staff['first_name'],
            'middle_name' => $staff['middle_name'],
            'last_name' => $staff['last_name'],
            'suffix' => $staff['suffix'],
            'email' => $staff['email'],
            'contact_number' => $staff['contact_number'],
            'status' => $staff['status'],
            'date_created' => $staff['date_created'],
            'gender' => $staff['gender'],
            'civil_status' => $staff['civil_status'],
            'name_edit_used' => $staff['name_edit_used'],
            'profile_pic' => $staff['profile_pic']
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

$conn->close();
?>
