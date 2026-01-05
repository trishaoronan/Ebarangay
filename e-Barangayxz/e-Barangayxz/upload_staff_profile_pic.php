<?php
// upload_staff_profile_pic.php - Handle staff profile picture upload
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

// Ensure profile_pic column exists
$colCheck = $conn->query("SHOW COLUMNS FROM staff_accounts LIKE 'profile_pic'");
if ($colCheck && $colCheck->num_rows === 0) {
    $conn->query("ALTER TABLE staff_accounts ADD COLUMN profile_pic VARCHAR(255) DEFAULT NULL");
}

// Check if file was uploaded
if (!isset($_FILES['profile_pic']) || $_FILES['profile_pic']['error'] !== UPLOAD_ERR_OK) {
    $errorMsg = 'No file uploaded';
    if (isset($_FILES['profile_pic'])) {
        switch ($_FILES['profile_pic']['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $errorMsg = 'File too large';
                break;
            case UPLOAD_ERR_PARTIAL:
                $errorMsg = 'File only partially uploaded';
                break;
            case UPLOAD_ERR_NO_FILE:
                $errorMsg = 'No file selected';
                break;
            default:
                $errorMsg = 'Upload error';
        }
    }
    echo json_encode(['success' => false, 'message' => $errorMsg]);
    exit;
}

$file = $_FILES['profile_pic'];
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$maxSize = 5 * 1024 * 1024; // 5MB

// Validate file type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mimeType, $allowedTypes)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Allowed: JPG, PNG, GIF, WEBP']);
    exit;
}

// Validate file size
if ($file['size'] > $maxSize) {
    echo json_encode(['success' => false, 'message' => 'File size exceeds 5MB limit']);
    exit;
}

// Create staff_profile_pics directory if it doesn't exist
$uploadDir = 'staff_profile_pics/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Generate unique filename
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
if (!$extension) {
    // Determine extension from mime type
    $extMap = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp'
    ];
    $extension = $extMap[$mimeType] ?? 'jpg';
}
$newFilename = 'staff_' . $staff_id . '_' . time() . '.' . $extension;
$uploadPath = $uploadDir . $newFilename;

// Delete old profile picture if exists
$stmt = $conn->prepare("SELECT profile_pic FROM staff_accounts WHERE staff_id = ?");
$stmt->bind_param("i", $staff_id);
$stmt->execute();
$result = $stmt->get_result();
$oldData = $result->fetch_assoc();
$stmt->close();

if ($oldData && !empty($oldData['profile_pic'])) {
    $oldFile = $oldData['profile_pic'];
    if (file_exists($oldFile) && strpos($oldFile, 'staff_profile_pics/') === 0) {
        unlink($oldFile);
    }
}

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save uploaded file']);
    exit;
}

// Update database with new profile picture path
$stmt = $conn->prepare("UPDATE staff_accounts SET profile_pic = ? WHERE staff_id = ?");
$stmt->bind_param("si", $uploadPath, $staff_id);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Profile picture uploaded successfully',
        'profile_pic' => $uploadPath
    ]);
} else {
    // Delete uploaded file if database update fails
    if (file_exists($uploadPath)) {
        unlink($uploadPath);
    }
    echo json_encode(['success' => false, 'message' => 'Failed to update database']);
}

$stmt->close();
$conn->close();
?>
