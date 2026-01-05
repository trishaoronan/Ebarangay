<?php
// resident_forgot_password.php
header('Content-Type: application/json');
error_reporting(E_ERROR | E_PARSE);
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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
if ($email === '') {
    echo json_encode(['success' => false, 'message' => 'Email is required.']);
    exit;
}

// Check residents table
$check = $conn->query("SHOW TABLES LIKE 'residents'");
if (!$check || $check->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => "No residents table found."]);
    exit;
}

$stmt = $conn->prepare("SELECT id, first_name, last_name FROM residents WHERE email = ? LIMIT 1");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Server error (prepare failed).']);
    exit;
}
$stmt->bind_param('s', $email);
$stmt->execute();
$res = $stmt->get_result();
if (!$res || $res->num_rows === 0) {
    // Do not reveal that email is missing. Respond success (to avoid enumeration).
    echo json_encode(['success' => true, 'message' => 'If an account exists for that email, a reset link has been sent.']);
    $stmt->close();
    exit;
}
$row = $res->fetch_assoc();
$stmt->close();
$resident_id = $row['id'];

// Ensure password_resets table exists
$conn->query("CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    resident_id INT DEFAULT NULL,
    email VARCHAR(255) NOT NULL,
    token VARCHAR(128) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (email), INDEX (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$token = bin2hex(random_bytes(20));
$expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour

$ins = $conn->prepare("INSERT INTO password_resets (resident_id, email, token, expires_at) VALUES (?, ?, ?, ?)");
if ($ins) {
    $ins->bind_param('isss', $resident_id, $email, $token, $expires);
    $ins->execute();
    $ins->close();
}

// Build reset link
$host = (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost');
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$resetLink = $scheme . '://' . $host . dirname($_SERVER['PHP_SELF']) . '/resident_reset_password.php?token=' . $token;

$subject = 'eBarangay Password Reset';
$name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) ?: 'Resident';
$message = "Hello $name,\n\nWe received a request to reset your password. Click the link below to set a new password (valid for 1 hour):\n\n$resetLink\n\nIf you did not request this, please ignore this message.\n\n— eBarangay";
$headers = 'From: noreply@' . $host . "\r\n" . 'Content-Type: text/plain; charset=UTF-8' . "\r\n";

$mailSent = false;
try {
    $mailSent = @mail($email, $subject, $message, $headers);
} catch (Exception $e) {
    $mailSent = false;
}

// Always return a generic success message to avoid account enumeration
if ($mailSent) {
    echo json_encode(['success' => true, 'message' => 'If an account exists for that email, a reset link has been sent.']);
} else {
    // If mail not sent, still respond success but include a developer hint in data (not for production)
    echo json_encode(['success' => true, 'message' => 'If an account exists for that email, a reset link has been sent.', 'data' => ['reset_link' => $resetLink]]);
}

$conn->close();
exit;
?>
