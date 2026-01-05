<?php
// resident_logout.php
header('Content-Type: application/json');
error_reporting(E_ERROR | E_PARSE);
session_start();

// Only clear resident-specific session variables, preserve staff session if exists
unset($_SESSION['resident_id']);
unset($_SESSION['resident_email']);
unset($_SESSION['resident_name']);
unset($_SESSION['resident_mobile']);
unset($_SESSION['resident_logged_in']);

// Note: We do NOT destroy the entire session or clear $_SESSION
// This preserves any staff login that might be active in another tab

echo json_encode(['success' => true, 'message' => 'Logged out', 'data' => ['redirect' => 'index.html']]);
exit;
?>
