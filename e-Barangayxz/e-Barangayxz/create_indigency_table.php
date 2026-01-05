<?php
// create_indigency_table.php
// Run this file once to create the certificate_of_indigency table

include 'db.php';

// Create certificate_of_indigency table
$createTable = "CREATE TABLE IF NOT EXISTS certificate_of_indigency (
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
    contact_number VARCHAR(20) NOT NULL,
    specific_purpose TEXT NOT NULL,
    estimated_monthly_income VARCHAR(50) NOT NULL,
    number_of_dependents INT,
    valid_id_path VARCHAR(255) NOT NULL,
    proof_of_income_path VARCHAR(255) NOT NULL,
    mode_of_release VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE,
    FOREIGN KEY (resident_id) REFERENCES residents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($createTable)) {
    echo "Table 'certificate_of_indigency' created successfully!<br>";
} else {
    echo "Error creating table: " . $conn->error . "<br>";
}

// Verify table structure
$result = $conn->query("DESCRIBE certificate_of_indigency");
if ($result) {
    echo "<br>Table structure:<br>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . $row['Default'] . "</td>";
        echo "<td>" . $row['Extra'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

$conn->close();
?>
