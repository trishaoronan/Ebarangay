<?php
// resident_register.php
header('Content-Type: application/json');
error_reporting(E_ERROR | E_PARSE);
include 'db.php';
session_start();

// Convert PHP errors to exceptions so we can return JSON on failure
set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(function($e) {
    // Log full exception to PHP error log for diagnosis
    error_log("Uncaught exception in resident_register.php: " . $e->__toString());
    http_response_code(500);
    // Return a safe JSON message to the client
    echo json_encode(['success' => false, 'message' => 'Server error while processing registration. Check server logs.']);
    exit;
});

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

$first = isset($input['firstName']) ? trim($input['firstName']) : '';
$middle = isset($input['middleName']) ? trim($input['middleName']) : '';
$last = isset($input['lastName']) ? trim($input['lastName']) : '';
$email = isset($input['email']) ? trim($input['email']) : '';
$password = isset($input['password']) ? $input['password'] : '';
$mobile = isset($input['mobile']) ? trim($input['mobile']) : '';
$street = isset($input['street']) ? trim($input['street']) : '';
$municipality = isset($input['municipality']) ? trim($input['municipality']) : '';
$barangay = isset($input['barangay']) ? trim($input['barangay']) : '';
$birthday = isset($input['birthday']) ? trim($input['birthday']) : null; // expected YYYY-MM-DD
$age = isset($input['age']) && $input['age'] !== '' ? intval($input['age']) : null;
$gender = isset($input['gender']) ? trim($input['gender']) : null;
$civil_status = isset($input['civil_status']) ? trim($input['civil_status']) : null;

if ($first === '' || $last === '' || $email === '' || $password === '') {
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit;
}

// Ensure residents table exists; create minimal table if absent
$check = $conn->query("SHOW TABLES LIKE 'residents'");
if (!$check || $check->num_rows === 0) {
    $createSql = "CREATE TABLE IF NOT EXISTS residents (
        id INT AUTO_INCREMENT PRIMARY KEY,
            first_name VARCHAR(100) NOT NULL,
            middle_name VARCHAR(100) DEFAULT NULL,
            last_name VARCHAR(100) NOT NULL,
            suffix VARCHAR(50) DEFAULT NULL,
        email VARCHAR(255) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        mobile VARCHAR(30) DEFAULT NULL,
        street VARCHAR(255) DEFAULT NULL,
        barangay_id VARCHAR(50) DEFAULT NULL,
        gender VARCHAR(30) DEFAULT NULL,
        birthday DATE DEFAULT NULL,
        age INT DEFAULT NULL,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $conn->query($createSql);
}

// Reject duplicate emails
$stmt = $conn->prepare("SELECT id FROM residents WHERE email = ? LIMIT 1");
if ($stmt) {
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'An account with that email already exists.']);
        $stmt->close();
        exit;
    }
    $stmt->close();
}

$hashed = password_hash($password, PASSWORD_DEFAULT);
// Ensure columns exist (for upgrades) - add birthday/age if missing
    $colCheck = $conn->query("SHOW COLUMNS FROM residents LIKE 'birthday'");
if ($colCheck && $colCheck->num_rows === 0) {
    $conn->query("ALTER TABLE residents ADD COLUMN birthday DATE DEFAULT NULL");
}
$colCheck2 = $conn->query("SHOW COLUMNS FROM residents LIKE 'age'");
if ($colCheck2 && $colCheck2->num_rows === 0) {
    $conn->query("ALTER TABLE residents ADD COLUMN age INT DEFAULT NULL");
}
// ensure gender column exists
$colCheck3 = $conn->query("SHOW COLUMNS FROM residents LIKE 'gender'");
if ($colCheck3 && $colCheck3->num_rows === 0) {
    $conn->query("ALTER TABLE residents ADD COLUMN gender VARCHAR(30) DEFAULT NULL");
}

// Insert with birthday and age
// Insert with gender, birthday and age
// ensure middle_name column exists
$colCheckMid = $conn->query("SHOW COLUMNS FROM residents LIKE 'middle_name'");
if ($colCheckMid && $colCheckMid->num_rows === 0) { $conn->query("ALTER TABLE residents ADD COLUMN middle_name VARCHAR(100) DEFAULT NULL"); }
// ensure suffix column exists
$colCheckSuffix = $conn->query("SHOW COLUMNS FROM residents LIKE 'suffix'");
if ($colCheckSuffix && $colCheckSuffix->num_rows === 0) { $conn->query("ALTER TABLE residents ADD COLUMN suffix VARCHAR(50) DEFAULT NULL"); }
// ensure civil_status column exists
$colCheckCS = $conn->query("SHOW COLUMNS FROM residents LIKE 'civil_status'");
if ($colCheckCS && $colCheckCS->num_rows === 0) { $conn->query("ALTER TABLE residents ADD COLUMN civil_status VARCHAR(50) DEFAULT NULL"); }
// ensure name_edit_used column exists (for single-edit tracking for Female+Single)
$colCheckNameEdit = $conn->query("SHOW COLUMNS FROM residents LIKE 'name_edit_used'");
if ($colCheckNameEdit && $colCheckNameEdit->num_rows === 0) { $conn->query("ALTER TABLE residents ADD COLUMN name_edit_used TINYINT(1) DEFAULT 0"); }
// Insert including suffix and civil_status
$ins = $conn->prepare("INSERT INTO residents (first_name, middle_name, last_name, suffix, email, password_hash, mobile, street, gender, birthday, age, civil_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
if (!$ins) {
    echo json_encode(['success' => false, 'message' => 'Server error (prepare failed).']);
    exit;
}
$suffix = isset($input['suffix']) ? trim($input['suffix']) : null;
$ins->bind_param('ssssssssssss', $first, $middle, $last, $suffix, $email, $hashed, $mobile, $street, $gender, $birthday, $age, $civil_status);
if ($ins->execute()) {
    $residentId = $ins->insert_id;
    
    // Send notification to all staff about new registration
    include_once 'add_staff_notification.php';
    $residentName = trim($first . ' ' . $last);
    addStaffNotification(
        $conn,
        'new_registration',
        'New Resident Registration',
        "A new resident account has been registered: $residentName",
        $residentId,
        'resident'
    );
    
    // Auto-login after registration
    session_regenerate_id(true);
    $_SESSION['resident_id'] = $residentId;
    $_SESSION['resident_email'] = $email;
    $_SESSION['resident_name'] = $first . ' ' . $last;

    echo json_encode(['success' => true, 'message' => 'Registration successful.', 'data' => ['redirect' => 'resident-dashboard.php']]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to create account: ' . $conn->error]);
}
$ins->close();
$conn->close();
?>
