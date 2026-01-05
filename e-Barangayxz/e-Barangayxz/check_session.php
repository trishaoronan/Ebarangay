<?php
// check_session.php - Check if resident is logged in
session_start();
header('Content-Type: application/json');

echo json_encode([
    'session_id' => session_id(),
    'resident_id' => $_SESSION['resident_id'] ?? 'NOT SET',
    'logged_in' => isset($_SESSION['resident_id']),
    'all_session_keys' => array_keys($_SESSION)
]);
?>
