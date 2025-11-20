<?php
/**
 * API for searching products by barcode or SKU
 * Used by the missing products feature
 */
header('Content-Type: application/json');
require '../config/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$search_term = isset($_GET['q']) ? trim($_GET['q']) : '';

if (empty($search_term)) {
    echo json_encode(['success' => false, 'message' => 'กรุณากรอกบาร์โค้ดหรือ SKU']);
    exit;
}

try {
    // Search by barcode or SKU (partial match)
    $sql = "SELECT 
                p.product_id,
                p.sku,
                p.barcode,
                p.name as product_name,
                p.image,
                poi.price_per_unit,
                poi.sale_price
            FROM products p
            LEFT JOIN purchase_order_items poi ON p.product_id = poi.product_id
            WHERE p.barcode LIKE ? OR p.sku LIKE ? OR p.name LIKE ?
            GROUP BY p.product_id
            ORDER BY 
                CASE 
                    WHEN p.barcode = ? THEN 0
                    WHEN p.sku = ? THEN 1
                    WHEN p.barcode LIKE ? THEN 2
                    WHEN p.sku LIKE ? THEN 3
                    ELSE 4
                END,
                p.name
            LIMIT 20";
    
    $stmt = $pdo->prepare($sql);
    $search_pattern = '%' . $search_term . '%';
    $stmt->execute([
        $search_pattern,
        $search_pattern,
        $search_pattern,
        $search_term,
        $search_term,
        $search_pattern,
        $search_pattern
    ]);
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'message' => empty($results) ? 'ไม่พบสินค้า' : 'พบสินค้า ' . count($results) . ' รายการ',
        'results' => $results
    ]);
    
} catch (Exception $e) {
    error_log("Missing product search error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()
    ]);
}
?>
