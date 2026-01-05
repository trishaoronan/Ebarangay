<?php
// remove_staff_profile_pic.php - Remove staff profile picture
session_start();
header('Content-Type: application/json');
error_reporting(E_ERROR | E_PARSE);

include 'db.php';

// Check if staff is logged in
if (!isset($_SESSION['staff_id']) || !isset($_SESSION['logged_in'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$staff_id = $_SESSION['staff_id'];

try {
    // Get current profile picture
    $stmt = $conn->prepare("SELECT profile_pic FROM staff_accounts WHERE staff_id = ?");
    $stmt->bind_param("i", $staff_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();

    if ($data && !empty($data['profile_pic'])) {
        // Delete the file
        $filePath = $data['profile_pic'];
        if (file_exists($filePath) && strpos($filePath, 'staff_profile_pics/') === 0) {
            unlink($filePath);
        }
    }

    // Clear profile_pic in database
    $stmt = $conn->prepare("UPDATE staff_accounts SET profile_pic = NULL WHERE staff_id = ?");
    $stmt->bind_param("i", $staff_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Profile picture removed']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update database']);
    }
    $stmt->close();

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

$conn->close();
?>
