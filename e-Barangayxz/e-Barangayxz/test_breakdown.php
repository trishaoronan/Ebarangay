<?php
session_start();
include 'db.php';

// Check if staff is logged in
if (!isset($_SESSION['staff_id'])) {
    die('Unauthorized');
}

// Fetch document type breakdown for each status
$breakdownQuery = "
    SELECT 
        document_type,
        status,
        COUNT(*) as count
    FROM requests
    GROUP BY document_type, status
    ORDER BY document_type, status
";
$breakdownResult = $conn->query($breakdownQuery);
$breakdown = [
    'total' => [],
    'pending' => [],
    'processing' => [],
    'approved' => [],
    'rejected' => []
];

if ($breakdownResult) {
    while ($row = $breakdownResult->fetch_assoc()) {
        $docType = $row['document_type'];
        $status = $row['status'];
        $count = $row['count'];
        
        // Add to total
        if (!isset($breakdown['total'][$docType])) {
            $breakdown['total'][$docType] = 0;
        }
        $breakdown['total'][$docType] += $count;
        
        // Add to specific status
        if ($status === 'pending') {
            $breakdown['pending'][$docType] = $count;
        } elseif ($status === 'processing') {
            $breakdown['processing'][$docType] = $count;
        } elseif ($status === 'approved') {
            $breakdown['approved'][$docType] = $count;
        } elseif ($status === 'rejected') {
            $breakdown['rejected'][$docType] = $count;
        }
    }
}

echo "<h2>Breakdown Data Test</h2>";
echo "<pre>";
print_r($breakdown);
echo "</pre>";

echo "<h3>JSON Output:</h3>";
echo "<pre>";
echo json_encode($breakdown, JSON_PRETTY_PRINT);
echo "</pre>";
?>
