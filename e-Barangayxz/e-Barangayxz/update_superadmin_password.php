<?php
// Update super admin password to follow strong password policy
// This version uses the project's `db.php` connection so it respects local DB settings.
header('Content-Type: text/html; charset=utf-8');
include_once 'db.php';

// New password: Superadmin123!
$new_password = 'Superadmin123!';
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

$email = 'superadmin@gmail.com';

// Prepare and execute update
$stmt = $conn->prepare("UPDATE staff_accounts SET password_hash = ? WHERE email = ?");
if (!$stmt) {
    echo "Error preparing statement: " . htmlspecialchars($conn->error);
    exit;
}
$stmt->bind_param("ss", $hashed_password, $email);

if ($stmt->execute()) {
    echo "✓ Super admin password updated successfully<br>";
    echo "New password: Superadmin123!<br>";
    echo "Hashed: " . htmlspecialchars($hashed_password) . "<br>";

    // Verify the update
    $verify_stmt = $conn->prepare("SELECT password_hash FROM staff_accounts WHERE email = ?");
    if ($verify_stmt) {
        $verify_stmt->bind_param("s", $email);
        $verify_stmt->execute();
        $result = $verify_stmt->get_result();
        $row = $result->fetch_assoc();

        echo "<br>Verification:<br>";
        if (isset($row['password_hash']) && password_verify($new_password, $row['password_hash'])) {
            echo "✓ Password verification successful!";
        } else {
            echo "✗ Password verification failed!";
        }

        $verify_stmt->close();
    } else {
        echo "Could not prepare verification statement: " . htmlspecialchars($conn->error);
    }
} else {
    echo "Error updating password: " . htmlspecialchars($stmt->error);
}

$stmt->close();
$conn->close();
?>
