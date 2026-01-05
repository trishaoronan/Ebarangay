<?php
session_start();
header('Content-Type: application/json');

// Check session
echo json_encode([
    'session_resident_id' => $_SESSION['resident_id'] ?? 'NOT SET',
    'post_data' => $_POST,
    'files_data' => $_FILES,
    'post_count' => count($_POST),
    'files_count' => count($_FILES)
]);
?>
