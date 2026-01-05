<?php
// create_notifications_table.php - Create notifications table if it doesn't exist
include 'db.php';

$sql = "CREATE TABLE IF NOT EXISTS notifications (
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

if ($conn->query($sql) === TRUE) {
    echo "Notifications table created successfully!";
} else {
    echo "Error creating table: " . $conn->error;
}

$conn->close();
?>
