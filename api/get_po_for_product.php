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

$receive_id = $_POST['receive_id'] ?? '';

if (empty($receive_id)) {
    echo json_encode(['success' => false, 'message' => 'Missing receive_id']);
    exit;
}

try {
    // ดึงข้อมูลสินค้าจาก receive_id
    $product_sql = "
        SELECT p.product_id, p.name as product_name, p.sku, p.barcode
        FROM receive_items ri
        LEFT JOIN purchase_order_items poi ON ri.item_id = poi.item_id
        LEFT JOIN products p ON poi.product_id = p.product_id
        WHERE ri.receive_id = ?
    ";
    $stmt = $pdo->prepare($product_sql);
    $stmt->execute([$receive_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูลสินค้า']);
        exit;
    }
    
    // ดึงรายการ PO ทั้งหมดที่มีสินค้านี้
    $po_sql = "
        SELECT 
            po.po_id,
            po.po_number,
            po.order_date,
            po.status as po_status,
            poi.item_id,
            poi.qty as ordered_qty,
            poi.price_per_unit as unit_cost,
            COALESCE(ri_sum.total_received, 0) as received_qty,
            s.name as supplier_name
        FROM purchase_orders po
        LEFT JOIN purchase_order_items poi ON po.po_id = poi.po_id
        LEFT JOIN products p ON poi.product_id = p.product_id
        LEFT JOIN suppliers s ON po.supplier_id = s.supplier_id
        LEFT JOIN (
            SELECT item_id, SUM(receive_qty) as total_received
            FROM receive_items
            GROUP BY item_id
        ) ri_sum ON poi.item_id = ri_sum.item_id
        WHERE p.product_id = ?
        AND poi.qty > COALESCE(ri_sum.total_received, 0)
        ORDER BY po.order_date DESC, po.po_number ASC
    ";
    
    $stmt = $pdo->prepare($po_sql);
    $stmt->execute([$product['product_id']]);
    $po_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ประมวลผลข้อมูล
    $processed_list = [];
    foreach ($po_list as $po) {
        $processed_list[] = [
            'po_id' => $po['po_id'],
            'item_id' => $po['item_id'],
            'po_number' => $po['po_number'],
            'order_date' => $po['order_date'],
            'po_status' => $po['po_status'],
            'ordered_qty' => (float)$po['ordered_qty'],
            'received_qty' => (float)$po['received_qty'],
            'unit_cost' => (float)$po['unit_cost'],
            'supplier_name' => $po['supplier_name']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $processed_list,
        'product_info' => [
            'product_id' => $product['product_id'],
            'product_name' => $product['product_name'],
            'sku' => $product['sku'],
            'barcode' => $product['barcode']
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_po_for_product.php: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'เกิดข้อผิดพลาดในการดึงข้อมูล'
    ]);
}
?>