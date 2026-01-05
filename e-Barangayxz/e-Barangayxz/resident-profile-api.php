<?php
// resident-profile-api.php
// Enable error logging but don't display to users
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
include 'auth_check.php';
include 'db.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$resident_id = $_SESSION['resident_id'] ?? null;
if (!$resident_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Ensure optional columns exist so SELECT won't fail on older schemas
$colCheckMid = $conn->query("SHOW COLUMNS FROM residents LIKE 'middle_name'");
if ($colCheckMid && $colCheckMid->num_rows === 0) { $conn->query("ALTER TABLE residents ADD COLUMN middle_name VARCHAR(100) DEFAULT NULL"); }
$colCheckSuffix = $conn->query("SHOW COLUMNS FROM residents LIKE 'suffix'");
if ($colCheckSuffix && $colCheckSuffix->num_rows === 0) { $conn->query("ALTER TABLE residents ADD COLUMN suffix VARCHAR(50) DEFAULT NULL"); }
// Ensure name_edit_used column exists (for Female+Single one-time edit tracking)
$colCheckNameEdit = $conn->query("SHOW COLUMNS FROM residents LIKE 'name_edit_used'");
if ($colCheckNameEdit && $colCheckNameEdit->num_rows === 0) { $conn->query("ALTER TABLE residents ADD COLUMN name_edit_used TINYINT(1) DEFAULT 0"); }
// Ensure name_edit_used_marriage column exists (for one-time name change when getting married)
$colCheckNameEditMarriage = $conn->query("SHOW COLUMNS FROM residents LIKE 'name_edit_used_marriage'");
if ($colCheckNameEditMarriage && $colCheckNameEditMarriage->num_rows === 0) { $conn->query("ALTER TABLE residents ADD COLUMN name_edit_used_marriage TINYINT(1) DEFAULT 0"); }
// Ensure address_last_changed column exists (for 1-month cooldown tracking)
$colCheckAddrChanged = $conn->query("SHOW COLUMNS FROM residents LIKE 'address_last_changed'");
if ($colCheckAddrChanged && $colCheckAddrChanged->num_rows === 0) { $conn->query("ALTER TABLE residents ADD COLUMN address_last_changed DATETIME DEFAULT NULL"); }
// Ensure civil_status_changed column exists (for one-time civil status change tracking)
$colCheckCivilChanged = $conn->query("SHOW COLUMNS FROM residents LIKE 'civil_status_changed'");
if ($colCheckCivilChanged && $colCheckCivilChanged->num_rows === 0) { $conn->query("ALTER TABLE residents ADD COLUMN civil_status_changed TINYINT(1) DEFAULT 0"); }
// Ensure profile_pic column exists
$colCheckProfilePic = $conn->query("SHOW COLUMNS FROM residents LIKE 'profile_pic'");
if ($colCheckProfilePic && $colCheckProfilePic->num_rows === 0) { $conn->query("ALTER TABLE residents ADD COLUMN profile_pic VARCHAR(255) DEFAULT NULL"); }

if ($method === 'GET') {
    $stmt = $conn->prepare("SELECT id, first_name, middle_name, last_name, suffix, email, mobile, street, municipality, barangay, gender, civil_status, birthday, age, profile_pic, name_edit_used, name_edit_used_marriage, address_last_changed, civil_status_changed FROM residents WHERE id = ? LIMIT 1");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'DB error']);
        exit;
    }
    $stmt->bind_param('i', $resident_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $data = $res->fetch_assoc() ?: [];
    $stmt->close();

    // Clean up invalid profile_pic values (fix any "0" or invalid paths from previous bugs)
    if (isset($data['profile_pic']) && ($data['profile_pic'] === '0' || $data['profile_pic'] === 0 || !file_exists($data['profile_pic']))) {
        $data['profile_pic'] = null;
        // Update database to clean up the invalid value
        $cleanupStmt = $conn->prepare("UPDATE residents SET profile_pic = NULL WHERE id = ?");
        if ($cleanupStmt) {
            $cleanupStmt->bind_param('i', $resident_id);
            $cleanupStmt->execute();
            $cleanupStmt->close();
        }
    }

    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}

if ($method === 'POST') {
    // If multipart/form-data, use $_POST and $_FILES, else use JSON
    $isMultipart = strpos($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data') !== false;
    if ($isMultipart) {
        $input = $_POST;
    } else {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) $input = $_POST;
    }

    // First, fetch current data from database to preserve unchanged fields
    $currentStmt = $conn->prepare("SELECT first_name, middle_name, last_name, suffix, mobile, street, municipality, barangay, gender, civil_status, birthday, age FROM residents WHERE id = ? LIMIT 1");
    $currentStmt->bind_param('i', $resident_id);
    $currentStmt->execute();
    $currentResult = $currentStmt->get_result();
    $currentValues = $currentResult->fetch_assoc() ?: [];
    $currentStmt->close();

    // Use input values if provided in the request, otherwise keep current database values
    // This allows the frontend to only send fields that should be updated (not locked fields)
    $first_name = isset($input['first_name']) ? trim($input['first_name']) : ($currentValues['first_name'] ?? '');
    $middle_name = isset($input['middle_name']) ? (trim($input['middle_name']) === '' ? null : trim($input['middle_name'])) : ($currentValues['middle_name'] ?? null);
    $last_name = isset($input['last_name']) ? trim($input['last_name']) : ($currentValues['last_name'] ?? '');
    $suffix = isset($input['suffix']) ? (trim($input['suffix']) === '' ? null : trim($input['suffix'])) : ($currentValues['suffix'] ?? null);
    $mobile = isset($input['mobile']) ? trim($input['mobile']) : ($currentValues['mobile'] ?? '');
    $street = isset($input['street']) ? trim($input['street']) : ($currentValues['street'] ?? '');
    $municipality = isset($input['municipality']) ? trim($input['municipality']) : ($currentValues['municipality'] ?? '');
    $barangay = isset($input['barangay']) ? trim($input['barangay']) : ($currentValues['barangay'] ?? '');
    $gender = isset($input['gender']) ? trim($input['gender']) : ($currentValues['gender'] ?? null);
    $civil_status = isset($input['civil_status']) ? trim($input['civil_status']) : ($currentValues['civil_status'] ?? null);
    $birthday = isset($input['birthday']) ? trim($input['birthday']) : ($currentValues['birthday'] ?? null);
    $age = isset($input['age']) && $input['age'] !== '' ? intval($input['age']) : ($currentValues['age'] ?? null);

    // Handle profile picture upload if present
    $profile_pic_path = null;
    if ($isMultipart && isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['profile_pic'];
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file['type'], $allowed)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid image type.']);
            exit;
        }
        if ($file['size'] > 5 * 1024 * 1024) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Image size exceeds 5MB.']);
            exit;
        }
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $targetDir = 'profile_pics/';
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
        $filename = 'resident_' . $resident_id . '_' . time() . '.' . $ext;
        $targetPath = $targetDir . $filename;
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to save image.']);
            exit;
        }
        $profile_pic_path = $targetPath;
    }

    if ($first_name === '' || $last_name === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'First and last name are required']);
        exit;
    }
    
    // Validate mobile number
    if (!empty($mobile)) {
        $mobileDigits = preg_replace('/\D/', '', $mobile); // Remove non-digit characters
        
        if (strlen($mobileDigits) !== 11) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid contact number: it must start with 09']);
            exit;
        }
        
        if (substr($mobileDigits, 0, 2) !== '09') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid contact number: it must start with 09']);
            exit;
        }
        
        // Use the cleaned digits
        $mobile = $mobileDigits;
    }

    // Fetch current data to check civil status rules and for change tracking
    $checkStmt = $conn->prepare("SELECT first_name, middle_name, last_name, suffix, street, mobile, barangay, municipality, gender, civil_status, birthday, age, email, name_edit_used, name_edit_used_marriage, address_last_changed, civil_status_changed FROM residents WHERE id = ? LIMIT 1");
    $checkStmt->bind_param('i', $resident_id);
    $checkStmt->execute();
    $currentData = $checkStmt->get_result()->fetch_assoc();
    $checkStmt->close();

    $currentGender = strtolower($currentData['gender'] ?? '');
    $currentCivilStatus = strtolower($currentData['civil_status'] ?? '');
    $nameEditUsed = (int)($currentData['name_edit_used'] ?? 0);
    $nameEditUsedForMarriage = (int)($currentData['name_edit_used_marriage'] ?? 0);
    $civilStatusChanged = (int)($currentData['civil_status_changed'] ?? 0);

    // Validation rules
    $lockedFieldsViolation = [];

    // Rule: First Name and Suffix are ALWAYS permanently locked (cannot ever be changed)
    if ($first_name !== ($currentData['first_name'] ?? '')) {
        $lockedFieldsViolation[] = 'First Name';
    }
    // Normalize suffix: treat null and empty string as equivalent
    $currentSuffix = ($currentData['suffix'] ?? '') === '' ? null : ($currentData['suffix'] ?? null);
    $newSuffix = ($suffix ?? '') === '' ? null : $suffix;
    if ($newSuffix !== $currentSuffix) {
        $lockedFieldsViolation[] = 'Suffix';
    }

    // Rule: Gender, Birthday, Age, Email are permanently locked
    if ($gender !== null && $gender !== ($currentData['gender'] ?? '')) {
        $lockedFieldsViolation[] = 'Gender';
    }
    if ($birthday !== null && $birthday !== ($currentData['birthday'] ?? '')) {
        $lockedFieldsViolation[] = 'Birthday';
    }
    if ($age !== null && (int)$age !== (int)($currentData['age'] ?? 0)) {
        $lockedFieldsViolation[] = 'Age';
    }
    // Note: Email is not submitted from resident profile form, so no check needed here

    // Rule: Address has a 1-month cooldown after each change
    $addressChanged = $street !== ($currentData['street'] ?? '');
    $addressLastChanged = $currentData['address_last_changed'] ?? null;
    if ($addressChanged && $addressLastChanged) {
        $lastChangedDate = new DateTime($addressLastChanged);
        $now = new DateTime();
        $oneMonthLater = clone $lastChangedDate;
        $oneMonthLater->modify('+1 month');
        if ($now < $oneMonthLater) {
            $daysLeft = $now->diff($oneMonthLater)->days;
            $lockedFieldsViolation[] = "Address (can be changed again in {$daysLeft} days)";
        }
    }

    // Check if this is a transition TO married status (allows one-time name change)
    $newCivilStatus = strtolower($civil_status ?? '');
    $isTransitioningToMarried = ($currentCivilStatus !== 'married' && $newCivilStatus === 'married');
    $nameEditUsedForMarriage = (int)($currentData['name_edit_used_marriage'] ?? 0);

    // Rule: MALE residents can NEVER change their middle name or last name
    if ($currentGender === 'male') {
        $currentMiddle = ($currentData['middle_name'] ?? '') === '' ? null : ($currentData['middle_name'] ?? null);
        $newMiddle = ($middle_name ?? '') === '' ? null : $middle_name;
        if ($newMiddle !== $currentMiddle) {
            $lockedFieldsViolation[] = 'Middle Name (males cannot change)';
        }
        if ($last_name !== ($currentData['last_name'] ?? '')) {
            $lockedFieldsViolation[] = 'Last Name (males cannot change)';
        }
    }

    // Rule: Civil status can only be changed ONCE
    $isCivilStatusChanging = ($newCivilStatus !== '' && $newCivilStatus !== $currentCivilStatus);
    if ($isCivilStatusChanging && $civilStatusChanged) {
        $lockedFieldsViolation[] = 'Civil Status (can only be changed once)';
    }
    // Rule: If already married, civil status is permanently locked
    if ($currentCivilStatus === 'married' && $isCivilStatusChanging) {
        $lockedFieldsViolation[] = 'Civil Status (married status is permanent)';
    }

    // Rule: If ALREADY Married and name_edit_used_marriage is set, cannot change middle_name, last_name
    if ($currentCivilStatus === 'married') {
        // Normalize: treat null and empty string as equivalent
        $currentMiddle = ($currentData['middle_name'] ?? '') === '' ? null : ($currentData['middle_name'] ?? null);
        $newMiddle = ($middle_name ?? '') === '' ? null : $middle_name;
        if ($newMiddle !== $currentMiddle) {
            $lockedFieldsViolation[] = 'Middle Name';
        }
        if ($last_name !== ($currentData['last_name'] ?? '')) {
            $lockedFieldsViolation[] = 'Last Name';
        }
    }
    // Rule: If transitioning TO married AND already used the marriage name edit
    else if ($isTransitioningToMarried && $nameEditUsedForMarriage) {
        // Normalize: treat null and empty string as equivalent
        $currentMiddle = ($currentData['middle_name'] ?? '') === '' ? null : ($currentData['middle_name'] ?? null);
        $newMiddle = ($middle_name ?? '') === '' ? null : $middle_name;
        if ($newMiddle !== $currentMiddle) {
            $lockedFieldsViolation[] = 'Middle Name';
        }
        if ($last_name !== ($currentData['last_name'] ?? '')) {
            $lockedFieldsViolation[] = 'Last Name';
        }
    }
    // Rule: If Female and Single and name_edit_used, cannot change middle_name, last_name
    else if ($currentGender === 'female' && $currentCivilStatus === 'single' && $nameEditUsed) {
        // Normalize: treat null and empty string as equivalent
        $currentMiddle = ($currentData['middle_name'] ?? '') === '' ? null : ($currentData['middle_name'] ?? null);
        $newMiddle = ($middle_name ?? '') === '' ? null : $middle_name;
        if ($newMiddle !== $currentMiddle) {
            $lockedFieldsViolation[] = 'Middle Name';
        }
        if ($last_name !== ($currentData['last_name'] ?? '')) {
            $lockedFieldsViolation[] = 'Last Name';
        }
    }

    if (!empty($lockedFieldsViolation)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Cannot change locked fields: ' . implode(', ', $lockedFieldsViolation)]);
        exit;
    }

    // Check if Female+Single is editing middle_name or last_name for the first time
    $shouldMarkNameEditUsed = false;
    if ($currentGender === 'female' && $currentCivilStatus === 'single' && !$nameEditUsed) {
        if (($middle_name !== ($currentData['middle_name'] ?? '')) || ($last_name !== ($currentData['last_name'] ?? ''))) {
            $shouldMarkNameEditUsed = true;
        }
    }

    // Check if transitioning to married and editing names (mark marriage name edit as used)
    $shouldMarkMarriageNameEditUsed = false;
    if ($isTransitioningToMarried && !$nameEditUsedForMarriage) {
        if (($middle_name !== ($currentData['middle_name'] ?? '')) || ($last_name !== ($currentData['last_name'] ?? ''))) {
            $shouldMarkMarriageNameEditUsed = true;
        }
    }

    // Check if address is being changed (for tracking purposes)
    $shouldUpdateAddressTimestamp = $addressChanged && empty($lockedFieldsViolation);

    // Check if civil status is being changed (for one-time tracking)
    $shouldMarkCivilStatusChanged = false;
    if ($isCivilStatusChanging && !$civilStatusChanged) {
        $shouldMarkCivilStatusChanged = true;
    }

    // ensure columns exist (in case older DB lacks them)
    $colCheckG = $conn->query("SHOW COLUMNS FROM residents LIKE 'gender'");
    if ($colCheckG && $colCheckG->num_rows === 0) { $conn->query("ALTER TABLE residents ADD COLUMN gender VARCHAR(30) DEFAULT NULL"); }
    $colCheck = $conn->query("SHOW COLUMNS FROM residents LIKE 'birthday'");
    if ($colCheck && $colCheck->num_rows === 0) { $conn->query("ALTER TABLE residents ADD COLUMN birthday DATE DEFAULT NULL"); }
    $colCheck2 = $conn->query("SHOW COLUMNS FROM residents LIKE 'age'");
    if ($colCheck2 && $colCheck2->num_rows === 0) { $conn->query("ALTER TABLE residents ADD COLUMN age INT DEFAULT NULL"); }
    $colCheck3 = $conn->query("SHOW COLUMNS FROM residents LIKE 'civil_status'");
    if ($colCheck3 && $colCheck3->num_rows === 0) { $conn->query("ALTER TABLE residents ADD COLUMN civil_status VARCHAR(50) DEFAULT NULL"); }
    $colCheckMid = $conn->query("SHOW COLUMNS FROM residents LIKE 'middle_name'");
    if ($colCheckMid && $colCheckMid->num_rows === 0) { $conn->query("ALTER TABLE residents ADD COLUMN middle_name VARCHAR(100) DEFAULT NULL"); }
    $colCheckSuffix = $conn->query("SHOW COLUMNS FROM residents LIKE 'suffix'");
    if ($colCheckSuffix && $colCheckSuffix->num_rows === 0) { $conn->query("ALTER TABLE residents ADD COLUMN suffix VARCHAR(50) DEFAULT NULL"); }
    // Ensure name_edit_used column exists
    $colCheckNameEditPost = $conn->query("SHOW COLUMNS FROM residents LIKE 'name_edit_used'");
    if ($colCheckNameEditPost && $colCheckNameEditPost->num_rows === 0) { $conn->query("ALTER TABLE residents ADD COLUMN name_edit_used TINYINT(1) DEFAULT 0"); }
    // Ensure address_last_changed column exists
    $colCheckAddrPost = $conn->query("SHOW COLUMNS FROM residents LIKE 'address_last_changed'");
    if ($colCheckAddrPost && $colCheckAddrPost->num_rows === 0) { $conn->query("ALTER TABLE residents ADD COLUMN address_last_changed DATETIME DEFAULT NULL"); }
    // Ensure name_edit_used_marriage column exists (for one-time name change when getting married)
    $colCheckNameEditMarriage = $conn->query("SHOW COLUMNS FROM residents LIKE 'name_edit_used_marriage'");
    if ($colCheckNameEditMarriage && $colCheckNameEditMarriage->num_rows === 0) { $conn->query("ALTER TABLE residents ADD COLUMN name_edit_used_marriage TINYINT(1) DEFAULT 0"); }
    // Ensure civil_status_changed column exists (for one-time civil status change tracking)
    $colCheckCivilChangedPost = $conn->query("SHOW COLUMNS FROM residents LIKE 'civil_status_changed'");
    if ($colCheckCivilChangedPost && $colCheckCivilChangedPost->num_rows === 0) { $conn->query("ALTER TABLE residents ADD COLUMN civil_status_changed TINYINT(1) DEFAULT 0"); }

    // Build UPDATE query based on whether we need to mark name_edit_used and/or address change timestamp
    $nameEditUsedValue = $shouldMarkNameEditUsed ? 1 : $nameEditUsed;
    $nameEditUsedMarriageValue = $shouldMarkMarriageNameEditUsed ? 1 : $nameEditUsedForMarriage;
    $civilStatusChangedValue = $shouldMarkCivilStatusChanged ? 1 : $civilStatusChanged;
    $currentTimestamp = date('Y-m-d H:i:s');

    if ($profile_pic_path !== null) {
        if ($shouldUpdateAddressTimestamp) {
            // 18 params: first_name, middle_name, last_name, suffix, mobile, street, municipality, barangay, gender, birthday, age(i), civil_status, profile_pic, name_edit_used(i), name_edit_used_marriage(i), civil_status_changed(i), address_last_changed, id(i)
            $stmt = $conn->prepare("UPDATE residents SET first_name = ?, middle_name = ?, last_name = ?, suffix = ?, mobile = ?, street = ?, municipality = ?, barangay = ?, gender = ?, birthday = ?, age = ?, civil_status = ?, profile_pic = ?, name_edit_used = ?, name_edit_used_marriage = ?, civil_status_changed = ?, address_last_changed = ? WHERE id = ?");
            if (!$stmt) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'DB prepare failed: ' . $conn->error]);
                exit;
            }
            $stmt->bind_param('ssssssssssississsi', $first_name, $middle_name, $last_name, $suffix, $mobile, $street, $municipality, $barangay, $gender, $birthday, $age, $civil_status, $profile_pic_path, $nameEditUsedValue, $nameEditUsedMarriageValue, $civilStatusChangedValue, $currentTimestamp, $resident_id);
        } else {
            // 17 params: first_name, middle_name, last_name, suffix, mobile, street, municipality, barangay, gender, birthday, age(i), civil_status, profile_pic, name_edit_used(i), name_edit_used_marriage(i), civil_status_changed(i), id(i)
            $stmt = $conn->prepare("UPDATE residents SET first_name = ?, middle_name = ?, last_name = ?, suffix = ?, mobile = ?, street = ?, municipality = ?, barangay = ?, gender = ?, birthday = ?, age = ?, civil_status = ?, profile_pic = ?, name_edit_used = ?, name_edit_used_marriage = ?, civil_status_changed = ? WHERE id = ?");
            if (!$stmt) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'DB prepare failed: ' . $conn->error]);
                exit;
            }
            $stmt->bind_param('ssssssssssissiiii', $first_name, $middle_name, $last_name, $suffix, $mobile, $street, $municipality, $barangay, $gender, $birthday, $age, $civil_status, $profile_pic_path, $nameEditUsedValue, $nameEditUsedMarriageValue, $civilStatusChangedValue, $resident_id);
        }
    } else {
        if ($shouldUpdateAddressTimestamp) {
            // 17 params: first_name, middle_name, last_name, suffix, mobile, street, municipality, barangay, gender, birthday, age(i), civil_status, name_edit_used(i), name_edit_used_marriage(i), civil_status_changed(i), address_last_changed, id(i)
            $stmt = $conn->prepare("UPDATE residents SET first_name = ?, middle_name = ?, last_name = ?, suffix = ?, mobile = ?, street = ?, municipality = ?, barangay = ?, gender = ?, birthday = ?, age = ?, civil_status = ?, name_edit_used = ?, name_edit_used_marriage = ?, civil_status_changed = ?, address_last_changed = ? WHERE id = ?");
            if (!$stmt) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'DB prepare failed: ' . $conn->error]);
                exit;
            }
            $stmt->bind_param('ssssssssssisiiisi', $first_name, $middle_name, $last_name, $suffix, $mobile, $street, $municipality, $barangay, $gender, $birthday, $age, $civil_status, $nameEditUsedValue, $nameEditUsedMarriageValue, $civilStatusChangedValue, $currentTimestamp, $resident_id);
        } else {
            // 16 params: first_name, middle_name, last_name, suffix, mobile, street, municipality, barangay, gender, birthday, age(i), civil_status, name_edit_used(i), name_edit_used_marriage(i), civil_status_changed(i), id(i)
            $stmt = $conn->prepare("UPDATE residents SET first_name = ?, middle_name = ?, last_name = ?, suffix = ?, mobile = ?, street = ?, municipality = ?, barangay = ?, gender = ?, birthday = ?, age = ?, civil_status = ?, name_edit_used = ?, name_edit_used_marriage = ?, civil_status_changed = ? WHERE id = ?");
            if (!$stmt) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'DB prepare failed: ' . $conn->error]);
                exit;
            }
            $stmt->bind_param('ssssssssssisiiii', $first_name, $middle_name, $last_name, $suffix, $mobile, $street, $municipality, $barangay, $gender, $birthday, $age, $civil_status, $nameEditUsedValue, $nameEditUsedMarriageValue, $civilStatusChangedValue, $resident_id);
        }
    }
    $ok = $stmt->execute();
    $executeError = $stmt->error;
    $stmt->close();

    if (!$ok) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Update failed: ' . $executeError]);
        exit;
    }

    if ($ok) {
        // Track what was changed for notification - check ALL fields
        // Helper function to normalize values for comparison (treat null and empty string as equal)
        $normalize = function($value) {
            if ($value === null || $value === '') return '';
            return trim((string)$value);
        };
        
        $changedFields = [];
        
        // Check profile picture
        if ($profile_pic_path !== null) {
            $changedFields[] = 'profile picture';
        }
        
        // Check name fields - normalize both values for accurate comparison
        if ($normalize($first_name) !== $normalize($currentData['first_name'] ?? null)) $changedFields[] = 'first name';
        if ($normalize($middle_name) !== $normalize($currentData['middle_name'] ?? null)) $changedFields[] = 'middle name';
        if ($normalize($last_name) !== $normalize($currentData['last_name'] ?? null)) $changedFields[] = 'last name';
        if ($normalize($suffix) !== $normalize($currentData['suffix'] ?? null)) $changedFields[] = 'suffix';
        
        // Check contact - normalize for comparison
        if ($normalize($mobile) !== $normalize($currentData['mobile'] ?? null)) $changedFields[] = 'contact number';
        
        // Check address fields - normalize for comparison
        $streetChanged = $normalize($street) !== $normalize($currentData['street'] ?? null);
        $barangayChanged = $normalize($barangay) !== $normalize($currentData['barangay'] ?? null);
        $municipalityChanged = $normalize($municipality) !== $normalize($currentData['municipality'] ?? null);
        
        // If any address field changed, use "address" as a general indicator
        if ($streetChanged || $barangayChanged || $municipalityChanged) {
            $changedFields[] = 'address';
        }
        
        // Check personal info - normalize for comparison
        if ($normalize($gender) !== $normalize($currentData['gender'] ?? null)) $changedFields[] = 'gender';
        if ($normalize($birthday) !== $normalize($currentData['birthday'] ?? null)) $changedFields[] = 'birthday';
        if ($normalize($civil_status) !== $normalize($currentData['civil_status'] ?? null)) $changedFields[] = 'civil status';
        
        // Notify staff if any trackable fields were changed
        if (!empty($changedFields)) {
            include_once 'add_staff_notification.php';
            $residentName = trim($first_name . ' ' . $last_name);
            $changedFieldsText = implode(', ', $changedFields);
            addStaffNotification(
                $conn,
                'profile_update',
                'Resident Profile Updated',
                "$residentName has updated their profile: $changedFieldsText",
                $resident_id,
                'resident'
            );
            
            // Log resident activity for profile changes
            include_once 'add_resident_activity.php';
            addResidentActivity(
                $conn,
                $resident_id,
                null,
                'profile_updated',
                'Profile Updated',
                "You updated your profile information: $changedFieldsText",
                null,
                null
            );
        }
        
        // Update session name/email if changed
        $_SESSION['resident_name'] = $first_name . ' ' . $last_name;
        $resp = ['success' => true, 'message' => 'Profile updated'];
        if ($profile_pic_path !== null) $resp['profile_pic'] = $profile_pic_path;
        if ($shouldMarkNameEditUsed) {
            $resp['message'] = 'Profile updated. Note: Your middle name and last name can no longer be changed.';
        }
        if ($shouldMarkMarriageNameEditUsed) {
            $resp['message'] = 'Profile updated. Your name has been updated for your marriage. Note: Your middle name and last name can no longer be changed.';
        }
        if ($shouldUpdateAddressTimestamp) {
            $resp['message'] = ($resp['message'] ?? 'Profile updated') . ' Address cannot be changed again for 1 month.';
        }
        echo json_encode($resp);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
