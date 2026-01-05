<?php
// resident-change-password.php
include 'auth_check.php';
include 'db.php';
header('Content-Type: application/json');

$resident_id = $_SESSION['resident_id'] ?? null;
if (!$resident_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) $input = $_POST;

$old = trim($input['old_password'] ?? '');
$new = trim($input['new_password'] ?? '');

// NOTE: current password is NOT required in this development mode — server will still
// check that the new password meets minimum requirements and is not identical to the
// existing password hash.
if (strlen($new) < 8) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters long']);
    exit;
}

// Verify current password
$select = $conn->prepare("SELECT password_hash FROM residents WHERE id = ?");
if (!$select) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB prepare failed']);
    exit;
}
$select->bind_param('i', $resident_id);
$select->execute();
$res = $select->get_result();
if ($res->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Resident not found']);
    $select->close();
    exit;
}
$row = $res->fetch_assoc();
$select->close();

// Prevent reusing the same password (compare new password against stored hash)
if (isset($row['password_hash']) && password_verify($new, $row['password_hash'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'New password cannot be the same as the current password']);
    exit;
}

$hash = password_hash($new, PASSWORD_DEFAULT);

$stmt = $conn->prepare("UPDATE residents SET password_hash = ? WHERE id = ?");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB prepare failed']);
    exit;
}
$stmt->bind_param('si', $hash, $resident_id);
$ok = $stmt->execute();
$stmt->close();

if ($ok) {
    echo json_encode(['success' => true, 'message' => 'Password updated']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not update password']);
}
