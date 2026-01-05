<?php
// csrf.php - simple CSRF helper
if (session_status() === PHP_SESSION_NONE) session_start();

function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        try {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        } catch (Exception $e) {
            // fallback
            $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
        }
    }
    return $_SESSION['csrf_token'];
}

function get_csrf_token_from_request() {
    // Prefer header
    $headers = getallheaders();
    if (!empty($headers['X-CSRF-Token'])) return $headers['X-CSRF-Token'];
    if (!empty($headers['x-csrf-token'])) return $headers['x-csrf-token'];
    // then POST field
    if (!empty($_POST['csrf_token'])) return $_POST['csrf_token'];
    return null;
}

function verify_csrf_token($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) return false;
    return hash_equals($_SESSION['csrf_token'], $token);
}

?>
