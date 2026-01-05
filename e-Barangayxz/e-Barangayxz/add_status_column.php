<?php
// add_status_column.php
// Adds the 'status' column to the residents table if it doesn't exist
// Valid values: 'active', 'suspended', 'restricted', 'deceased'

include 'db.php';

header('Content-Type: application/json');

try {
    // Check if column already exists
    $checkColumn = $conn->query("SHOW COLUMNS FROM residents LIKE 'status'");
    
    if ($checkColumn && $checkColumn->num_rows > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Status column already exists in residents table.'
        ]);
        exit;
    }
    
    // Add the status column with ENUM type
    $sql = "ALTER TABLE residents ADD COLUMN status ENUM('active', 'suspended', 'restricted', 'deceased') NOT NULL DEFAULT 'active' AFTER is_active";
    
    if ($conn->query($sql)) {
        // Also add date_of_death and death_remarks columns for auditing
        $conn->query("ALTER TABLE residents ADD COLUMN date_of_death DATE NULL AFTER status");
        $conn->query("ALTER TABLE residents ADD COLUMN death_remarks TEXT NULL AFTER date_of_death");
        $conn->query("ALTER TABLE residents ADD COLUMN status_changed_at DATETIME NULL AFTER death_remarks");
        $conn->query("ALTER TABLE residents ADD COLUMN status_changed_by INT NULL AFTER status_changed_at");
        
        echo json_encode([
            'success' => true,
            'message' => 'Status column and related fields added successfully to residents table.'
        ]);
    } else {
        throw new Exception('Failed to add status column: ' . $conn->error);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>
