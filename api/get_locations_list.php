<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/db_connect.php';

try {
    // Get all locations
    $sql = "
        SELECT 
            location_id,
            row_code,
            bin,
            shelf,
            description
        FROM locations
        ORDER BY row_code, bin, shelf
    ";
    
    $stmt = $pdo->query($sql);
    $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $locations
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
