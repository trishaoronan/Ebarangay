<?php
// get_notifications.php - Fetch live notifications for staff
header('Content-Type: application/json');
session_start();

include 'db.php';

// Check if staff is logged in
if (!isset($_SESSION['staff_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$staff_id = $_SESSION['staff_id'];
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;

try {
    // Check if notifications table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'notifications'");
    
    if (!$tableCheck || $tableCheck->num_rows === 0) {
        // Table doesn't exist yet, create it
        $createSql = "CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            staff_id INT NOT NULL,
            notification_type VARCHAR(50) NOT NULL,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            related_id INT,
            related_type VARCHAR(50),
            is_read TINYINT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (staff_id) REFERENCES staff_accounts(staff_id) ON DELETE CASCADE,
            INDEX idx_staff_id (staff_id),
            INDEX idx_is_read (is_read),
            INDEX idx_created_at (created_at)
        )";
        
        if (!$conn->query($createSql)) {
            throw new Exception('Failed to create notifications table: ' . $conn->error);
        }
    }
    
    // Fetch notifications for this staff
    $sql = "SELECT 
                id,
                notification_type,
                title,
                message,
                related_id,
                related_type,
                is_read,
                created_at
            FROM notifications
            WHERE staff_id = ?
            ORDER BY created_at DESC
            LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    
    $stmt->bind_param("ii", $staff_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = [
            'id' => $row['id'],
            'type' => $row['notification_type'],
            'title' => $row['title'],
            'message' => $row['message'],
            'related_id' => $row['related_id'],
            'related_type' => $row['related_type'],
            'is_read' => (bool)$row['is_read'],
            'created_at' => $row['created_at']
        ];
    }
    
    // Count unread notifications
    $countSql = "SELECT COUNT(*) as unread_count FROM notifications WHERE staff_id = ? AND is_read = 0";
    $countStmt = $conn->prepare($countSql);
    if (!$countStmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    
    $countStmt->bind_param("i", $staff_id);
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $countRow = $countResult->fetch_assoc();
    $unread_count = $countRow['unread_count'];
    
    echo json_encode([
        'success' => true,
        'data' => $notifications,
        'unread_count' => $unread_count,
        'count' => count($notifications)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    error_log('get_notifications.php error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch notifications',
        'error' => $e->getMessage()
    ]);
}

$conn->close();
?>
