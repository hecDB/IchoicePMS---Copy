<?php
session_start();
require '../config/db_connect.php';

$po_id = $_GET['po_id'] ?? null;

if (!$po_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'PO ID is required']);
    exit;
}

try {
    // Debug logging
    error_log("get_po_items_new_product.php - PO ID: " . $po_id);
    
    // Get PO items for new product PO (using temp_products)
    $sql = "
        SELECT 
            poi.item_id,
            tp.product_name,
            tp.provisional_sku as sku,
            tp.provisional_barcode as barcode,
            tp.unit,
            poi.qty as order_qty,
            poi.price_per_unit as unit_price,
            c.code as currency_code,
            COALESCE(received_summary.total_received, 0) as received_qty,
            (poi.qty - COALESCE(received_summary.total_received, 0)) as remaining_qty
        FROM purchase_order_items poi
        LEFT JOIN temp_products tp ON poi.temp_product_id = tp.temp_product_id
        LEFT JOIN (
            SELECT item_id, SUM(receive_qty) as total_received 
            FROM receive_items 
            GROUP BY item_id
        ) received_summary ON poi.item_id = received_summary.item_id
        LEFT JOIN purchase_orders po ON poi.po_id = po.po_id
        LEFT JOIN currencies c ON po.currency_id = c.currency_id
        WHERE poi.po_id = :po_id AND poi.temp_product_id IS NOT NULL
        ORDER BY poi.item_id ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['po_id' => $po_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("Found " . count($items) . " items");
    error_log("Items data: " . print_r($items, true));

    // Process items
    $processed_items = [];
    foreach ($items as $item) {
        $processed_items[] = [
            'item_id' => $item['item_id'],
            'product_name' => $item['product_name'] ?? 'Unknown Product',
            'barcode' => $item['barcode'] ?? null,
            'sku' => $item['sku'] ?? '-',
            'unit' => $item['unit'] ?? '-',
            'order_qty' => (float)$item['order_qty'],
            'unit_price' => (float)$item['unit_price'],
            'currency_code' => $item['currency_code'] ?? 'THB',
            'received_qty' => (float)$item['received_qty'],
            'remaining_qty' => (float)$item['remaining_qty']
        ];
    }

    echo json_encode([
        'success' => true,
        'items' => $processed_items,
        'debug' => [
            'po_id' => $po_id,
            'raw_count' => count($items),
            'processed_count' => count($processed_items),
            'sql' => $sql
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
