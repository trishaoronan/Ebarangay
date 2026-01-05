<?php
// submit_payment_proof.php
include 'auth_check.php'; // This handles session_start() and authentication
header('Content-Type: application/json');

include 'db.php';

$resident_id = $_SESSION['resident_id'];
$request_id = $_POST['request_id'] ?? null;
$amount = $_POST['amount'] ?? null;
$payment_method = $_POST['payment_method'] ?? null;
$reference_number = $_POST['reference_number'] ?? '';
$notes = $_POST['notes'] ?? '';

// Validate input
if (!$request_id || !$amount || !$payment_method) {
    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields']);
    exit;
}

// Verify that this request belongs to the logged-in resident
$checkStmt = $conn->prepare("SELECT id FROM requests WHERE id = ? AND resident_id = ?");
$checkStmt->bind_param('ii', $request_id, $resident_id);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();

if ($checkResult->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    $checkStmt->close();
    exit;
}
$checkStmt->close();

// Handle file upload
$proofPath = null;
if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = 'uploads/payment_proofs/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $fileExt = strtolower(pathinfo($_FILES['payment_proof']['name'], PATHINFO_EXTENSION));
    $allowedExts = ['jpg', 'jpeg', 'png', 'pdf'];
    
    if (!in_array($fileExt, $allowedExts)) {
        echo json_encode(['success' => false, 'message' => 'Invalid file type. Only images and PDF are allowed.']);
        exit;
    }
    
    // 5MB limit
    if ($_FILES['payment_proof']['size'] > 5242880) {
        echo json_encode(['success' => false, 'message' => 'File size must not exceed 5MB.']);
        exit;
    }
    
    $fileName = 'payment_' . $resident_id . '_' . $request_id . '_' . time() . '.' . $fileExt;
    $uploadPath = $uploadDir . $fileName;
    
    if (move_uploaded_file($_FILES['payment_proof']['tmp_name'], $uploadPath)) {
        $proofPath = $uploadPath;
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to upload payment proof.']);
        exit;
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Please upload payment proof.']);
    exit;
}

// Create payment_submissions table if it doesn't exist
$createTableQuery = "CREATE TABLE IF NOT EXISTS payment_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    resident_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(100) NOT NULL,
    reference_number VARCHAR(255) DEFAULT NULL,
    proof_path VARCHAR(500) NOT NULL,
    notes TEXT DEFAULT NULL,
    status VARCHAR(50) DEFAULT 'pending',
    submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    verified_at DATETIME DEFAULT NULL,
    FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE,
    FOREIGN KEY (resident_id) REFERENCES residents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

$conn->query($createTableQuery);

// Add payment_proof column if it doesn't exist
$conn->query("ALTER TABLE requests ADD COLUMN IF NOT EXISTS payment_proof VARCHAR(500) DEFAULT NULL");

// Insert payment submission
$stmt = $conn->prepare("INSERT INTO payment_submissions (request_id, resident_id, amount, payment_method, reference_number, proof_path, notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    exit;
}

$stmt->bind_param('iidssss', $request_id, $resident_id, $amount, $payment_method, $reference_number, $proofPath, $notes);

if ($stmt->execute()) {
    $submission_id = $conn->insert_id;
    $stmt->close();
    
    // Update requests table with payment proof and change status to "Paid"
    $updateStmt = $conn->prepare("UPDATE requests SET payment_proof = ?, payment_status = 'Paid' WHERE id = ?");
    if ($updateStmt) {
        $updateStmt->bind_param('si', $proofPath, $request_id);
        $updateStmt->execute();
        $updateStmt->close();
    }
    
    // Get resident name for notification
    $residentName = $_SESSION['resident_name'] ?? 'A resident';
    
    // Get document type from request
    $docType = 'document';
    $docStmt = $conn->prepare("SELECT document_type FROM requests WHERE id = ?");
    if ($docStmt) {
        $docStmt->bind_param('i', $request_id);
        $docStmt->execute();
        $docResult = $docStmt->get_result();
        if ($docRow = $docResult->fetch_assoc()) {
            $docType = $docRow['document_type'] ?? 'document';
        }
        $docStmt->close();
    }
    
    // Notify staff about payment submission
    include_once 'add_staff_notification.php';
    include_once 'add_resident_activity.php';
    
    addStaffNotification(
        $conn,
        'payment_sent',
        'Payment Proof Submitted',
        "$residentName has sent payment proof for $docType (₱" . number_format($amount, 2) . ")",
        $request_id,
        'payment'
    );
    
    // Add resident activity for payment submission
    addResidentActivity(
        $conn,
        $resident_id,
        $request_id,
        'payment_submitted',
        'Payment Submitted',
        "You submitted payment of ₱" . number_format($amount, 2) . " for {$docType}. Waiting for confirmation.",
        null,  // No staff involved yet
        $docType
    );
    
    echo json_encode([
        'success' => true,
        'message' => 'Payment submitted successfully!',
        'submission_id' => $submission_id
    ]);
} else {
    // Delete uploaded file if database insert fails
    if ($proofPath && file_exists($proofPath)) {
        unlink($proofPath);
    }
    echo json_encode(['success' => false, 'message' => 'Failed to submit payment: ' . $stmt->error]);
    $stmt->close();
}
$conn->close();
?>
