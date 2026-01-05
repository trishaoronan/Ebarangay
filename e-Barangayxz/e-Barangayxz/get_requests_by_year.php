<?php
// get_requests_by_year.php
header('Content-Type: application/json');
error_reporting(E_ERROR | E_PARSE);
include 'db.php';

$year = isset($_GET['year']) ? $_GET['year'] : 'current';

try {
    $data = [
        'labels' => [],
        'counts' => [],
        'most_requested' => []
    ];

    $allLabels = ["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"];
    
    // Handle year parameter
    if ($year === 'all' || $year === 'ALL') {
        // Get all requests grouped by month (across all years)
        $monthlyCounts = array_fill(1, 12, 0);
        $result = $conn->query("SELECT MONTH(requested_at) as m, COUNT(*) as c FROM requests GROUP BY m");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $monthlyCounts[(int)$row['m']] = (int)$row['c'];
            }
        }
        
        // Use all 12 months for ALL year view
        $data['labels'] = $allLabels;
        $data['counts'] = array_values($monthlyCounts);
        
        // Get most requested documents (all time)
        $result = $conn->query("SELECT document_type, COUNT(*) as cnt FROM requests GROUP BY document_type ORDER BY cnt DESC LIMIT 4");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $data['most_requested'][] = [
                    'name' => $row['document_type'],
                    'count' => (int)$row['cnt']
                ];
            }
        }
    } else {
        // Specific year
        if ($year === 'current') {
            $year = date('Y');
        }
        $year = (int)$year;
        
        $monthlyCounts = array_fill(1, 12, 0);
        $stmt = $conn->prepare("SELECT MONTH(requested_at) as m, COUNT(*) as c FROM requests WHERE YEAR(requested_at) = ? GROUP BY m");
        $stmt->bind_param('i', $year);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $monthlyCounts[(int)$row['m']] = (int)$row['c'];
            }
        }
        $stmt->close();
        
        // Only show the last 5 months with data (or last 5 months of the year if no data)
        $lastMonth = 12;
        for ($i = 12; $i >= 1; $i--) {
            if ($monthlyCounts[$i] > 0) {
                $lastMonth = $i;
                break;
            }
        }
        $startMonth = max(1, $lastMonth - 4);
        $data['labels'] = array_slice($allLabels, $startMonth - 1, 5);
        $data['counts'] = array_slice(array_values($monthlyCounts), $startMonth - 1, 5);
        
        // Get most requested documents for specific year
        $stmt = $conn->prepare("SELECT document_type, COUNT(*) as cnt FROM requests WHERE YEAR(requested_at) = ? GROUP BY document_type ORDER BY cnt DESC LIMIT 4");
        $stmt->bind_param('i', $year);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $data['most_requested'][] = [
                    'name' => $row['document_type'],
                    'count' => (int)$row['cnt']
                ];
            }
        }
        $stmt->close();
    }
    
    echo json_encode(['success' => true, 'data' => $data]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

$conn->close();
?>
