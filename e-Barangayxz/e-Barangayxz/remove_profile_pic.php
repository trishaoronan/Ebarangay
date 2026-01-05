<?php
// remove_profile_pic.php
include 'auth_check.php';
include 'db.php';

header('Content-Type: application/json');

$resident_id = $_SESSION['resident_id'] ?? null;
if (!$resident_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check if profile_pic column exists
$colCheck = $conn->query("SHOW COLUMNS FROM residents LIKE 'profile_pic'");
if (!$colCheck || $colCheck->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Profile picture feature not available']);
    exit;
}

// Get current profile pic path
$stmt = $conn->prepare("SELECT profile_pic FROM residents WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $resident_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

$currentPic = $row['profile_pic'] ?? null;

// Delete the file if it exists
if ($currentPic && file_exists($currentPic)) {
    unlink($currentPic);
}

// Update database to remove profile_pic
$updateStmt = $conn->prepare("UPDATE residents SET profile_pic = NULL WHERE id = ?");
$updateStmt->bind_param('i', $resident_id);
$success = $updateStmt->execute();
$updateStmt->close();

if ($success) {
    echo json_encode(['success' => true, 'message' => 'Profile picture removed']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to remove profile picture']);
}
