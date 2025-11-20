<?php
/**
 * API for getting missing products list
 */
header('Content-Type: application/json');
require '../config/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$filter_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;

try {
    // Get missing products with user information
    $sql = "SELECT 
                mp.missing_id,
                mp.product_id,
                mp.sku,
                mp.barcode,
                mp.product_name,
                mp.quantity_missing,
                mp.remark,
                mp.created_at,
                u.name as created_by_name,
                mp.reported_by
            FROM missing_products mp
            LEFT JOIN users u ON mp.reported_by = u.user_id
            WHERE DATE(mp.created_at) = ?
            ORDER BY mp.created_at DESC
            LIMIT ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$filter_date, $limit]);
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'count' => count($results),
        'date' => $filter_date,
        'data' => $results
    ]);
    
} catch (Exception $e) {
    error_log("Error getting missing products: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()
    ]);
}
?>
