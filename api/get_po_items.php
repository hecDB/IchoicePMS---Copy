<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/db_connect.php';

if (!isset($_GET['po_id'])) {
    echo json_encode(['success' => false, 'error' => 'Missing PO ID']);
    exit;
}

$po_id = $_GET['po_id'];

try {
    // Get PO items with product details and received quantities
    // Only show items with existing products (temp_product_id IS NULL)
    $sql = "
        SELECT 
            poi.item_id,
            poi.product_id,
            p.name as product_name,
            p.sku,
            p.barcode,
            p.unit,
            poi.qty as order_qty,
            poi.price_per_unit as unit_price,
            poi.total as total_price,
            poi.currency as currency_code,
            COALESCE(SUM(ri.receive_qty), 0) as received_qty,
            (poi.qty - COALESCE(SUM(ri.receive_qty), 0)) as remaining_qty,
            MAX(ri.expiry_date) as expiry_date
        FROM purchase_order_items poi
        LEFT JOIN products p ON poi.product_id = p.product_id
        LEFT JOIN receive_items ri ON poi.item_id = ri.item_id
        WHERE poi.po_id = :po_id AND poi.temp_product_id IS NULL
        GROUP BY poi.item_id, poi.product_id, p.name, p.sku, p.barcode, p.unit, poi.qty, poi.price_per_unit, poi.total, poi.currency
        ORDER BY p.name
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':po_id', $po_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format numbers for display
    foreach ($items as &$item) {
        $item['unit_price'] = number_format($item['unit_price'], 2);
        $item['total_price'] = number_format($item['total_price'], 2);
        $item['order_qty'] = number_format($item['order_qty'], 0);
        $item['received_qty'] = number_format($item['received_qty'], 0);
        $item['remaining_qty'] = number_format($item['remaining_qty'], 0);
        $item['expiry_date'] = $item['expiry_date'] ?? null;
    }
    
    echo json_encode([
        'success' => true,
        'items' => $items
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>