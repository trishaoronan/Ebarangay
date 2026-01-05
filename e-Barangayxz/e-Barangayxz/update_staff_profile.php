<?php
// update_staff_profile.php - Update logged-in staff profile with field locking rules
session_start();
header('Content-Type: application/json');
error_reporting(E_ERROR | E_PARSE);

include 'db.php';

// Check if staff is logged in
if (!isset($_SESSION['staff_id']) || !isset($_SESSION['logged_in'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$staff_id = $_SESSION['staff_id'];

// Get input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$first_name = isset($input['first_name']) ? trim($input['first_name']) : null;
$middle_name = isset($input['middle_name']) ? trim($input['middle_name']) : null;
$last_name = isset($input['last_name']) ? trim($input['last_name']) : null;
$suffix = isset($input['suffix']) ? trim($input['suffix']) : null;
$contact_number = isset($input['contact_number']) ? trim($input['contact_number']) : null;
$gender = isset($input['gender']) ? trim($input['gender']) : null;
$civil_status = isset($input['civil_status']) ? trim($input['civil_status']) : null;

try {
    // Ensure columns exist
    $conn->query("ALTER TABLE staff_accounts ADD COLUMN IF NOT EXISTS gender VARCHAR(30) DEFAULT NULL");
    $conn->query("ALTER TABLE staff_accounts ADD COLUMN IF NOT EXISTS civil_status VARCHAR(50) DEFAULT NULL");
    $conn->query("ALTER TABLE staff_accounts ADD COLUMN IF NOT EXISTS name_edit_used TINYINT(1) DEFAULT 0");

    // Fetch current data to check field locking rules
    $checkStmt = $conn->prepare("SELECT first_name, middle_name, last_name, suffix, gender, civil_status, name_edit_used FROM staff_accounts WHERE staff_id = ?");
    $checkStmt->bind_param("i", $staff_id);
    $checkStmt->execute();
    $currentData = $checkStmt->get_result()->fetch_assoc();
    $checkStmt->close();

    if (!$currentData) {
        echo json_encode(['success' => false, 'message' => 'Staff not found.']);
        exit;
    }

    $currentGender = strtolower($currentData['gender'] ?? '');
    $currentCivilStatus = strtolower($currentData['civil_status'] ?? '');
    $nameEditUsed = (int)($currentData['name_edit_used'] ?? 0);

    $lockedFieldsViolation = [];

    // === GENDER IS ALWAYS LOCKED FOR ALL STAFF ===
    if ($gender !== null && strtolower($gender) !== $currentGender && $currentGender !== '') {
        $lockedFieldsViolation[] = 'Gender';
    }

    // === MALE RULES ===
    // Full name (first, middle, last, suffix) and civil status are PERMANENTLY locked
    if ($currentGender === 'male') {
        if ($first_name !== null && $first_name !== ($currentData['first_name'] ?? '')) {
            $lockedFieldsViolation[] = 'First Name';
        }
        if ($middle_name !== null && $middle_name !== ($currentData['middle_name'] ?? '')) {
            $lockedFieldsViolation[] = 'Middle Name';
        }
        if ($last_name !== null && $last_name !== ($currentData['last_name'] ?? '')) {
            $lockedFieldsViolation[] = 'Last Name';
        }
        if ($suffix !== null && $suffix !== ($currentData['suffix'] ?? '')) {
            $lockedFieldsViolation[] = 'Suffix';
        }
        if ($civil_status !== null && strtolower($civil_status) !== $currentCivilStatus && $currentCivilStatus !== '') {
            $lockedFieldsViolation[] = 'Civil Status';
        }
    }
    // === FEMALE RULES ===
    // First Name and Suffix: PERMANENTLY locked
    // Civil Status, Middle Name, Last Name: Can be changed ONCE if Single
    // If Married: Civil Status, Middle Name, Last Name become PERMANENTLY locked
    else if ($currentGender === 'female') {
        // First name and suffix always locked for female
        if ($first_name !== null && $first_name !== ($currentData['first_name'] ?? '')) {
            $lockedFieldsViolation[] = 'First Name';
        }
        if ($suffix !== null && $suffix !== ($currentData['suffix'] ?? '')) {
            $lockedFieldsViolation[] = 'Suffix';
        }

        // If already married, lock civil status, middle name, last name
        if ($currentCivilStatus === 'married') {
            if ($civil_status !== null && strtolower($civil_status) !== 'married') {
                $lockedFieldsViolation[] = 'Civil Status';
            }
            if ($middle_name !== null && $middle_name !== ($currentData['middle_name'] ?? '')) {
                $lockedFieldsViolation[] = 'Middle Name';
            }
            if ($last_name !== null && $last_name !== ($currentData['last_name'] ?? '')) {
                $lockedFieldsViolation[] = 'Last Name';
            }
        }
        // If single and already used one-time edit, lock middle name and last name
        else if ($currentCivilStatus === 'single' && $nameEditUsed) {
            if ($middle_name !== null && $middle_name !== ($currentData['middle_name'] ?? '')) {
                $lockedFieldsViolation[] = 'Middle Name';
            }
            if ($last_name !== null && $last_name !== ($currentData['last_name'] ?? '')) {
                $lockedFieldsViolation[] = 'Last Name';
            }
            // Civil status can still be changed from single to married (one time)
        }
    }

    if (!empty($lockedFieldsViolation)) {
        echo json_encode(['success' => false, 'message' => 'Cannot change locked fields: ' . implode(', ', $lockedFieldsViolation)]);
        exit;
    }

    // Check if Female+Single is editing middle_name, last_name, or civil_status for the first time
    $shouldMarkNameEditUsed = false;
    if ($currentGender === 'female' && $currentCivilStatus === 'single' && !$nameEditUsed) {
        $middleChanged = ($middle_name !== null && $middle_name !== ($currentData['middle_name'] ?? ''));
        $lastChanged = ($last_name !== null && $last_name !== ($currentData['last_name'] ?? ''));
        $civilChanged = ($civil_status !== null && strtolower($civil_status) !== $currentCivilStatus);
        
        if ($middleChanged || $lastChanged || $civilChanged) {
            $shouldMarkNameEditUsed = true;
        }
    }

    // Build UPDATE query
    $updates = [];
    $params = [];
    $types = '';

    if ($middle_name !== null) {
        $updates[] = "middle_name = ?";
        $params[] = $middle_name;
        $types .= 's';
    }
    if ($last_name !== null) {
        $updates[] = "last_name = ?";
        $params[] = $last_name;
        $types .= 's';
    }
    if ($contact_number !== null) {
        $updates[] = "contact_number = ?";
        $params[] = $contact_number;
        $types .= 's';
    }
    if ($gender !== null) {
        $updates[] = "gender = ?";
        $params[] = $gender;
        $types .= 's';
    }
    if ($civil_status !== null) {
        $updates[] = "civil_status = ?";
        $params[] = $civil_status;
        $types .= 's';
    }
    if ($shouldMarkNameEditUsed) {
        $updates[] = "name_edit_used = 1";
    }

    if (empty($updates)) {
        echo json_encode(['success' => true, 'message' => 'No changes to save.']);
        exit;
    }

    $sql = "UPDATE staff_accounts SET " . implode(', ', $updates) . " WHERE staff_id = ?";
    $params[] = $staff_id;
    $types .= 'i';

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }

    $stmt->bind_param($types, ...$params);
    $ok = $stmt->execute();
    $stmt->close();

    if ($ok) {
        // Log activity for significant changes
        $logChanges = [];
        if ($civil_status !== null && strtolower($civil_status) !== $currentCivilStatus) {
            $logChanges[] = "civil status to " . ucfirst($civil_status);
        }
        if ($middle_name !== null && $middle_name !== ($currentData['middle_name'] ?? '')) {
            $logChanges[] = "middle name to " . $middle_name;
        }
        if ($last_name !== null && $last_name !== ($currentData['last_name'] ?? '')) {
            $logChanges[] = "last name to " . $last_name;
        }
        
        if (!empty($logChanges)) {
            try {
                $actionDetails = "Changed " . implode(", ", $logChanges);
                $logSql = "INSERT INTO activity_log (staff_id, action_type, action_details, affected_staff_id, timestamp) 
                           VALUES (?, 'Profile Update', ?, ?, NOW())";
                $logStmt = $conn->prepare($logSql);
                if ($logStmt) {
                    $logStmt->bind_param("isi", $staff_id, $actionDetails, $staff_id);
                    $logStmt->execute();
                    $logStmt->close();
                }
            } catch (Exception $logEx) {
                error_log('Failed to log profile update: ' . $logEx->getMessage());
            }
        }
        
        $message = 'Profile updated successfully.';
        if ($shouldMarkNameEditUsed) {
            $message .= ' Note: Your middle name, last name, and civil status can no longer be changed.';
        }
        echo json_encode(['success' => true, 'message' => $message]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update profile.']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

$conn->close();
?>
