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
            p.image,
            poi.qty as order_qty,
            poi.price_per_unit as unit_price,
            COALESCE(NULLIF(poi.price_original, 0), poi.price_per_unit) as price_original,
            poi.total as total_price,
            COALESCE(c.code, 'THB') as currency_code,
            COALESCE(SUM(ri.receive_qty), 0) as received_qty,
            COALESCE(dmg.damaged_unsellable_qty, 0) as damaged_unsellable_qty,
            COALESCE(dmg.damaged_sellable_qty, 0) as damaged_sellable_qty,
            COALESCE(dmg.damaged_unsellable_qty, 0) + COALESCE(dmg.damaged_sellable_qty, 0) as damaged_qty,
            0 as pending_inspection_qty,
            GREATEST(
                poi.qty 
                - COALESCE(SUM(ri.receive_qty), 0) 
                - COALESCE(poi.cancel_qty, 0) 
                - COALESCE(dmg.damaged_unsellable_qty, 0)
                - COALESCE(dmg.damaged_sellable_qty, 0), 
                0
            ) as remaining_qty,
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
        LEFT JOIN (
            SELECT po_id, product_id,
                SUM(CASE WHEN (is_returnable = 0 OR is_returnable = '0') THEN return_qty ELSE 0 END) AS damaged_unsellable_qty,
                SUM(CASE WHEN (is_returnable = 1 OR is_returnable = '1') THEN return_qty ELSE 0 END) AS damaged_sellable_qty
            FROM returned_items
            WHERE reason_name = 'สินค้าชำรุดบางส่วน'
                AND product_id IS NOT NULL
            GROUP BY po_id, product_id
        ) dmg ON poi.po_id = dmg.po_id AND poi.product_id = dmg.product_id
        WHERE poi.po_id = :po_id AND poi.temp_product_id IS NULL
        GROUP BY poi.item_id, poi.product_id, p.name, p.sku, p.barcode, p.unit, p.image, poi.qty, poi.price_per_unit, poi.price_original, poi.total, c.code,
                 poi.is_cancelled, poi.is_partially_cancelled, poi.cancel_qty, poi.cancel_reason, poi.cancel_notes, poi.cancelled_at, poi.cancelled_by,
                 dmg.damaged_unsellable_qty, dmg.damaged_sellable_qty
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
        $item['price_original'] = (float)$item['price_original'];
        $item['total_price'] = (float)$item['total_price'];
        $item['received_qty'] = (float)($item['received_qty'] ?? 0);
        $item['remaining_qty'] = (float)($item['remaining_qty'] ?? 0);
        $item['cancel_qty'] = (float)($item['cancel_qty'] ?? 0);
        $item['damaged_unsellable_qty'] = (float)($item['damaged_unsellable_qty'] ?? 0);
        $item['damaged_sellable_qty'] = (float)($item['damaged_sellable_qty'] ?? 0);
        $item['damaged_qty'] = (float)($item['damaged_qty'] ?? 0);
        $item['pending_inspection_qty'] = (float)($item['pending_inspection_qty'] ?? 0);
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