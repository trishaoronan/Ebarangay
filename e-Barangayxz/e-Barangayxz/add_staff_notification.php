<?php
// add_staff_notification.php - Helper function to add notifications for all staff members
// This file can be included in other PHP files to send notifications to staff

/**
 * Add a notification for all active staff members
 * 
 * @param mysqli $conn Database connection
 * @param string $type Notification type (e.g., 'new_request', 'payment_sent', 'new_registration', 'profile_update')
 * @param string $title Notification title
 * @param string $message Notification message
 * @param int|null $related_id Related record ID (e.g., request_id, resident_id)
 * @param string|null $related_type Related record type (e.g., 'request', 'resident', 'payment')
 * @return bool Success status
 */
function addStaffNotification($conn, $type, $title, $message, $related_id = null, $related_type = null) {
    try {
        // Ensure notifications table exists (without foreign key for flexibility)
        $tableCheck = $conn->query("SHOW TABLES LIKE 'notifications'");
        if (!$tableCheck || $tableCheck->num_rows === 0) {
            $createSql = "CREATE TABLE IF NOT EXISTS notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                staff_id INT NOT NULL,
                notification_type VARCHAR(50) NOT NULL,
                title VARCHAR(255) NOT NULL,
                message TEXT NOT NULL,
                related_id INT DEFAULT NULL,
                related_type VARCHAR(50) DEFAULT NULL,
                is_read TINYINT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_staff_id (staff_id),
                INDEX idx_is_read (is_read),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            if (!$conn->query($createSql)) {
                error_log("Failed to create notifications table: " . $conn->error);
                return false;
            }
        }

        // Get all active staff members (case-insensitive status check)
        $staffQuery = $conn->query("SELECT staff_id FROM staff_accounts WHERE LOWER(status) = 'active'");
        if (!$staffQuery) {
            error_log("Failed to query staff accounts: " . $conn->error);
            return false;
        }

        if ($staffQuery->num_rows === 0) {
            // No active staff to notify - try getting ALL staff as fallback
            $staffQuery = $conn->query("SELECT staff_id FROM staff_accounts");
            if (!$staffQuery || $staffQuery->num_rows === 0) {
                error_log("No staff accounts found at all");
                return false;
            }
        }

        // Prepare insert statement
        $stmt = $conn->prepare("INSERT INTO notifications (staff_id, notification_type, title, message, related_id, related_type) VALUES (?, ?, ?, ?, ?, ?)");
        if (!$stmt) {
            error_log("Failed to prepare notification insert: " . $conn->error);
            return false;
        }

        // Insert notification for each staff member
        while ($staff = $staffQuery->fetch_assoc()) {
            $staff_id = (int)$staff['staff_id'];
            $related_id_int = $related_id !== null ? (int)$related_id : null;
            $stmt->bind_param("isssis", $staff_id, $type, $title, $message, $related_id_int, $related_type);
            if (!$stmt->execute()) {
                error_log("Failed to insert notification for staff $staff_id: " . $stmt->error);
            }
        }

        $stmt->close();
        return true;

    } catch (Exception $e) {
        error_log("Error in addStaffNotification: " . $e->getMessage());
        return false;
    }
}

/**
 * Add a notification for a specific staff member
 * 
 * @param mysqli $conn Database connection
 * @param int $staff_id Staff ID to notify
 * @param string $type Notification type
 * @param string $title Notification title
 * @param string $message Notification message
 * @param int|null $related_id Related record ID
 * @param string|null $related_type Related record type
 * @return bool Success status
 */
function addSingleStaffNotification($conn, $staff_id, $type, $title, $message, $related_id = null, $related_type = null) {
    try {
        $stmt = $conn->prepare("INSERT INTO notifications (staff_id, notification_type, title, message, related_id, related_type) VALUES (?, ?, ?, ?, ?, ?)");
        if (!$stmt) {
            error_log("Failed to prepare notification insert: " . $conn->error);
            return false;
        }

        $stmt->bind_param("isssss", $staff_id, $type, $title, $message, $related_id, $related_type);
        $result = $stmt->execute();
        $stmt->close();

        return $result;

    } catch (Exception $e) {
        error_log("Error in addSingleStaffNotification: " . $e->getMessage());
        return false;
    }
}
?>
