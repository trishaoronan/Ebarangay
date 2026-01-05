<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['staff_id'])) {
        die('Unauthorized');
}

echo "<h2>Restore Original Release Modes</h2>";
echo "<hr>";

$totalUpdated = 0;

// Helper to check table and column existence
function hasTable($conn, $table) {
    $res = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($table) . "'");
    return $res && $res->num_rows > 0;
}
function hasColumn($conn, $table, $column) {
    $res = $conn->query("SHOW COLUMNS FROM `$table` LIKE '" . $conn->real_escape_string($column) . "'");
    return $res && $res->num_rows > 0;
}

// List of document tables known to store mode_of_release
$tables = [
    'barangay_clearance'       => 'bc',
    'barangay_id'              => 'bid',
    'burial_assistance'        => 'ba',
    'business_permit'          => 'bp',
    // Indigency variants
    'certificate_of_indigency' => 'ci',
    'indigency_certificate'    => 'ici',
    // Residency variants
    'certificate_of_residency' => 'cr',
    'residency_certificate'    => 'rc2',
    // Other known documents
    'good_moral'               => 'gm',
    'solo_parent'              => 'sp',
    'no_derogatory'            => 'nd',
    'blotter_reports'          => 'br',
    'non_employment'           => 'ne',
    'low_income'               => 'li',
];

foreach ($tables as $table => $alias) {
    if (!hasTable($conn, $table)) {
        echo "• Skipped $table (table not found)<br>";
        continue;
    }
    if (!hasColumn($conn, $table, 'mode_of_release')) {
        echo "• Skipped $table (mode_of_release column not found)<br>";
        continue;
    }

    // Update r.mode_of_release from the doc table when:
    // - requests.mode_of_release is NULL, empty, N/A variants
    // - OR it differs from the document table's value
    $sql = "UPDATE requests r 
                    INNER JOIN `$table` t ON r.id = t.request_id 
                    SET r.mode_of_release = t.mode_of_release 
                    WHERE t.mode_of_release IS NOT NULL 
                        AND t.mode_of_release <> '' 
                        AND (
                            r.mode_of_release IS NULL OR 
                            r.mode_of_release IN ('', 'N/A', 'NA', 'n/a') OR 
                            r.mode_of_release <> t.mode_of_release
                        )";

    if ($conn->query($sql)) {
        $count = $conn->affected_rows;
        $totalUpdated += $count;
        echo "✓ $table: Updated $count records<br>";
    } else {
        echo "✗ $table error: " . $conn->error . "<br>";
    }
}

echo "<hr>";
echo "<p style='font-size: 18px; font-weight: bold; color: green;'>✓ Restoration complete! Updated <strong>$totalUpdated</strong> total records.</p>";
echo "<p><a href='sidebar-requests.php' style='font-size: 16px;'>Go back to requests</a></p>";
?>


