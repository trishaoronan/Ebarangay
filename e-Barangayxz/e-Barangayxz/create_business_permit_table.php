<?php
include 'db.php';

// Create business_permit table
$tableCreate = "CREATE TABLE IF NOT EXISTS business_permit (
    id INT AUTO_INCREMENT PRIMARY KEY,
    resident_id INT NOT NULL,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    middle_name VARCHAR(100),
    suffix VARCHAR(50),
    date_of_birth DATE,
    civil_status VARCHAR(50),
    complete_address TEXT,
    contact_number VARCHAR(20),
    business_name VARCHAR(255),
    business_type VARCHAR(100),
    business_location TEXT,
    valid_id_path TEXT,
    ownership_proof_path TEXT,
    mode_of_release VARCHAR(50),
    status VARCHAR(20) DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (resident_id) REFERENCES residents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($tableCreate)) {
    echo "✓ business_permit table created/verified successfully<br>";
} else {
    echo "✗ Error: " . $conn->error . "<br>";
}

$conn->close();
?>
