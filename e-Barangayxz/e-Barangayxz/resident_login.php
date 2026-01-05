<?php
// resident_login.php
header('Content-Type: application/json');
error_reporting(E_ERROR | E_PARSE);
include 'db.php';
session_start();

// Accept POST form-data or JSON
$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use POST.']);
    exit;
}

$input = [];
$contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
if (stripos($contentType, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true) ?: [];
} else {
    $input = $_POST;
}

$email = isset($input['email']) ? trim($input['email']) : '';
$password = isset($input['password']) ? $input['password'] : '';

if ($email === '' || $password === '') {
    echo json_encode(['success' => false, 'message' => 'Email and password are required.']);
    exit;
}

// Check residents table
$check = $conn->query("SHOW TABLES LIKE 'residents'");
if (!$check || $check->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => "Table 'residents' not found. Please create or run registration first."]);
    exit;
}

// Check if status column exists
$statusColumnExists = false;
$checkCol = $conn->query("SHOW COLUMNS FROM residents LIKE 'status'");
if ($checkCol && $checkCol->num_rows > 0) {
    $statusColumnExists = true;
}

$sql = $statusColumnExists 
    ? "SELECT id, email, password_hash, first_name, last_name, is_active, status FROM residents WHERE email = ? LIMIT 1"
    : "SELECT id, email, password_hash, first_name, last_name, is_active FROM residents WHERE email = ? LIMIT 1";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Server error (prepare failed).']);
    exit;
}
$stmt->bind_param('s', $email);
$stmt->execute();
$res = $stmt->get_result();
if (!$res || $res->num_rows === 0) {
    // generic message to avoid leaking which emails exist
    echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
    $stmt->close();
    exit;
}
$row = $res->fetch_assoc();
$stmt->close();

if (!isset($row['password_hash']) || !password_verify($password, $row['password_hash'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
    exit;
}

// Check account status (deceased, suspended, restricted)
$status = isset($row['status']) ? strtolower($row['status']) : 'active';

if ($status === 'deceased') {
    echo json_encode(['success' => false, 'message' => 'This account has been permanently deactivated. Please contact the barangay office for assistance.']);
    exit;
}

if ($status === 'suspended') {
    echo json_encode(['success' => false, 'message' => 'Your account has been temporarily suspended. Please contact the barangay office.']);
    exit;
}

// Restricted accounts CAN login but with limited access
// We'll store the status in session so the dashboard can check it

// Optionally check is_active flag (legacy support)
if (isset($row['is_active']) && intval($row['is_active']) === 0) {
    echo json_encode(['success' => false, 'message' => 'Account is inactive. Please contact the barangay office.']);
    exit;
}

// Auth successful: set session
session_regenerate_id(true);
$_SESSION['resident_id'] = $row['id'];
$_SESSION['resident_email'] = $row['email'];
$_SESSION['resident_name'] = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
$_SESSION['resident_status'] = $status; // Store status for restricted access checks

echo json_encode(['success' => true, 'message' => 'Login successful.', 'data' => ['redirect' => 'resident-dashboard.php']]);
$conn->close();
?>
