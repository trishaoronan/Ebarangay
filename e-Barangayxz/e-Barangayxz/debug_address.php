<?php
session_start();
include 'db.php';

if (!isset($_SESSION['resident_id'])) {
    die('Not logged in');
}

$resident_id = $_SESSION['resident_id'];
$stmt = $conn->prepare("SELECT id, first_name, last_name, street, barangay, municipality, mobile FROM residents WHERE id = ?");
$stmt->bind_param('i', $resident_id);
$stmt->execute();
$result = $stmt->get_result();
$resident = $result->fetch_assoc();
$stmt->close();

echo "<h3>Resident Address Debug</h3>";
echo "<pre>";
echo "Resident ID: " . $resident['id'] . "\n";
echo "Name: " . $resident['first_name'] . " " . $resident['last_name'] . "\n\n";
echo "Street: '" . ($resident['street'] ?? 'NULL') . "'\n";
echo "Barangay: '" . ($resident['barangay'] ?? 'NULL') . "'\n";
echo "Municipality: '" . ($resident['municipality'] ?? 'NULL') . "'\n";
echo "Mobile: '" . ($resident['mobile'] ?? 'NULL') . "'\n\n";

// Build address like the forms do
$addressParts = [];
if (!empty($resident['street'])) $addressParts[] = $resident['street'];
if (!empty($resident['barangay'])) $addressParts[] = $resident['barangay'];
if (!empty($resident['municipality'])) $addressParts[] = $resident['municipality'];

$fullAddress = implode(', ', $addressParts);
echo "Full Address: '" . $fullAddress . "'\n";
echo "Address Empty: " . (empty($fullAddress) ? 'YES' : 'NO') . "\n";
echo "</pre>";

echo "<hr><p><a href='resident-profile.php'>Go to Profile to Update Address</a></p>";
?>
