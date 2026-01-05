<?php
// update_staff.php - admin endpoint to update staff details
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

function s($v) { return trim(htmlspecialchars($v ?? '', ENT_QUOTES)); }

$firstName = s($_POST['firstName'] ?? '');
$middleName = s($_POST['middleName'] ?? null);
$lastName = s($_POST['lastName'] ?? '');
$suffix = s($_POST['suffix'] ?? null);
$email = s($_POST['email'] ?? '');
$contactNumber = preg_replace('/[^0-9]/', '', $_POST['contactNumber'] ?? '');
$status = s($_POST['status'] ?? 'active');

$errors = [];
if ($firstName === '') $errors[] = 'First name required';
if ($lastName === '') $errors[] = 'Last name required';
if ($email === '') $errors[] = 'Email required';
if (!preg_match('/^09\d{9}$/', $contactNumber)) $errors[] = 'Contact must be 11 digits starting with 09';

if ($errors) { http_response_code(400); echo json_encode(['success'=>false,'message'=>implode('; ',$errors)]); exit; }

// Prevent changing superadmin email/status
$q = $conn->prepare("SELECT email FROM staff_accounts WHERE staff_id = ?");
$q->bind_param("i", $id); $q->execute(); $r = $q->get_result();
if ($row = $r->fetch_assoc()) {
    if ($row['email'] === 'superadmin@gmail.com') {
        echo json_encode(['success' => false, 'message' => 'Cannot edit super admin']); exit;
    }
}
$q->close();

$upd = $conn->prepare("UPDATE staff_accounts SET first_name = ?, middle_name = ?, last_name = ?, suffix = ?, email = ?, contact_number = ?, status = ?, date_updated = NOW() WHERE staff_id = ?");
$upd->bind_param("sssssssi", $firstName, $middleName, $lastName, $suffix, $email, $contactNumber, $status, $id);
if ($upd->execute()) {
    // Log activity
    try {
        $adminId = $_SESSION['admin_id'] ?? 0;
        $actionType = 'Edit staff';
        $actionDetails = "Updated staff #{$id} to {$firstName} {$lastName} (email: {$email}, contact: {$contactNumber}, status: {$status})";
        $log = $conn->prepare("INSERT INTO activity_log (staff_id, action_type, action_details, affected_staff_id, timestamp) VALUES (?, ?, ?, ?, NOW())");
        if ($log) { $log->bind_param("issi", $adminId, $actionType, $actionDetails, $id); @$log->execute(); $log->close(); }
    } catch (Exception $e) {}

    echo json_encode(['success'=>true,'message'=>'Staff updated']);
} else {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Failed to update: '.$conn->error]);
}

$conn->close();
?>
