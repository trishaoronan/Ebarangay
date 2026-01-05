<?php
include 'db.php';

$sql = "CREATE TABLE IF NOT EXISTS activity_log (
  log_id INT AUTO_INCREMENT PRIMARY KEY,
  staff_id INT NOT NULL DEFAULT 0,
  action_type VARCHAR(100) NOT NULL,
  action_details TEXT,
  affected_staff_id INT NULL,
  timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($sql)) {
  echo "\xE2\x9C\x93 activity_log table created/verified successfully<br>";
} else {
  echo "\xE2\x9C\x97 Error: " . $conn->error . "<br>";
}

$conn->close();
?>