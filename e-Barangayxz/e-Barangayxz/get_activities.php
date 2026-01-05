<?php
// get_activities.php - Activity feed from activity_log table
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

include 'db.php';

try {
    $activities = [];

    // Check if activity_log table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'activity_log'");
    $activityLogExists = ($tableCheck && $tableCheck->num_rows > 0);

    // Fetch from activity_log if it exists
    if ($activityLogExists) {
        $sql = "SELECT 
                    al.log_id as id,
                    al.staff_id, 
                    al.action_type, 
                    al.action_details, 
                    al.timestamp,
                    al.affected_staff_id,
                    COALESCE(sa.first_name, '') as first_name,
                    COALESCE(sa.last_name, '') as last_name,
                    COALESCE(aff.first_name, '') as aff_first,
                    COALESCE(aff.last_name, '') as aff_last
                FROM activity_log al
                LEFT JOIN staff_accounts sa ON al.staff_id = sa.staff_id
                LEFT JOIN staff_accounts aff ON al.affected_staff_id = aff.staff_id
                ORDER BY al.timestamp DESC 
                LIMIT 100";

        $result = $conn->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $staffName = trim($row['first_name'] . ' ' . $row['last_name']);
                // For Super Admin (staff_id = 0)
                if ($row['staff_id'] == 0 || empty($staffName)) {
                    $staffName = 'Super Admin';
                }
                
                $affectedName = trim($row['aff_first'] . ' ' . $row['aff_last']);
                
                // Determine action icon based on action_type
                $actionType = $row['action_type'] ?? 'Unknown';
                $icon = 'bi-activity';
                $badgeClass = 'bg-secondary';
                
                // Map action types to icons and badge colors
                $actionLower = strtolower($actionType);
                if (strpos($actionLower, 'login') !== false) {
                    $icon = 'bi-box-arrow-in-right';
                    $badgeClass = 'bg-success';
                } else if (strpos($actionLower, 'logout') !== false) {
                    $icon = 'bi-box-arrow-right';
                    $badgeClass = 'bg-secondary';
                } else if (strpos($actionLower, 'profile') !== false) {
                    $icon = 'bi-person-circle';
                    $badgeClass = 'bg-info';
                } else if (strpos($actionLower, 'approved') !== false) {
                    $icon = 'bi-check-circle';
                    $badgeClass = 'bg-success';
                } else if (strpos($actionLower, 'rejected') !== false) {
                    $icon = 'bi-x-circle';
                    $badgeClass = 'bg-danger';
                } else if (strpos($actionLower, 'released') !== false || strpos($actionLower, 'upload') !== false) {
                    $icon = 'bi-file-earmark-check';
                    $badgeClass = 'bg-info';
                } else if (strpos($actionLower, 'password') !== false) {
                    $icon = 'bi-key';
                    $badgeClass = 'bg-warning';
                } else if (strpos($actionLower, 'payment') !== false) {
                    $icon = 'bi-cash-coin';
                    $badgeClass = 'bg-primary';
                } else if (strpos($actionLower, 'processing') !== false) {
                    $icon = 'bi-gear';
                    $badgeClass = 'bg-warning';
                } else if (strpos($actionLower, 'ready') !== false) {
                    $icon = 'bi-check2-square';
                    $badgeClass = 'bg-info';
                } else if (strpos($actionLower, 'completed') !== false) {
                    $icon = 'bi-check2-all';
                    $badgeClass = 'bg-success';
                } else if (strpos($actionLower, 'status') !== false || strpos($actionLower, 'toggle') !== false) {
                    $icon = 'bi-toggle-on';
                    $badgeClass = 'bg-info';
                }
                
                $activities[] = [
                    'id' => 'log_' . $row['id'],
                    'action' => $actionType,
                    'details' => ($row['action_details'] ?? 'No details'),
                    'timestamp' => $row['timestamp'] ?? date('Y-m-d H:i:s'),
                    'source' => 'staff_log',
                    'staffName' => $staffName,
                    'affectedStaffName' => $affectedName ?: null,
                    'icon' => $icon,
                    'badgeClass' => $badgeClass
                ];
            }
        } else {
            error_log('Activity log query failed: ' . $conn->error);
        }
    }

    // Sort by timestamp (most recent first) - already sorted from query
    // Return top 50
    $activities = array_slice($activities, 0, 50);

    echo json_encode([
        'success' => true, 
        'data' => $activities, 
        'count' => count($activities),
        'tables' => [
            'activity_log' => $activityLogExists
        ]
    ]);

} catch (Exception $e) {
    error_log('get_activities.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to load activities',
        'error' => $e->getMessage()
    ]);
}

if (isset($conn)) {
    $conn->close();
}
?>
