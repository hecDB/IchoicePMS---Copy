<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require '../config/db_connect.php';

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$product_id = $input['product_id'] ?? '';

if (empty($product_id)) {
    echo json_encode(['success' => false, 'message' => 'Missing product_id']);
    exit;
}

try {
    // ดึงข้อมูลตำแหน่งสินค้าจากตาราง product_location และ locations
    $sql = "
        SELECT 
            l.location_id,
            l.row_code,
            l.bin,
            l.shelf,
            l.description,
            pl.created_at as location_assigned_date
        FROM product_location pl
        LEFT JOIN locations l ON pl.location_id = l.location_id
        WHERE pl.product_id = ?
        ORDER BY pl.created_at DESC
        LIMIT 1
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$product_id]);
    $location = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($location) {
        echo json_encode([
            'success' => true,
            'data' => [
                'location_id' => $location['location_id'],
                'row_code' => $location['row_code'],
                'bin' => $location['bin'],
                'shelf' => $location['shelf'],
                'description' => $location['description'],
                'location_assigned_date' => $location['location_assigned_date']
            ]
        ]);
    } else {
        // หาตำแหน่งจากตาราง products โดยตรง (ถ้ามี)
        $sql_product = "
            SELECT 
                p.location,
                p.location_description
            FROM products p
            WHERE p.product_id = ?
        ";
        
        $stmt_product = $pdo->prepare($sql_product);
        $stmt_product->execute([$product_id]);
        $product_location = $stmt_product->fetch(PDO::FETCH_ASSOC);
        
        if ($product_location && ($product_location['location'] || $product_location['location_description'])) {
            echo json_encode([
                'success' => true,
                'data' => [
                    'location_id' => null,
                    'row_code' => null,
                    'bin' => null,
                    'shelf' => null,
                    'description' => $product_location['location_description'] ?: $product_location['location'],
                    'location_assigned_date' => null
                ]
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'data' => null,
                'message' => 'No location found for this product'
            ]);
        }
    }
    
} catch (Exception $e) {
    error_log("Error in get_product_location.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>