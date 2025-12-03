<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

try {
    require_once '../config/db_connect.php';
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

if (!isset($_GET['po_id'])) {
    echo json_encode(['success' => false, 'error' => 'Missing PO ID']);
    exit;
}

$po_id = $_GET['po_id'];

try {
    // Get PO items with product details, received quantities, and cancellation info
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
            COALESCE(c.code, 'THB') as currency_code,
            COALESCE(SUM(ri.receive_qty), 0) as received_qty,
            (poi.qty - COALESCE(SUM(ri.receive_qty), 0)) as remaining_qty,
            MAX(ri.expiry_date) as expiry_date,
            poi.is_cancelled,
            poi.is_partially_cancelled,
            COALESCE(poi.cancel_qty, 0) as cancel_qty,
            poi.cancel_reason,
            poi.cancel_notes,
            poi.cancelled_at,
            poi.cancelled_by
        FROM purchase_order_items poi
        LEFT JOIN products p ON poi.product_id = p.product_id
        LEFT JOIN purchase_orders po ON poi.po_id = po.po_id
        LEFT JOIN currencies c ON po.currency_id = c.currency_id
        LEFT JOIN receive_items ri ON poi.item_id = ri.item_id
        WHERE poi.po_id = :po_id AND poi.temp_product_id IS NULL
        GROUP BY poi.item_id, poi.product_id, p.name, p.sku, p.barcode, p.unit, poi.qty, poi.price_per_unit, poi.total, c.code,
                 poi.is_cancelled, poi.is_partially_cancelled, poi.cancel_qty, poi.cancel_reason, poi.cancel_notes, poi.cancelled_at, poi.cancelled_by
        ORDER BY p.name
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':po_id', $po_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format items - keep numbers as numbers, not strings
    foreach ($items as &$item) {
        $item['order_qty'] = (float)$item['order_qty'];
        $item['unit_price'] = (float)$item['unit_price'];
        $item['total_price'] = (float)$item['total_price'];
        $item['received_qty'] = (float)($item['received_qty'] ?? 0);
        $item['remaining_qty'] = (float)($item['remaining_qty'] ?? 0);
        $item['cancel_qty'] = (float)($item['cancel_qty'] ?? 0);
        $item['is_cancelled'] = (bool)$item['is_cancelled'];
        $item['is_partially_cancelled'] = (bool)$item['is_partially_cancelled'];
    }
    unset($item);
    
    echo json_encode([
        'success' => true,
        'items' => $items
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>