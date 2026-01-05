<?php
// get_payment_proof.php
session_start();
header('Content-Type: application/json');

include 'db.php';

// Check if staff is logged in
if (!isset($_SESSION['staff_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get request ID
$requestId = isset($_GET['request_id']) ? intval($_GET['request_id']) : 0;

if (!$requestId) {
    echo json_encode(['success' => false, 'message' => 'Invalid request ID']);
    exit;
}

// Fetch payment submission details
$query = "
    SELECT 
        ps.id,
        ps.request_id,
        ps.resident_id,
        ps.amount,
        ps.payment_method,
        ps.reference_number,
        ps.proof_path,
        ps.notes,
        ps.status,
        ps.submitted_at,
        ps.verified_at
    FROM payment_submissions ps
    WHERE ps.request_id = ?
    ORDER BY ps.submitted_at DESC
    LIMIT 1
";

$stmt = $conn->prepare($query);
$stmt->bind_param('i', $requestId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $data = $result->fetch_assoc();
    echo json_encode([
        'success' => true,
        'data' => $data
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'No payment proof found for this request'
    ]);
}

$stmt->close();
$conn->close();
?>
