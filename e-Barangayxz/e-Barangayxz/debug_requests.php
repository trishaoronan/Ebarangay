<?php
// debug_requests.php
session_start();
include 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['resident_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$resident_id = $_SESSION['resident_id'];

$sql = "SELECT id, resident_id, document_type, status, payment_status, payment_proof, requested_at 
        FROM requests 
        WHERE resident_id = ? 
        ORDER BY requested_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $resident_id);
$stmt->execute();
$result = $stmt->get_result();

$requests = [];
while ($row = $result->fetch_assoc()) {
    $requests[] = $row;
}

echo json_encode([
    'resident_id' => $resident_id,
    'total_requests' => count($requests),
    'requests' => $requests
], JSON_PRETTY_PRINT);

$stmt->close();
$conn->close();
?>
