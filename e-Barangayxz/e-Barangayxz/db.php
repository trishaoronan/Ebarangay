<?php
// db.php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ebarangay";

// Robust connect: try primary host, then 127.0.0.1 if localhost is used
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$hosts = [$servername];
if (strtolower($servername) === 'localhost') $hosts[] = '127.0.0.1';
$conn = null;
$lastEx = null;
foreach ($hosts as $h) {
    try {
        $conn = new mysqli($h, $username, $password, $dbname);
        break;
    } catch (mysqli_sql_exception $ex) {
        $lastEx = $ex;
    }
}

if (!$conn) {
    header('Content-Type: application/json');
    http_response_code(500);
    $msg = 'Database connection failed.';
    if ($lastEx) $msg .= ' ' . $lastEx->getMessage();
    $msg .= ' Possible causes: MySQL server is not running, wrong credentials, or firewall blocking port 3306.';
    $msg .= ' Please start MySQL (XAMPP Control Panel) or check connection settings in db.php.';
    // Provide a short JSON response and stop execution
    die(json_encode(['success' => false, 'message' => $msg]));
}

$conn->set_charset('utf8mb4');
?>
