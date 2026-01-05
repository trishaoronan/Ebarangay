<?php
session_start();
header('Content-Type: application/json');

// Check if staff is logged in
if (!isset($_SESSION['staff_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

include 'db.php';

// Validate required fields
$required_fields = ['first', 'last', 'gender', 'civil', 'email', 'street', 'contact', 'dob', 'password'];
foreach ($required_fields as $field) {
    if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
        echo json_encode(['success' => false, 'message' => ucfirst($field) . ' is required']);
        exit;
    }
}

// Get form data
$first_name = trim($_POST['first']);
$middle_name = isset($_POST['middle']) ? trim($_POST['middle']) : '';
$last_name = trim($_POST['last']);
$gender = trim($_POST['gender']);
$civil_status = trim($_POST['civil']);
$email = trim($_POST['email']);
$municipality = isset($_POST['municipality']) ? trim($_POST['municipality']) : 'Santa Maria';
$barangay = isset($_POST['barangay']) ? trim($_POST['barangay']) : 'Pulong Buhangin';
$street = trim($_POST['street']);
$mobile = trim($_POST['contact']);
$birthday = trim($_POST['dob']);
$password = trim($_POST['password']);

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit;
}

// Validate contact number format (09XX-XXX-XXXX)
if (!preg_match('/^09\d{2}-\d{3}-\d{4}$/', $mobile)) {
    echo json_encode(['success' => false, 'message' => 'Invalid contact number format']);
    exit;
}

// Validate password strength
if (strlen($password) < 8 || 
    !preg_match('/[A-Z]/', $password) || 
    !preg_match('/[a-z]/', $password) || 
    !preg_match('/[0-9]/', $password) || 
    !preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
    echo json_encode(['success' => false, 'message' => 'Password does not meet security requirements']);
    exit;
}

// Check if email already exists
$check_email = $conn->prepare("SELECT id FROM residents WHERE email = ?");
$check_email->bind_param("s", $email);
$check_email->execute();
$check_email->store_result();

if ($check_email->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Email already registered']);
    $check_email->close();
    exit;
}
$check_email->close();

// Hash the password
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Calculate age from birthday
$birthDate = new DateTime($birthday);
$today = new DateTime();
$age = (int)$today->diff($birthDate)->y;

// Insert into database with correct column names
$stmt = $conn->prepare("INSERT INTO residents (first_name, middle_name, last_name, gender, civil_status, email, mobile, street, birthday, age, password_hash) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    exit;
}

$stmt->bind_param("sssssssssis", $first_name, $middle_name, $last_name, $gender, $civil_status, $email, $mobile, $street, $birthday, $age, $hashed_password);

if ($stmt->execute()) {
    $resident_id = $stmt->insert_id;
    
    // Log the registration activity (optional)
    try {
        $staff_id = $_SESSION['staff_id'];
        $activity_log = $conn->prepare("INSERT INTO staff_activities (staff_id, activity_type, description) VALUES (?, 'add_resident', ?)");
        if ($activity_log) {
            $description = "Registered new resident: $first_name $last_name (ID: $resident_id)";
            $activity_log->bind_param("is", $staff_id, $description);
            $activity_log->execute();
            $activity_log->close();
        }
    } catch (Exception $e) {
        // Activity logging failed but resident was added successfully
        error_log("Activity logging failed: " . $e->getMessage());
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'Resident registered successfully',
        'resident_id' => $resident_id
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to register resident: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>
