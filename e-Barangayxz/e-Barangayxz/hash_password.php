<?php
// hash_password.php - Helper script to hash passwords
include 'db.php';

// Hash the super admin password
$superadmin_password = 'superadmin123';
$hashed_superadmin = password_hash($superadmin_password, PASSWORD_DEFAULT);

// Hash the default staff password
$default_password = 'Admin123!';
$hashed_default = password_hash($default_password, PASSWORD_DEFAULT);

echo "<h2>Password Hashing Results</h2>";
echo "<p><strong>Super Admin Password:</strong> superadmin123</p>";
echo "<p><strong>Hashed:</strong> $hashed_superadmin</p>";
echo "<hr>";
echo "<p><strong>Default Staff Password:</strong> Admin123!</p>";
echo "<p><strong>Hashed:</strong> $hashed_default</p>";
echo "<hr>";

// Update super admin password in database
$sql = "UPDATE staff_accounts SET password_hash = ? WHERE email = 'superadmin@gmail.com'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $hashed_superadmin);

if ($stmt->execute()) {
    echo "<p style='color: green;'><strong>✓ Super admin password updated successfully!</strong></p>";
} else {
    echo "<p style='color: red;'><strong>✗ Failed to update super admin password: " . $conn->error . "</strong></p>";
}
$stmt->close();

// Update all other staff passwords to the new default
$sql2 = "UPDATE staff_accounts SET password_hash = ? WHERE email != 'superadmin@gmail.com'";
$stmt2 = $conn->prepare($sql2);
$stmt2->bind_param("s", $hashed_default);

if ($stmt2->execute()) {
    $affected = $stmt2->affected_rows;
    echo "<p style='color: green;'><strong>✓ Updated $affected staff account(s) to default password 'Admin123!'</strong></p>";
} else {
    echo "<p style='color: red;'><strong>✗ Failed to update staff passwords: " . $conn->error . "</strong></p>";
}
$stmt2->close();

echo "<hr>";
echo "<p><strong>You can now login with:</strong></p>";
echo "<ul>";
echo "<li>Super Admin: superadmin@gmail.com / superadmin123</li>";
echo "<li>All Staff: their email / Admin123!</li>";
echo "</ul>";

$conn->close();
?>
