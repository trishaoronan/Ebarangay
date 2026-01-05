<?php
/**
 * Get Resident Locations for Map Display
 * Returns resident information for mapping on the staff dashboard
 */

session_start();
header('Content-Type: application/json');

// Check if staff is logged in
if (!isset($_SESSION['staff_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

include 'db.php';

try {
    // Fetch residents with their addresses
    // Using the actual database schema columns
    $query = "SELECT 
                id,
                CONCAT(
                    COALESCE(first_name, ''), 
                    CASE WHEN middle_name IS NOT NULL AND middle_name != '' THEN CONCAT(' ', middle_name) ELSE '' END,
                    ' ', 
                    COALESCE(last_name, ''),
                    CASE WHEN suffix IS NOT NULL AND suffix != '' THEN CONCAT(' ', suffix) ELSE '' END
                ) as name,
                CONCAT(
                    COALESCE(street, ''),
                    CASE WHEN barangay IS NOT NULL AND barangay != '' THEN CONCAT(', ', barangay) ELSE '' END,
                    CASE WHEN municipality IS NOT NULL AND municipality != '' THEN CONCAT(', ', municipality) ELSE '' END
                ) as address,
                mobile as contact
              FROM residents 
              ORDER BY last_name, first_name
              LIMIT 200";
    
    $result = $conn->query($query);
    
    $residents = [];
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Clean up the name (remove extra spaces)
            $name = trim(preg_replace('/\s+/', ' ', $row['name']));
            $address = trim(preg_replace('/^,\s*/', '', $row['address'])); // Remove leading comma if any
            
            $residents[] = [
                'id' => $row['id'],
                'name' => $name ?: 'Resident',
                'address' => $address ?: 'Pulong Buhangin, Santa Maria, Bulacan',
                'contact' => $row['contact'] ?: '',
                'latitude' => null,
                'longitude' => null
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'residents' => $residents,
        'count' => count($residents)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
