<?php
// delete_staff.php - soft-delete staff account (admin only)
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

// Prevent deleting superadmin
$check = $conn->prepare("SELECT email, first_name, middle_name, last_name, suffix, contact_number, date_created, password_hash, status, date_updated FROM staff_accounts WHERE staff_id = ?");
$check->bind_param("i", $id);
$check->execute();
$res = $check->get_result();
if ($res->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Staff not found']);
    exit;
}
$row = $res->fetch_assoc();
if ($row['email'] === 'superadmin@gmail.com') {
    echo json_encode(['success' => false, 'message' => 'Cannot delete super admin']);
    exit;
}

$fullName = trim($row['first_name'] . ' ' . $row['last_name']);
// Ensure archive table exists
$createArchive = "CREATE TABLE IF NOT EXISTS archived_staff (
    archived_id INT AUTO_INCREMENT PRIMARY KEY,
    original_staff_id INT,
    first_name VARCHAR(255),
    middle_name VARCHAR(255),
    last_name VARCHAR(255),
    suffix VARCHAR(50),
    email VARCHAR(255),
    contact_number VARCHAR(50),
    password_hash VARCHAR(255),
    status VARCHAR(50),
    original_date_created DATETIME,
    original_date_updated DATETIME,
    archived_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
@$conn->query($createArchive);

// Copy the full record into archive then delete from main table
$ins = $conn->prepare("INSERT INTO archived_staff (original_staff_id, first_name, middle_name, last_name, suffix, email, contact_number, password_hash, status, original_date_created, original_date_updated, archived_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
$origDate = $row['date_created'] ?? null;
$origUpdated = $row['date_updated'] ?? null;
$ins->bind_param("issssssssss", $id, $row['first_name'], $row['middle_name'], $row['last_name'], $row['suffix'], $row['email'], $row['contact_number'], $row['password_hash'], $row['status'], $origDate, $origUpdated);
$ok = $ins->execute();
if ($ok) {
    $ins->close();
    $del = $conn->prepare("DELETE FROM staff_accounts WHERE staff_id = ?");
    $del->bind_param("i", $id);
    if ($del->execute()) {
    // Log activity
    try {
        $adminId = $_SESSION['admin_id'] ?? 0;
        // Use a concise, human-friendly activity log entry (no internal IDs)
        $actionType = 'Deleted staff';
        $actionDetails = $fullName;
        $log = $conn->prepare("INSERT INTO activity_log (staff_id, action_type, action_details, affected_staff_id, timestamp) VALUES (?, ?, ?, ?, NOW())");
        if ($log) {
            $log->bind_param("issi", $adminId, $actionType, $actionDetails, $id);
            @$log->execute();
            $log->close();
        }
    } catch (Exception $e) { }

        echo json_encode(['success' => true, 'message' => 'Staff deleted and archived']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to delete from main table: ' . $conn->error]);
    }
    $del->close();
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to archive record before delete']);
}

$conn->close();
?>
