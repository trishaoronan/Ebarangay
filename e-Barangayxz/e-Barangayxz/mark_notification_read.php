<?php
// mark_notification_read.php
header('Content-Type: application/json');
error_reporting(E_ERROR | E_PARSE);
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Only POST allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id = isset($input['id']) ? (int)$input['id'] : (int)($_POST['id'] ?? 0);
if (!$id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Notification id required']);
    exit;
}

try {
    // If notifications table exists
    $check = $conn->query("SHOW TABLES LIKE 'notifications'");
    if (!$check || $check->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => "Table 'notifications' not found"]);
        exit;
    }

    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
    if (!$stmt) throw new Exception($conn->error);
    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    $stmt->close();

    if ($ok) echo json_encode(['success' => true]);
    else echo json_encode(['success' => false, 'message' => 'Failed to mark notification read']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>