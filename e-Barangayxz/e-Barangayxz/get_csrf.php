<?php
// get_csrf.php - return current CSRF token for logged-in sessions
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
include 'csrf.php';

if (empty($_SESSION['logged_in'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$token = generate_csrf_token();
echo json_encode(['success' => true, 'csrf_token' => $token]);
?>
