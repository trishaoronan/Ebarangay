<?php
// create_good_moral_table.php
include 'db.php';

$createTableSQL = "
CREATE TABLE IF NOT EXISTS good_moral (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    resident_id INT NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    middle_name VARCHAR(100),
    suffix VARCHAR(20),
    date_of_birth DATE NOT NULL,
    civil_status VARCHAR(50) NOT NULL,
    complete_address TEXT NOT NULL,
    contact_number VARCHAR(30) NOT NULL,
    specific_purpose TEXT NOT NULL,
    declaration_confirmed TINYINT(1) DEFAULT 1,
    valid_id_path VARCHAR(255) NOT NULL,
    mode_of_release VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE,
    FOREIGN KEY (resident_id) REFERENCES residents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

if ($conn->query($createTableSQL) === TRUE) {
    echo "Table 'good_moral' created successfully or already exists.\n";
} else {
    echo "Error creating table: " . $conn->error . "\n";
}

$conn->close();
?>
