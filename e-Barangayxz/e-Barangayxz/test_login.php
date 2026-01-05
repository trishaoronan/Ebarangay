<?php
// test_login.php - Test password verification
include 'db.php';

$test_email = 'superadmin@gmail.com';
$test_password = 'superadmin123';

echo "<h2>Login Test for: $test_email</h2>";

// Get the stored hash from database
$sql = "SELECT staff_id, email, password_hash, first_name, last_name FROM staff_accounts WHERE email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $test_email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "<p style='color: red;'>✗ Email not found in database</p>";
    exit;
}

$user = $result->fetch_assoc();
echo "<p><strong>User found:</strong> " . $user['first_name'] . " " . $user['last_name'] . "</p>";
echo "<p><strong>Email:</strong> " . $user['email'] . "</p>";
echo "<p><strong>Stored Hash:</strong> " . $user['password_hash'] . "</p>";
echo "<hr>";

// Test password verification
echo "<h3>Testing password: '$test_password'</h3>";

if (password_verify($test_password, $user['password_hash'])) {
    echo "<p style='color: green; font-weight: bold;'>✓ PASSWORD VERIFIED! Login should work.</p>";
} else {
    echo "<p style='color: red; font-weight: bold;'>✗ PASSWORD VERIFICATION FAILED!</p>";
    echo "<p>The password '$test_password' does not match the stored hash.</p>";
    
    // Try to check if it's stored as plain text
    if ($test_password === $user['password_hash']) {
        echo "<p style='color: orange;'>⚠ Password is stored as PLAIN TEXT (not hashed)!</p>";
    }
}

echo "<hr>";

// Test with staff password
$test_email2 = 'ericka@gmail.com';
$test_password2 = 'Admin123!';

echo "<h2>Login Test for: $test_email2</h2>";

$sql2 = "SELECT staff_id, email, password_hash, first_name, last_name FROM staff_accounts WHERE email = ?";
$stmt2 = $conn->prepare($sql2);
$stmt2->bind_param("s", $test_email2);
$stmt2->execute();
$result2 = $stmt2->get_result();

if ($result2->num_rows > 0) {
    $user2 = $result2->fetch_assoc();
    echo "<p><strong>User found:</strong> " . $user2['first_name'] . " " . $user2['last_name'] . "</p>";
    echo "<p><strong>Stored Hash:</strong> " . $user2['password_hash'] . "</p>";
    echo "<hr>";
    
    echo "<h3>Testing password: '$test_password2'</h3>";
    
    if (password_verify($test_password2, $user2['password_hash'])) {
        echo "<p style='color: green; font-weight: bold;'>✓ PASSWORD VERIFIED! Login should work.</p>";
    } else {
        echo "<p style='color: red; font-weight: bold;'>✗ PASSWORD VERIFICATION FAILED!</p>";
        echo "<p>The password '$test_password2' does not match the stored hash.</p>";
    }
}

$conn->close();
?>
