<?php
session_start();
require '../config/db_connect.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'ไม่ได้เข้าสู่ระบบ']);
    exit;
}

$po_id = $_GET['po_id'] ?? null;
if (!$po_id) {
    echo json_encode(['success' => false, 'message' => 'ไม่พบรหัสใบสั่งซื้อ']);
    exit;
}

try {
    // Get PO items with product details and received quantities
    $sql = "
        SELECT 
            poi.item_id,
            poi.po_id,
            poi.product_id,
            p.name as product_name,
            p.sku,
            p.barcode,
            p.unit,
            poi.qty as ordered_qty,
            poi.price_per_unit as unit_cost,
            c.code as currency_code,
            COALESCE(SUM(ri.receive_qty), 0) as received_qty,
            (poi.qty - COALESCE(SUM(ri.receive_qty), 0)) as remaining_qty
        FROM purchase_order_items poi
        LEFT JOIN products p ON poi.product_id = p.product_id
        LEFT JOIN purchase_orders po ON poi.po_id = po.po_id
        LEFT JOIN currencies c ON po.currency_id = c.currency_id
        LEFT JOIN receive_items ri ON poi.item_id = ri.item_id
        WHERE poi.po_id = ?
        GROUP BY poi.item_id, poi.po_id, poi.product_id, p.name, p.sku, p.barcode, p.unit, 
                 poi.qty, poi.price_per_unit, c.code
        ORDER BY p.name ASC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$po_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format data
    $formatted_items = array_map(function($item) {
        return [
            'item_id' => $item['item_id'],
            'po_id' => $item['po_id'],
            'product_id' => $item['product_id'],
            'product_name' => $item['product_name'],
            'sku' => $item['sku'],
            'barcode' => $item['barcode'],
            'unit' => $item['unit'],
            'ordered_qty' => floatval($item['ordered_qty']),
            'unit_cost' => floatval($item['unit_cost']),
            'currency_code' => $item['currency_code'],
            'received_qty' => floatval($item['received_qty']),
            'remaining_qty' => floatval($item['remaining_qty'])
        ];
    }, $items);
    
    echo json_encode([
        'success' => true,
        'data' => $formatted_items,
        'message' => 'โหลดข้อมูลสำเร็จ'
    ]);

} catch (Exception $e) {
    error_log("Get PO items error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'เกิดข้อผิดพลาดในการโหลดข้อมูล: ' . $e->getMessage()
    ]);
}
?>