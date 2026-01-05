<?php
// One-time schema migration: rename legacy tables to simplified names
// Run via browser: http://localhost/e-Barangayxz/migrate_table_names.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

include 'db.php';

$actions = [];

function renameIfExists(mysqli $conn, string $old, string $new, array &$actions) {
  $checkOld = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($old) . "'");
  $checkNew = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($new) . "'");
  if ($checkOld && $checkOld->num_rows > 0) {
    if ($checkNew && $checkNew->num_rows === 0) {
      $ok = $conn->query("RENAME TABLE $old TO $new");
      $actions[] = $ok ? "Renamed $old -> $new" : "Failed to rename $old -> $new: " . $conn->error;
    } else {
      $actions[] = "$new already exists; skipping rename for $old";
    }
  } else {
    $actions[] = "$old not found; nothing to rename";
  }
}

renameIfExists($conn, 'barangay_id_applications', 'barangay_id', $actions);
renameIfExists($conn, 'low_income_certificates', 'low_income', $actions);

header('Content-Type: text/plain');
foreach ($actions as $line) {
  echo $line . "\n";
}
echo "Done.";
?>
