<?php
// restore_staff.php - restore a soft-deleted staff account (admin only)
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
include 'db.php';
include_once 'csrf.php';

// require superadmin
if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] !== 'superadmin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// CSRF
$csrf_token = get_csrf_token_from_request();
if (!verify_csrf_token($csrf_token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Only POST allowed']);
    exit;
}

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid staff id']);
    exit;
}

// Check staff exists
// For restore we expect the staff to be in archived_staff (copied earlier).
// Look up archived row by original_staff_id
$check = $conn->prepare("SELECT * FROM archived_staff WHERE original_staff_id = ? LIMIT 1");
$check->bind_param("i", $id);
$check->execute();
$res = $check->get_result();
if ($res->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Archived staff not found']);
    exit;
}
$row = $res->fetch_assoc();
$fullName = trim($row['first_name'] . ' ' . $row['last_name']);

// Insert back into main table
// Insert with password_hash preserved if available
$ins = $conn->prepare("INSERT INTO staff_accounts (first_name, middle_name, last_name, suffix, email, contact_number, password_hash, status, date_created, date_updated) VALUES (?, ?, ?, ?, ?, ?, ?, 'active', ?, ?)");
$origDate = $row['original_date_created'] ?? null;
$origUpdated = $row['original_date_updated'] ?? null;
$ins->bind_param("sssssssss", $row['first_name'], $row['middle_name'], $row['last_name'], $row['suffix'], $row['email'], $row['contact_number'], $row['password_hash'], $origDate, $origUpdated);
if ($ins->execute()) {
    // remove from archive
    $archId = $row['archived_id'];
    $del = $conn->prepare("DELETE FROM archived_staff WHERE archived_id = ?");
    $del->bind_param("i", $archId);
    $del->execute();
    $del->close();
    // Log activity
    try {
        $adminId = $_SESSION['admin_id'] ?? 0;
        // concise, human-friendly message
        $actionType = 'Restored staff';
        $actionDetails = $fullName;
        $log = $conn->prepare("INSERT INTO activity_log (staff_id, action_type, action_details, affected_staff_id, timestamp) VALUES (?, ?, ?, ?, NOW())");
        if ($log) {
            $log->bind_param("issi", $adminId, $actionType, $actionDetails, $id);
            @$log->execute();
            $log->close();
        }
    } catch (Exception $e) { }

    echo json_encode(['success' => true, 'message' => 'Staff restored']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to insert back into main table: ' . $conn->error]);
}

$conn->close();
?>
