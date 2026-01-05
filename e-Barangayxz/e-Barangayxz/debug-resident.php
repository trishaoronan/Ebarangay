<?php
// debug-resident.php - temporary debug endpoint, remove after use
include 'auth_check.php';
include 'db.php';
header('Content-Type: application/json');
$resident_id = $_SESSION['resident_id'] ?? null;
$sess = [];
foreach ($_SESSION as $k => $v) { $sess[$k] = $v; }
if (!$resident_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not logged in', 'session' => $sess]);
    exit;
}
// fetch resident row
$stmt = $conn->prepare("SELECT * FROM residents WHERE id = ? LIMIT 1");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB prepare failed', 'error' => $conn->error, 'session' => $sess]);
    exit;
}
$stmt->bind_param('i', $resident_id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();

echo json_encode(['success' => true, 'session' => $sess, 'resident' => $row]);
