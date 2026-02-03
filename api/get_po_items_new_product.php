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
            poi.product_id,
            tp.product_name,
            tp.provisional_sku as sku,
            tp.provisional_barcode as barcode,
            tp.unit,
            tp.product_image,
            poi.qty as order_qty,
            poi.price_per_unit as unit_price,
            poi.total as total_price,
            c.code as currency_code,
            COALESCE(received_summary.total_received, 0) as received_qty,
            GREATEST(poi.qty - COALESCE(received_summary.total_received, 0) - COALESCE(poi.cancel_qty, 0), 0) as remaining_qty,
            COALESCE(received_summary.expiry_date, NULL) as expiry_date,
            poi.is_cancelled,
            poi.is_partially_cancelled,
            COALESCE(poi.cancel_qty, 0) as cancel_qty,
            poi.cancel_reason,
            poi.cancel_notes,
            poi.cancelled_at,
            poi.cancelled_by
        FROM purchase_order_items poi
        LEFT JOIN temp_products tp ON poi.temp_product_id = tp.temp_product_id
        LEFT JOIN (
            SELECT item_id, MAX(expiry_date) as expiry_date, SUM(receive_qty) as total_received 
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
            'product_id' => isset($item['product_id']) ? (int)$item['product_id'] : null,
            'product_name' => $item['product_name'] ?? 'Unknown Product',
            'barcode' => $item['barcode'] ?? null,
            'sku' => $item['sku'] ?? '-',
            'unit' => $item['unit'] ?? '-',
            'order_qty' => (float)$item['order_qty'],
            'unit_price' => (float)$item['unit_price'],
            'total_price' => isset($item['total_price']) ? (float)$item['total_price'] : (float)$item['order_qty'] * (float)$item['unit_price'],
            'currency_code' => $item['currency_code'] ?? 'THB',
            'received_qty' => (float)$item['received_qty'],
            'remaining_qty' => (float)$item['remaining_qty'],
            'expiry_date' => $item['expiry_date'] ?? null,
            'product_image' => $item['product_image'] ?? null,
            'is_cancelled' => isset($item['is_cancelled']) ? (bool)$item['is_cancelled'] : false,
            'is_partially_cancelled' => isset($item['is_partially_cancelled']) ? (bool)$item['is_partially_cancelled'] : false,
            'cancel_qty' => isset($item['cancel_qty']) ? (float)$item['cancel_qty'] : 0.0,
            'cancel_reason' => $item['cancel_reason'] ?? null,
            'cancel_notes' => $item['cancel_notes'] ?? null,
            'cancelled_at' => $item['cancelled_at'] ?? null,
            'cancelled_by' => $item['cancelled_by'] ?? null
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
