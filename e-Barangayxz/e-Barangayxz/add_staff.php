<?php
// add_staff.php

// Disable HTML error display to prevent JSON corruption
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Set JSON content type FIRST
header('Content-Type: application/json');

// Include your DB connection (already sets JSON header)
include 'db.php';

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Only POST requests allowed.']);
    exit;
}

// Sanitize input
function sanitize($input) {
    return trim(htmlspecialchars($input ?? '', ENT_QUOTES));
}

$firstName = sanitize($_POST['firstName'] ?? '');
$middleName = sanitize($_POST['middleName'] ?? null); // nullable
$lastName = sanitize($_POST['lastName'] ?? '');
$suffix = sanitize($_POST['suffix'] ?? null);        // nullable
$contactNumber = sanitize($_POST['contactNumber'] ?? '');
$email = sanitize($_POST['email'] ?? '');
$gender = sanitize($_POST['gender'] ?? null);        // nullable
$civilStatus = sanitize($_POST['civilStatus'] ?? null); // nullable
$plainPassword = sanitize($_POST['password'] ?? 'Admin123!'); // Default password
$password = password_hash($plainPassword, PASSWORD_DEFAULT); // Hash the password

// Validate required fields
$errors = [];

// First name validation - letters and spaces only
if ($firstName === '') {
    $errors[] = 'First Name is required.';
} elseif (!preg_match('/^[a-zA-Z\s]+$/', $firstName)) {
    $errors[] = 'First Name can only contain letters and spaces.';
}

// Middle name validation - letters and spaces only (if provided)
if ($middleName !== '' && $middleName !== null && !preg_match('/^[a-zA-Z\s]+$/', $middleName)) {
    $errors[] = 'Middle Name can only contain letters and spaces.';
}

// Last name validation - letters and spaces only
if ($lastName === '') {
    $errors[] = 'Last Name is required.';
} elseif (!preg_match('/^[a-zA-Z\s]+$/', $lastName)) {
    $errors[] = 'Last Name can only contain letters and spaces.';
}

// Suffix validation - letters and periods only (if provided)
if ($suffix !== '' && $suffix !== null && !preg_match('/^[a-zA-Z.]+$/', $suffix)) {
    $errors[] = 'Suffix can only contain letters and periods.';
}

// Contact number validation
if (!preg_match('/^09\d{9}$/', $contactNumber)) {
    $errors[] = 'Contact Number must be 11 digits starting with 09.';
}

// Email validation - must be lowercase and only allow alphanumeric + @ and . before @gmail.com
if ($email === '') {
    $errors[] = 'Email is required.';
} elseif ($email !== strtolower($email)) {
    $errors[] = 'Email must be in lowercase.';
} elseif (!preg_match('/^[a-z0-9.]+@gmail\.com$/', $email)) {
    $errors[] = 'Email must be a valid Gmail address (lowercase, no special characters except @ and .).';
}

if ($errors) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
    exit;
}

// Ensure gender and civil_status columns exist
$colCheckG = $conn->query("SHOW COLUMNS FROM staff_accounts LIKE 'gender'");
if ($colCheckG && $colCheckG->num_rows === 0) { $conn->query("ALTER TABLE staff_accounts ADD COLUMN gender VARCHAR(30) DEFAULT NULL"); }
$colCheckCS = $conn->query("SHOW COLUMNS FROM staff_accounts LIKE 'civil_status'");
if ($colCheckCS && $colCheckCS->num_rows === 0) { $conn->query("ALTER TABLE staff_accounts ADD COLUMN civil_status VARCHAR(50) DEFAULT NULL"); }

// Prepare SQL INSERT statement (include password_hash, gender, civil_status)
$sql = "INSERT INTO staff_accounts 
        (first_name, middle_name, last_name, suffix, email, contact_number, password_hash, gender, civil_status, status, date_created, date_updated)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW(), NOW())";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => "DB prepare failed: " . $conn->error]);
    exit;
}

// Bind parameters (s = string, allow null for middle_name, suffix, gender, civil_status)
// Password is hashed before storage
$stmt->bind_param(
    "sssssssss",
    $firstName,
    $middleName,
    $lastName,
    $suffix,
    $email,
    $contactNumber,
    $password,
    $gender,
    $civilStatus
);

if ($stmt->execute()) {
    $insertedId = $stmt->insert_id;
    
    // Try to log activity (don't fail if this doesn't work)
    try {
        $fullName = trim($firstName . ' ' . ($middleName ? $middleName . ' ' : '') . $lastName . ($suffix ? ' ' . $suffix : ''));
        $adminStaffId = 0; // Use 0 to represent Super Admin
        $logSql = "INSERT INTO activity_log (staff_id, action_type, action_details, affected_staff_id, timestamp) VALUES (?, ?, ?, ?, NOW())";
        $logStmt = $conn->prepare($logSql);
        if ($logStmt) {
            $actionType = "Added staff";
            $actionDetails = "Super Admin added staff {$fullName} (email: {$email}, contact: {$contactNumber})";
            $logStmt->bind_param("issi", $adminStaffId, $actionType, $actionDetails, $insertedId);
            @$logStmt->execute(); // Suppress errors
            $logStmt->close();
        }
    } catch (Exception $e) {
        // Silently fail activity logging - staff was added successfully
        error_log("Activity log failed: " . $e->getMessage());
    }
    
    echo json_encode([
        'success' => true,
        'message' => "Staff member '{$firstName} {$lastName}' added successfully.",
        'data' => [
            'id' => $insertedId,
            'firstName' => $firstName,
            'middleName' => $middleName,
            'lastName' => $lastName,
            'suffix' => $suffix,
            'email' => $email,
            'contactNumber' => $contactNumber,
            'status' => 'active',
            'createdAt' => date('c')
        ]
    ]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => "Insert failed: " . $stmt->error]);
}

$stmt->close();
$conn->close();
exit;