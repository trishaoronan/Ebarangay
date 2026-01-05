<?php
// auth_check.php - include at top of protected PHP pages
error_reporting(E_ERROR | E_PARSE);
if (session_status() === PHP_SESSION_NONE) session_start();

// If resident not logged in, redirect to login page
if (empty($_SESSION['resident_id'])) {
    // If request looks like AJAX/JSON, return 401 JSON
    $accept = isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : '';
    if (strpos($accept, 'application/json') !== false || isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized: please login.']);
        exit;
    }
    header('Location: login-register.html');
    exit;
}

// resident is authenticated; $resident_id is available
$resident_id = $_SESSION['resident_id'];
?>
