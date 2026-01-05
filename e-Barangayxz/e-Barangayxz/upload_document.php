<?php
// upload_document.php
session_start();
header('Content-Type: application/json');

// Check if staff is logged in
if (!isset($_SESSION['staff_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

include 'db.php';
include_once 'add_resident_activity.php';

$request_id = $_POST['request_id'] ?? null;
$notes = $_POST['notes'] ?? '';

// Validate input
if (!$request_id) {
    echo json_encode(['success' => false, 'message' => 'Request ID is required']);
    exit;
}

// Get staff name for activity logging
$staff_name = 'Staff';
$staffStmt = $conn->prepare("SELECT first_name, last_name FROM staff_accounts WHERE staff_id = ?");
if ($staffStmt) {
    $staffStmt->bind_param('i', $_SESSION['staff_id']);
    $staffStmt->execute();
    $staffResult = $staffStmt->get_result();
    if ($staffRow = $staffResult->fetch_assoc()) {
        $staff_name = trim($staffRow['first_name'] . ' ' . $staffRow['last_name']);
    }
    $staffStmt->close();
}

// Handle file upload
$documentPath = null;
if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = 'uploads/documents/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $fileExt = strtolower(pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION));
    $allowedExts = ['pdf', 'doc', 'docx'];
    
    if (!in_array($fileExt, $allowedExts)) {
        echo json_encode(['success' => false, 'message' => 'Invalid file type. Only PDF, DOC, and DOCX are allowed.']);
        exit;
    }
    
    // 10MB limit
    if ($_FILES['document']['size'] > 10485760) {
        echo json_encode(['success' => false, 'message' => 'File size must not exceed 10MB.']);
        exit;
    }
    
    $fileName = 'document_' . $request_id . '_' . time() . '.' . $fileExt;
    $uploadPath = $uploadDir . $fileName;
    
    if (move_uploaded_file($_FILES['document']['tmp_name'], $uploadPath)) {
        $documentPath = $uploadPath;
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to upload document.']);
        exit;
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Please upload a document.']);
    exit;
}

// Verify payment status is "Paid" before allowing upload
$checkStmt = $conn->prepare("SELECT payment_status, resident_id, document_type FROM requests WHERE id = ?");
$checkStmt->bind_param('i', $request_id);
$checkStmt->execute();
$result = $checkStmt->get_result();
$request = $result->fetch_assoc();
$checkStmt->close();

if (!$request || $request['payment_status'] !== 'Paid') {
    // Delete uploaded file since payment not verified
    if ($documentPath && file_exists($documentPath)) {
        unlink($documentPath);
    }
    echo json_encode(['success' => false, 'message' => 'Document can only be uploaded for paid requests']);
    exit;
}

// Update request with document path, notes, and set status to Completed
$stmt = $conn->prepare("UPDATE requests SET document_path = ?, notes = ?, status = 'Completed', given_at = NOW() WHERE id = ?");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    exit;
}

$stmt->bind_param('ssi', $documentPath, $notes, $request_id);

if ($stmt->execute()) {
    // Add resident activity for document ready
    $resident_id = $request['resident_id'];
    $docType = $request['document_type'] ?? 'Document';
    
    addResidentActivity(
        $conn,
        $resident_id,
        $request_id,
        'document_ready',
        'Document Ready for Download',
        "Your {$docType} is now ready! Processed by {$staff_name}. You can download it from your dashboard.",
        $staff_name,
        $docType
    );
    
    // Also add document released activity
    addResidentActivity(
        $conn,
        $resident_id,
        $request_id,
        'document_released',
        'Document Released',
        "Your {$docType} has been officially released by {$staff_name}. You can now download your document.",
        $staff_name,
        $docType
    );
    
    // Log the activity
    $staff_id = $_SESSION['staff_id'];
    $action_type = 'Document Uploaded & Released';
    $action_details = "Staff uploaded and released document for request #$request_id";
    
    $logStmt = $conn->prepare("INSERT INTO activity_log (staff_id, action_type, action_details) VALUES (?, ?, ?)");
    if ($logStmt) {
        $logStmt->bind_param('iss', $staff_id, $action_type, $action_details);
        $logStmt->execute();
        $logStmt->close();
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Document uploaded successfully! Request status changed to Completed.',
        'document_path' => $documentPath
    ]);
} else {
    // Delete uploaded file if database update fails
    if ($documentPath && file_exists($documentPath)) {
        unlink($documentPath);
    }
    echo json_encode(['success' => false, 'message' => 'Failed to update request: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>
