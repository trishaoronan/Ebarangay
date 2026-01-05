<?php
// add_resident_activity.php - Helper function to add activity log for residents
// Activity types: 'request_submitted', 'request_approved', 'request_rejected', 'payment_confirmed', 'document_ready', 'document_released', 'resident_login', 'resident_logout', 'profile_updated'

/**
 * Add an activity log for a resident
 * 
 * @param mysqli $conn Database connection
 * @param int $resident_id Resident ID
 * @param int|null $request_id Request ID (if applicable)
 * @param string $activity_type Activity type
 * @param string $title Activity title
 * @param string $message Activity message
 * @param string|null $staff_name Staff name who performed the action
 * @param string|null $document_type Type of document
 * @return bool Success status
 */
function addResidentActivity($conn, $resident_id, $request_id, $activity_type, $title, $message, $staff_name = null, $document_type = null) {
    try {
        // Ensure resident_activities table exists
        $tableCheck = $conn->query("SHOW TABLES LIKE 'resident_activities'");
        if (!$tableCheck || $tableCheck->num_rows === 0) {
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
            if (!$conn->query($createSql)) {
                error_log("Failed to create resident_activities table: " . $conn->error);
                return false;
            }
        }

        // Insert activity
        $stmt = $conn->prepare("INSERT INTO resident_activities (resident_id, request_id, activity_type, title, message, staff_name, document_type) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt) {
            error_log("Failed to prepare resident activity insert: " . $conn->error);
            return false;
        }

        $request_id_val = $request_id !== null ? (int)$request_id : null;
        $stmt->bind_param("iisssss", $resident_id, $request_id_val, $activity_type, $title, $message, $staff_name, $document_type);
        
        $result = $stmt->execute();
        if (!$result) {
            error_log("Failed to insert resident activity: " . $stmt->error);
        }
        
        $stmt->close();
        return $result;

    } catch (Exception $e) {
        error_log("Error in addResidentActivity: " . $e->getMessage());
        return false;
    }
}
?>
