<?php
// get_resident_activities.php - Fetch recent activities for logged-in resident
header('Content-Type: application/json');
session_start();

include 'db.php';

// Check if resident is logged in
if (!isset($_SESSION['resident_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized', 'activities' => []]);
    exit;
}

$resident_id = $_SESSION['resident_id'];
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;

try {
    // Check if resident_activities table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'resident_activities'");
    
    if (!$tableCheck || $tableCheck->num_rows === 0) {
        // Table doesn't exist yet, create it
        $createSql = "CREATE TABLE IF NOT EXISTS resident_activities (
            id INT AUTO_INCREMENT PRIMARY KEY,
            resident_id INT NOT NULL,
            request_id INT DEFAULT NULL,
            activity_type VARCHAR(50) NOT NULL,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            staff_name VARCHAR(255) DEFAULT NULL,
            document_type VARCHAR(100) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_resident_id (resident_id),
            INDEX idx_request_id (request_id),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        $conn->query($createSql);
    }
    
    // First, try to get activities from the resident_activities table
    $sql = "SELECT 
                id,
                request_id,
                activity_type,
                title,
                message,
                staff_name,
                document_type,
                created_at
            FROM resident_activities
            WHERE resident_id = ?
            ORDER BY created_at DESC
            LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    
    $stmt->bind_param("ii", $resident_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $activities = [];
    while ($row = $result->fetch_assoc()) {
        $activities[] = [
            'id' => $row['id'],
            'request_id' => $row['request_id'],
            'activity_type' => $row['activity_type'],
            'title' => $row['title'],
            'message' => $row['message'],
            'staff_name' => $row['staff_name'],
            'document_type' => $row['document_type'],
            'created_at' => $row['created_at']
        ];
    }
    $stmt->close();
    
    // If no activities in the table, generate from existing requests
    if (empty($activities)) {
        $reqSql = "SELECT id, document_type, status, payment_status, requested_at, given_at 
                   FROM requests 
                   WHERE resident_id = ? 
                   ORDER BY requested_at DESC 
                   LIMIT ?";
        $reqStmt = $conn->prepare($reqSql);
        if ($reqStmt) {
            $reqStmt->bind_param("ii", $resident_id, $limit);
            $reqStmt->execute();
            $reqResult = $reqStmt->get_result();
            
            while ($req = $reqResult->fetch_assoc()) {
                $docType = $req['document_type'] ?? 'Document';
                $status = strtolower($req['status'] ?? 'pending');
                $paymentStatus = strtolower($req['payment_status'] ?? 'unpaid');
                
                // Add request submitted activity
                $activities[] = [
                    'id' => 'req_' . $req['id'],
                    'request_id' => $req['id'],
                    'activity_type' => 'request_submitted',
                    'title' => 'Document Requested',
                    'message' => "You requested {$docType}.",
                    'staff_name' => null,
                    'document_type' => $docType,
                    'created_at' => $req['requested_at']
                ];
                
                // Add status-based activities
                if ($status === 'approved' || $status === 'processing') {
                    $activities[] = [
                        'id' => 'approved_' . $req['id'],
                        'request_id' => $req['id'],
                        'activity_type' => 'request_approved',
                        'title' => 'Document Approved',
                        'message' => "Your {$docType} has been approved. Please proceed with payment.",
                        'staff_name' => 'Barangay Staff',
                        'document_type' => $docType,
                        'created_at' => $req['requested_at'] // Approximate
                    ];
                }
                
                if ($status === 'rejected') {
                    $activities[] = [
                        'id' => 'rejected_' . $req['id'],
                        'request_id' => $req['id'],
                        'activity_type' => 'request_rejected',
                        'title' => 'Document Rejected',
                        'message' => "Your {$docType} request was rejected.",
                        'staff_name' => 'Barangay Staff',
                        'document_type' => $docType,
                        'created_at' => $req['requested_at']
                    ];
                }
                
                if ($paymentStatus === 'paid') {
                    $activities[] = [
                        'id' => 'paid_' . $req['id'],
                        'request_id' => $req['id'],
                        'activity_type' => 'payment_confirmed',
                        'title' => 'Payment Confirmed',
                        'message' => "Payment for {$docType} has been confirmed.",
                        'staff_name' => 'Barangay Staff',
                        'document_type' => $docType,
                        'created_at' => $req['requested_at']
                    ];
                }
                
                if ($status === 'completed' && $req['given_at']) {
                    $activities[] = [
                        'id' => 'ready_' . $req['id'],
                        'request_id' => $req['id'],
                        'activity_type' => 'document_ready',
                        'title' => 'Document Ready',
                        'message' => "Your {$docType} is ready for download.",
                        'staff_name' => 'Barangay Staff',
                        'document_type' => $docType,
                        'created_at' => $req['given_at']
                    ];
                }
            }
            $reqStmt->close();
            
            // Sort by created_at descending
            usort($activities, function($a, $b) {
                return strtotime($b['created_at']) - strtotime($a['created_at']);
            });
            
            // Limit to requested number
            $activities = array_slice($activities, 0, $limit);
        }
    }
    
    echo json_encode([
        'success' => true,
        'activities' => $activities
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching activities: ' . $e->getMessage(),
        'activities' => []
    ]);
}

$conn->close();
?>
