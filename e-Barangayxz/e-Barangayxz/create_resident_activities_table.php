<?php
// create_resident_activities_table.php - Create resident_activities table
include 'db.php';

$sql = "CREATE TABLE IF NOT EXISTS resident_activities (
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

if ($conn->query($sql)) {
    echo "resident_activities table created successfully!";
} else {
    echo "Error: " . $conn->error;
}

$conn->close();
?>
