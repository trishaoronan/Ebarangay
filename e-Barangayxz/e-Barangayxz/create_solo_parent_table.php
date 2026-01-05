<?php
include 'db.php';

$tableCreate = "CREATE TABLE IF NOT EXISTS solo_parent (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    resident_id INT NOT NULL,
    last_name VARCHAR(100),
    first_name VARCHAR(100),
    middle_name VARCHAR(100),
    suffix VARCHAR(50),
    date_of_birth DATE,
    civil_status VARCHAR(50),
    complete_address TEXT,
    contact_number VARCHAR(20),
    children_count INT,
    children_ages TEXT,
    reason TEXT,
    valid_id_path TEXT,
    supporting_paths TEXT,
    mode_of_release VARCHAR(50),
    status VARCHAR(20) DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (resident_id) REFERENCES residents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($tableCreate)) {
    echo "\xE2\x9C\x93 solo_parent table created/verified successfully<br>";
} else {
    echo "\xE2\x9C\x97 Error: " . $conn->error . "<br>";
}

$conn->close();
?>